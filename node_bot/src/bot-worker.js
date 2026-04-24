import PQueue from "p-queue";
import { config } from "./config.js";
import { logger } from "./logger.js";
import { metrics } from "./metrics.js";
import { TelegramClient } from "./telegram-api.js";

export class BotWorker {
  constructor(bot, laravelApi) {
    this.bot = bot;
    this.laravelApi = laravelApi;
    this.telegram = new TelegramClient(bot.token);
    this.queue = new PQueue({ concurrency: config.poller.updateQueueConcurrency });
    this.offset = 0;
    this.running = false;
    this.stopped = false;
    this.loopPromise = null;
    this.log = logger.child({ botId: bot.id, botName: bot.name || `bot-${bot.id}` });
  }

  async start() {
    if (this.running) {
      return;
    }

    this.running = true;
    this.stopped = false;
    this.log.info("Starting worker");

    try {
      await this.telegram.deleteWebhook();
    } catch (error) {
      this.log.debug({ message: error.message }, "deleteWebhook failed, continue");
    }

    this.offset = await this.laravelApi.getOffset(this.bot.id);
    this.log.info({ offset: this.offset }, "Offset loaded");

    this.loopPromise = this.loop();
    this.loopPromise.catch((error) => {
      this.log.error({ message: error.message }, "Worker loop crashed");
      this.running = false;
      this.stopped = true;
    });
  }

  async loop() {
    while (this.running && !this.stopped) {
      try {
        const updates = await this.telegram.getUpdates(this.offset);
        if (!updates.length) {
          continue;
        }

        metrics.updatesReceived.inc({ bot_id: String(this.bot.id) }, updates.length);

        for (const update of updates) {
          this.queue.add(() => this.handleUpdate(update));
        }

        this.offset = updates[updates.length - 1].update_id + 1;
        await this.laravelApi.saveOffset(this.bot.id, this.offset);
      } catch (error) {
        this.log.error({ message: error.message }, "Polling loop error");
        metrics.pollingErrors.inc({ bot_id: String(this.bot.id) });

        if (String(error.message || "").toLowerCase().includes("invalid")) {
          this.log.error("Stopping worker because token appears invalid");
          this.stopped = true;
          this.running = false;
          break;
        }

        await new Promise((resolve) => setTimeout(resolve, 5000));
      }
    }

    await this.queue.onIdle();
    this.log.info("Worker stopped");
  }

  async handleUpdate(update) {
    try {
      await this.laravelApi.sendUpdate(this.bot.id, update);
      metrics.updatesSent.inc({ bot_id: String(this.bot.id), status: "ok" });
    } catch (error) {
      this.log.error(
        { message: error.message, updateId: update?.update_id },
        "Failed to send update to Laravel",
      );
      metrics.updatesSent.inc({ bot_id: String(this.bot.id), status: "error" });
    }
  }

  async stop() {
    this.running = false;
    this.stopped = true;
    await this.queue.onIdle();
    if (this.loopPromise) {
      await this.loopPromise.catch(() => undefined);
    }
  }
}
