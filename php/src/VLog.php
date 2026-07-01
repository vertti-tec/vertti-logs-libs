<?php

declare(strict_types=1);

namespace Vertti\Logs;

/**
 * Fachada estática do SDK, equivalente ao `import vlog from "vertti-logs"` do Node.
 *
 * Uso:
 *   VLog::init('seu-token');
 *   VLog::info('mensagem de info');
 */
final class VLog
{
    private static ?VLogClient $instance = null;

    private function __construct() {}

    private static function instance(): VLogClient
    {
        if (self::$instance === null) {
            self::$instance = new VLogClient();
        }

        return self::$instance;
    }

    /** @param string|array{token?: string, url?: string, allowHttp?: bool} $config */
    public static function init($config): void
    {
        self::instance()->init($config);
    }

    /** @return array{ok: bool, status: int} */
    public static function send(array $logs): array
    {
        return self::instance()->send($logs);
    }

    /** @return array{ok: bool, status: int} */
    public static function debug(string $message, array $extra = []): array
    {
        return self::instance()->debug($message, $extra);
    }

    /** @return array{ok: bool, status: int} */
    public static function info(string $message, array $extra = []): array
    {
        return self::instance()->info($message, $extra);
    }

    /** @return array{ok: bool, status: int} */
    public static function warn(string $message, array $extra = []): array
    {
        return self::instance()->warn($message, $extra);
    }

    /** @return array{ok: bool, status: int} */
    public static function error(string $message, array $extra = []): array
    {
        return self::instance()->error($message, $extra);
    }

    public static function requestLogger(): callable
    {
        return self::instance()->requestLogger();
    }

    /**
     * Reseta a instância singleton. Útil em testes.
     * @internal
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
