FROM node:20-alpine

WORKDIR /application

RUN npm install -g pm2

COPY node_bot/package*.json ./
RUN npm install --omit=dev --no-audit --no-fund

COPY node_bot/src ./src
COPY node_bot/ecosystem.config.cjs ./ecosystem.config.cjs

EXPOSE 3001

CMD ["pm2-runtime", "ecosystem.config.cjs"]
