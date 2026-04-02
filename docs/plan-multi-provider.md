# Multi-Provider Ingestion Plan

## Why This Document Exists

The original plan assumed a single data source (BIBLIONET). We now want to add
OpenLibrary while waiting for BIBLIONET credentials, and the design should handle
any number of future providers (WorldCat, BNF, DNB, Z39.50 targets, etc.).

This document defines the architecture and the specific steps to add OpenLibrary.

---

## OpenLibrary — What We Learned

| Property | Detail |
|----------|--------|
| Auth | None required. Include `User-Agent` header for 3 req/sec |
| Rate limit | 3 req/sec with User-Agent, 1 req/sec without |
| Bulk dumps | Monthly JSON dumps — millions of records, free download |
| Incremental sync | `/recentchanges/YYYY/MM/DD.json` — daily delta feed |
| ISBN lookup | `GET /isbn/{isbn}.json` — single-record precision |
| Work lookup | `GET /works/{OLID}.json` |
| Author lookup | `GET /authors/{OLID}.json` |
| Authority IDs | Author records carry VIAF, ISNI, Wikidata IDs natively |
| Subjects | Library of Congress Subject Headings (LCSH), not Thema |
| Language coverage | English-primary but multilingual editions exist |
| FRBR fit | Works and Editions map cleanly; Expressions must be inferred from language |

### Why OpenLibrary Is Valuable for Us Right Now

1. **No credentials needed** — we can start integrating today.
2. **Author records carry VIAF + Wikidata IDs** — direct input for our
   Phase 5 authority matching, no separate API call needed for known authors.
3. **Massive English-language coverage** — millions of works and editions.
4. **Real data to test the pipeline** — we can fully test Phases 4–6 before
   BIBLIONET access arrives.
5. **ISBN lookup** — the cleanest ingestion path: give it an ISBN, get back
   a fully-formed edition record with author and work links.

---

## Architecture: How Multiple Providers Fit Together

### The Core Insight

The `raw_ingestion_records` table already has a `source_system` column.
It was designed from day one to be a **provider-agnostic staging buffer**.
Everything downstream (the FRBR resolution pipeline) only reads from that table,
so adding a new provider means adding code on the left side of this diagram
without touching anything on the right:

```
┌─────────────────────┐      ┌─────────────────────┐
│  BIBLIONET Client   │─────▶│                     │
└─────────────────────┘      │                     │
                             │  raw_ingestion_     │
┌─────────────────────┐      │  records            │
│  OpenLibrary Client │─────▶│  (source_system,    │──▶  Pipeline (Phase 6)
└─────────────────────┘      │   payload JSON)     │
                             │                     │
┌─────────────────────┐      │                     │
│  Future Provider    │─────▶│                     │
└─────────────────────┘      └─────────────────────┘
```

Each provider has its own:
- **Client** — knows the API (auth, endpoints, pagination, rate limits)
- **Fetch command** — artisan command to trigger ingestion
- **Parser** — converts the provider's JSON into our common `IncomingBookDTO` (Phase 6)

The **resolution services** (AuthorResolver, WorkResolver, EditionCreator) are
completely unaware of which provider the data came from.

### Layer Responsibilities

```
Layer               Provider-specific?   Files
─────────────────   ─────────────────    ────────────────────────────────────
Client              YES                  app/Clients/BiblionetClient.php
                                         app/Clients/OpenLibraryClient.php
Fetch command       YES                  app/Console/Commands/BiblionetFetch.php
                                         app/Console/Commands/OpenLibraryFetch.php
Staging table       NO                   raw_ingestion_records (source_system col)
Parser              YES                  app/Services/BiblionetParser.php  (Phase 6)
                                         app/Services/OpenLibraryParser.php (Phase 6)
Common DTO          NO                   app/DTOs/IncomingBookDTO.php (Phase 6)
Resolvers           NO                   AuthorResolver, WorkResolver, etc. (Phase 6)
```

### The Pipeline Router (Phase 6)

When `ProcessRawRecordJob` picks up a raw record, it routes to the right parser
based on `source_system`:

```php
// app/Jobs/ProcessRawRecordJob.php  (Phase 6)
$parser = match ($this->record->source_system) {
    'biblionet'     => app(BiblionetParser::class),
    'openlibrary'   => app(OpenLibraryParser::class),
    default         => throw new UnknownSourceSystemException($this->record->source_system),
};

$dto = $parser->parse($this->record->payload);
// ... then resolvers run the same for all providers
```

This is a **Strategy pattern** — the resolver pipeline is fixed, the parser is
swappable per source.

---

