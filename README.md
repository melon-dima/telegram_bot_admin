# Telegram Bot Admin

Админ-панель и API для управления Telegram-ботами.

## Зачем этот проект

Проект нужен, чтобы централизованно работать с несколькими ботами в одном месте:

- управлять ботами и их настройками;
- видеть чаты, пользователей и сообщения;
- отправлять сообщения из админки;
- разграничивать доступ между пользователями;
- принимать входящие обновления Telegram на backend.

## Текущая архитектура

- `Laravel API` (основной backend);
- `PostgreSQL` (основная БД);
- простой SPA-интерфейс на базе `public/index.html`;
- `node_bot` для Telegram polling.

## Запуск (только через Docker Compose)

### 1. Поднять сервисы

```bash
docker compose up -d --build
```

Команда поднимает:
- `tba_php` (Laravel + nginx + php-fpm);
- `tba_node_bot` (polling-сервис Telegram).

### 2. Первичная инициализация Laravel внутри контейнера

```bash
docker compose exec tba_php sh -lc '[ -f .env ] || cp .env.example .env'
docker compose exec tba_php php artisan key:generate
docker compose exec tba_php php artisan migrate --force
```

### 3. Обязательные переменные для `tba_node_bot` в `.env`

```env
LARAVEL_API_URL=http://tba_php/api
SERVICE_API_TOKEN=your_service_token
```

### 4. Проверка, что polling работает постоянно

```bash
docker compose ps
docker compose logs -f tba_node_bot
```

Health/metrics:
- `http://localhost:3001/health`
- `http://localhost:3001/metrics`

### 5. Полезные команды

```bash
docker compose exec tba_php php artisan optimize:clear
docker compose exec tba_php php artisan route:list
docker compose exec tba_php composer test
docker compose logs -f tba_php
```

## Roadmap

1. Перевести текущий `public/index.html` в полноценный SPA (Vue или React) с роутингом и структурой экранов.
2. Улучшить UX админки: фильтры, поиск, статусы, удобная работа с чатами и сообщениями.
3. Добавить realtime-обновления (WebSocket/Reverb), чтобы снизить зависимость UI от polling.
4. Добавить CI-пайплайн: линтеры, тесты, сборка, автопроверка перед merge.
