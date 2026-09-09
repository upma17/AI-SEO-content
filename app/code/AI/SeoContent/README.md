# AI_SeoContent — Magento 2 Module

Vendor: **AI** | Module: **SeoContent**

Automatically generates SEO and catalog content for products using **Gemini, OpenAI (ChatGPT), or Claude**. Supports **Message Queue** (MySQL or RabbitMQ) and an **Admin Job Monitor** for large catalogs (10k+ SKUs).

---

## LLM providers

**Stores → Configuration → AI → SEO Content Generator → LLM Provider**

| Provider | Default model | API key |
|----------|---------------|---------|
| **Google Gemini** (default) | `gemini-3.5-flash-lite` | [Google AI Studio](https://aistudio.google.com/apikey) |
| **OpenAI (ChatGPT)** | `gpt-4o-mini` | [OpenAI Platform](https://platform.openai.com/api-keys) |
| **Anthropic (Claude)** | `claude-3-5-haiku-latest` | [Anthropic Console](https://console.anthropic.com/) |

When you change **Provider**, admin shows only that provider's **API Key** and **Model** dropdown.

### Available models

| Provider | Models |
|----------|--------|
| Gemini | Gemini 3.5 Flash Lite *(default)*, 3.5 Flash, 3.6 Flash, 2.5 Flash Lite |
| OpenAI | GPT-4o Mini *(default)*, GPT-4o, GPT-4.1 Mini |
| Claude | Claude 3.5 Haiku *(default)*, Claude Sonnet 4 |

---

## Features

| Feature | Description |
|---------|-------------|
| Cron enqueue | Nightly batch enqueue (500 SKUs/store default) |
| Message Queue | MySQL (dev) or **RabbitMQ AMQP** (production) |
| Admin grid | **Catalog → AI SEO Jobs** — monitor status, retry failed |
| Generated fields | `meta_title`, `meta_description`, `meta_keyword`, `short_description` |
| LLM providers | Gemini, OpenAI, Claude — switch in admin |
| CLI | `ai:seo:generate`, `ai:seo:consumer:start` |

---

## Admin Job Monitor

**Catalog → AI SEO Jobs**

Shows live counts:

| Status | Meaning |
|--------|---------|
| Pending | Enqueued, waiting for consumer |
| Processing | Consumer is working on it |
| Completed | SEO saved successfully |
| Failed | API or save error (see Error column) |
| Skipped | Dry run or already populated |

**Mass action:** Select failed/skipped jobs → **Retry Selected**

Each row links to the product edit page.

---

## Queue connections

### Database queue (default — dev / small stores)

Admin: **Queue Connection = Database Queue (MySQL)**

```bash
bin/magento ai:seo:consumer:start
# equivalent to:
bin/magento queue:consumers:start aiSeoContentGenerate
```

### RabbitMQ AMQP (Adobe Commerce production)

**1. Install RabbitMQ** on your server.

**2. Add to `app/etc/env.php`:**

```php
'queue' => [
    'amqp' => [
        'host' => '127.0.0.1',
        'port' => '5672',
        'user' => 'your-rabbitmq-user',
        'password' => 'your-rabbitmq-password',
        'virtualhost' => '/',
    ],
    'consumers_wait_for_messages' => 1,
],
```

**3. Admin:** Queue Connection = **RabbitMQ (AMQP)**

**4. Deploy & upgrade:**

```bash
bin/magento setup:upgrade
bin/magento cache:flush
```

**5. Start consumer:**

```bash
bin/magento ai:seo:consumer:start
# uses consumer: aiSeoContentGenerateAmqp
```

---

## Installation

```bash
# Copy app/code/AI/SeoContent to Magento root
bin/magento module:enable AI_SeoContent
bin/magento setup:upgrade
bin/magento cache:flush
```

**Stores → Configuration → AI → SEO Content Generator**

| Setting | Production |
|---------|------------|
| Provider | Gemini (free tier) or OpenAI/Claude (paid) |
| API Key | Key for selected provider |
| Model | Default per provider (recommended) |
| Enable Cron | Yes |
| Use Message Queue | Yes |
| Queue Connection | RabbitMQ (or DB for dev) |
| Enqueue Batch Size | 500 |
| Generate Meta Keywords | Yes |
| Generate Short Description | Yes |
| Dry Run | No (after testing) |

---

## Commands

```bash
# Enqueue products to queue
bin/magento ai:seo:generate --enqueue --force

# Sync processing (no queue)
bin/magento ai:seo:generate --sync --force

# Start consumer (auto-picks db or amqp from admin)
bin/magento ai:seo:consumer:start

# With message limit
bin/magento ai:seo:consumer:start --max-messages=5000
```

---

## Production Supervisor (RabbitMQ)

```ini
[program:magento_ai_seo_amqp]
command=/usr/bin/php /var/www/magento/bin/magento ai:seo:consumer:start --max-messages=10000
directory=/var/www/magento
autostart=true
autorestart=true
user=www-data
numprocs=2
stdout_logfile=/var/log/supervisor/ai_seo_consumer.log
stderr_logfile=/var/log/supervisor/ai_seo_consumer_err.log
```

Set admin **Queue Connection = RabbitMQ** before deploying this.

---

## Database table

`ai_seo_content_job` — created by `setup:upgrade`

| Column | Description |
|--------|-------------|
| job_id | Primary key |
| product_id / store_id / sku | Product reference |
| status | pending / processing / completed / failed / skipped |
| queue_connection | db or amqp |
| result_summary | Fields generated |
| error_message | Failure reason |

View in admin grid or:

```sql
SELECT status, COUNT(*) FROM ai_seo_content_job GROUP BY status;
```

---

## Architecture

```
Cron → enqueueBatch() → ai_seo_content_job (pending)
                     → Message Queue (db or amqp)
Consumer → Gemini API → Save product → job (completed)
Admin Grid ← ai_seo_content_job table
```

---

## Logs

```bash
tail -f var/log/ai_seo_content.log
```

---

## Troubleshooting

| Issue | Fix |
|-------|-----|
| Jobs stuck in Pending | Start consumer: `ai:seo:consumer:start` |
| AMQP connection failed | Check `env.php` RabbitMQ credentials |
| Grid empty | Run `setup:upgrade`, enqueue products first |
| Retry not working | Consumer must be running after retry |
| Duplicate pending jobs | Module dedupes pending/processing per product+store |

---

## Requirements

- Magento 2.4.x / Adobe Commerce 2.4.x
- PHP 8.1+
- Google Gemini, OpenAI, or Anthropic API key (per selected provider)
- RabbitMQ (optional, for AMQP mode)
