# Phase 3 Lesson — Thema Seeding

Everything we did in Phase 3, explained in depth.  
Files touched: `database/seeders/ThemaSubjectSeeder.php`, a new migration, `app/Console/Commands/ThemaUpdate.php`.

---

## Table of Contents

1. [What is a Seeder?](#1-what-is-a-seeder)
2. [The `storage_path()` helper and where files live](#2-the-storage_path-helper-and-where-files-live)
3. [Reading and decoding JSON](#3-reading-and-decoding-json)
4. [The `??` null coalescing operator](#4-the--null-coalescing-operator)
5. [Why we use `DB::table()` instead of Eloquent for bulk inserts](#5-why-we-use-dbtable-instead-of-eloquent-for-bulk-inserts)
6. [The two-pass insert strategy (and why length-sort failed)](#6-the-two-pass-insert-strategy-and-why-length-sort-failed)
7. [PostgreSQL foreign key constraints and deferrability](#7-postgresql-foreign-key-constraints-and-deferrability)
8. [Adding a migration to alter an existing table](#8-adding-a-migration-to-alter-an-existing-table)
9. [Progress bars in artisan commands](#9-progress-bars-in-artisan-commands)
10. [Creating a reusable artisan command](#10-creating-a-reusable-artisan-command)
11. [Command options and the attribute signature syntax](#11-command-options-and-the-attribute-signature-syntax)
12. [Calling one command from another](#12-calling-one-command-from-another)
13. [Useful artisan commands for seeders](#13-useful-artisan-commands-for-seeders)

---

## 1. What is a Seeder?

A **seeder** is a PHP class whose sole job is to insert data into the database.  
Laravel provides a base `Seeder` class in `Illuminate\Database\Seeder`.

```
database/
└── seeders/
    ├── DatabaseSeeder.php      ← the "root" seeder, calls others
    ├── RolesSeeder.php
    └── ThemaSubjectSeeder.php  ← what we built
```

Every seeder has one method: `run()`. Laravel calls it when you execute:

```bash
php artisan db:seed --class=ThemaSubjectSeeder
```

**Seeders have no history.** Unlike migrations, Laravel does not track which seeders have run. Running the same seeder twice just executes `run()` again. This is intentional — seeders are designed to be idempotent (safe to run repeatedly), which is why ours starts by deleting all rows.

**Generate a new seeder stub:**
```bash
php artisan make:seeder ThemaSubjectSeeder
```
This creates the file with an empty `run()` method. You then fill it in.

---

## 2. The `storage_path()` helper and where files live

Laravel provides several path helpers so you never hardcode absolute paths:

| Helper | Resolves to |
|--------|-------------|
| `base_path()` | Project root (`/home/.../eu-catalog`) |
| `app_path()` | `app/` directory |
| `database_path()` | `database/` directory |
| `storage_path()` | `storage/` directory |
| `public_path()` | `public/` directory |
| `resource_path()` | `resources/` directory |

We stored the Thema JSON at `storage/thema/thema_en.json`, so we reference it with:

```php
$path = storage_path('thema/thema_en.json');
// resolves to: /home/waylander/Projects/eu-catalog/storage/thema/thema_en.json
```

**Why `storage/` and not `public/`?**  
The `storage/` directory is not web-accessible (it's outside `public/`). Reference data files, uploads, and logs belong here — you don't want them served directly by the web server.

**`Storage` facade vs `storage_path()`**  
Laravel also has a `Storage` facade (`Illuminate\Support\Facades\Storage`) that provides a higher-level file API with support for multiple "disks" (local, S3, etc.):

```php
// Via Storage facade (rooted at storage/app/)
Storage::disk('local')->get('thema/thema_en.json');

// Via storage_path() (rooted at storage/)
file_get_contents(storage_path('thema/thema_en.json'));
```

The seeder uses `storage_path()` + native `file_get_contents()` because we're doing a one-time file read — the full `Storage` facade is more useful when you need disk abstraction (e.g., swap local for S3 without code changes).

---

## 3. Reading and decoding JSON

```php
$json = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
```

Breaking this down:

- **`file_get_contents($path)`** — reads the entire file into a string. Fine for a 3MB JSON file; for very large files you'd use streaming.
- **`json_decode(..., true)`** — the second argument `true` means "return associative arrays, not objects". Without it you'd get `stdClass` objects and write `$data->ThemaCodes` instead of `$data['ThemaCodes']`. Arrays are generally easier to work with in PHP.
- **`512`** — maximum nesting depth. Default is 512; Thema JSON nests at most ~3 levels deep, so this is fine.
- **`JSON_THROW_ON_ERROR`** — without this flag, `json_decode` returns `null` on error and you have to check `json_last_error()` manually. With this flag it throws a `JsonException` immediately. Always use this flag — silent failures are hard to debug.

**Thema v1.5 vs v1.6 JSON structure:**

```
v1.5:  { "ThemaCodes": [ {...}, {...}, ... ] }

v1.6:  {
         "CodeList": {
           "ThemaCodes": {
             "Code": [ {...}, {...}, ... ]
           }
         }
       }
```

EDItEUR changed the nesting between versions. Our code handles both by trying multiple paths using `??` (see next section).

---

## 4. The `??` null coalescing operator

PHP's `??` operator returns the left side if it exists and is not `null`, otherwise it tries the right side. Crucially, it **does not throw a notice** if the left-side key doesn't exist in an array — it just returns `null` and moves on.

```php
$codes = $json['ThemaCodes']
    ?? $json['CodeList']['ThemaCodes']['Code']
    ?? $json['CodeList']['ThemaCodes']
    ?? null;
```

This reads as: "try `$json['ThemaCodes']`; if that's null/missing, try the nested v1.6 path; if that's also null, try the outer v1.6 path; if nothing works, give up with `null`".

**Compare with `?:` (ternary shorthand):**

```php
// ?:  returns left side if it's TRUTHY, otherwise right side
$name = $input ?: 'default';   // returns 'default' if $input is "", 0, false, or null

// ??  returns left side if it EXISTS and is not NULL
$name = $input ?? 'default';   // returns 'default' only if $input is null/missing
```

The difference matters when a value can legitimately be `0` or `""`. Use `??` when you're checking for existence, `?:` when you're checking for truthiness.

**Type casting with `??`:**

```php
// CodeParent is sometimes an integer in v1.6 (e.g. 1A has parent 1, not "1")
$parent = trim((string) ($code['CodeParent'] ?? ''));
```

- `$code['CodeParent'] ?? ''` — if the key is missing, use empty string as default
- `(string) (...)` — explicitly cast to string before calling `trim()`
- PHP 8+ `trim()` requires a string argument and throws `TypeError` if you pass an integer

---

## 5. Why we use `DB::table()` instead of Eloquent for bulk inserts

We have two ways to insert records in Laravel:

**Eloquent (model-based):**
```php
ThemaSubject::create([
    'code'       => 'FBA',
    'heading_en' => 'Modern fiction',
    ...
]);
```

**Query builder (`DB::table()`):**
```php
DB::table('thema_subjects')->insert([
    ['code' => 'FBA', 'heading_en' => 'Modern fiction', ...],
    ['code' => 'FBB', 'heading_en' => 'Historical fiction', ...],
    // ... up to 500 rows at once
]);
```

For a one-time seed of 9,187 rows, Eloquent is the wrong choice because:

| Concern | Eloquent `create()` | `DB::table()->insert()` |
|---------|---------------------|-------------------------|
| Model events | Fires `creating`, `created` events per row | No events |
| Mutators | Runs all `set` mutators | No mutators |
| Timestamps | Sets `created_at`/`updated_at` automatically | You control this |
| Speed | ~1 query per row | 1 query per batch of N rows |
| Memory | Instantiates a model object per row | Just arrays |

For **9,187 rows**, Eloquent `create()` would fire ~18,374 events and make 9,187 individual INSERT queries. With `DB::table()->insert()` in batches of 500, we make ~19 queries total.

**Rule of thumb:** Use Eloquent for normal application logic (user actions, single record operations). Use `DB::table()` for bulk data operations (seeders, migrations, reports).

---

## 6. The two-pass insert strategy (and why length-sort failed)

### Why we need ordering at all

`thema_subjects` has a self-referential foreign key:

```
thema_subjects.parent_code  →  thema_subjects.code
```

This means: if you insert code `FB` (parent: `F`) before code `F` exists in the table, PostgreSQL refuses with a **foreign key violation**. Parents must be inserted before their children.

### First attempt: sort by code length

The plan's original idea was to sort by code length — `A` (length 1) before `AB` (length 2) before `ABC` (length 3). This works for most Thema codes because the parent is literally the prefix: `FBA`'s parent is `FB`.

```php
usort($codes, fn($a, $b) => strlen($a['CodeValue']) <=> strlen($b['CodeValue']));
```

### Why it broke

Thema v1.6 introduced **national extension codes** that break the prefix rule:

```
Code:   1FPCT   (length 5, Tibet)
Parent: 1FPC-CN-N   (length 9, China national extension)
```

A 5-character child has a 9-character parent. Length-sort inserts the child first → FK violation.

### The fix: two-pass insert

**Pass 1** — insert every row with `parent_code = null`. No FK can be violated because we're not claiming any parent yet.

**Pass 2** — batch-update the `parent_code` for every code that has one.

```php
// Pass 1: all rows, parent_code deliberately null
foreach (array_chunk($rows, 500) as $chunk) {
    DB::table('thema_subjects')->insert($chunk);
}

// Pass 2: set the parent links
foreach (array_keys($parentMap) as $childCode) {
    DB::table('thema_subjects')
        ->where('code', $childCode)
        ->update(['parent_code' => $parentMap[$childCode]]);
}
```

This is simple and correct regardless of code length or naming patterns. The trade-off is that pass 2 makes ~9,161 individual UPDATE queries. For a once-per-version seed this is perfectly acceptable.

### Alternative: topological sort

A more algorithmically correct approach would be to build a dependency graph and sort it so parents always come before children. This is called a **topological sort** — you walk the graph depth-first from roots, yielding each node only after all its ancestors have been yielded. It's the "right" computer science answer but adds ~30 lines of complexity for a problem that two-pass solves cleanly.

---

## 7. PostgreSQL foreign key constraints and deferrability

The seeder originally had:

```php
DB::statement('SET CONSTRAINTS ALL DEFERRED');
```

This did nothing, and here's why.

In PostgreSQL, a foreign key constraint can be marked **DEFERRABLE**, which means: "don't check this constraint after every INSERT statement — wait until the transaction commits, then check everything at once." This would let us insert parent/child in any order within one transaction.

Our migration created the FK without `DEFERRABLE`:

```php
$table->foreign('parent_code')
    ->references('code')
    ->on('thema_subjects')
    ->nullOnDelete();
```

This creates a non-deferrable (immediate) constraint. `SET CONSTRAINTS ALL DEFERRED` only affects deferrable constraints — it's a no-op here.

To make it work the deferred way, the migration would need:

```php
// Raw SQL because Laravel's schema builder doesn't expose DEFERRABLE
DB::statement('
    ALTER TABLE thema_subjects
    ADD CONSTRAINT thema_subjects_parent_code_foreign
    FOREIGN KEY (parent_code) REFERENCES thema_subjects(code)
    ON DELETE SET NULL
    DEFERRABLE INITIALLY IMMEDIATE
');
```

We chose **not** to do this because it would require a raw SQL migration, and the two-pass approach is simpler and more readable.

---

## 8. Adding a migration to alter an existing table

When you need to change a table that already has a migration, **never edit the original migration file**. Instead, create a new one.

**Why?** Migrations work like a changelog. The `migrations` database table records which files have run (by filename). If you edit an already-run migration, the database won't re-run it — only new environments starting fresh would get your change. Existing environments would be out of sync.

**The rule:** one migration per change, always additive.

```bash
php artisan make:migration widen_thema_subjects_code_column
```

We needed to widen `varchar(10)` to `varchar(20)` because Thema v1.6 has codes up to 14 characters. The migration had to:

1. Drop the FK first (PostgreSQL won't let you alter a column that a FK references)
2. Alter both columns
3. Re-add the FK

```php
public function up(): void
{
    // 1. Drop FK
    Schema::table('thema_subjects', function (Blueprint $table) {
        $table->dropForeign(['parent_code']);
    });

    // 2. Widen both columns
    Schema::table('thema_subjects', function (Blueprint $table) {
        $table->string('code', 20)->change();
        $table->string('parent_code', 20)->nullable()->change();
    });

    // 3. Re-add FK
    Schema::table('thema_subjects', function (Blueprint $table) {
        $table->foreign('parent_code')
            ->references('code')
            ->on('thema_subjects')
            ->nullOnDelete();
    });
}
```

**The `.change()` method** — calling `->change()` at the end of a column definition tells Laravel "alter this existing column" rather than "add a new column". It requires the `doctrine/dbal` package under the hood (already present in Laravel 13).

**The `down()` method** reverses the migration exactly. `php artisan migrate:rollback` calls `down()`. Good migrations are always reversible.

```bash
# Apply the migration
php artisan migrate

# Roll it back (reverts to varchar(10))
php artisan migrate:rollback
```

---

## 9. Progress bars in artisan commands

The `$this->command` property inside a seeder gives access to the console output — but only when the seeder is called via artisan. It's an instance of `Illuminate\Console\OutputStyle`.

```php
// Create a progress bar with a known total
$bar = $this->command->getOutput()->createProgressBar(count($rows));
$bar->start();

foreach ($rows as $row) {
    // ... do work ...
    $bar->advance();    // move forward by 1
    // or:
    $bar->advance(10);  // move forward by 10
}

$bar->finish();
$this->command->newLine(); // move to the next line after the bar
```

Other useful output methods on `$this->command`:

```php
$this->command->info('Normal message — green');
$this->command->warn('Warning — yellow');
$this->command->error('Error — red');
$this->command->line('Plain text');
```

These map directly to the output methods available inside artisan commands (`$this->info()`, `$this->warn()`, etc.). Seeders just go through `$this->command` because they're not commands themselves.

---

## 10. Creating a reusable artisan command

Artisan commands are PHP classes in `app/Console/Commands/`. They're how you expose functionality at the terminal.

Generate a stub:
```bash
php artisan make:command ThemaUpdate
```

This creates `app/Console/Commands/ThemaUpdate.php` with an empty `handle()` method.

**The anatomy of a command:**

```php
#[Signature('thema:update {--download} {--url=} {--force}')]
#[Description('Seed or re-seed Thema subject codes.')]
class ThemaUpdate extends Command
{
    public function handle(): int
    {
        // Your logic here.
        // Return self::SUCCESS (0) or self::FAILURE (1).
        // The exit code matters in shell scripts and CI pipelines.
        return self::SUCCESS;
    }
}
```

**How Laravel discovers commands:** In Laravel 11+, commands in `app/Console/Commands/` are discovered automatically — no registration needed. Previously you had to list them in `app/Console/Kernel.php`. The discovery relies on Composer's PSR-4 autoloading: any class in that directory extending `Command` is registered.

---

## 11. Command options and the attribute signature syntax

Laravel 13 uses PHP 8 attributes for command signatures:

```php
#[Signature('thema:update
    {--download : Download the latest Thema JSON before seeding}
    {--url= : Custom download URL (overrides default)}
    {--force : Skip confirmation prompt}')]
```

**Syntax reference:**

| Syntax | Meaning | Access |
|--------|---------|--------|
| `{name}` | Required argument | `$this->argument('name')` |
| `{name?}` | Optional argument | `$this->argument('name')` |
| `{name=default}` | Argument with default | `$this->argument('name')` |
| `{--flag}` | Boolean flag (present/absent) | `$this->option('flag')` → true/false |
| `{--key=}` | Option with value | `$this->option('key')` → string or null |
| `{--key=default}` | Option with default | `$this->option('key')` → string |

**The old way (still valid, just older style):**

```php
// Before Laravel 11 / PHP 8 attributes:
protected $signature = 'thema:update {--download} {--url=} {--force}';
protected $description = 'Seed or re-seed Thema subject codes.';
```

Both approaches work in Laravel 13. The attribute syntax is preferred for new code because the class definition is cleaner.

---

## 12. Calling one command from another

Inside a command's `handle()` method, you can invoke other artisan commands:

```php
// Shows the called command's output in the terminal
$this->call('db:seed', ['--class' => ThemaSubjectSeeder::class]);

// Runs the command but suppresses its output
$this->callSilently('db:seed', ['--class' => ThemaSubjectSeeder::class]);
```

The second argument is an array of arguments/options. Option names include the `--` prefix as the array key.

**Why do this instead of instantiating the seeder directly?**

```php
// You could do this:
(new ThemaSubjectSeeder())->run();

// But $this->command won't be set, so progress bars and info() calls
// inside the seeder would throw errors.
```

Calling via `$this->call('db:seed', ...)` ensures Laravel fully bootstraps the seeder, wiring up `$this->command` correctly so all output methods work.

---

## 13. Useful artisan commands for seeders

```bash
# Run a specific seeder
php artisan db:seed --class=ThemaSubjectSeeder

# Run all seeders (calls DatabaseSeeder, which calls others)
php artisan db:seed

# Drop all tables, re-run all migrations, then seed
php artisan migrate:fresh --seed
# ⚠️  Destroys all data — development only!

# Run migrations only (no seeding)
php artisan migrate

# Roll back the last batch of migrations
php artisan migrate:rollback

# Show migration status (which have run, which haven't)
php artisan migrate:status

# Show row counts per table
php artisan db:table --counts

# Interactive PHP REPL — great for testing queries on live data
php artisan tinker
```

**Inside tinker** you can test the seeded data:

```php
// Count total subjects
App\Models\ThemaSubject::count();   // → 9187

// Find all root categories (no parent)
App\Models\ThemaSubject::whereNull('parent_code')->pluck('heading_en', 'code');

// Traverse the hierarchy
$arts = App\Models\ThemaSubject::find('A');
$arts->children;           // direct children of 'A'
$arts->children[0]->children;  // grandchildren
```

---

## Summary of what we built

| File | What it does |
|------|-------------|
| `database/seeders/ThemaSubjectSeeder.php` | Parses Thema v1.6 JSON, inserts 9,187 subject codes using two-pass strategy |
| `database/migrations/..._widen_thema_subjects_code_column.php` | Widens `code` and `parent_code` from varchar(10) to varchar(20) |
| `app/Console/Commands/ThemaUpdate.php` | Artisan command to re-seed; `--download` fetches latest from EDItEUR |
| `storage/thema/thema_en.json` | The source data file (not committed to git) |

## Key concepts encountered

- **Seeders** — no history, always re-run from scratch, use `DB::table()` for bulk ops
- **`storage_path()`** — path helpers keep code environment-agnostic
- **`??` operator** — safe null coalescing, no notices on missing keys
- **`DB::table()` vs Eloquent** — use the query builder for bulk data, Eloquent for application logic
- **Self-referential FK** — parent/child in the same table; requires ordered inserts or two-pass strategy
- **FK deferrability** — PostgreSQL can defer FK checks to transaction commit, but only on `DEFERRABLE` constraints
- **Migrations are additive** — never edit a migration that's been run; add a new one
- **`.change()`** — alter an existing column in a migration
- **Progress bars** — `$this->command->getOutput()->createProgressBar()`
- **PHP attributes for signatures** — `#[Signature(...)]` and `#[Description(...)]` replace string properties in Laravel 11+
- **`$this->call()`** — invoke one artisan command from inside another, keeping console output wired up
