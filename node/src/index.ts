const DEFAULT_LOGS_URL = "https://api-vertti-logs.velog.com.br/logs";
const MAX_METADATA_BYTES = 64 * 1024; // 64 KB
const SDK_NAME = "vertti-logs-node";
// __SDK_VERSION__ é substituído pelo tsup em tempo de build (ver package.json)
declare const __SDK_VERSION__: string;
const SDK_VERSION = __SDK_VERSION__;

export type LogLevel = "debug" | "info" | "warn" | "error";

export interface LogEntry {
  timestamp?: string;
  level: LogLevel;
  message: string;
  traceId?: string;
  spanId?: string;
  metadata?: Record<string, unknown>;
}

export interface VLogConfig {
  token: string;
  url?: string;
  /** Permite HTTP explicitamente — use apenas em ambientes de desenvolvimento/teste */
  allowHttp?: boolean;
}

function assertHttps(url: string, allowHttp: boolean): void {
  const { protocol, hostname } = new URL(url);
  const isLocalhost = hostname === "localhost" || hostname === "127.0.0.1";
  if (protocol !== "https:" && !isLocalhost && !allowHttp) {
    throw new Error(
      `vlog: a URL deve usar HTTPS (recebido: ${protocol}). ` +
      `Para ambientes de teste, use { allowHttp: true } no vlog.init().`
    );
  }
}

// Removes newlines and control characters to prevent log injection
function sanitize(value: string): string {
  return value.replace(/[\x00-\x1f\x7f]/g, " ");
}

class VLog {
  private token: string | null = null;
  private url: string = DEFAULT_LOGS_URL;

  init(config: string | VLogConfig): void {
    const raw = typeof config === "string" ? config : config.token;
    const token = raw.trim();
    if (!token) {
      throw new Error("vlog: token não pode ser vazio");
    }
    this.token = token;

    if (typeof config !== "string" && config.url) {
      assertHttps(config.url, config.allowHttp ?? false);
      this.url = config.url;
    }
  }

  private assertToken(): string {
    if (!this.token) {
      throw new Error("vlog: chame vlog.init(token) antes de usar o SDK");
    }
    return this.token;
  }

  async send(logs: LogEntry | LogEntry[]): Promise<{ ok: boolean; status: number }> {
    const token = this.assertToken();
    const entries = Array.isArray(logs) ? logs : [logs];

    const mapped = entries.map((e) => {
      if (e.metadata) {
        const size = JSON.stringify(e.metadata).length;
        if (size > MAX_METADATA_BYTES) {
          throw new Error(`vlog: metadata excede o limite de ${MAX_METADATA_BYTES / 1024} KB`);
        }
      }
      return {
        timestamp: e.timestamp ?? new Date().toISOString(),
        level: e.level,
        message: sanitize(e.message),
        traceId: e.traceId ? sanitize(e.traceId) : undefined,
        spanId: e.spanId ? sanitize(e.spanId) : undefined,
        metadata: {
          ...e.metadata,
          _sdk: { name: SDK_NAME, version: SDK_VERSION },
        },
      };
    });

    const response = await fetch(this.url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
        Accept: "*/*",

      },
      body: JSON.stringify({ logs: mapped }),
    });

    return { ok: response.ok, status: response.status };
  }

  debug(message: string, extra?: Omit<LogEntry, "level" | "message">) {
    return this.send({ level: "debug", message, ...extra });
  }

  info(message: string, extra?: Omit<LogEntry, "level" | "message">) {
    return this.send({ level: "info", message, ...extra });
  }

  warn(message: string, extra?: Omit<LogEntry, "level" | "message">) {
    return this.send({ level: "warn", message, ...extra });
  }

  error(message: string, extra?: Omit<LogEntry, "level" | "message">) {
    return this.send({ level: "error", message, ...extra });
  }

  requestLogger() {
    return (req: any, res: any, next: () => void) => {
      res.on("finish", () => {
        // remoteAddress é o IP real da conexão TCP, não pode ser forjado via header
        const ip = req.socket?.remoteAddress ?? "unknown";
        this.info(`${req.method} ${sanitize(req.path)}`, {
          metadata: { status: res.statusCode, ip },
        }).catch(() => {});
      });
      next();
    };
  }
}

const vlog = new VLog();
export default vlog;