## OpenLibrary Integration Steps

### Step OL-1: Configuration

Add to `config/services.php`:

```php
'openlibrary' => [
    'base_url'   => env('OPENLIBRARY_BASE_URL', 'https://openlibrary.org'),
    'user_agent' => env('OPENLIBRARY_USER_AGENT', 'EUCatalog/1.0 (your@email.com)'),
    // 3 req/sec with User-Agent; use 200ms sleep between requests
    'rate_limit' => (int) env('OPENLIBRARY_RATE_LIMIT', 3),
],
```

Add to `.env` / `.env.example`:

```
OPENLIBRARY_BASE_URL=https://openlibrary.org
OPENLIBRARY_USER_AGENT=EUCatalog/1.0 (admin@yourdomain.com)
OPENLIBRARY_RATE_LIMIT=3
```

No credentials — just the User-Agent.

---

### Step OL-2: The Client Interface

OpenLibrary's capabilities differ from BIBLIONET's, so it gets its own interface.
Do **not** force a generic interface on both clients — that would mean the
lowest common denominator. Each provider interface describes exactly what
that provider can do.

```php
// app/Clients/Contracts/OpenLibraryClientInterface.php

interface OpenLibraryClientInterface
{
    // Edition/book by ISBN
    public function fetchByIsbn(string $isbn): ?array;

    // Full work record with all editions
    public function fetchWork(string $olid): array;
    public function fetchWorkEditions(string $olid, int $limit = 50): array;

    // Author record (includes VIAF, Wikidata, ISNI, name variants)
    public function fetchAuthor(string $olid): array;
    public function fetchAuthorWorks(string $olid, int $limit = 50): array;

    // Search
    public function search(string $query, int $limit = 20, int $offset = 0): array;
    public function searchByTitle(string $title, int $limit = 20): array;
    public function searchByAuthor(string $authorName, int $limit = 20): array;

    // Incremental sync: returns changesets for the given day
    public function fetchChanges(DateTimeInterface $date, int $limit = 100, int $offset = 0): array;
}
```

---

### Step OL-3: The Client Implementation

Key implementation notes:

- **No auth** — just set the `User-Agent` header on every request.
- **ISBN redirect** — `GET /isbn/{isbn}.json` returns a 301 redirect to the
  edition OLID. Laravel's HTTP client follows redirects automatically.
- **Author authority IDs** — the author's `remote_ids` object contains `viaf`,
  `isni`, `wikidata` — exactly what Phase 5 needs. Store them during ingestion
  so the authority matcher can skip API calls for known authors.
- **`covers` array quirk** — may contain `-1` as a null placeholder. Filter
  these out.
- **Rate limiting** — `usleep(1_000_000 / $rateLimit)` between requests,
  same pattern as `BiblionetClient`.

```php
// app/Clients/OpenLibraryClient.php

class OpenLibraryClient implements OpenLibraryClientInterface
{
    public function fetchByIsbn(string $isbn): ?array
    {
        // OL returns HTTP 301 → edition OLID, then 200 with the record.
        // Laravel Http follows redirects by default.
        $response = $this->get("/isbn/{$isbn}.json");

        // Returns null if OL doesn't know this ISBN (404).
        return $response;
    }

    public function fetchAuthor(string $olid): array
    {
        // Author records carry remote_ids: { viaf, isni, wikidata, ... }
        // This is gold for Phase 5 — we get authority IDs for free.
        return $this->get("/authors/{$olid}.json");
    }

    // ... etc
}
```

---

### Step OL-4: The Fetch Command

```bash
php artisan openlibrary:fetch --isbn=9780743273565
php artisan openlibrary:fetch --search="Nikos Kazantzakis"
php artisan openlibrary:fetch --sync            # yesterday's changes
php artisan openlibrary:fetch --sync --date=2026-03-01
php artisan openlibrary:fetch --work=OL45804W   # single work + all editions
```

Modes:

| Mode | When to use |
|------|-------------|
| `--isbn` | One-off lookup for a specific book |
| `--search` | Query-based bulk ingestion |
| `--work` | Import a known work and all its editions |
| `--sync` | Nightly incremental delta |

The staging logic is identical to `BiblionetFetch`:
`updateOrCreate` on `(source_system='openlibrary', source_record_id=OLID)`.

---

### Step OL-5: Bind in AppServiceProvider

```php
$this->app->bind(OpenLibraryClientInterface::class, function () {
    return new OpenLibraryClient(
        baseUrl:   config('services.openlibrary.base_url'),
        userAgent: config('services.openlibrary.user_agent'),
        rateLimit: config('services.openlibrary.rate_limit'),
    );
});
```

