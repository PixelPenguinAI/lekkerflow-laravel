# Laravel LekkerFlow

Ship Laravel application errors to the [LekkerFlow](https://lekkerflow.com) error webhook as a Monolog log channel.

Anything your app logs at `error` level or above (including unhandled exceptions reported by Laravel) is POSTed to LekkerFlow, where identical errors are collapsed into a single counted issue.

## Installation

```bash
composer require pixel-penguin/laravel-lekkerflow
```

The service provider is auto-discovered. It registers a `lekkerflow` log channel for you — you do **not** need to edit `config/logging.php`.

## Configuration

Add the channel to your logging stack and set your token:

```dotenv
LOG_STACK=single,lekkerflow

LEKKERFLOW_ERROR_WEBHOOK_TOKEN=your-webhook-token
# Optional overrides:
# LEKKERFLOW_ERROR_WEBHOOK_URL=https://lekkerflow.com/api/error-webhook/capture
# LEKKERFLOW_LOG_LEVEL=error
# LEKKERFLOW_ENVIRONMENT=staging   # defaults to APP_ENV
# LEKKERFLOW_RELEASE=              # app version / git sha
# LEKKERFLOW_TIMEOUT=5
```

When the token is empty the channel is a **no-op**, so it is safe to leave `lekkerflow` in your stack on environments that should not report (e.g. local).

To customise further, publish the config:

```bash
php artisan vendor:publish --tag=lekkerflow-config
```

## How it works

The channel maps each log record to the LekkerFlow payload:

| LekkerFlow field  | Source                                                        |
|-------------------|---------------------------------------------------------------|
| `message`         | The log message                                               |
| `level`           | Monolog level → `critical \| error \| warning \| info \| debug` |
| `exception_class`, `file`, `line`, `stack_trace` | From `context['exception']` when an exception is logged |
| `url`             | From `context['url']` when present                            |
| `context`         | Any remaining log context                                     |
| `environment`     | `LEKKERFLOW_ENVIRONMENT`, falling back to `APP_ENV`           |
| `release`         | `LEKKERFLOW_RELEASE`                                          |

Reporting failures (timeout, DNS, 5xx) are swallowed so the webhook can never mask or replace the original application error.

### Reporting manually

```php
use Illuminate\Support\Facades\Log;

Log::channel('lekkerflow')->error('Payment gateway timed out', [
    'url' => request()->fullUrl(),
    'order_id' => $order->id,
]);
```

## Testing

```bash
composer install
composer test
```

## Releasing

`release.sh` bumps the semver tag and pushes it; Packagist picks it up via its
GitHub webhook.

```bash
./release.sh         # patch: v1.2.3 -> v1.2.4
./release.sh minor   # minor: v1.2.3 -> v1.3.0
./release.sh major   # major: v1.2.3 -> v2.0.0
```

## License

MIT
