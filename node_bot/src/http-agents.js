import http from "node:http";
import https from "node:https";
import { config } from "./config.js";

const baseAgentOptions = {
  keepAlive: config.network.keepAlive,
  maxSockets: config.network.maxSockets,
  maxFreeSockets: config.network.maxFreeSockets,
  maxRequestsPerSocket: config.network.maxRequestsPerSocket,
};

export const httpAgent = new http.Agent(baseAgentOptions);

export const httpsAgent = new https.Agent({
  ...baseAgentOptions,
  maxCachedSessions: config.network.tlsMaxCachedSessions,
});

export const closeHttpAgents = () => {
  httpAgent.destroy();
  httpsAgent.destroy();
};
