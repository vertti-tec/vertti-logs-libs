<?php

declare(strict_types=1);

namespace Vertti\Logs\Tests;

use PHPUnit\Framework\TestCase;
use Vertti\Logs\VLog;
use Vertti\Logs\VLogException;

final class VLogTest extends TestCase
{
    protected function tearDown(): void
    {
        VLog::reset();
    }

    public function testInitRejeitaTokenVazio(): void
    {
        $this->expectException(VLogException::class);
        $this->expectExceptionMessage('token não pode ser vazio');
        VLog::init('   ');
    }

    public function testSendSemInitLancaExcecao(): void
    {
        $this->expectException(VLogException::class);
        $this->expectExceptionMessage('chame VLog::init');
        VLog::send(['level' => 'info', 'message' => 'oi']);
    }

    public function testInitRejeitaUrlHttpSemAllowHttp(): void
    {
        $this->expectException(VLogException::class);
        $this->expectExceptionMessage('deve usar HTTPS');
        VLog::init([
            'token' => 'abc',
            'url' => 'http://exemplo.com/logs',
        ]);
    }

    public function testInitAceitaUrlHttpComAllowHttp(): void
    {
        VLog::init([
            'token' => 'abc',
            'url' => 'http://127.0.0.1:8000/logs',
            'allowHttp' => true,
        ]);
        $this->assertTrue(true); // não lançou exceção
    }

    public function testInitAceitaLocalhostSemAllowHttp(): void
    {
        VLog::init([
            'token' => 'abc',
            'url' => 'http://localhost:8000/logs',
        ]);
        $this->assertTrue(true); // não lançou exceção
    }

    public function testSendRejeitaLevelInvalido(): void
    {
        VLog::init('token-valido');
        $this->expectException(VLogException::class);
        $this->expectExceptionMessage('level inválido');
        VLog::send(['level' => 'fatal', 'message' => 'oi']);
    }

    public function testSendRejeitaMetadataMuitoGrande(): void
    {
        VLog::init('token-valido');
        $this->expectException(VLogException::class);
        $this->expectExceptionMessage('metadata excede o limite');
        VLog::send([
            'level' => 'info',
            'message' => 'oi',
            'metadata' => ['payload' => str_repeat('a', 70 * 1024)],
        ]);
    }
}
