# Provider: Open Library

## What It Is

[Open Library](https://openlibrary.org) is an open, editable library catalog run by the
Internet Archive. It contains ~40M edition records, ~25M work records, and ~12M author
records, covering primarily English-language books with significant multilingual coverage.

**Why it matters for this project:**
- No credentials required (just a `User-Agent` header)
- Author records natively carry VIAF, Wikidata, and ISNI authority IDs — free input for Phase 6 authority matching
- Monthly bulk data dumps — full catalog available for download
- Daily incremental feed via RecentChanges API
- Real data to test the full ingestion pipeline before BIBLIONET access arrives

---

## Data Model

OpenLibrary's data model maps onto FRBR almost directly:

| OpenLibrary entity | FRBR equivalent | OLID suffix | Example |
|--------------------|-----------------|-------------|---------|
| Work (`/works/`)   | Work            | `W`         | `OL45804W` |
| Edition (`/books/`)| Edition         | `M`         | `OL7353617M` |
| Author (`/authors/`)| Person/Author  | `A`         | `OL34184A` |

**Expression** is not an explicit entity in OpenLibrary. It must be inferred from the
edition's `languages` field. An edition in English and an edition in Greek of the same
work represent two different Expressions in our FRBR model.

### Work record key fields
```json
{
  "key": "/works/OL45804W",
  "title": "Fantastic Mr. Fox",
  "description": "...",
  "authors": [{"author": {"key": "/authors/OL34184A"}}],
  "subjects": ["Animals -- Fiction", "Foxes -- Fiction"],
  "first_publish_date": "1970"
}
```

### Edition record key fields
```json
{
  "key": "/books/OL7353617M",
  "title": "Fantastic Mr. Fox",
  "works": [{"key": "/works/OL45804W"}],
  "authors": [{"key": "/authors/OL34184A"}],
  "isbn_10": ["0140328726"],
  "isbn_13": ["9780140328721"],
  "publishers": ["Puffin"],
  "publish_date": "1988",
  "number_of_pages": 96,
  "languages": [{"key": "/languages/eng"}],
  "covers": [8739161]
}
```

### Author record key fields
```json
{
  "key": "/authors/OL34184A",
  "name": "Roald Dahl",
  "birth_date": "13 September 1916",
  "death_date": "23 November 1990",
  "remote_ids": {
    "viaf": "108159131",
    "isni": "0000000121441970",
    "wikidata": "Q25415"
  },
  "alternate_names": ["Roald Dahl", "R. Dahl"]
}
```

The `remote_ids` on author records is particularly valuable — it gives us VIAF, ISNI, and
Wikidata IDs for free, which is exactly what Phase 6 authority matching needs.

---

## FRBR Mapping

| OpenLibrary field | Our table/column | Notes |
|-------------------|-----------------|-------|
| `works[0].key` | `works.id` (via lookup) | Must resolve the work OLID |
| `title` | `works.title` | On both work and edition records |
| `languages[0].key` | `expressions.language` | Strip `/languages/` prefix → ISO code |
| `isbn_13[0]` | `editions.isbn_13` | Array — take first valid one |
| `isbn_10[0]` | `editions.isbn_10` | Array — take first valid one |
| `publishers[0]` | `publishers.name` | Plain string, not structured |
| `publish_date` | `editions.published_year` | Many formats: "1997", "March 1997", "circa 1990" |
| `number_of_pages` | `editions.pages` | May be absent |
| `authors[].key` | `authors` (via lookup) | OLID references — must resolve to names |
| `name` | `authors.primary_name` | On author records |
| `remote_ids.viaf` | `authors.viaf_id` | Direct from author record |
| `remote_ids.wikidata` | `authors.wikidata_id` | Direct from author record |
| `remote_ids.isni` | `authors.isni` | Direct from author record |
| `subjects` | `work_subjects` | LCSH format, not Thema — see note below |

### The Subject Problem

OpenLibrary uses **Library of Congress Subject Headings (LCSH)**, not Thema. Our catalog
uses Thema. Options:

1. **Store LCSH raw** — add a `lcsh_subjects` JSON column to works (simplest, non-destructive)
2. **Build a LCSH→Thema crosswalk** — complex and imperfect; defer to later phase
3. **Manual mapping** — assign Thema codes via the review queue for important works

**Current decision:** store LCSH subjects as free text in the raw payload. Thema assignment
is a manual or future-automated step. This is a known limitation of cross-catalog ingestion.

---

## Data Sources

### 1. Bulk Dump Files (Initial Load)

OpenLibrary publishes monthly full dumps at:
```
https://openlibrary.org/data/ol_dump_editions_latest.txt.gz   (~8GB compressed, ~40M records)
https://openlibrary.org/data/ol_dump_works_latest.txt.gz      (~3GB compressed, ~25M records)
https://openlibrary.org/data/ol_dump_authors_latest.txt.gz    (~1GB compressed, ~12M records)
https://openlibrary.org/data/ol_dump_latest.txt.gz            (combined — all types in one file)
```

The combined dump (`ol_dump_latest.txt.gz`) contains all record types in a single file,
each line tagged with its type.

**Dump file format (TSV, 5 columns):**
```
/type/edition  /books/OL7353617M   5   2024-11-01T10:22:00   {"key":"/books/OL7353617M",...}
/type/work     /works/OL45804W     3   2024-10-15T08:30:00   {"key":"/works/OL45804W",...}
/type/author   /authors/OL34184A   2   2024-09-20T12:00:00   {"key":"/authors/OL34184A",...}
```

Columns: `type | key | revision | last_modified | json_payload`

**How to derive `record_type`:** strip `/type/` prefix from column 1.
- `/type/edition` → `edition`
- `/type/work` → `work`
- `/type/author` → `author`
- `/type/redirect` → skip
- `/type/delete` → skip or mark deleted

**How to derive `source_record_id`:** strip leading `/` from column 2.
- `/books/OL7353617M` → `books/OL7353617M`
- `/works/OL45804W` → `works/OL45804W`
- `/authors/OL34184A` → `authors/OL34184A`

**Local storage convention:**
```
storage/providers/openlibrary/ol_dump_editions_YYYY-MM-DD.txt.gz
storage/providers/openlibrary/ol_dump_works_YYYY-MM-DD.txt.gz
storage/providers/openlibrary/ol_dump_authors_YYYY-MM-DD.txt.gz
storage/providers/openlibrary/ol_dump_YYYY-MM-DD.txt.gz        ← combined
```

Place downloaded files here. The `openlibrary:import-dump` command auto-detects the
most recent file in this directory.

### 2. RecentChanges API (Daily Incremental)

```
GET https://openlibrary.org/recentchanges/YYYY/MM/DD.json?limit=500
```

Returns changesets for a given day. Each changeset lists the keys that were modified.
Used by `openlibrary:fetch --sync` for daily delta updates after the initial bulk load.

**Important:** the RecentChanges feed includes editions, works, and authors mixed together.
Currently `openlibrary:fetch --sync` only extracts `/books/` keys (editions). Works and
authors changed on the same day are not captured. This is a known gap to address.

### 3. Direct API Lookups (On-demand)

| Purpose | Endpoint |
|---------|----------|
| ISBN lookup | `GET /isbn/{isbn}.json` → redirects to edition |
| Edition | `GET /books/{olid}.json` |
| Work | `GET /works/{olid}.json` |
| Work editions | `GET /works/{olid}/editions.json?limit=N` |
| Author | `GET /authors/{olid}.json` |
| Search | `GET /search.json?q=...&limit=N` |

**Auth:** None. Include `User-Agent: EUCatalog/1.0 (admin@domain.com)` on every request.
**Rate limit:** 3 req/sec with User-Agent, 1 req/sec without.

---

## Integration Architecture

### record_type in raw_ingestion_records

The staging table needs a `record_type` column so the normaliser knows what FRBR entity
each raw record represents. Without it, the normaliser has to parse `source_record_id` to
infer the type — fragile and provider-specific.

```
raw_ingestion_records
  source_system    = 'openlibrary'
  source_record_id = 'books/OL7353617M'
  record_type      = 'edition'           ← NEW: explicit type
  payload          = { ... json ... }
  status           = 'pending'
```

For BIBLIONET, `record_type` might be `'book'` for a record that the normaliser expands
into both a work and an edition. The field is provider-vocabulary, not FRBR vocabulary.

### Ingestion Flow

```
INITIAL LOAD (one-time)
  Download dump → storage/providers/openlibrary/
  php artisan openlibrary:import-dump          ← reads local files, auto-detects type
  → raw_ingestion_records (millions of pending records)

DAILY DELTA (automated via scheduler)
  catalog:sync → openlibrary:fetch --sync
  → raw_ingestion_records (hundreds of new/updated records per day)

NORMALISATION (Phase 5, not yet built)
  catalog:normalise
  → reads raw_ingestion_records where status='pending'
  → OpenLibraryMapper maps edition/work/author records to FRBR DTOs
  → CatalogWriter upserts into works / expressions / editions / authors
  → status = 'processed'
```

### Provider-Agnostic Design Principles

1. **`raw_ingestion_records` is the contract** between ingestion (provider-specific) and
   normalisation (provider-agnostic). Everything to the right of that table is universal.

2. **`record_type` is provider vocabulary** — the mapper translates it to FRBR entities.
   OpenLibrary's `edition` → our `Edition`. BIBLIONET's `book` → our `Work` + `Edition`.

3. **One mapper per provider** — `OpenLibraryMapper`, `BiblionetMapper`, etc. Each takes
   a raw payload and returns a `NormalisedBookRecord` DTO (or multiple DTOs for records
   that map to multiple FRBR entities).

4. **`MapperRegistry::for($sourceSystem)` routes to the right mapper** — adding a new
   provider means adding one mapper class and one registry entry.

---

## Current Implementation Status

### Done ✓
- `OpenLibraryClient` — HTTP client with retry, timeout, throttle, `fetchEdition`, `fetchWork`, `fetchWorkEditions`, `fetchAuthor`, `fetchByIsbn`, `fetchChanges`
- `OpenLibraryClientInterface` — contract defining all methods
- `openlibrary:fetch` — artisan command with `--isbn`, `--work`, `--search`, `--sync`, `--full`, `--dry-run`, `--limit`
- `openlibrary:import-dump` — stub command for local dump file import (needs rewrite — see gaps below)
- `catalog:sync` — orchestrator that auto-detects full vs incremental per provider
- `ProviderCredential` admin panel — Settings → Data Providers, with Sync button
- Async sync via `SyncProviderJob` + database queue + Filament bell notifications
- Nightly scheduler in `routes/console.php` at 03:00

### Gaps / Known Issues ✗

1. **`openlibrary:import-dump` is broken for URLs** — `compress.zlib://https://` silently
   returns empty streams. Rewrite to read local files from `storage/providers/openlibrary/`
   using `gzopen()`.

2. **No `record_type` column** — `raw_ingestion_records` has no explicit type field.
   The normaliser cannot distinguish edition/work/author records without parsing keys.
   Requires a new migration.

3. **`openlibrary:fetch --sync` only captures editions** — `fetchChangesForDay()` filters
   for `/books/` keys only. Works and authors changed on the same day are ignored.

4. **`openlibrary:fetch --full` is impractical for history** — walking the RecentChanges
   API day-by-day from 2010 would make ~5,000 API calls per day × 5,000+ days. Use bulk
   dump instead.

5. **Authors not fetched during edition sync** — when an edition is staged, its linked
   author OLIDs are not resolved. The normaliser will need to make API calls or the sync
   should pre-fetch and stage authors too.

6. **LCSH subjects not mapped to Thema** — OpenLibrary subjects are LCSH strings; no
   crosswalk to our Thema codes exists yet.

---

## Planned: openlibrary:import-dump (Rewrite)

The rewritten command will:

1. Auto-detect dump files from `storage/providers/openlibrary/` (or accept `--file`)
2. Support combined dump (`ol_dump_YYYY-MM-DD.txt.gz`) and per-type files
3. Infer `record_type` from the `/type/` column in the TSV
4. Skip `/type/redirect` and `/type/delete` records
5. Set `record_type` on each staged record
6. Support `--type=editions|works|authors|all` to filter which types to import
7. Stream via `gzopen()` — never loads file into memory

```bash
# Auto-detect latest dump in storage/providers/openlibrary/
php artisan openlibrary:import-dump

# Import only editions from a specific file
php artisan openlibrary:import-dump --file=storage/providers/openlibrary/ol_dump_2026-03-31.txt.gz --type=editions

# Test with small limit
php artisan openlibrary:import-dump --limit=1000 --dry-run
```

---

## Planned: OpenLibraryMapper (Phase 5)

The mapper translates a raw payload from `raw_ingestion_records` into FRBR DTOs.
Different logic per `record_type`:

```
record_type = 'edition' → NormalisedEditionRecord
  - Requires work OLID resolution (may trigger a work lookup)
  - May require author OLID resolution (may trigger author lookup)

record_type = 'work' → NormalisedWorkRecord
  - Title, description, subjects
  - Author OLIDs (resolve from raw_ingestion_records cache or API)

record_type = 'author' → NormalisedAuthorRecord
  - Name, birth/death dates
  - VIAF, Wikidata, ISNI from remote_ids (free authority data)
```

The mapper processes `author` records first, then `work` records, then `edition` records,
so that work/edition normalisation can reference already-resolved authors.

---

## Future Considerations

- **Cover images** — edition `covers` array contains cover IDs; images at `https://covers.openlibrary.org/b/id/{id}-L.jpg`
- **Reading log / ratings** — OpenLibrary dumps include `ol_dump_reading-log` and `ol_dump_ratings` — potential data for popularity signals
- **Wikidata enrichment** — author `remote_ids.wikidata` can be used to fetch additional data from Wikidata (nationality, gender, occupation) via SPARQL
- **Monthly dump refresh** — re-running `openlibrary:import-dump` monthly keeps the catalog fresh; upsert ensures no duplicates
