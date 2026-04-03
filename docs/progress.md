# Implementation Progress

## Overview

| Phase | Name | Status |
|-------|------|--------|
| 1 | Foundation | ✓ Complete |
| 2 | Filament Admin | ✓ Complete |
| 3 | Thema Seeding | ✓ Complete |
| 4 | Multi-Provider Ingestion | ✓ Complete |
| 5 | Normalisation Pipeline | In Progress |
| 6 | Authority Matching | Not Started |
| 7 | Pipeline Services (FRBR Write) | Not Started |
| 8 | Search & Indexing | Not Started |
| 9 | Public API | Not Started |
| 10 | Testing & Deployment | Not Started |
| 11 | Federation | Not Started |

---

## Phase 1: Foundation ✓ COMPLETE

- [x] Laravel project created
- [x] PostgreSQL configured
- [x] Nginx configured
- [x] Required packages installed
- [x] Directory structure created
- [x] HasUuid trait
- [x] All 15 migrations
- [x] All 12 Eloquent models
- [x] Relationships verified

---

## Phase 2: Filament Admin (Steps 29-55) ✓ COMPLETE

### Installation
- [x] 29. Install Filament (v4.9.3)
- [x] 30. Create admin user
- [x] 31. Configure panel (branding, navigation groups, global search)
- [x] 32. Update User model (FilamentUser + Spatie roles)

### Entity Resources
- [x] 33. AuthorResource (with NameVariants + Works relation managers)
- [x] 34. AuthorNameVariantResource (inline relation manager on Author)
- [x] 35. PublisherResource (with NameVariants + Editions relation managers)
- [x] 36. WorkResource (with Authors + Expressions relation managers)
- [x] 37. ExpressionResource (with Contributors + Editions relation managers)
- [x] 38. EditionResource (with ProvenanceLog relation manager, FRBR chain links)
- [x] 39. PublisherNameVariantResource (inline relation manager on Publisher)
- [x] 40. ThemaSubjectResource
- [x] 41. ProvenanceResource (read-only, Ingestion group)
- [x] 42. EditionProvenanceLogResource (read-only relation manager on Edition)
- [x] 43. RawIngestionRecordResource (read-only, Ingestion group)
- [x] 44. ReviewQueueResource (view + edit for resolution)
- [x] 45. All resources verified

### Dashboard & Custom Pages
- [x] 46. Dashboard widgets (CatalogStatsOverview + AuthorityCoverageWidget)
- [ ] 47. Review Queue manager (custom page — deferred to Phase 6)
- [ ] 48. Author merge page (deferred to Phase 5)
- [ ] 49. Work merge page (deferred to Phase 6)
- [ ] 50. Ingestion monitor (deferred to Phase 4)
- [ ] 51. Quality report page (deferred to Phase 5)
- [ ] 52. Test custom pages

### Polish
- [x] 53. Navigation structure (Catalog / Classification / Ingestion / Quality groups)
- [x] 54. Global search (Works, Authors, Editions, Publishers)
- [x] 55. Roles seeder (super_admin, admin, editor, cataloger)

### Notes
- Used Filament v4 (v3 does not support Laravel 13)
- Section component lives in Filament\Schemas\Components (not Forms/Infolists)
- Provenance/RawIngestion/ReviewQueue have no Create page (system-managed)
- FRBR chain fully navigable: Edition → Expression → Work (clickable links)
- Pivot fields (role, position) exposed on Work↔Author attach modal
- Custom pages (merge, monitor, quality) deferred — need ingestion data first

---

## Phase 3: Thema Seeding (Steps 56-65) ✓ COMPLETE

- [x] 56. Download from EDItEUR (v1.6 JSON, stored at storage/thema/thema_en.json)
- [x] 57. Analyze structure (v1.6: CodeList.ThemaCodes.Code array)
- [x] 58. Plan Greek headings (heading_el nullable, to be populated later)
- [x] 59. Create seeder class (ThemaSubjectSeeder)
- [x] 60. Parse XML/JSON (JSON parsing with version-agnostic path resolution)
- [x] 61. Handle hierarchy order (two-pass insert: rows first, parent links second)
- [x] 62. Implement seeder (batch insert + batch update, progress bars)
- [x] 63. Run seeder (9,187 codes, 26 root categories)
- [x] 64. Verify in admin (ThemaSubjectResource available)
- [x] 65. Create update command (php artisan thema:update --download)

### Notes
- Thema v1.6 has codes up to 14 chars (national extensions like 1KBB-US-NAKCMG)
  — required widening code/parent_code columns from varchar(10) to varchar(20)
  (migration: 2026_04_02_094832_widen_thema_subjects_code_column)
