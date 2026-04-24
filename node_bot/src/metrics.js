import { Counter, Gauge, Histogram, Registry, collectDefaultMetrics } from "prom-client";

export const registry = new Registry();
collectDefaultMetrics({ register: registry, prefix: "poller_" });

const updatesReceived = new Counter({
  name: "poller_updates_received_total",
  help: "Total updates received from Telegram",
  labelNames: ["bot_id"],
  registers: [registry],
});

const updatesSent = new Counter({
  name: "poller_updates_sent_total",
  help: "Total updates sent to Laravel",
  labelNames: ["bot_id", "status"],
  registers: [registry],
});

const pollingErrors = new Counter({
  name: "poller_polling_errors_total",
  help: "Polling loop errors",
  labelNames: ["bot_id"],
  registers: [registry],
});

const activeBots = new Gauge({
  name: "poller_active_bots",
  help: "Currently active bot workers",
  registers: [registry],
});

const telegramRequestDuration = new Histogram({
  name: "poller_telegram_request_seconds",
  help: "Telegram request duration in seconds",
  labelNames: ["method", "status"],
  buckets: [0.1, 0.5, 1, 2, 5, 10, 30, 60],
  registers: [registry],
});

const laravelRequestDuration = new Histogram({
  name: "poller_laravel_request_seconds",
  help: "Laravel request duration in seconds",
  labelNames: ["endpoint", "status"],
  buckets: [0.01, 0.05, 0.1, 0.5, 1, 2, 5, 10],
  registers: [registry],
});

export const metrics = {
  updatesReceived,
  updatesSent,
  pollingErrors,
  activeBots,
  telegramRequestDuration,
  laravelRequestDuration,
};
