import axios from "axios";
import pRetry, { AbortError } from "p-retry";
import { config } from "./config.js";
import { httpAgent, httpsAgent } from "./http-agents.js";
import { logger } from "./logger.js";
import { metrics } from "./metrics.js";

export class TelegramClient {
  constructor(token) {
    this.http = axios.create({
      baseURL: `https://api.telegram.org/bot${token}`,
      timeout: (config.poller.pollTimeout + 10) * 1000,
      httpAgent,
      httpsAgent,
    });
  }

  async getUpdates(offset = 0) {
    return pRetry(
      async () => {
        const startedAt = Date.now();
        try {
          const { data } = await this.http.post("/getUpdates", {
            offset,
            timeout: config.poller.pollTimeout,
            allowed_updates: [
              "message",
              "edited_message",
              "callback_query",
              "my_chat_member",
              "chat_member",
            ],
          });

          if (!data?.ok) {
            throw new Error(data?.description || "Unknown Telegram API error");
          }

          metrics.telegramRequestDuration.observe(
            { method: "get_updates", status: "ok" },
            (Date.now() - startedAt) / 1000,
          );
          return data.result || [];
        } catch (error) {
          metrics.telegramRequestDuration.observe(
            { method: "get_updates", status: "error" },
            (Date.now() - startedAt) / 1000,
          );

          const status = error?.response?.status;
          if (status === 401) {
            throw new AbortError("Bot token is invalid");
          }

          if (status === 429) {
            const retryAfter = error?.response?.data?.parameters?.retry_after || 1;
            logger.warn({ retryAfter }, "Telegram rate limit reached");
            await new Promise((resolve) => setTimeout(resolve, retryAfter * 1000));
          }

          throw error;
        }
      },
      {
        retries: config.retry.retries,
        minTimeout: config.retry.minTimeout,
        maxTimeout: config.retry.maxTimeout,
        onFailedAttempt: (error) => {
          logger.warn(
            {
              attempt: error.attemptNumber,
              retriesLeft: error.retriesLeft,
              message: error.message,
            },
            "Retrying getUpdates",
          );
        },
      },
    );
  }

  async deleteWebhook() {
    const { data } = await this.http.post("/deleteWebhook", {
      drop_pending_updates: false,
    });
    return data?.result;
  }
}