- Parent codes are not always a prefix of the child code; length-sort was
  insufficient — switched to two-pass insert to avoid FK violations
- CodeParent is sometimes integer in v1.6 JSON; cast to string before use
- Level calculation: strlen(code) - 1 (approximate; hyphens inflate depth for
  national extension codes but this is acceptable for display purposes)

---

## Phase 4: Multi-Provider Ingestion — Partial

### Infrastructure ✓
- [x] 67. Create configuration (config/services.php — biblionet + openlibrary blocks)
- [x] 68. Create BiblionetClientInterface + OpenLibraryClientInterface contracts
- [x] 69. Implement BiblionetClient (OAuth2, retry, throttle)
- [x] 70. Implement OpenLibraryClient (User-Agent, timeout 30s, retry on 5xx/timeout, fetchEdition)
- [x] Create BiblionetAuthException, BiblionetRateLimitException, BiblionetApiException

### Provider Credentials Admin ✓
- [x] ProviderCredential model (UUID, encrypted credentials, JSON settings)
- [x] Migration: provider_credentials table (is_active, auto_sync, last_ingestion_at)
- [x] Migration: convert provenance.source_system from enum CHECK to plain VARCHAR
- [x] Filament resource (Settings group, KeyValue form, ToggleColumn table)
- [x] Form auto-fills defaults when provider is selected (->live() + afterStateUpdated)
- [x] AppServiceProvider: DB-backed credential injection with Schema::hasTable() guard

### Fetch Commands ✓
- [x] php artisan biblionet:fetch (--full, --since, --limit, --dry-run)
- [x] php artisan openlibrary:fetch (--isbn, --work, --search, --full, --sync, --since, --date, --limit, --dry-run)
- [x] php artisan catalog:sync (orchestrator — auto-detects full vs incremental per provider)
- [x] Staged to raw_ingestion_records with Provenance batch tracking
- [x] Verified: OpenLibrary ISBN fetch works; sync mode fetches ~700 editions/day via RecentChanges

### Automation ✓
- [x] catalog:sync reads last_ingestion_at — null→full, set→incremental
- [x] Nightly scheduler in routes/console.php (catalog:sync at 03:00)
- [x] Cron installed: * * * * * php artisan schedule:run
- [x] Async sync via SyncProviderJob + database queue (QUEUE_CONNECTION=database)
- [x] Admin "Fetch Changes" + "Force Full Sync" buttons dispatch job, bell notification on completion
- [x] Filament database notifications (notifications.data migrated to jsonb for PostgreSQL)

### BIBLIONET Provider ✓ (completed session 2026-04-03)
- [x] `ethelserth/biblionet-laravel` library installed and wired up
- [x] `ProviderType` backed enum (Biblionet, OpenLibrary) with `HasLabel` — replaces raw array definitions
- [x] `ProviderDefinition` readonly DTO — typed credential/setting defaults per provider
- [x] `ProviderCredential` model casts `provider` to `ProviderType`; `SyncProviderJob` and `CatalogSync` updated
- [x] `AppServiceProvider` overrides library singleton with DB credentials (username/password from admin)
- [x] `biblionet:fetch` rewritten — incremental via `getTitlesByLastUpdate()`, full via `getMonthTitles()` (summaries)
- [x] Migration: `add_record_type_to_raw_ingestion_records` applied
- [x] Library bug fixed: `getTitle()` and `getTitleByIsbn()` unwrap `$data[0][0]` (consistent with `getSubject`/`getLanguage`)
- [x] Tested: single title, incremental fetch, staging to `raw_ingestion_records`
- [x] Rate limit guard: 1 000 requests/day hard limit; default budget 950 with `--max-requests` override
- [x] Auth error handling: any `BiblionetException` bubbles to `handle()`, stops command, shows clear message

### Outstanding ✗
- [ ] `openlibrary:import-dump` needs rewrite — URL streaming broken, must use local files
- [ ] `openlibrary:fetch --sync` only captures editions; works and authors from RecentChanges ignored
- [ ] Author OLIDs not pre-fetched and staged alongside editions during sync
- [ ] Full Biblionet historical fetch not yet run (use `biblionet:fetch --full --since=YYYY-MM`)
      Note: 1 000 requests/day limit — spread full historical import across multiple days

### Notes
- See docs/providers/openlibrary.md for full OpenLibrary data model, gaps, and plans
- OpenLibrary dump files go in storage/providers/openlibrary/ (user downloads manually)
- RecentChanges endpoint: use real past dates only (future dates hang the connection)
- Full historical sync via dump files, not RecentChanges day-walking (too slow for years of history)

---

## Phase 5: Normalisation Pipeline (Steps 81-100)

