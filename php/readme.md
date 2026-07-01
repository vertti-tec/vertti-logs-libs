# vertti-logs (PHP)

SDK para envio de logs ao Vertti Logs.

## Instalação

```bash
composer require vertti/vertti-logs
```

## Uso

```php
use Vertti\Logs\VLog;

VLog::init('seu-token');

VLog::debug('mensagem de debug');
VLog::info('mensagem de info');
VLog::warn('mensagem de aviso');
VLog::error('mensagem de erro');
```

## Com metadados

```php
use Vertti\Logs\VLog;

VLog::error('falha ao processar pagamento', [
    'traceId' => 'abc-123',
    'spanId' => 'xyz-789',
    'metadata' => ['orderId' => '999', 'valor' => 149.90],
]);
```

## Múltiplos logs de uma vez

```php
use Vertti\Logs\VLog;

VLog::send([
    ['level' => 'info',  'message' => 'inicio do fluxo', 'traceId' => 't1'],
    ['level' => 'debug', 'message' => 'etapa 1',          'traceId' => 't1'],
    ['level' => 'info',  'message' => 'fim do fluxo',     'traceId' => 't1'],
]);
```

## Modo desenvolvimento (sem SSL)

Para ambientes locais ou de teste onde o servidor não usa HTTPS, passe a URL e habilite `allowHttp`:

```php
VLog::init([
    'token' => 'seu-token',
    'url' => 'http://129.148.45.50:8000/logs',
    'allowHttp' => true,
]);
```

**Atenção:** nunca use `allowHttp => true` em produção. Sem HTTPS o token trafega em texto claro.

## Logando requisições automaticamente

Diferente do Node/Express, o PHP não tem um evento de "fim da resposta" nativo em todo
setup — então `requestLogger()` retorna um `callable` que você dispara manualmente ao
fim do ciclo de vida da requisição.

### Script simples / bootstrap genérico

```php
use Vertti\Logs\VLog;

VLog::init('seu-token');

register_shutdown_function(VLog::requestLogger());
```

### Laravel (middleware)

```php
// app/Http/Middleware/VertiLogsMiddleware.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Vertti\Logs\VLog;

class VertiLogsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        (VLog::requestLogger())();
        return $response;
    }
}
```

### Slim / middleware PSR-15 genérico

```php
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    (VLog::requestLogger())();
    return $response;
});
```

## Rodando os testes

```bash
composer install
composer test
```