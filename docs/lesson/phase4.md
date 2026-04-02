# Phase 4 Lesson — BIBLIONET Client, OpenLibrary & Provider Architecture

Everything we built in Phase 4, explained in depth.

Files touched: `app/Clients/`, `app/Console/Commands/`,
`app/Providers/AppServiceProvider.php`, `app/Models/ProviderCredential.php`,
`config/services.php`, `routes/console.php`, two new migrations.

---

## Table of Contents

1. [The problem with calling APIs directly from commands](#1-the-problem-with-calling-apis-directly-from-commands)
2. [Interfaces — programming to a contract](#2-interfaces--programming-to-a-contract)
3. [The Laravel Service Container](#3-the-laravel-service-container)
4. [Constructor injection in artisan commands](#4-constructor-injection-in-artisan-commands)
5. [Custom exception classes](#5-custom-exception-classes)
6. [Laravel's HTTP client](#6-laravels-http-client)
7. [OAuth2 client credentials flow](#7-oauth2-client-credentials-flow)
8. [Caching the token with `Cache::remember()`](#8-caching-the-token-with-cacheremember)
9. [PHP Generators — `yield` and memory-efficient pagination](#9-php-generators--yield-and-memory-efficient-pagination)
10. [Rate limiting with `usleep()`](#10-rate-limiting-with-usleep)
11. [`config()` vs `env()` — why you must not call `env()` in classes](#11-config-vs-env--why-you-must-not-call-env-in-classes)
12. [`updateOrCreate()` — idempotent staging](#12-updateorcreate--idempotent-staging)
13. [The scheduler — `routes/console.php`](#13-the-scheduler--routesconsolephp)
14. [`withoutOverlapping()` and `runInBackground()`](#14-withoutoverlapping-and-runinbackground)
15. [The `--dry-run` pattern](#15-the---dry-run-pattern)

---

## 1. The problem with calling APIs directly from commands

The naive approach would be to put all the HTTP logic inside `BiblionetFetch`:

```php
class BiblionetFetch extends Command
{
    public function handle(): int
    {
        $token = Http::post('https://biblionet.gr/.../token', [...]);
        $books = Http::withToken($token)->get('https://biblionet.gr/.../books');
        // ...
    }
}
```

This has two serious problems:

**Problem 1 — Untestable.** You can't test `BiblionetFetch` without making real HTTP
calls to the BIBLIONET server. Tests become slow, flaky, and dependent on external
uptime.

**Problem 2 — Not reusable.** If a second command (say, an admin panel action
"Re-fetch this book") also needs to call BIBLIONET, you'd have to duplicate
the HTTP logic.

The solution: extract the API communication into a **client class**, accessed
through an **interface**. The command depends on the interface, not the
implementation.

---

## 2. Interfaces — programming to a contract

An **interface** in PHP declares what a class can do, without specifying how.

```php
// The contract — declares capabilities
interface BiblionetClientInterface
{
    public function authenticate(): void;
    public function fetchBooks(int $page = 1, int $perPage = 100): array;
    public function fetchBooksSince(DateTimeInterface $since): Generator;
    public function fetchBook(string $id): array;
}

// The real implementation — knows HOW to talk to the API
class BiblionetClient implements BiblionetClientInterface
{
    public function fetchBooks(int $page = 1, int $perPage = 100): array
    {
        return $this->get('/books', ['page' => $page]);
    }
    // ...
}

// A test fake — returns hardcoded data, no HTTP calls
class FakeBiblionetClient implements BiblionetClientInterface
{
    public function fetchBooks(int $page = 1, int $perPage = 100): array
    {
        return [['id' => '1', 'title' => 'Test Book']];
    }
    // ...
}
```

Now `BiblionetFetch` depends on the interface:

```php
class BiblionetFetch extends Command
{
    public function __construct(private readonly BiblionetClientInterface $client)
    { ... }
}
```

In tests, you swap `BiblionetClient` for `FakeBiblionetClient`. The command
doesn't know or care which it gets — it only knows about the interface.

This is the **Dependency Inversion Principle** (D in SOLID): high-level modules
(commands) should not depend on low-level modules (HTTP implementations).
Both should depend on abstractions (interfaces).

---

## 3. The Laravel Service Container

The Laravel **service container** is a box that knows how to build objects. When
code asks for an interface, the container builds the right concrete class.

We teach the container in `AppServiceProvider::register()`:

```php
$this->app->bind(BiblionetClientInterface::class, function () {
    return new BiblionetClient(
        baseUrl:      config('services.biblionet.base_url'),
        clientId:     config('services.biblionet.client_id'),
        clientSecret: config('services.biblionet.client_secret'),
        rateLimit:    config('services.biblionet.rate_limit'),
    );
});
```

**`bind()` vs `singleton()`:**

| Method | Behaviour |
|--------|-----------|
| `bind()` | Creates a new instance every time it's requested |
| `singleton()` | Creates once, returns the same instance every time |

For a HTTP client, `singleton()` makes sense in most cases (you want one client
with one cached token shared across the request). We used `bind()` in Phase 4
because the DB-based credentials require a fresh lookup. In practice either
works since each queue job runs in its own process.

**How the container resolves it:**

When Laravel instantiates `BiblionetFetch`, it reads the constructor:
```php
public function __construct(private readonly BiblionetClientInterface $client)
```
It sees the type-hint, looks up `BiblionetClientInterface` in its registry,
finds our binding, runs the closure, and injects the result. You never
call `new BiblionetFetch()` yourself — Laravel does it.

---

## 4. Constructor injection in artisan commands

Artisan commands can receive dependencies through their constructor, just like
controllers.

```php
class BiblionetFetch extends Command
{
    public function __construct(private readonly BiblionetClientInterface $client)
    {
        parent::__construct(); // required — calls Command's own constructor
    }
}
```

`parent::__construct()` is mandatory here. `Command` does initialisation work
in its constructor (setting up input/output definitions). If you forget to call
it, the command won't parse arguments or options correctly.

**`readonly` properties (PHP 8.1+):** The `readonly` modifier means the property
can only be set once (in the constructor) and never changed afterwards. This
is a clean way to declare injected dependencies — they are immutable for the
lifetime of the object.

---

## 5. Custom exception classes

We created three exception classes instead of throwing generic `RuntimeException`:

```php
class BiblionetAuthException extends RuntimeException {}
class BiblionetRateLimitException extends RuntimeException
{
    public function __construct(public readonly int $retryAfter = 60, ...) { ... }
}
class BiblionetApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $statusCode = 0, ...) { ... }
}
```

**Why bother with separate exception classes?**

Because callers can `catch` specific types:

```php
try {
    $books = $client->fetchBooks($page);
} catch (BiblionetRateLimitException $e) {
    sleep($e->retryAfter);   // back off and retry
} catch (BiblionetAuthException $e) {
    Log::critical('Check BIBLIONET credentials');
    return self::FAILURE;    // don't retry — needs human
} catch (BiblionetApiException $e) {
    Log::error('API error', ['status' => $e->statusCode]);
    // maybe retry
}
```

If everything was `RuntimeException`, you'd have to parse the message string to
know what kind of failure occurred — fragile and ugly.

**PHP `readonly` constructor promotion** is used on exception properties
(`public readonly int $retryAfter`). This declares, promotes (creates the
property), and assigns it all in one line — a PHP 8.1 feature.

---

## 6. Laravel's HTTP client

Laravel wraps Guzzle in a clean fluent API:

```php
use Illuminate\Support\Facades\Http;

// GET with query params
$response = Http::get('https://api.example.com/books', ['page' => 1]);

// POST a form (application/x-www-form-urlencoded)
$response = Http::asForm()->post('/token', ['grant_type' => 'client_credentials']);

// POST JSON (application/json)
$response = Http::post('/books', ['title' => 'Test']);

// With Bearer token
$response = Http::withToken($token)->get('/books');

// With custom headers
$response = Http::withHeaders(['User-Agent' => 'MyApp/1.0'])->get('/books');

// With retry logic
$response = Http::retry(3, 500)->get('/books');
// 3 attempts, 500ms delay between (exponential by default)
```

**Checking responses:**

```php
$response->ok();           // 200
$response->successful();   // 200–299
$response->clientError();  // 400–499
$response->serverError();  // 500–599
$response->status();       // integer status code
$response->json();         // decoded JSON as array
$response->body();         // raw string body
$response->header('X-Key');// single header value
$response->throw();        // throws HttpClientException if not 2xx
```

**`retry()` with a condition callback:**

```php
Http::retry(3, 500, function (\Throwable $e) {
    return $e instanceof RequestException
        && $e->response->serverError();
})->get('/books');
```

The third argument is a closure that decides whether to retry. Here we only retry
on server errors (5xx), not client errors (4xx — those indicate a bug in our code,
retrying won't help).

---

## 7. OAuth2 client credentials flow

BIBLIONET uses **OAuth2 client credentials** — the simplest OAuth2 flow, designed
for machine-to-machine communication (no user involved):

```
Client                          Auth Server
  │                                 │
  │  POST /token                    │
  │  { grant_type: client_credentials,
  │    client_id: xxx,              │
  │    client_secret: yyy }         │
  │─────────────────────────────────▶
  │                                 │
  │  { access_token: "abc...",      │
  │    expires_in: 3600 }           │
  │◀─────────────────────────────────
  │                                 │
  │  GET /books                     API Server
  │  Authorization: Bearer abc...   │
  │─────────────────────────────────▶
  │                                 │
  │  { data: [...] }                │
  │◀─────────────────────────────────
```

The token is a temporary credential. After `expires_in` seconds it becomes invalid
and you must re-authenticate. We handle this by caching the token and clearing
it on 401 responses.

---

## 8. Caching the token with `Cache::remember()`

```php
Cache::put('biblionet.token', $token, $ttl);   // store
Cache::get('biblionet.token');                  // retrieve (null if expired/missing)
Cache::has('biblionet.token');                  // check existence
Cache::forget('biblionet.token');               // delete
```

**The cache driver in this project** is configured in `.env`:
```
CACHE_STORE=database   # local dev: stored in cache table
# CACHE_STORE=redis    # production: fast in-memory store
```

**Why store the token in cache and not a property?**

A property (`$this->token`) lives only for the duration of the current PHP
process. In a queue worker setup, each job runs in its own process — you'd
re-authenticate on every job. The cache is shared across all processes and
persists across requests, so one worker authenticates and all others reuse
the cached token.

**The `TOKEN_EXPIRY_BUFFER_SECONDS` constant:**

```php
$ttl = max(0, $expiresIn - self::TOKEN_EXPIRY_BUFFER_SECONDS); // 60s buffer
```

If BIBLIONET says the token is valid for 3600 seconds, we cache it for 3540.
This prevents the edge case where we check `Cache::has()` at second 3599,
find the token "valid", send the request, but the token expires before it
arrives at the server.

---

## 9. PHP Generators — `yield` and memory-efficient pagination

A **generator** is a function that produces a sequence of values lazily — one
at a time, on demand. It uses the `yield` keyword instead of `return`.

```php
// Normal function — loads everything into memory first
public function fetchAllBooks(): array
{
    $all = [];
    for ($page = 1; ...) {
        $all = array_merge($all, $this->fetchPage($page));  // grows unboundedly
    }
    return $all;  // returns 200,000 items at once — possible OOM
}

// Generator — yields one item at a time, uses constant memory
public function fetchBooksSince(DateTimeInterface $since): Generator
{
    $page = 1;
    do {
        $books = $this->get('/books', ['modified_since' => ..., 'page' => $page]);
        foreach ($books as $book) {
            yield $book;  // pauses here, returns $book, resumes on next iteration
        }
        $page++;
    } while (!empty($books));
}
```

The caller iterates with `foreach` as if it were a normal array:

```php
foreach ($this->client->fetchBooksSince($since) as $book) {
    $this->stageRecord($book, $provenance);
    // The API call for page 2 only happens when page 1 is exhausted
}
```

**`yield from`** delegates to another generator:

```php
private function incrementalFetch(Carbon $since): \Generator
{
    yield from $this->client->fetchBooksSince($since);
    // Transparently passes values through from the client's generator
}
```

**Why generators for API pagination?**

A full BIBLIONET catalog could be 100,000+ books. With a normal array you'd
hold all of them in memory simultaneously. With a generator, at any moment
you only hold one page (100 records). Memory usage is flat regardless of
catalog size.

---

## 10. Rate limiting with `usleep()`

```php
private function throttle(): void
{
    if ($this->rateLimit > 0) {
        usleep((int) (1_000_000 / $this->rateLimit));
    }
}
```

`usleep()` sleeps for the given number of **microseconds** (1 second = 1,000,000 µs).

| `$rateLimit` | Sleep per request |
|-------------|-------------------|
| 1 | 1,000,000 µs = 1.0s |
| 3 | 333,333 µs ≈ 0.33s |
| 10 | 100,000 µs = 0.1s |

**Numeric literal underscores** (`1_000_000`) — PHP 7.4+ allows underscores in
numeric literals as visual separators. `1_000_000 === 1000000`. Use them
whenever a large number appears in code — it's much harder to miscount digits.

This is a simple "leaky bucket" rate limiter — good enough for modest rate limits.
For high-throughput production systems, a Redis-based token bucket (e.g. the
`spatie/laravel-rate-limited-job-middleware` package) is more accurate.

---

## 11. `config()` vs `env()` — why you must not call `env()` in classes

You have two ways to read configuration:

```php
// Direct .env access — WRONG in application code
env('BIBLIONET_CLIENT_ID')

// Via the config layer — CORRECT
config('services.biblionet.client_id')
```

**Why does it matter?**

Laravel has a `config:cache` command that compiles all config files into a single
PHP file for performance. Once config is cached, `env()` returns `null` for most
variables because `.env` is no longer loaded.

```bash
php artisan config:cache   # runs in production — compiles config to bootstrap/cache/config.php
php artisan config:clear   # clears the cache (run after changing .env)
```

**The correct chain:**
```
.env file  →  config/services.php  →  config('services.biblionet.key')
```

`env()` should only appear inside `config/*.php` files. Everywhere else, use
`config()`.

**Exception:** `APP_KEY`, `APP_ENV`, and a few others are read by Laravel's core
before config is loaded — those are the only legitimate uses of `env()` outside
config files.

---

## 12. `updateOrCreate()` — idempotent staging

```php
RawIngestionRecord::updateOrCreate(
    // 1st array: the "find by" criteria (unique key)
    [
        'source_system'    => 'biblionet',
        'source_record_id' => (string) $book['id'],
    ],
    // 2nd array: the "set these values" data
    [
        'payload'       => $book,
        'status'        => 'pending',
        'provenance_id' => $provenance->id,
        'fetched_at'    => now(),
    ]
);
```

**What it does:**
1. Tries to find a record matching the first array.
2. If found: updates it with the second array's values.
3. If not found: inserts a new record merging both arrays.

This makes the fetch command **idempotent** — you can run it multiple times and
the result is the same. Re-running won't create duplicate records.

**Under the hood:** Laravel issues:
```sql
-- Check existence
SELECT * FROM raw_ingestion_records
WHERE source_system = 'biblionet' AND source_record_id = '12345'

-- Then either:
INSERT INTO raw_ingestion_records (...) VALUES (...)
-- or:
UPDATE raw_ingestion_records SET payload = ..., status = ... WHERE id = ...
```

**`firstOrCreate()` vs `updateOrCreate()`:**

| Method | If exists | If not exists |
|--------|-----------|---------------|
| `firstOrCreate($attrs, $values)` | Returns existing, no update | Creates with `$attrs + $values` |
| `updateOrCreate($attrs, $values)` | Updates with `$values`, returns it | Creates with `$attrs + $values` |

Use `firstOrCreate()` when you only want to insert-if-missing and don't want to
overwrite. Use `updateOrCreate()` when a re-fetch should refresh the data.

---

## 13. The scheduler — `routes/console.php`

In Laravel 11+, scheduled tasks are defined in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('biblionet:fetch --since=yesterday')
    ->dailyAt('03:00');
```

**How it actually runs:** You add one cron entry to the server:

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

This runs `php artisan schedule:run` every minute. Laravel checks internally
which scheduled tasks are due and runs only those. You don't need a cron entry
per command — just the one `schedule:run` entry.

**Scheduling methods:**

```php
->everyMinute()
->everyFiveMinutes()
->hourly()
->hourlyAt(15)          // at :15 of every hour
->daily()               // midnight
->dailyAt('03:00')      // 3am
->weeklyOn(1, '08:00')  // Monday at 8am
->monthly()
->cron('0 3 * * *')     // raw cron expression
```

---

## 14. `withoutOverlapping()` and `runInBackground()`

```php
Schedule::command('biblionet:fetch --since=yesterday')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/biblionet-sync.log'));
```

**`withoutOverlapping()`** — prevents a second instance starting if the first
is still running. Uses a cache lock (or database lock) keyed by the command name.
If a BIBLIONET fetch takes 90 minutes and runs at 3:00 AM, without this you'd
get a second fetch starting at 3:01 AM (because `schedule:run` runs every minute).

**`runInBackground()`** — by default, the scheduler waits for a command to finish
before evaluating the next task. `runInBackground()` fires the command as a
background process so the scheduler can move on immediately. Useful when you have
multiple long-running sync commands scheduled at the same time.

**`appendOutputTo()`** — redirects stdout/stderr to a log file. Without this,
output is discarded. This is your operations log — check it if a sync fails.

---

## 15. The `--dry-run` pattern

```php
#[Signature('biblionet:fetch {--dry-run : Fetch but do not write to database}')]

if ($this->option('dry-run')) {
    $this->warn('DRY RUN — no data will be written.');
}

// ... later:
if (! $dryRun) {
    $this->stageRecord($book, $provenance);
}
```

Dry-run is a safety valve for testing and debugging. With `--dry-run` you can:
- Verify the API connection works
- Count how many records would be fetched
- See what the raw data looks like

...without writing anything to the database. Always add this to fetch/sync
commands — it's invaluable when you're diagnosing an issue on production.

---

## 16. DB-backed credentials and the `Schema::hasTable()` guard

We moved provider credentials from `.env` into a `provider_credentials` database
table, managed through the Filament admin panel (Settings → Data Providers).
This lets admins rotate API keys without a deployment.

The binding closures in `AppServiceProvider` read from the DB:

```php
$this->app->bind(OpenLibraryClientInterface::class, function () {
    $cred = Schema::hasTable('provider_credentials')
        ? ProviderCredential::forProvider('openlibrary')
        : null;

    return new OpenLibraryClient(
        userAgent: $cred?->credential('user_agent') ?? config('...'),
        // ...
    );
});
```

**Why `Schema::hasTable()` is necessary:**

When you run `php artisan migrate`, Laravel bootstraps the full application —
including resolving artisan commands from the container. Commands with constructor
injection (`OpenLibraryFetch` needs `OpenLibraryClientInterface`) trigger their
binding closures immediately. But at that moment, `provider_credentials` hasn't
been created yet — the migration we're running IS the one that creates it.

Without the guard, `migrate` crashes with:
```
SQLSTATE[42P01]: Undefined table: relation "provider_credentials" does not exist
```

`Schema::hasTable()` runs a single cheap `information_schema` query and returns
`false` during migration, allowing the client to fall back to config values.
After the migration runs, it returns `true` and the DB lookup works normally.

**The `?->` null-safe operator** (PHP 8.0) chains method calls on a possibly-null
object without needing `if ($cred !== null)` checks:

```php
$cred?->credential('user_agent')
// same as:
$cred !== null ? $cred->credential('user_agent') : null
```

---

## 17. `enum` columns vs plain strings in PostgreSQL

The original `provenance.source_system` was declared as an `enum`:

```php
$table->enum('source_system', ['biblionet', 'nlg', 'onix', 'manual']);
```

Laravel translates `enum()` to a `VARCHAR(255)` column with a `CHECK` constraint:
```sql
CHECK (source_system IN ('biblionet', 'nlg', 'onix', 'manual'))
```

When we tried to insert `source_system = 'openlibrary'`, PostgreSQL rejected it:
```
ERROR: new row violates check constraint "provenance_source_system_check"
```

**The lesson:** Don't encode a growing list of values in a database constraint.
Constraints are schema — changing them requires a migration. The list of providers
will grow (OpenLibrary, WorldCat, BNF…), so the constraint would need a new
migration every time.

**The fix:** Drop the CHECK constraint and use a plain `string`. Validation of
the provider name happens at the application layer (the `ProviderCredential` model
knows the valid providers), not the database layer.

```php
// Migration to remove the constraint:
\DB::statement(
    'ALTER TABLE provenance DROP CONSTRAINT IF EXISTS provenance_source_system_check'
);
```

**Rule of thumb:** Use `enum` / CHECK constraints only for values that will
never grow (e.g. `status IN ('draft', 'published', 'archived')`). Use plain
strings for anything that could gain new values over time.

---

## 18. `??` inside string interpolation — a PHP gotcha

During debugging we hit this PHP parse error:

```php
// WRONG — PHP won't parse ?? inside {}
$this->error("Failed to stage record {$book['id'] ?? '?'}: ...");
```

PHP's string interpolation `{$var}` only supports simple expressions — property
access (`{$obj->prop}`) and array access (`{$arr['key']}`). Operators like `??`,
`?:`, or function calls are not allowed inside `{}`.

**Fix:** Extract to a variable first:

```php
$recordId = $book['id'] ?? '?';
$this->error("Failed to stage record {$recordId}: ...");
```

Alternatively, use concatenation:
```php
$this->error('Failed to stage record ' . ($book['id'] ?? '?') . ': ...');
```

---

## 19. The multi-provider automation pattern

The `catalog:sync` command is the automated ingestion driver. Instead of one
cron entry per provider, a single scheduled command reads the DB to know what
to run:

```php
// routes/console.php
Schedule::command('catalog:sync')->dailyAt('03:00')->withoutOverlapping();

// app/Console/Commands/CatalogSync.php
foreach (ProviderCredential::activeAutoSync() as $provider) {
    $command = self::PROVIDER_COMMANDS[$provider->provider];
    $this->call($command);
    $provider->update(['last_ingestion_at' => now()]);
}
```

**To add a new provider to automated sync:**
1. Add a row to `provider_credentials` via the admin panel
2. Toggle `auto_sync = true`
3. Register the command in `CatalogSync::PROVIDER_COMMANDS`

No deploy needed for steps 1–2. The schedule picks it up at the next run.

---

## Summary of what we built

| File | What it does |
|------|-------------|
| `app/Clients/Contracts/BiblionetClientInterface.php` | Contract for BIBLIONET |
| `app/Clients/Contracts/OpenLibraryClientInterface.php` | Contract for OpenLibrary |
| `app/Clients/BiblionetClient.php` | OAuth2, retry, token cache, throttle |
| `app/Clients/OpenLibraryClient.php` | User-Agent, retry, throttle, no auth |
| `app/Clients/Exceptions/Biblionet*.php` | Typed exceptions for BIBLIONET failures |
| `app/Console/Commands/BiblionetFetch.php` | Full/incremental fetch + staging |
| `app/Console/Commands/OpenLibraryFetch.php` | ISBN / search / work / sync modes |
| `app/Console/Commands/CatalogSync.php` | Nightly driver — loops over active providers |
| `app/Models/ProviderCredential.php` | DB-backed credentials with encrypted cast |
| `app/Filament/Resources/ProviderCredentials/` | Admin panel UI under Settings |
| `app/Providers/AppServiceProvider.php` | DI bindings with Schema guard + DB fallback |
| `config/services.php` | Fallback config values |
| `routes/console.php` | Single nightly schedule entry |
| migration: `create_provider_credentials_table` | New table with encrypted credentials |
| migration: `convert_provenance_source_system_to_string` | Drops enum CHECK constraint |

## Key concepts encountered

- **Interface / contract** — separate "what" from "how"; enables testing via fakes
- **Service container** — Laravel's DI box; `bind()` teaches it what to build
- **Constructor injection** — dependencies declared in the constructor, container supplies them
- **`parent::__construct()`** — always call in command constructors
- **`readonly` properties** — PHP 8.1 immutable properties; clean for injected deps
- **Custom exceptions** — typed failures; callers catch specific types
- **Laravel HTTP client** — fluent Guzzle wrapper; `withToken()`, `retry()`, `asForm()`
- **OAuth2 client credentials** — machine-to-machine token flow
- **Cache for tokens** — share across processes; use buffer to avoid expiry edge cases
- **PHP Generators** — `yield` produces values lazily; constant memory for large datasets
- **`yield from`** — transparently delegates to another generator
- **`usleep()`** — microsecond sleep for rate limiting
- **`config()` over `env()`** — mandatory; `env()` breaks after `config:cache`
- **`updateOrCreate()`** — idempotent upsert; safe to run multiple times
- **Laravel scheduler** — one cron entry runs all tasks; `withoutOverlapping()`, `runInBackground()`
- **`--dry-run`** — test without side effects; always add to fetch commands
- **`Schema::hasTable()` guard** — prevent DB queries during `migrate` bootstrap
- **`?->` null-safe operator** — chain calls on nullable objects without `if` checks
- **`??` in string interpolation** — not allowed; extract to variable first
- **`enum` vs `string` columns** — don't encode growing lists in CHECK constraints
- **DB-backed credentials with `encrypted:array` cast** — secrets encrypted at rest, managed via admin panel
- **Multi-provider automation via `catalog:sync`** — single scheduler entry, DB-driven provider list
