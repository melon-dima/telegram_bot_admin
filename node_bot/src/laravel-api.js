import axios from "axios";
import { createHash } from "node:crypto";
import pRetry, { AbortError } from "p-retry";
import { config } from "./config.js";
import { httpAgent, httpsAgent } from "./http-agents.js";
import { logger } from "./logger.js";
import { metrics } from "./metrics.js";

const extractDataArray = (payload) => {
  if (Array.isArray(payload)) {
    return payload;
  }
  if (Array.isArray(payload?.data)) {
    return payload.data;
  }
  return [];
};

export class LaravelAPI {
  constructor() {
    this.http = axios.create({
      baseURL: config.laravel.url,
      timeout: 15000,
      httpAgent,
      httpsAgent,
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-Service-Token": config.laravel.token,
        "X-Internal-Token": config.laravel.token,
      },
    });
    this.botTokens = new Map();
    this.offsets = new Map();
  }

  async getPollingBots() {
    const { data } = await this.http.get("/bots");
    const bots = extractDataArray(data);

    this.botTokens.clear();
    for (const bot of bots) {
      if (bot?.id && bot?.token) {
        this.botTokens.set(String(bot.id), String(bot.token));
      }
    }

    return bots;
  }

  async sendUpdate(botId, update) {
    const token = this.botTokens.get(String(botId));
    if (!token) {
      throw new AbortError(`No token found for bot ${botId}`);
    }

    const secret = createHash("sha256").update(token).digest("hex");

    return pRetry(
      async () => {
        const start = Date.now();
        try {
          const { data } = await this.http.post(`/telegram/webhook/${botId}/${secret}`, update);
          metrics.laravelRequestDuration.observe(
            { endpoint: "send_update", status: "ok" },
            (Date.now() - start) / 1000,
          );
          return data;
        } catch (error) {
          metrics.laravelRequestDuration.observe(
            { endpoint: "send_update", status: "error" },
            (Date.now() - start) / 1000,
          );
          const status = error?.response?.status;
          if (status >= 400 && status < 500 && status !== 429) {
            throw new AbortError(`Laravel rejected update: ${status}`);
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
              botId,
              updateId: update?.update_id,
              attempt: error.attemptNumber,
              retriesLeft: error.retriesLeft,
              message: error.message,
            },
            "Retrying update send to Laravel",
          );
        },
      },
    );
  }

  async saveOffset(botId, offset) {
    this.offsets.set(String(botId), Number.parseInt(String(offset), 10) || 0);
  }

  async getOffset(botId) {
    return this.offsets.get(String(botId)) || 0;
  }

  async heartbeat(stats) {
    logger.debug({ stats }, "Heartbeat collected");
  }
}