---

### Step OL-6: OpenLibrary Parser (Phase 6 — implement when ready)

This converts the staged OpenLibrary JSON payload into our common
`IncomingBookDTO`. Key mapping decisions:

| OpenLibrary field | Our DTO field | Notes |
|-------------------|---------------|-------|
| `title` | `title` | |
| `works[0].key` → `/works/OL123W` | (fetch separately) | Edition links back to work |
| `isbn_13[0]` | `isbn13` | Array — take first |
| `isbn_10[0]` | `isbn10` | |
| `publishers[0]` | `publisherName` | String, not structured |
| `publish_date` | `publicationDate` | Many formats: "1997", "March 1997", etc. |
| `languages[0].key` → `/languages/eng` | `language` | Strip to ISO code |
| `number_of_pages` | `pages` | |
| `authors[].key` → fetch author | `contributors` | Must do separate API call for name |
| `subjects` | `themaCodes` | LCSH, not Thema — needs separate mapping table |

**The subject problem:** OpenLibrary uses LCSH (Library of Congress Subject
Headings), not Thema. We have two options:
1. Store LCSH subjects as-is (add an `lcsh_subjects` column to works/editions)
2. Build a LCSH→Thema mapping table (complex, imperfect, could be a Phase 10 task)

Recommendation: store LCSH subjects raw for now, add Thema subjects manually
or via the review queue. This is a known limitation of cross-catalog ingestion.

---

### Step OL-7: Scheduled incremental sync

Add to `routes/console.php`:

```php
Schedule::command('openlibrary:fetch --sync')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/openlibrary-sync.log'));
```

---

## What Changes in the Existing Phase 4 Work

**Nothing breaks.** The BIBLIONET client and fetch command stay exactly as they
are. We're adding, not replacing. The only shared files are:

- `config/services.php` — add the `openlibrary` block
- `app/Providers/AppServiceProvider.php` — add the new binding
- `routes/console.php` — add the new schedule

---

## Work Ordering

We can build OL-1 through OL-5 right now. OL-6 (the parser) is Phase 6 work
and we'll do it alongside the BIBLIONET parser.

```
Now (Phase 4 context):
  OL-1  Add config
  OL-2  Create interface
  OL-3  Implement client
  OL-4  Create fetch command
  OL-5  Bind in AppServiceProvider
  OL-7  Add to scheduler

When BIBLIONET credentials arrive:
  Test BiblionetFetch (steps 76, 77, 80)

Phase 6:
  OL-6  OpenLibraryParser
  OL-8  Route ProcessRawRecordJob by source_system
  OL-9  LCSH subject handling decision
```

---

## Future Providers — What Each One Needs

When adding provider N in the future, the checklist is always:

1. [ ] `config/services.php` block
2. [ ] `.env.example` entries
3. [ ] `app/Clients/Contracts/ProviderNClientInterface.php`
4. [ ] `app/Clients/ProviderNClient.php`
5. [ ] `app/Clients/Exceptions/ProviderN*.php` (if auth/rate-limit needed)
6. [ ] `app/Console/Commands/ProviderNFetch.php`
7. [ ] `AppServiceProvider` binding
8. [ ] `routes/console.php` schedule
9. [ ] `app/Services/ProviderNParser.php` (Phase 6)
10. [ ] Add to `ProcessRawRecordJob` router match statement (Phase 6)

No changes to the database schema, models, or resolution services.

---

## Potential Future Providers

| Provider | Coverage | Auth | Notes |
|----------|----------|------|-------|
| BIBLIONET | Greek books | OAuth2 | Primary source, pending |
| OpenLibrary | Global, English-heavy | None | Integrating now |
| WorldCat | Global, 500M+ records | API key (free tier) | Highest coverage |
| BNF (Bibliothèque nationale de France) | French books | None | Good for EU |
| DNB (Deutsche Nationalbibliothek) | German books | None | SRU/Z39.50 |
| Project Gutenberg | Public domain texts | None | Full text available |
| CrossRef | Academic/journals | Polite pool | DOI-based, not books |
| Google Books | Global | API key | Cover images, snippets |

---

## Summary

- The ingestion architecture is already multi-provider by design
- Adding OpenLibrary requires ~4 new files and 3 small edits
- OpenLibrary gives us real data to test the full pipeline immediately
- Author records from OpenLibrary include VIAF/Wikidata IDs, which
  significantly simplifies Phase 5 for English-language authors
- The LCSH→Thema subject mapping is a deferred problem
- When BIBLIONET arrives, it slots in without changing anything already built
