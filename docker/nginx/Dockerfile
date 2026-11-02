# syntax=docker/dockerfile:1.7
# Production web image: the React build plus nginx. Build context is the repository root.

FROM node:24-alpine AS build
WORKDIR /app
COPY web/package.json web/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY web/ ./
RUN npm run build

FROM nginx:1.28-alpine AS production
COPY docker/nginx/snippets/ /etc/nginx/snippets/
COPY docker/nginx/production.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /var/www/web/dist
COPY api/public /var/www/api/public
