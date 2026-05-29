# Laravel Notification System

Enterprise-grade, production-ready notification system built with Laravel 13, Redis queues, and Pest PHP tests.

---

## Architecture Overview

```
HTTP Request
    │
    ▼
NotificationController (thin — validation + response only)
    │
    ▼
NotificationService (business logic, cache, transactions)
    │
    ▼
EloquentNotificationRepository (data access abstraction)
    │
    ▼
Notification Model (Eloquent)

Queue:
ProcessNotificationJob ──► NotificationChannelManager ──► Channel Strategy (Email/SMS/Push)
```

### Design Patterns Used

| Pattern           | Implementation                                     |
|-------------------|----------------------------------------------------|
| Repository        | `NotificationRepositoryInterface` + Eloquent impl  |
| DTO               | `NotificationData`, `NotificationFilterData`, etc  |
| Value Object      | `TenantId`, `UserId`                               |
| Strategy          | `NotificationChannelInterface` per channel type    |
| Service Layer     | `NotificationService`                              |
| Manager/Registry  | `NotificationChannelManager`                       |

---

## Requirements

- PHP 8.3+
- Laravel 13
- MySQL 8+
- Redis 6+
- Composer

---

## Installation

```bash
# 1. Clone repository
git clone <repo-url> notification-system
cd notification-system

# 2. Install dependencies
composer install

# 3. Copy environment
cp .env.example .env

# 4. Generate app key
php artisan key:generate

# 5. Configure .env — set DB and Redis credentials (see below)

# 6. Run migrations
php artisan migrate

# 7. (Optional) Seed test data
php artisan db:seed
```

---

## Redis Setup

Install Redis locally:

```bash
# macOS
brew install redis
brew services start redis

# Ubuntu
sudo apt install redis-server
sudo systemctl enable --now redis
```

Verify Redis is running:

```bash
redis-cli ping
# PONG
```

Set in `.env`:

```dotenv
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

---

## Queue Worker Commands

### Start a specific channel worker
```bash
# Email notifications
php artisan queue:work redis --queue=notifications-email --tries=5 --backoff=10,30,90,270,810

# SMS notifications
php artisan queue:work redis --queue=notifications-sms --tries=5 --backoff=10,30,90,270,810

# Push notifications
php artisan queue:work redis --queue=notifications-push --tries=5 --backoff=10,30,90,270,810
```

### Start all queues (recommended for production via Supervisor)
```bash
php artisan queue:work redis \
  --queue=notifications-email,notifications-sms,notifications-push \
  --tries=5 \
  --timeout=30 \
  --backoff=10,30,90,270,810
