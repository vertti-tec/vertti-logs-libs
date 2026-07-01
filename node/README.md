# vertti-logs

SDK para envio de logs ao Vertti Logs.

## Instalação

```bash
npm install vertti-logs
```

## Uso

```js
import vlog from "vertti-logs";

vlog.init("seu-token");

await vlog.debug("mensagem de debug");
await vlog.info("mensagem de info");
await vlog.warn("mensagem de aviso");
await vlog.error("mensagem de erro");
```

### Com metadados

```js
await vlog.error("falha ao processar pagamento", {
  traceId: "abc-123",
  spanId: "xyz-789",
  metadata: { orderId: "999", valor: 149.90 },
});
```

### Múltiplos logs de uma vez

```js
await vlog.send([
  { level: "info",  message: "inicio do fluxo", traceId: "t1" },
  { level: "debug", message: "etapa 1",          traceId: "t1" },
  { level: "info",  message: "fim do fluxo",     traceId: "t1" },
]);
```

### Modo desenvolvimento (sem SSL)

Para ambientes locais ou de teste onde o servidor não usa HTTPS, passe a URL e habilite `allowHttp`:

```js
vlog.init({
  token: "seu-token",
  url: "http://129.148.45.50:8000/logs",
  allowHttp: true,
});
```

> **Atenção:** nunca use `allowHttp: true` em produção. Sem HTTPS o token trafega em texto claro.

### Middleware Express

```js
import express from "express";
import vlog from "vertti-logs";

vlog.init("seu-token");

const app = express();
app.use(vlog.requestLogger()); // loga todas as requisições automaticamente
```