# OverPHP

Minimal, API-first PHP framework designed for serverless and traditional deployments.

- Dependency Injection container (`OverPHP\Core\Container`)
- Dynamic routing with parameters (`/user/{id}`)
- Optional static client serving with SPA fallback
- Optional MySQL/SQLite layer with pluggable drivers
- Optional CSRF protection for fullstack use
- CLI scaffolding tool
- CORS helper and clean JSON responses
- Performance benchmark

## Install

```bash
composer require godie/overphp
```

### Scaffold a new project

```bash
vendor/bin/overphp new my-app
cd my-app
composer install
php -S localhost:8000 index.php
```

## Quick Start

```bash
composer install
composer test
php -S localhost:8000 index.php
# GET http://localhost:8000/api/hello
```

## Structure

```
index.php                          # Entrypoint
config.example.php                 # Configuration template
bin/overphp                        # CLI scaffolding tool
src/
  Core/
    Container.php                  # DI service container
    Router.php                     # Router with dynamic params and client serving
    Response.php                   # Response object (json, raw)
    Benchmark.php                  # Request performance stats
    Security.php                   # Security headers, CSRF, XSS helpers
  Controllers/
    HelloController.php            # Demo controller
  Helpers/
    cors.php                       # CORS preflight and headers
    database.php                   # DB helper functions
  Libs/
    Database.php                   # Database manager (driver registry)
    Drivers/
      DriverInterface.php          # Driver contract
      AbstractDriver.php           # Base class with PDO defaults
      MysqlDriver.php              # MySQL driver
      SqliteDriver.php             # SQLite driver
tests/                             # PHPUnit tests
```

## Requirements

- PHP 8.1+
- Composer
- MySQL or SQLite only if DB layer is enabled

## Routing

Register routes in `index.php`:

```php
$router->add('GET', '/hello', 'HelloController@index');
$router->add('GET', '/hello/{name}', 'HelloController@show');
$router->add('POST', '/ping', function (): array {
    return ['pong' => true];
});
```

Dynamic parameters are passed to the handler:

```php
$router->add('GET', '/user/{id}', function (string $id): array {
    return ['user_id' => $id];
});
```

Handler options:
- Closure (receives route params as arguments)
- `"Controller@method"` (resolved via DI container)

### Route Options

```php
$router->add('POST', '/webhook', 'WebhookController@handle', ['skip_csrf' => true]);
```

## Dependency Injection

The framework includes a simple DI container. Controllers are resolved through it, so constructor dependencies are injected automatically:

```php
// index.php — register services
$container = Container::getInstance();
$container->singleton(Database::class, function () use ($config) {
    return new Database($config);
});

// HelloController.php — dependencies injected automatically
final class HelloController
{
    public function __construct(private ?Database $db = null) {}

    public function index(): array
    {
        return ['db_enabled' => $this->db?->isEnabled()];
    }
}
```

## Response Object

Controllers can return arrays (auto-serialized to JSON) or `Response` objects for full control:

```php
use OverPHP\Core\Response;

public function show(string $id): Response
{
    return Response::json(['id' => $id], 200);
}

public function download(): Response
{
    return Response::raw($fileContents, 200, [
        'Content-Type' => 'application/octet-stream',
    ]);
}
```

## Client Serving

Serve a static frontend (React, Vue, etc.) alongside your API. Enable in config:

```php
'client' => [
    'enabled' => true,
    'path' => __DIR__ . '/client',
    'fallback_index' => 'index.html',
],
```

- Requests matching `route_prefix` (`/api`) go to the router
- All other requests serve static files from the client directory
- Missing files fall back to `index.html` (SPA support)
- Directory traversal is prevented

## Configuration

Copy `config.example.php` to `config.php` and edit, or use environment variables.

### Core

| Key | Env | Default | Description |
|-----|-----|---------|-------------|
| `allowed_origins` | — | `['http://localhost:5173']` | CORS allowlist |
| `route_prefix` | `API_PREFIX` | `/api` | API route prefix |

### Benchmark

| Key | Env | Default |
|-----|-----|---------|
| `benchmark.enabled` | `BENCHMARK_ENABLED` | `false` |

When enabled, JSON responses include:

```json
"_performance": {
  "time": "1.21ms",
  "memory": "10.50KB",
  "peak": "2.45MB"
}
```

### Security / CSRF

| Key | Env | Default | Description |
|-----|-----|---------|-------------|
| `security.csrf_enabled` | `CSRF_ENABLED` | `false` | Enable session-based CSRF protection |

CSRF is **disabled by default** because the framework is API-first and typically used with stateless authentication (JWT, API keys). Enable it if you use session/cookie-based auth in a fullstack setup.

When enabled, state-changing requests (`POST`, `PUT`, `PATCH`, `DELETE`) must include a valid CSRF token via `X-CSRF-TOKEN` header or `_csrf_token` form field. Individual routes can opt out with `'skip_csrf' => true`.

### Client

| Key | Env | Default |
|-----|-----|---------|
| `client.enabled` | `CLIENT_ENABLED` | `false` |
| `client.path` | `CLIENT_PATH` | `./client` |
| `client.fallback_index` | — | `index.html` |

### Database (optional)

| Key | Env | Default |
|-----|-----|---------|
| `database.enabled` | `DB_ENABLED` | `false` |
| `database.driver` | `DB_DRIVER` | `mysql` |

The database layer uses a **pluggable driver architecture**. Ships with MySQL and SQLite.

#### MySQL

```
DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD, DB_CHARSET
```

#### SQLite

```php
'database' => [
    'enabled' => true,
    'driver'  => 'sqlite',
    'sqlite'  => [
        'path' => __DIR__ . '/database/overphp.sqlite',
        'options' => [],
    ],
],
```

> SQLite requires no external server. Ideal for development, testing, or small-scale APIs.

#### Custom Driver

Implement `DriverInterface` or extend `AbstractDriver`:

```php
final class PgsqlDriver extends AbstractDriver
{
    public function getName(): string { return 'pgsql'; }

    public function connect(array $driverConfig): \PDO
    {
        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s',
            $driverConfig['host'] ?? '127.0.0.1',
            $driverConfig['port'] ?? 5432,
            $driverConfig['database'] ?? ''
        );
        return new \PDO($dsn, $driverConfig['username'] ?? '', $driverConfig['password'] ?? '',
            $this->mergeOptions($driverConfig['options'] ?? []));
    }
}
```

Register before use:

```php
Database::registerDriver(new PgsqlDriver());
```

## Creating a Controller

```php
<?php
declare(strict_types=1);

namespace OverPHP\Controllers;

final class MyController
{
    public function index(): array
    {
        return ['success' => true];
    }
}
```

```php
$router->add('GET', '/my', 'MyController@index');
```

## License

MIT
