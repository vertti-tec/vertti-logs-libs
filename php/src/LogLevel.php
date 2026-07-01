<?php

declare(strict_types=1);

namespace Vertti\Logs;

/**
 * Níveis de log suportados. Usamos constantes (e não enum nativo) para manter
 * compatibilidade com PHP 7.4+.
 */
final class LogLevel
{
    public const DEBUG = 'debug';
    public const INFO = 'info';
    public const WARN = 'warn';
    public const ERROR = 'error';

    /** @var string[] */
    private const ALL = [self::DEBUG, self::INFO, self::WARN, self::ERROR];

    public static function isValid(string $level): bool
    {
        return in_array($level, self::ALL, true);
    }
}