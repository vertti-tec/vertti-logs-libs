<?php

declare(strict_types=1);

namespace Vertti\Logs;

/**
 * Implementação de instância do SDK. Normalmente você não usa esta classe
 * diretamente — use a fachada estática Vertti\Logs\VLog.
 */
final class VLogClient
{
    private const DEFAULT_LOGS_URL = 'https://api-vertti-logs.velog.com.br/logs';
    private const MAX_METADATA_BYTES = 64 * 1024; // 64 KB
    private const SDK_NAME = 'vertti-logs-php';

    private ?string $token = null;
    private string $url = self::DEFAULT_LOGS_URL;

    /**
     * @param string|array{token?: string, url?: string, allowHttp?: bool} $config
     */
    public function init($config): void
    {
        if (is_string($config)) {
            $token = trim($config);
            $url = null;
            $allowHttp = false;
        } elseif (is_array($config)) {
            $token = trim((string) ($config['token'] ?? ''));
            $url = $config['url'] ?? null;
            $allowHttp = (bool) ($config['allowHttp'] ?? false);
        } else {
            throw new VLogException('vlog: config deve ser string ou array');
        }

        if ($token === '') {
            throw new VLogException('vlog: token não pode ser vazio');
        }

        $this->token = $token;

        if ($url !== null) {
            $this->assertHttps($url, $allowHttp);
            $this->url = $url;
        }
    }

    private function assertToken(): string
    {
        if ($this->token === null) {
            throw new VLogException('vlog: chame VLog::init(token) antes de usar o SDK');
        }

        return $this->token;
    }

    private function assertHttps(string $url, bool $allowHttp): void
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? '';
        $host = $parts['host'] ?? '';
        $isLocalhost = $host === 'localhost' || $host === '127.0.0.1';

        if ($scheme !== 'https' && !$isLocalhost && !$allowHttp) {
            throw new VLogException(sprintf(
                'vlog: a URL deve usar HTTPS (recebido: %s). ' .
                    'Para ambientes de teste, use ["allowHttp" => true] no VLog::init().',
                $scheme !== '' ? $scheme . ':' : '(vazio)'
            ));
        }
    }

    // Remove quebras de linha e caracteres de controle para evitar log injection
    private function sanitize(string $value): string
    {
        return preg_replace('/[\x00-\x1f\x7f]/', ' ', $value) ?? $value;
    }

    private function isListOfEntries(array $logs): bool
    {
        // Uma entrada única é um array associativo com a chave "level".
        // Uma lista de entradas é um array indexado sequencialmente (0, 1, 2...).
        if ($logs === []) {
            return true;
        }

        return array_keys($logs) === range(0, count($logs) - 1);
    }

    /**
     * @param array{level?: string, message?: string, timestamp?: string, traceId?: string, spanId?: string, metadata?: array}|array<int, array{level: string, message: string, timestamp?: string, traceId?: string, spanId?: string, metadata?: array}> $logs
     *   Uma entrada única (array associativo) ou uma lista de entradas.
     * @return array{ok: bool, status: int}
     */
    public function send(array $logs): array
    {
        $token = $this->assertToken();
        $entries = $this->isListOfEntries($logs) ? $logs : [$logs];

        $mapped = array_map(function (array $e): array {
            if (!isset($e['level'], $e['message'])) {
                throw new VLogException('vlog: cada log precisa de "level" e "message"');
            }

            if (!LogLevel::isValid($e['level'])) {
                throw new VLogException(sprintf('vlog: level inválido "%s"', $e['level']));
            }

            $metadata = $e['metadata'] ?? [];

            if ($metadata !== []) {
                $size = strlen((string) json_encode($metadata));
                if ($size > self::MAX_METADATA_BYTES) {
                    throw new VLogException(sprintf(
                        'vlog: metadata excede o limite de %d KB',
                        self::MAX_METADATA_BYTES / 1024
                    ));
                }
            }

            $log = [
                'timestamp' => $e['timestamp'] ?? gmdate('Y-m-d\TH:i:s.v\Z'),
                'level' => $e['level'],
                'message' => $this->sanitize((string) $e['message']),
                'metadata' => array_merge($metadata, [
                    '_sdk' => [
                        'name' => self::SDK_NAME,
                        'version' => self::getSdkVersion(),
                    ],
                ]),
            ];

            if (isset($e['traceId'])) {
                $log['traceId'] = $this->sanitize((string) $e['traceId']);
            }

            if (isset($e['spanId'])) {
                $log['spanId'] = $this->sanitize((string) $e['spanId']);
            }

            return $log;
        }, $entries);

        $payload = (string) json_encode(
            ['logs' => $mapped],
            JSON_UNESCAPED_UNICODE
        );

        $ch = curl_init($this->url);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
                'Accept: */*',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError !== '') {
            return [
                'ok' => false,
                'status' => 0,
                'body' => null,
                'error' => $curlError,
            ];
        }

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => $response ? (string) $response : null,
            'error' => null,
        ];
    }

    /**
     * @param array{traceId?: string, spanId?: string, metadata?: array} $extra
     * @return array{ok: bool, status: int}
     */
    public function debug(string $message, array $extra = []): array
    {
        return $this->send(array_merge(['level' => LogLevel::DEBUG, 'message' => $message], $extra));
    }

    /** @return array{ok: bool, status: int} */
    public function info(string $message, array $extra = []): array
    {
        return $this->send(array_merge(['level' => LogLevel::INFO, 'message' => $message], $extra));
    }

    /** @return array{ok: bool, status: int} */
    public function warn(string $message, array $extra = []): array
    {
        return $this->send(array_merge(['level' => LogLevel::WARN, 'message' => $message], $extra));
    }

    /** @return array{ok: bool, status: int} */
    public function error(string $message, array $extra = []): array
    {
        return $this->send(array_merge(['level' => LogLevel::ERROR, 'message' => $message], $extra));
    }

    /**
     * PHP não tem um ciclo de vida de "request/response" contínuo como o Express,
     * então este método retorna um callable para ser disparado manualmente ao fim
     * da requisição (ex.: register_shutdown_function, ou middleware do seu framework).
     */
    public function requestLogger(): callable
    {
        return function (): void {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
            // REMOTE_ADDR é o IP real da conexão TCP, não pode ser forjado via header
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $status = http_response_code() ?: 200;

            try {
                $this->info(sprintf('%s %s', $method, $this->sanitize((string) $path)), [
                    'metadata' => ['status' => $status, 'ip' => $ip],
                ]);
            } catch (\Throwable $e) {
                // silencioso, equivalente ao .catch(() => {}) do SDK Node
            }
        };
    }

    private static function getSdkVersion(): string
    {
        static $version = null;
        if ($version !== null) {
            return $version;
        }

        if (
            class_exists(\Composer\InstalledVersions::class)
            && \Composer\InstalledVersions::isInstalled('vertti/vertti-logs')
        ) {
            $version = \Composer\InstalledVersions::getPrettyVersion('vertti/vertti-logs') ?? 'unknown';
        } else {
            $version = 'unknown';
        }

        return $version;
    }
}
