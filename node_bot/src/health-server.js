import http from "node:http";
import { logger } from "./logger.js";
import { registry } from "./metrics.js";

const json = (res, statusCode, payload) => {
  res.statusCode = statusCode;
  res.setHeader("Content-Type", "application/json; charset=utf-8");
  res.end(JSON.stringify(payload));
};

export class HealthServer {
  constructor(port, manager) {
    this.port = port;
    this.manager = manager;
    this.server = null;
  }

  start() {
    this.server = http.createServer(async (req, res) => {
      const url = req.url || "/";

      if (url.startsWith("/health")) {
        json(res, 200, {
          status: "ok",
          timestamp: new Date().toISOString(),
          ...this.manager.getStats(),
        });
        return;
      }

      if (url.startsWith("/metrics")) {
        res.statusCode = 200;
        res.setHeader("Content-Type", registry.contentType);
        res.end(await registry.metrics());
        return;
      }

      json(res, 404, { message: "Not found" });
    });

    this.server.listen(this.port, () => {
      logger.info({ port: this.port }, "Health server started");
    });
  }

  stop() {
    if (!this.server) {
      return Promise.resolve();
    }

    return new Promise((resolve, reject) => {
      this.server.close((error) => {
        if (error) {
          reject(error);
          return;
        }
        resolve();
      });
    });
  }
}
