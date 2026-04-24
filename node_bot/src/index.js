import { BotManager } from "./bot-manager.js";
import { config } from "./config.js";
import { HealthServer } from "./health-server.js";
import { closeHttpAgents } from "./http-agents.js";
import { LaravelAPI } from "./laravel-api.js";
import { logger } from "./logger.js";

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const bootstrap = async () => {
  process.on("warning", (warning) => {
    logger.warn(
      {
        name: warning.name,
        message: warning.message,
        stack: warning.stack,
      },
      "Node process warning",
    );
  });

  const laravelApi = new LaravelAPI();
  const manager = new BotManager(laravelApi);
  const healthServer = new HealthServer(config.health.port, manager);

  await manager.start();
  healthServer.start();

  const heartbeatTimer = setInterval(() => {
    laravelApi.heartbeat(manager.getStats()).catch(() => undefined);
  }, config.heartbeatInterval);

  let shuttingDown = false;
  const shutdown = async (signal) => {
    if (shuttingDown) {
      return;
    }
    shuttingDown = true;

    logger.info({ signal }, "Graceful shutdown started");
    clearInterval(heartbeatTimer);

    await Promise.allSettled([manager.stop(), healthServer.stop()]);
    closeHttpAgents();
    await sleep(100);
    logger.info("Shutdown complete");
    process.exit(0);
  };

  process.on("SIGINT", () => shutdown("SIGINT"));
  process.on("SIGTERM", () => shutdown("SIGTERM"));
};

bootstrap().catch((error) => {
  logger.error({ message: error.message, stack: error.stack }, "Failed to start poller");
  closeHttpAgents();
  process.exit(1);
});