```

### Monitor failed jobs
```bash
php artisan queue:failed
php artisan queue:retry all
php artisan queue:flush
```

### Supervisor configuration (production)

```ini
[program:notification-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/artisan queue:work redis --queue=notifications-email,notifications-sms,notifications-push --sleep=3 --tries=5 --timeout=30
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/storage/logs/worker.log
stopwaitsecs=3600
```

---

## Testing Commands

```bash
# Run all tests
php artisan test

# Run with Pest directly
vendor/bin/pest

# Run specific test file
vendor/bin/pest tests/Feature/PublishNotificationTest.php

# Run with coverage
vendor/bin/pest --coverage

# Run only unit tests
vendor/bin/pest tests/Unit

# Run only feature tests
vendor/bin/pest tests/Feature
```

---

## API Documentation

### Base URL
```
/api/v1
```

All requests must include:
```
Accept: application/json
Content-Type: application/json
```

---

### POST /api/v1/notifications

Publish a new notification. Stores in DB and dispatches to Redis queue.

**Rate Limit:** 10 requests per `user_id` per hour.

**Request Body:**

```json
{
  "tenant_id": 1,
  "user_id": 15,
  "type": "email",
  "title": "Application Approved",
  "message": "Your application has been approved.",
  "metadata": {
    "application_id": 1001
  }
}
```

| Field       | Type    | Required | Notes                              |
|-------------|---------|----------|------------------------------------|
| `tenant_id` | integer | ✅       | Must be positive                   |
| `user_id`   | integer | ✅       | Must be positive; used for rate limiting |
| `type`      | string  | ✅       | `email`, `sms`, or `push`          |
| `title`     | string  | ✅       | Max 255 chars                      |
| `message`   | string  | ✅       | Max 5000 chars                     |
| `metadata`  | object  | ❌       | Arbitrary key-value JSON           |

**Success Response (202 Accepted):**

```json
{
  "success": true,
  "message": "Notification queued successfully.",
  "data": {
    "id": 1,
    "tenant_id": 1,
    "user_id": 15,
    "type": "email",
    "title": "Application Approved",
    "message": "Your application has been approved.",
    "metadata": { "application_id": 1001 },
    "status": "pending",
    "attempts": 0,
    "processed_at": null,
    "failed_at": null,
    "created_at": "2024-01-01T00:00:00+00:00",
    "updated_at": "2024-01-01T00:00:00+00:00"
  }
}
```

**Validation Error (422):**

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "type": ["The selected type is invalid."]
  }
}
```

**Rate Limited (429):**

```json
{
  "success": false,
  "message": "Rate limit exceeded. Maximum 10 notifications per user per hour.",
  "errors": {}
}
```

---

### GET /api/v1/notifications

Returns paginated list of notifications with optional filters.

**Query Parameters:**

| Parameter   | Type    | Notes                                          |
|-------------|---------|------------------------------------------------|
| `status`    | string  | `pending`, `processing`, `processed`, `failed` |
| `type`      | string  | `email`, `sms`, `push`                         |
| `tenant_id` | integer | Filter by tenant                               |
| `user_id`   | integer | Filter by user                                 |
| `per_page`  | integer | Default: 15, Max: 100                          |
| `page`      | integer | Default: 1                                     |

**Example:**
```
GET /api/v1/notifications?status=failed&tenant_id=1&per_page=25
```

**Response (200):**

```json
{
  "data": [
    {
      "id": 42,
      "tenant_id": 1,
      "user_id": 15,
      "type": "email",
      "title": "Application Approved",
      "message": "Your application has been approved.",
      "metadata": {},
      "status": "failed",
      "attempts": 5,
      "processed_at": null,
      "failed_at": "2024-01-01T01:00:00+00:00",
      "created_at": "2024-01-01T00:00:00+00:00",
      "updated_at": "2024-01-01T01:00:00+00:00"
    }
  ],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 4,
    "per_page": 25,
    "to": 25,
    "total": 100
  }
}
```

---

### GET /api/v1/notifications/summary

Returns aggregated counts per status. Response is cached in Redis for 5 minutes.

**Response (200):**

```json
{
  "success": true,
  "message": "Summary retrieved successfully.",
  "data": {
    "total": 100,
    "processed": 80,
    "failed": 10,
    "pending": 8,
    "processing": 2
  }
}
```

---

## Notification Statuses

| Status       | Description                                    |
|--------------|------------------------------------------------|
| `pending`    | Stored, waiting to be picked up by worker      |
| `processing` | Currently being processed by a queue worker    |
| `processed`  | Successfully sent                              |
| `failed`     | Permanently failed after exhausting all retries|

---

## Queue & Retry Strategy

- **Max tries:** 5
- **Backoff (exponential):** 10s → 30s → 90s → 270s → 810s
- **Unique jobs:** Enabled via `ShouldBeUnique` — no duplicate processing for the same notification
- **Dead letter:** `failed_jobs` table captures exhausted jobs
- **Idempotency guard:** Job skips already-processed notifications

---

## Caching Strategy

| Key Pattern                          | TTL      | Invalidated When                  |
|--------------------------------------|----------|-----------------------------------|
| `notifications:summary`              | 5 minutes| New notification or status change |
| `notifications:list:*`               | 1 minute | Automatically expires             |

---

## Rate Limiting

- **Limit:** 10 notifications per `user_id` per hour
- **Implementation:** Laravel `RateLimiter` with Redis backend
- **Scope:** Per `user_id` (not per IP or tenant)
- **Concurrency safe:** Redis atomic increment

---

## Architectural Decisions

1. **Repository pattern** decouples business logic from Eloquent, making the system testable and storage-agnostic.
2. **Strategy pattern** for channels means adding new channel types (Webhook, Slack, etc.) requires only registering a new `NotificationChannelInterface` implementation — no changes to core logic.
3. **`ShouldBeUnique`** prevents duplicate job processing under concurrency using Redis locks.
4. **`DB::transaction`** wraps notification creation + job dispatch to prevent orphaned records.
5. **Separate queues per channel type** (`notifications-email`, `notifications-sms`, `notifications-push`) allow independent scaling and prioritization of workers.
6. **Cache invalidation** is explicit and scoped — summary cache is cleared on writes; list cache uses TTL expiry.
7. **DTOs are `readonly`** — immutable data carriers prevent accidental mutation across layers.

---

## Extensibility Guide

### Adding a new channel (e.g. Webhook)

1. Create `app/Services/Channels/WebhookChannel.php` implementing `NotificationChannelInterface`
2. Add `NotificationType::Webhook` to the enum
3. Register in `AppServiceProvider::register()`:
   ```php
   $manager->register(new WebhookChannel());
   ```
4. Done. No other files change.

### Adding scheduled notifications

1. Create a `scheduled_at` column in the migration
2. Dispatch `ProcessNotificationJob` with `->delay($notification->scheduled_at)`
3. Add filtering support in the repository

---

## Assumptions

- Authentication/authorization is handled by an upstream API gateway or middleware layer (not in scope).
- `tenant_id` and `user_id` are provided by the client; no users table is required.
- The "simulation" requirement means `Log::info()` is used instead of a real transport — the channel strategy pattern makes this trivially replaceable.
- All times are UTC.
- The `metadata` field is free-form JSON for extensibility (template IDs, deep-link URLs, etc.).


install Memurai-for-Redis-v8.2-RC1 to run on windows
memurai data  check memurai is runnig or not


memurai-cli
127.0.0.1:6379> set test 1
OK
127.0.0.1:6379>
127.0.0.1:6379> get test
"1"
127.0.0.1:6379>


php artisan serve


php artisan queue:work redis --queue=notifications-email,notifications-sms,notifications-push --tries=5




Invoke-RestMethod -Uri "http://localhost:8000/api/v1/notifications" `
  -Method POST `
  -ContentType "application/json" `
  -Headers @{Accept="application/json"} `
  -Body '{"tenant_id": 1, "user_id": 15, "type": "email", "title": "Test", "message": "Hello World"}'
# 🔔 Laravel Notification System — README

A simple guide to understand, run, and test this project.

---

## 📋 Table of Contents

1. [What is this project?](#what-is-this-project)
2. [What you need installed](#what-you-need-installed)
3. [How to start the project](#how-to-start-the-project)
4. [Step 1 — Check Redis (Memurai) is running](#step-1--check-redis-memurai-is-running)
5. [Step 2 — Start the Web Server](#step-2--start-the-web-server)
6. [Step 3 — Start the Queue Worker](#step-3--start-the-queue-worker)
7. [Step 4 — Send a Notification](#step-4--send-a-notification)
8. [All API Endpoints](#all-api-endpoints)
9. [How the whole system works](#how-the-whole-system-works)
10. [What each terminal shows](#what-each-terminal-shows)
11. [Common Errors and Fixes](#common-errors-and-fixes)

---

## What is this project?

This is a **Notification System API** built with Laravel.

It lets you:
- ✅ Send notifications (email, SMS, push) via an API
- ✅ Store every notification in a MySQL database
- ✅ Process notifications in the background using a queue (so the API is fast)
- ✅ Check the status of all notifications
- ✅ Get a summary (how many sent, failed, pending)

**Simple example:** When a user's job application is approved, your system calls this API → it saves the notification → a background worker sends the email → done.

---

## What you need installed

| Tool | Purpose | Check if installed |
|---|---|---|
| PHP 8.3+ | Runs Laravel | `php --version` |
| Composer | Installs PHP packages | `composer --version` |
| MySQL 8+ | Stores notifications | `mysql --version` |
| Memurai | Redis for Windows (queue + cache) | Check Windows Services |
| Laravel 13 | The framework | `php artisan --version` |

---

## How to start the project

Every time you want to run this project, you need **3 terminals open at the same time**.

```
Terminal 1 → Web Server (php artisan serve)
Terminal 2 → Queue Worker (php artisan queue:work ...)
Terminal 3 → Send requests / test the API
```

Think of it like this:
- **Terminal 1** = the restaurant front desk (takes orders)
- **Terminal 2** = the kitchen (processes orders in background)
- **Terminal 3** = you, the customer (placing orders)

---

## Step 1 — Check Redis (Memurai) is running

Before starting anything, make sure Memurai (Redis) is running.
Redis is used to store the queue jobs and cache API responses.

**Open PowerShell and run:**

```powershell
memurai-cli
```

You should see this:

```
127.0.0.1:6379>
```

Now test it by setting and getting a value:

```
127.0.0.1:6379> set test 1
OK
127.0.0.1:6379> get test
"1"
127.0.0.1:6379>
```

✅ If you see `OK` and `"1"` — **Redis is working perfectly.**

❌ If you get an error — Memurai is not running. Fix:
1. Press `Windows key` → search **Services**
2. Find **Memurai** in the list
3. Right-click → **Start**
4. Try `memurai-cli` again

**Quick check without memurai-cli:**

```powershell
Test-NetConnection -ComputerName 127.0.0.1 -Port 6379
```

If `TcpTestSucceeded : True` → Memurai is running ✅

---

## Step 2 — Start the Web Server

Open **Terminal 1** and run:

```powershell
php artisan serve
```

You should see:

```
INFO  Server running on [http://127.0.0.1:8000].
Press Ctrl+C to stop.
```

✅ This means your API is now accessible at `http://localhost:8000`

> **Keep this terminal open.** If you close it, the API stops working.

---

## Step 3 — Start the Queue Worker

Open **Terminal 2** and run:

```powershell
php artisan queue:work redis --queue=notifications-email,notifications-sms,notifications-push --tries=5
```

You should see:

```
INFO  Processing jobs from the [notifications-email, notifications-sms, notifications-push] queues.
```

Then it goes **blank/silent** — this is completely normal. ✅

It is waiting for notifications to process. The moment a notification arrives, it will process it immediately and show:

```
2026-05-28 07:33:45  App\Jobs\ProcessNotificationJob  RUNNING
2026-05-28 07:33:46  App\Jobs\ProcessNotificationJob  492.71ms DONE
```

> **Keep this terminal open.** If you close it, notifications will never be processed (they stay stuck as "pending").

**What each part of this command means:**

| Part | Meaning |
|---|---|
| `php artisan queue:work` | Start the background worker |
| `redis` | Use Redis (Memurai) as the queue storage |
| `--queue=notifications-email,...` | Listen to these 3 queues (one per notification type) |
| `--tries=5` | If a job fails, retry up to 5 times before giving up |

---

## Step 4 — Send a Notification

Open **Terminal 3** and run this command to create a test notification:

```powershell
Invoke-RestMethod -Uri "http://localhost:8000/api/v1/notifications" `
  -Method POST `
  -ContentType "application/json" `
  -Headers @{Accept="application/json"} `
  -Body '{"tenant_id": 1, "user_id": 15, "type": "email", "title": "Test", "message": "Hello World"}'
```

**What each part means:**

| Part | Meaning |
|---|---|
| `Invoke-RestMethod` | PowerShell command to send HTTP requests (like curl) |
| `-Uri "http://localhost:8000/api/v1/notifications"` | The URL of our API endpoint |
| `-Method POST` | We are CREATING a new notification |
| `-ContentType "application/json"` | Tells the server we are sending JSON data |
| `-Headers @{Accept="application/json"}` | Tells the server to reply with JSON |
| `"tenant_id": 1` | Which company this notification belongs to |
| `"user_id": 15` | Which user to notify (also controls rate limiting) |
| `"type": "email"` | Channel type — options: `email`, `sms`, `push` |
| `"title": "Test"` | The subject/headline of the notification |
| `"message": "Hello World"` | The body content of the notification |

**You should get this response:**

```json
{
  "success": true,
  "message": "Notification queued successfully.",
  "data": {
    "id": 1,
    "tenant_id": 1,
    "user_id": 15,
    "type": "email",
    "title": "Test",
    "message": "Hello World",
    "metadata": [],
    "status": "pending",
    "attempts": 0,
    "processed_at": null,
    "failed_at": null,
    "created_at": "2026-05-28T07:33:42+00:00",
    "updated_at": "2026-05-28T07:33:42+00:00"
  }
}
```

✅ `"success": true` — notification was saved and queued

Now **switch to Terminal 2** and you'll see it being processed:

```
2026-05-28 07:33:45  App\Jobs\ProcessNotificationJob  RUNNING
2026-05-28 07:33:46  App\Jobs\ProcessNotificationJob  492.71ms DONE
```

✅ The notification was processed successfully.

---

## All API Endpoints

### 1. Create a Notification

```
POST http://localhost:8000/api/v1/notifications
```

```powershell
Invoke-RestMethod -Uri "http://localhost:8000/api/v1/notifications" `
  -Method POST `
  -ContentType "application/json" `
  -Headers @{Accept="application/json"} `
  -Body '{"tenant_id": 1, "user_id": 15, "type": "email", "title": "Test", "message": "Hello World"}'
```

---

### 2. List All Notifications

```
GET http://localhost:8000/api/v1/notifications
```

```powershell
Invoke-RestMethod -Uri "http://localhost:8000/api/v1/notifications" `
  -Headers @{Accept="application/json"}
```

**With filters:**

```powershell
# Filter by status
Invoke-RestMethod -Uri "http://localhost:8000/api/v1/notifications?status=processed" `
  -Headers @{Accept="application/json"}

# Filter by type
Invoke-RestMethod -Uri "http://localhost:8000/api/v1/notifications?type=email" `
  -Headers @{Accept="application/json"}

# Filter by user
Invoke-RestMethod -Uri "http://localhost:8000/api/v1/notifications?user_id=15" `
  -Headers @{Accept="application/json"}

# Filter by tenant
Invoke-RestMethod -Uri "http://localhost:8000/api/v1/notifications?tenant_id=1" `
  -Headers @{Accept="application/json"}
```

Available status values: `pending` `processing` `processed` `failed`

---

### 3. Get Summary

```
GET http://localhost:8000/api/v1/notifications/summary
```

```powershell
Invoke-RestMethod -Uri "http://localhost:8000/api/v1/notifications/summary" `
  -Headers @{Accept="application/json"}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "total": 10,
    "processed": 8,
    "failed": 1,
    "pending": 1,
    "processing": 0
  }
}
```

---

## How the whole system works

Here is what happens from the moment you send a request to the moment it is processed:

```
You (Terminal 3)
    │
    │  POST /api/v1/notifications  (with JSON body)
    ▼
Web Server (Terminal 1 — php artisan serve)
    │
    │  Validates the request
    │  Saves notification to MySQL  (status = "pending")
    │  Pushes job to Redis queue
    │  Returns 202 response immediately  ← you see the JSON response here
    ▼
Redis (Memurai)
    │  Job is sitting in queue: notifications-email
    ▼
Queue Worker (Terminal 2 — queue:work)
    │
    │  Picks up the job
    │  Marks status = "processing"
    │  Sends the notification (logs it via Log::info)
    │  Marks status = "processed"
    │  Sets processed_at timestamp
    ▼
MySQL Database
    │  Notification record updated
    ▼
Done ✅
```

---

## What each terminal shows

### Terminal 1 — Web Server
```
INFO  Server running on [http://127.0.0.1:8000].
```
Silent after that — shows nothing unless there is an error.

### Terminal 2 — Queue Worker

While waiting (no jobs):
```
INFO  Processing jobs from the [notifications-email, ...] queues.
[blank — this is normal, it is just waiting]
```

When a job arrives:
```
2026-05-28 07:33:45  App\Jobs\ProcessNotificationJob ....... RUNNING
2026-05-28 07:33:46  App\Jobs\ProcessNotificationJob . 492.71ms DONE
```

If a job fails and retries:
```
2026-05-28 07:33:45  App\Jobs\ProcessNotificationJob ....... RUNNING
2026-05-28 07:33:46  App\Jobs\ProcessNotificationJob . 492.71ms FAILED
```

### Terminal 3 — Your requests
Shows the JSON response from the API after each command you run.

---

## Common Errors and Fixes

### ❌ "No connection could be made... tcp://127.0.0.1:6379"
**Cause:** Memurai (Redis) is not running.
**Fix:**
```powershell
# Check if running:
Test-NetConnection -ComputerName 127.0.0.1 -Port 6379

# Start Memurai via Windows Services, then retry
```

---

### ❌ "404 Not Found"
**Cause:** Routes are not registered or web server is not running.
**Fix:**
```powershell
# Check routes are registered:
php artisan route:list

# Make sure php artisan serve is running in Terminal 1
```

---

### ❌ "422 Unprocessable Content"
**Cause:** The JSON you sent is missing a required field or has an invalid value.
**Fix:** Make sure your body includes all required fields:
- `tenant_id` (number)
- `user_id` (number)
- `type` (must be: `email`, `sms`, or `push`)
- `title` (text)
- `message` (text)

---

### ❌ "429 Too Many Requests"
**Cause:** You sent more than 10 notifications for the same `user_id` within 1 hour.
**Fix:** Use a different `user_id` or wait 1 hour. This is intentional rate limiting.

---

### ❌ Worker shows nothing / notifications stay "pending"
**Cause:** Queue worker (Terminal 2) is not running.
**Fix:** Open Terminal 2 and run:
```powershell
php artisan queue:work redis --queue=notifications-email,notifications-sms,notifications-push --tries=5
```

---

### ❌ "SQLSTATE: No such table: notifications"
**Cause:** Migrations have not been run yet.
**Fix:**
```powershell
php artisan migrate
```

---

## Quick Start Checklist

Every time you start this project, run these in order:

```
[ ] 1. Open memurai-cli → type: set test 1 → should get OK
[ ] 2. Terminal 1: php artisan serve
[ ] 3. Terminal 2: php artisan queue:work redis --queue=notifications-email,notifications-sms,notifications-push --tries=5
[ ] 4. Terminal 3: Send a test notification with Invoke-RestMethod
[ ] 5. Check Terminal 2 shows RUNNING then DONE
[ ] 6. Check summary endpoint shows processed count increased
```

All 6 checked = system is fully working ✅



POST http://localhost:8000/api/v1/notifications
{
  "tenant_id": 1,
  "user_id": 15,
  "type": "email",
  "title": "Globaly Hub",
  "message": "For Interview"
}


testing data 
{
  "tenant_id": 1,
  "user_id": 15,
  "type": "email",
  "title": "Test",
  "message": "Hello World",
  "metadata": {
    "email": "test@example.com",
    "priority": "high",
    "source": "postman",
    "template": "welcome"
  }
}
