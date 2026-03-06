# Log Service

A Symfony 8 microservice for collecting, processing, and publishing logs to RabbitMQ via the Symfony Messenger component.

Every published message carries:

| Field          | Description                              |
|----------------|------------------------------------------|
| `log`          | The original log entry (array)           |
| `batch_id`     | Unique ID for the entire batch           |
| `published_at` | ISO 8601 timestamp of publication        |
| `retry_count`  | Number of retries (starts at 0)          |


Services:

| Service    | URL / port                           |
|------------|--------------------------------------|
| API        | http://localhost:80                  |
| RabbitMQ   | amqp://localhost:5672                |
| RabbitMQ UI| http://localhost:15672 (admin/admin) |

---

## Running tests

```bash
# Inside the app container:
docker compose exec app php vendor/bin/phpunit --testdox

# Or directly (needs PHP 8.4+ and composer deps locally):
composer install
php vendor/bin/phpunit --testdox
```

Test suites:

- **Unit** – `tests/Unit/Service/LogValidatorTest.php` – 15 tests covering all validation rules with no I/O.
- **Integration** – `tests/Integration/Controller/LogIngestionControllerTest.php` – 8 tests using Symfony's `WebTestCase` with the `in-memory://` Messenger transport (no real RabbitMQ needed).

---

## API reference

### `POST /api/logs/ingest`

Accepts a JSON batch of log entries.

#### Request

```
Content-Type: application/json
```

```json
{
  "logs": [
    {
      "timestamp": "2026-02-26T10:30:45Z",
      "level": "error",
      "service": "auth-service",
      "message": "User authentication failed",
      "context": {
        "user_id": 123,
        "ip": "192.168.1.1",
        "error_code": "INVALID_TOKEN"
      },
      "trace_id": "abc123def456"
    }
  ]
}
```

**Required fields per log entry:** `timestamp`, `level`, `service`, `message`

**Valid levels:** `debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, `emergency`

**Max logs per batch:** 1 000

#### Responses

| Status | Meaning                        |
|--------|--------------------------------|
| 202    | Batch accepted & queued        |
| 400    | Validation error               |
| 415    | Wrong Content-Type             |

**202 response body:**

```json
{
  "status": "accepted",
  "batch_id": "batch_550e8400e29b41d4a716446655440000",
  "logs_count": 2
}
```

**400 response body:**

```json
{
  "status": "error",
  "errors": [
    "Log entry[0]: missing required field \"message\"."
  ]
}
```

---

## curl examples

### Send a valid batch of two logs

```bash
curl -s -X POST http://localhost:8080/api/logs/ingest \
  -H "Content-Type: application/json" \
  -d '{
    "logs": [
      {
        "timestamp": "2026-02-26T10:30:45Z",
        "level": "error",
        "service": "auth-service",
        "message": "User authentication failed",
        "context": {"user_id": 123, "ip": "192.168.1.1"},
        "trace_id": "abc123def456"
      },
      {
        "timestamp": "2026-02-26T10:30:46Z",
        "level": "info",
        "service": "api-gateway",
        "message": "Request processed",
        "context": {"endpoint": "/api/users", "method": "GET", "response_time_ms": 145},
        "trace_id": "abc123def456"
      }
    ]
  }' | jq .
```

Expected output:

```json
{
  "status": "accepted",
  "batch_id": "batch_550e8400e29b41d4a716446655440000",
  "logs_count": 2
}
```

---

### Trigger a 400 (missing required field)

```bash
curl -s -X POST http://localhost:8080/api/logs/ingest \
  -H "Content-Type: application/json" \
  -d '{
    "logs": [
      {
        "timestamp": "2026-02-26T10:30:45Z",
        "level": "info",
        "service": "my-service"
      }
    ]
  }' | jq .
```

Expected output:

```json
{
  "status": "error",
  "errors": [
    "Log entry[0]: missing required field \"message\"."
  ]
}
```

---

### Trigger a 400 (invalid level)

```bash
curl -s -X POST http://localhost:8080/api/logs/ingest \
  -H "Content-Type: application/json" \
  -d '{
    "logs": [
      {
        "timestamp": "2026-02-26T10:30:45Z",
        "level": "verbose",
        "service": "my-service",
        "message": "Something happened"
      }
    ]
  }' | jq .
```

---

### Consume messages manually

```bash
docker compose exec app \
  php bin/console messenger:consume async --limit=10 -vv
```

---

## Configuration

All runtime parameters live in `.env`:

```dotenv
APP_ENV=dev
APP_SECRET=SuperSecretString
MESSENGER_TRANSPORT_DSN=amqp://admin:admin@rabbitmq:5672/%2f
```

---

## Project structure

```
log-service/
├── config/
│   ├── packages/
│   │   ├── framework.yaml
│   │   ├── messenger.yaml
│   │   └── test/
│   │       └── messenger.yaml
│   ├── bundles.php
│   ├── routes.yaml
│   └── services.yaml
├── docker/
│   ├── nginx/default.conf
│   └── php/Dockerfile
├── public/index.php
├── src/
│   ├── Controller/
│   │   └── LogIngestionController.php
│   ├── DTO/
│   │   └── LogEntry.php
│   ├── Message/
│   │   └── LogMessage.php    ← Messenger message (carries metadata)
│   ├── Service/
│   │   ├── LogPublisher.php  ← dispatches messages to the bus
│   │   └── LogValidator.php  ← pure validation logic
│   └── Kernel.php
├── tests/
│   ├── Unit/Service/
│   │   └── LogValidatorTest.php
│   └── Integration/Controller/
│       └── LogIngestionControllerTest.php
├── .env
├── .env.example
├── .env.test
├── composer.json
├── docker-compose.yml
├── phpunit.xml.dist
└── README.md
```
