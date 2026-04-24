import dotenv from "dotenv";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const defaultEnvPath = path.resolve(__dirname, "../../.env");
dotenv.config({ path: process.env.DOTENV_PATH || defaultEnvPath });

const toInt = (value, fallback) => {
  const parsed = Number.parseInt(value ?? "", 10);
  return Number.isNaN(parsed) ? fallback : parsed;
};

export const config = {
  env: process.env.NODE_ENV || "development",
  logLevel: process.env.LOG_LEVEL || "info",
  logPretty: process.env.LOG_PRETTY === "true",
  laravel: {
    url: process.env.LARAVEL_API_URL || "http://localhost:8000/api",
    token: process.env.SERVICE_API_TOKEN || "",
  },
  poller: {
    pollTimeout: toInt(process.env.POLL_TIMEOUT, 30),
    botsRefreshInterval: toInt(process.env.BOTS_REFRESH_INTERVAL, 60000),
    updateQueueConcurrency: toInt(process.env.UPDATE_QUEUE_CONCURRENCY, 10),
  },
  retry: {
    retries: toInt(process.env.MAX_RETRIES, 5),
    minTimeout: toInt(process.env.RETRY_MIN_TIMEOUT, 1000),
    maxTimeout: toInt(process.env.RETRY_MAX_TIMEOUT, 30000),
  },
  health: {
    port: toInt(process.env.HEALTH_PORT, 3001),
  },
  network: {
    keepAlive: process.env.HTTP_KEEP_ALIVE === "true",
    maxSockets: toInt(process.env.HTTP_MAX_SOCKETS, 50),
    maxFreeSockets: toInt(process.env.HTTP_MAX_FREE_SOCKETS, 10),
    maxRequestsPerSocket: toInt(process.env.HTTP_MAX_REQUESTS_PER_SOCKET, 8),
    tlsMaxCachedSessions: toInt(process.env.HTTPS_MAX_CACHED_SESSIONS, 100),
  },
  heartbeatInterval: toInt(process.env.HEARTBEAT_INTERVAL, 30000),
};

if (!config.laravel.token) {
  throw new Error(
    `SERVICE_API_TOKEN is required. Set it in ${process.env.DOTENV_PATH || defaultEnvPath}`,
  );
}
