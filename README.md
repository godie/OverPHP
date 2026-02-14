# OverPHP

OverPHP is a minimal API-first PHP framework with:
- namespaced router (`OverPHP\Core\Router`)
- optional performance benchmark (`OverPHP\Core\Benchmark`)
- optional MySQL/SQLite layer (`OverPHP\Libs\Database`)
- CORS helper and clean JSON responses

## Structure
- `index.php`: framework entrypoint
- `config.php`: runtime configuration
- `config.example.php`: configuration template
- `src/Core/Router.php`: router and response emitter
- `src/Core/Benchmark.php`: request performance stats (time/memory/peak)
- `src/Helpers/cors.php`: CORS preflight and headers
- `src/Helpers/database.php`: helper functions for DB layer
- `src/Libs/Database.php`: database manager (driver registry)
- `src/Libs/Drivers/`: pluggable database drivers
  - `DriverInterface.php`: contract for drivers
  - `AbstractDriver.php`: base class with shared PDO defaults
  - `MysqlDriver.php`: MySQL driver
  - `SqliteDriver.php`: SQLite driver
- `src/Controllers/HelloController.php`: demo controller
- `tests/`: minimal PHPUnit tests

## Requirements
- PHP 8.1+ (recommended 8.2+)
- Composer (for tests/dev workflow)
- MySQL or SQLite only if DB layer is enabled

## Quick Start
1. Install dev dependencies:
   - `composer install`
2. Run tests:
   - `composer test`
3. Start local server:
   - `php -S localhost:8000 index.php`
4. Test route:
   - `GET http://localhost:8000/api/hello`

## Configuration
Edit `config.php` or use environment variables.

### Core
- `allowed_origins`: CORS allowlist
- `route_prefix`: default `/api`

### Benchmark
- `benchmark.enabled`: enable `_performance` in JSON responses
- env: `BENCHMARK_ENABLED=true|false`

### Database (optional)
- `database.enabled`: enables PDO connection
- `database.driver`: `mysql`, `sqlite`, or any custom driver name

The database layer uses a **pluggable driver architecture**. OverPHP ships with
MySQL and SQLite drivers, and you can register your own.

#### Built-in: MySQL
- `database.mysql.*`: host, port, database, username, password, charset

Env variables:
- `DB_ENABLED`
- `DB_DRIVER` (default `mysql`)
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_CHARSET`

#### Built-in: SQLite
- `database.sqlite.path`: path to the `.sqlite` file, or `:memory:` for in-memory databases

Env variables:
- `DB_ENABLED`
- `DB_DRIVER=sqlite`
- `DB_SQLITE_PATH` (default `./database/overphp.sqlite`)

SQLite example in `config.php`:

```php
'database' => [
    'enabled' => true,
    'driver'  => 'sqlite',
    'sqlite'  => [
        // File-based — the file is created automatically if it doesn't exist:
        'path' => __DIR__ . '/database/overphp.sqlite',

        // Or use an in-memory database (useful for testing, data lost on shutdown):
        // 'path' => ':memory:',

        'options' => [],
    ],
],
```

> **Tip:** SQLite requires no external server. It is ideal for local development,
> prototyping, testing, or small-scale APIs. The framework automatically enables
> WAL mode and foreign key enforcement.

#### Creating a Custom Driver

Create a class that implements `DriverInterface` (or extends `AbstractDriver`
for free PDO defaults):

```php
<?php
declare(strict_types=1);

namespace App\Drivers;

use OverPHP\Libs\Drivers\AbstractDriver;

final class PgsqlDriver extends AbstractDriver
{
    public function getName(): string
    {
        return 'pgsql';
    }

    public function connect(array $driverConfig): \PDO
    {
        $host     = $driverConfig['host'] ?? '127.0.0.1';
        $port     = $driverConfig['port'] ?? 5432;
        $database = $driverConfig['database'] ?? '';
        $username = $driverConfig['username'] ?? '';
        $password = $driverConfig['password'] ?? '';

        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $database);

        return new \PDO($dsn, $username, $password, $this->mergeOptions($driverConfig['options'] ?? []));
    }
}
```

Then register it before calling `getConnection()`:

```php
use OverPHP\Libs\Database;
use App\Drivers\PgsqlDriver;

Database::registerDriver(new PgsqlDriver());
```

And add the config section:

```php
'database' => [
    'enabled' => true,
    'driver'  => 'pgsql',
    'pgsql'   => [
        'host' => '127.0.0.1',
        'port' => 5432,
        'database' => 'myapp',
        'username' => 'postgres',
        'password' => 'secret',
        'options' => [],
    ],
],
```

If DB is disabled, the framework works normally without trying to connect.

## Adding Routes
In `index.php`, register routes with:

```php
$router->add('GET', '/hello', 'HelloController@index');
$router->add('POST', '/ping', function (): array {
    return ['pong' => true];
});
```

Handler options:
- Closure
- `"Controller@method"` (controller under `OverPHP\Controllers`)

## Creating a Controller
Create `src/Controllers/MyController.php`:

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

Then add route:

```php
$router->add('GET', '/my', 'MyController@index');
```

## Benchmark Output
When enabled, JSON responses include:

```json
"_performance": {
  "time": "1.21ms",
  "memory": "10.50KB",
  "peak": "2.45MB"
}
```