### Architecture
Each provider has a **Mapper** that translates raw JSON payload fields into a
provider-agnostic **NormalisedBookRecord** DTO. A shared **CatalogWriter** service
then upserts into the FRBR tables. This is what moves records from
`raw_ingestion_records` (status=pending) into `works / expressions / editions / authors`.

```
raw_ingestion_records (pending)
    → MapperRegistry::for($sourceSystem)
    → ProviderMapper::map($payload)  ← one per provider (OpenLibrary, BIBLIONET…)
    → NormalisedBookRecord (DTO)      ← provider-agnostic FRBR shape
    → CatalogWriter::write($dto)      ← upserts Work, Expression, Edition, Author
    → raw_ingestion_records status = 'processed'
```

Why a mapper per provider? Because field names differ:
- OpenLibrary: `title`, `isbn_13[]`, `publishers[]`, `publish_date`, `languages[].key`
- BIBLIONET: completely different structure (TBD when credentials arrive)
The DTO is the common language between them.

### Prerequisites (do first)
- [ ] 81. Migration: add `record_type` VARCHAR to raw_ingestion_records (edition, work, author, book…)
- [ ] 82. Rewrite openlibrary:import-dump to read local files from storage/providers/openlibrary/
         - Auto-detect latest dump file in directory
         - Support combined dump (all types) and per-type files
         - Infer record_type from /type/ column in TSV
         - Skip /type/redirect and /type/delete records
         - Use gzopen() for local .gz files (not compress.zlib:// URL approach)
         - php artisan openlibrary:import-dump [--file=path] [--type=editions|works|authors|all] [--limit=N] [--dry-run]

### DTOs & Contracts
- [ ] 83. MapperInterface contract (map(array $payload, string $recordType): NormalisedRecord[])
- [ ] 84. NormalisedWorkRecord DTO
- [ ] 85. NormalisedExpressionRecord DTO (inferred from edition language)
- [ ] 86. NormalisedEditionRecord DTO (isbn, pages, publish_year, publisher)
- [ ] 87. NormalisedAuthorRecord DTO (name, viaf_id, wikidata_id, isni)
- [ ] 88. MapperRegistry (resolves mapper by source_system string)

### OpenLibrary Mapper
- [ ] 89. OpenLibraryMapper implements MapperInterface
- [ ] 90. map() routes to mapEdition / mapWork / mapAuthor based on record_type
- [ ] 91. mapEdition: isbn_10/13, pages, publish_date, publishers, language, work OLID
- [ ] 92. mapWork: title, description, subjects (LCSH stored raw)
- [ ] 93. mapAuthor: name, alternate_names, birth/death dates, remote_ids (VIAF, Wikidata, ISNI)
- [ ] 94. Process order: authors first → works → editions (so FK references resolve)

### BIBLIONET Mapper (blocked on credentials)
- [ ] 95. BiblionetMapper implements MapperInterface (implement when API docs available)

### Catalog Writer
- [ ] 96. CatalogWriter service (provider-agnostic — knows only FRBR DTOs)
- [ ] 97. findOrCreateWork (match by title + author fingerprint, or create new)
- [ ] 98. findOrCreateExpression (match by work + language code)
- [ ] 99. createOrUpdateEdition (ISBN as unique key; composite key fallback)
- [ ] 100. attachAuthors (name variants; authority IDs from DTO)
- [ ] 101. attachPublisher (findOrCreate by name)
- [ ] 102. Write edition_provenance_log entry

### Job & Command
- [ ] 103. ProcessRawIngestion job (reads pending records, routes by source_system+record_type)
- [ ] 104. php artisan catalog:normalise (--provider, --record-type, --limit, --dry-run)
- [ ] 105. Verify: raw record → works/editions/authors tables visible in admin

---

## Phase 6: Authority Matching (Steps 101-130)

### Support Utilities
- [ ] 101. Greek text normalizer
- [ ] 102. ISBN validator / formatter
- [ ] 103. Normalizer tests
- [ ] 104. ISBN tests

### VIAF Client
- [ ] 105. Create client + interface
- [ ] 106. Search by name
- [ ] 107. Search with birth/death dates
- [ ] 108. Parse response
- [ ] 109. Implement caching
- [ ] 110. Error handling
- [ ] 111. Test with known authors (Καζαντζάκης → VIAF 17227014)

### Wikidata Client
- [ ] 112. Create client
- [ ] 113. Entity search
- [ ] 114. SPARQL queries (P31=Q5 for persons, P213 for ISNI)
- [ ] 115. Extract identifiers (VIAF P214, ISNI P213, BNF P268)
- [ ] 116. Caching
- [ ] 117. Test

### Authority Matcher
- [ ] 118. MatchResult DTO (confidence float, matched_viaf, matched_wikidata)
- [ ] 119. Confidence scorer (name similarity, birth year, nationality)
- [ ] 120. AuthorityMatcher service
- [ ] 121. Matching logic (VIAF-first, Wikidata fallback)
- [ ] 122. Best match selection (>= 0.8 → auto-link, else review queue)
- [ ] 123. Scorer tests
- [ ] 124. Matcher tests

### Author Resolver
- [ ] 125. AuthorResolver service
- [ ] 126. Local search (existing authors by name variant)
- [ ] 127. Create-or-link
- [ ] 128. Store name variants
- [ ] 129. Route low-confidence to review_queue
- [ ] 130. Review queue admin page

---

## Phase 7: Search & Indexing

- [ ] 136. Install Elasticsearch PHP client
- [ ] 137. Create works index mapping
- [ ] 138. Create editions index mapping
- [ ] 139. Create authors index mapping
- [ ] 140. Configure multilingual analyzers
- [ ] 141. Create IndexWorkJob
- [ ] 142. Create IndexEditionJob
- [ ] 143. Create IndexAuthorJob
- [ ] 144. Subscribe to domain events
- [ ] 145. Implement bulk indexing
- [ ] 146. Create search:reindex command
- [ ] 147. Create CatalogSearchService
- [ ] 148. Implement multi-field search
- [ ] 149. Implement faceted filtering
- [ ] 150. Add search to Filament
- [ ] 151. Test search accuracy
- [ ] 152. Test search performance
- [ ] 153. Configure index aliases
- [ ] 154. Implement zero-downtime reindexing
- [ ] 155. Document search API

---

## Phase 8: Public API (Steps 156-175)

- [ ] 156. Create WorkController
- [ ] 157. Create EditionController
- [ ] 158. Create AuthorController
- [ ] 159. Create SearchController
- [ ] 160. Create WorkResource (JSON)
- [ ] 161. Create EditionResource (JSON)
- [ ] 162. Create AuthorResource (JSON)
- [ ] 163. Implement pagination
- [ ] 164. Implement sparse fieldsets
- [ ] 165. Implement include parameter
- [ ] 166. Implement filtering
- [ ] 167. Implement sorting
- [ ] 168. Create JSON-LD output for works
- [ ] 169. Create JSON-LD output for authors
- [ ] 170. Add content negotiation
- [ ] 171. Install Laravel Sanctum
- [ ] 172. Implement API token auth
- [ ] 173. Implement rate limiting
- [ ] 174. Generate OpenAPI spec
- [ ] 175. Create API documentation

---

## Phase 9: Testing & Deployment (Steps 176-190)

- [ ] 176. Unit tests for Support classes
- [ ] 177. Unit tests for Services
- [ ] 178. Unit tests for Clients (mocked)
- [ ] 179. Feature tests for API endpoints
- [ ] 180. Feature tests for ingestion pipeline
- [ ] 181. Feature tests for search
- [ ] 182. Achieve 80%+ coverage
- [ ] 183. Create Docker Compose (dev)
- [ ] 184. Create Dockerfile (production)
- [ ] 185. Configure PostgreSQL container
- [ ] 186. Configure Redis container
- [ ] 187. Configure Elasticsearch container
- [ ] 188. Configure queue workers
- [ ] 189. Create deployment scripts
- [ ] 190. Document deployment process

---

## Phase 10: Federation (Steps 191-200)

- [ ] 191. Design federation protocol
- [ ] 192. Implement OAI-PMH endpoint
- [ ] 193. Create shared identifier scheme
- [ ] 194. Implement node registration
- [ ] 195. Implement record harvesting
- [ ] 196. Implement cross-node deduplication
- [ ] 197. Create Wikidata contribution workflow
- [ ] 198. Document federation protocol
- [ ] 199. Pilot with partner institution
- [ ] 200. Refine based on pilot feedback

---

## Notes

### Phase 1 Completion Notes
- Used PostgreSQL for UUID support
- All models use HasUuid trait
- FRBR hierarchy verified: Edition → Expression → Work → Authors
- Test data created through tinker

### Decisions Made
- Standard Laravel structure (not domain-driven directories)
- Filament moved to Phase 2 (early admin visibility)
- Generic raw_ingestion_records table (not source-specific)
- Thema code as primary key (not UUID)

### Blockers
- BIBLIONET API credentials needed for Phase 4
- Thema Greek translations may need manual work

### Resources
- BIBLIONET: https://elivip.gr/en/biblionet
- Thema: https://www.editeur.org/151/Thema/
- VIAF: https://viaf.org
- Wikidata: https://www.wikidata.org