<?php

declare(strict_types=1);

namespace Vertti\Logs;

/**
 * Exceção lançada pelo SDK (config inválida, token ausente, payload inválido, etc).
 */
final class VLogException extends \RuntimeException {}
