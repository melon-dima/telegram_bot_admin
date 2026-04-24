import pino from "pino";
import { config } from "./config.js";

export const logger = pino({
  level: config.logLevel,
  transport:
    config.env !== "production" && config.logPretty
      ? {
          target: "pino-pretty",
          options: { colorize: true, translateTime: "HH:MM:ss.l" },
        }
      : undefined,
  base: { service: "node-bot-poller" },
});
