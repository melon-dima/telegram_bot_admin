import { BotWorker } from "./bot-worker.js";
import { config } from "./config.js";
import { logger } from "./logger.js";
import { metrics } from "./metrics.js";

const normalizeBots = (bots) =>
  bots
    .filter((bot) => bot && bot.id && bot.token)
    .map((bot) => ({
      id: bot.id,
      name: bot.name || `bot-${bot.id}`,
      token: bot.token,
      is_active: bot.is_active ?? bot.isActive ?? true,
      mode: String(bot.mode || "poll").toLowerCase(),
    }));

export class BotManager {
  constructor(laravelApi) {
    this.laravelApi = laravelApi;
    this.workers = new Map();
    this.syncTimer = null;
    this.running = false;
    this.startedAt = Date.now();
    this.log = logger.child({ scope: "bot-manager" });
  }

  async start() {
    if (this.running) {
      return;
    }
    this.running = true;

    try {
      await this.syncBots();
    } catch (error) {
      this.log.error({ message: error.message }, "Initial bot sync failed, will retry");
    }
    this.syncTimer = setInterval(() => {
      this.syncBots().catch((error) => {
        this.log.error({ message: error.message }, "Failed to sync bots");
      });
    }, config.poller.botsRefreshInterval);

    this.log.info({ everyMs: config.poller.botsRefreshInterval }, "Bot manager started");
  }

  async syncBots() {
    if (!this.running) {
      return;
    }

    const rawBots = await this.laravelApi.getPollingBots();
    const bots = normalizeBots(rawBots).filter(
      (bot) => (bot.mode === "poll" || bot.mode === "polling") && bot.is_active,
    );
    const activeIds = new Set(bots.map((bot) => String(bot.id)));

    for (const [id, worker] of this.workers.entries()) {
      if (!activeIds.has(String(id))) {
        await worker.stop();
        this.workers.delete(id);
        this.log.info({ botId: id }, "Stopped worker");
      }
    }

    for (const bot of bots) {
      const id = String(bot.id);
      const existing = this.workers.get(id);
      if (!existing) {
        const worker = new BotWorker(bot, this.laravelApi);
        this.workers.set(id, worker);
        await worker.start();
        this.log.info({ botId: id }, "Started new worker");
        continue;
      }

      if (existing.bot.token !== bot.token) {
        await existing.stop();
        const worker = new BotWorker(bot, this.laravelApi);
        this.workers.set(id, worker);
        await worker.start();
        this.log.info({ botId: id }, "Restarted worker after token change");
      }
    }

    metrics.activeBots.set(this.workers.size);
  }

  getStats() {
    return {
      active_bots: this.workers.size,
      uptime_seconds: Math.floor((Date.now() - this.startedAt) / 1000),
      pid: process.pid,
      memory_mb: Math.round(process.memoryUsage().rss / 1024 / 1024),
    };
  }

  async stop() {
    this.running = false;
    if (this.syncTimer) {
      clearInterval(this.syncTimer);
      this.syncTimer = null;
    }

    await Promise.all(
      [...this.workers.values()].map((worker) => worker.stop().catch(() => undefined)),
    );
    this.workers.clear();
    metrics.activeBots.set(0);
    this.log.info("Bot manager stopped");
  }
}
