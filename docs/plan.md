# EU Bibliographic Catalog - Implementation Plan

## Phase Overview

| Phase | Name | Steps | Description |
|-------|------|-------|-------------|
| 1 | Foundation | 1-28 | ✓ Laravel, PostgreSQL, schema, models |
| 2 | Filament Admin | 29-55 | Admin panel, CRUD for all entities |
| 3 | Thema Seeding | 56-65 | Import subject classification |
| 4 | BIBLIONET Client | 66-80 | API client, fetching, staging |
| 5 | Authority Matching | 81-110 | VIAF, Wikidata, confidence scoring |
| 6 | Pipeline Services | 111-135 | Resolvers, ingestion pipeline |
| 7 | Search & Indexing | 136-155 | Elasticsearch integration |
| 8 | Public API | 156-175 | REST endpoints, JSON-LD |
| 9 | Testing & Deployment | 176-190 | Tests, Docker, production |
| 10 | Federation | 191-200 | OAI-PMH, partner integration |

---

## Phase 1: Foundation ✓ COMPLETE

Steps 1-28: Laravel project, PostgreSQL, migrations, Eloquent models.

See docs/progress.md for completed checklist.

---

## Phase 2: Filament Admin Panel

### Why Now?

Building the admin panel before automation gives us:
- Visual verification of data and relationships
- Manual record creation for testing
- Review queue ready when ingestion starts
- Stakeholder demos without building separate UI

### Installation (Steps 29-32)

**Step 29: Install Filament**
```bash
composer require filament/filament
php artisan filament:install --panels
```

**Step 30: Create admin user**
```bash
php artisan make:filament-user
```

**Step 31: Configure panel**
- Set path to /admin
- Configure branding (EU Bibliographic Catalog)
- Set up navigation groups

**Step 32: Update User model**
```php
use Filament\Models\Contracts\FilamentUser;

class User extends Authenticatable implements FilamentUser
{
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin; // Add is_admin column or use gate
    }
}
```

### Entity Resources (Steps 33-45)

**Step 33: AuthorResource**
```bash
php artisan make:filament-resource Author --generate
```
- Table columns: display_name, sort_name, nationality, authority_confidence, needs_review
- Filters: needs_review, has VIAF, nationality
- Form: all fields with proper validation
- Relation manager: AuthorNameVariants (inline create/edit)

**Step 34: AuthorNameVariantResource**
- Usually managed inline from AuthorResource
- Standalone for bulk operations if needed

**Step 35: PublisherResource**
```bash
php artisan make:filament-resource Publisher --generate
```
- Table columns: name, country, edition count
- Relation manager: PublisherNameVariants

**Step 36: WorkResource**
```bash
php artisan make:filament-resource Work --generate
```
- Table columns: original_title, original_language, first_publication_year, author names
- Filters: language, year range, has Wikidata ID
- Form: all fields
- Relation managers:
  - Authors (with pivot fields: role, position)
  - Expressions
  - Subjects (Thema codes)

**Step 37: ExpressionResource**
```bash
php artisan make:filament-resource Expression --generate
```
- Table columns: title, language, expression_type, work title, edition count
- Filters: language, expression_type
- Relation managers:
  - Contributors (with role)
  - Editions

**Step 38: EditionResource**
```bash
php artisan make:filament-resource Edition --generate
```
- Table columns: isbn13, title (from expression), publisher, publication_year, format
- Filters: format, year range, publisher, source_system
- Form: all fields with ISBN validation
- Read-only: provenance log

**Step 39: PublisherNameVariantResource**
- Inline from PublisherResource

**Step 40: ThemaSubjectResource**
```bash
php artisan make:filament-resource ThemaSubject --generate
```
- Tree view showing hierarchy
- Table columns: code, heading_en, heading_el, level, children count
- Form: code, parent (select), headings

**Step 41: ProvenanceResource**
```bash
php artisan make:filament-resource Provenance --generate
```
- Table columns: source_system, batch_id, timestamps, record counts
- Read-only (ingestion creates these)
- Detail view shows linked raw records and edition logs

**Step 42: EditionProvenanceLogResource**
- Read-only, accessed from Edition detail view
- Shows history of changes

**Step 43: RawIngestionRecordResource**
```bash
php artisan make:filament-resource RawIngestionRecord --generate
```
- Table columns: source_system, source_record_id, status, fetched_at, processed_at
- Filters: status, source_system
- Actions: retry failed, view payload (JSON viewer)

**Step 44: ReviewQueueResource**
```bash
php artisan make:filament-resource ReviewQueue --generate
```
- Table columns: entity_type, issue_type, status, created_at
- Filters: status, entity_type, issue_type
- Custom actions: resolve, ignore
- Link to related entity

**Step 45: Verify all resources work**
- Create test records manually
- Verify relationships display correctly
- Test inline relation managers

### Dashboard & Custom Pages (Steps 46-52)

**Step 46: Dashboard widgets**
- Stats overview: total works, editions, authors, publishers
- Records needing review count
- Recent ingestion batches
- Authority coverage (% with VIAF, Wikidata)

**Step 47: Review Queue custom page**
```bash
php artisan make:filament-page ReviewQueueManager
```
- Split view: list on left, detail on right
- Quick actions: approve match, reject, merge entities
- Context display: show matched candidates, confidence scores

**Step 48: Author merge page**
```bash
php artisan make:filament-page AuthorMerge
```
- Select two authors to compare
- Show all works, expressions, name variants
- Preview merge result
- Execute merge with audit log

**Step 49: Work merge page**
- Similar to author merge
- Handle expression reassignment

**Step 50: Ingestion monitor page**
- Real-time view of current batch progress
- Error log viewer
- Retry controls

**Step 51: Quality report page**
- Authority coverage stats
- Duplicate detection results
- Data completeness metrics

**Step 52: Test all custom pages**

### Polish & Configuration (Steps 53-55)

**Step 53: Navigation structure**
```
Catalog
├── Works
├── Expressions
├── Editions
├── Authors
└── Publishers

Classification
└── Thema Subjects

Ingestion
├── Raw Records
├── Provenance
└── Ingestion Monitor

Quality
├── Review Queue
├── Author Merge
├── Work Merge
└── Quality Reports

Settings
└── Users
```

**Step 54: Global search**
- Configure searchable resources
- Works by title
- Authors by name
- Editions by ISBN
- Publishers by name

**Step 55: Final testing**
- Create sample data through admin
- Verify all CRUD operations
- Test relationship management
- Verify review queue workflow

---

## Phase 3: Thema Seeding

### Source & Download (Steps 56-58)

**Step 56: Download Thema from EDItEUR**
- URL: https://www.editeur.org/151/Thema/
- Get latest version (XML or JSON)
- Store in storage/app/thema/

**Step 57: Analyze structure**
```xml
<Code>
    <CodeValue>FBA</CodeValue>
    <CodeDescription>Modern and contemporary fiction</CodeDescription>
    <CodeParent>FA</CodeParent>
</Code>
```

**Step 58: Plan Greek headings**
- Check if Greek translations available from EDItEUR
- If not, plan manual translation or leave nullable

### Seeder Implementation (Steps 59-65)

**Step 59: Create seeder class**
```bash
php artisan make:seeder ThemaSubjectSeeder
```

**Step 60: Parse XML/JSON**
- Read source file
- Extract code, parent, headings
- Calculate level from parent chain

**Step 61: Handle hierarchy order**
- Sort by level (parents first)
- Or use upsert with deferred foreign key checks

**Step 62: Implement seeder**
```php
public function run(): void
{
    $thema = json_decode(Storage::get('thema/thema-v1.5.json'), true);
    
    // Sort by level
    usort($thema, fn($a, $b) => $a['level'] <=> $b['level']);
    
    foreach ($thema as $code) {
        ThemaSubject::create([
            'code' => $code['code'],
            'parent_code' => $code['parent'] ?: null,
            'heading_en' => $code['heading_en'],
            'heading_el' => $code['heading_el'] ?? null,
            'level' => $code['level'],
        ]);
    }
}
```

**Step 63: Run seeder**
```bash
php artisan db:seed --class=ThemaSubjectSeeder
```

**Step 64: Verify in admin**
- Check hierarchy displays correctly
- Test parent/child navigation
- Verify code count (~2,500)

**Step 65: Create command for updates**
```bash
php artisan make:command ThemaUpdate
```
- Re-download and sync changes
- Handle new codes, modified headings

---

## Phase 4: BIBLIONET Client

### Setup (Steps 66-70)

**Step 66: Register for API access**
- Contact BIBLIONET / elivip.gr
- Obtain client_id and client_secret
- Document rate limits and terms

**Step 67: Create configuration**
```php
// config/services.php
'biblionet' => [
    'base_url' => env('BIBLIONET_BASE_URL'),
    'client_id' => env('BIBLIONET_CLIENT_ID'),
    'client_secret' => env('BIBLIONET_CLIENT_SECRET'),
    'rate_limit' => env('BIBLIONET_RATE_LIMIT', 1),
],
```

**Step 68: Create client interface**
```php
// app/Clients/BiblionetClientInterface.php
interface BiblionetClientInterface
{
    public function authenticate(): void;
    public function fetchBooks(int $page = 1, int $perPage = 100): array;
    public function fetchBooksSince(DateTimeInterface $since): Generator;
    public function fetchBook(string $id): array;
}
```

**Step 69: Implement client**
```bash
touch app/Clients/BiblionetClient.php
```
- OAuth2 authentication
- Token caching
- Rate limiting (1 req/sec)
- Retry logic with backoff
- Response validation

**Step 70: Create exceptions**
```php
// app/Clients/Exceptions/
BiblionetAuthException.php
BiblionetRateLimitException.php
BiblionetApiException.php
```

### Fetching & Staging (Steps 71-80)

**Step 71: Create fetch command**
```bash
php artisan make:command BiblionetFetch
```
- Options: --full, --since=DATE, --limit=N
- Progress bar for large fetches

**Step 72: Implement staging logic**
```php
// For each record from API:
RawIngestionRecord::updateOrCreate(
    [
        'source_system' => 'biblionet',
        'source_record_id' => $record['id'],
    ],
    [
        'payload' => $record,
        'status' => 'pending',
        'fetched_at' => now(),
    ]
);
```

**Step 73: Create provenance record per batch**
```php
$provenance = Provenance::create([
    'source_system' => 'biblionet',
    'batch_id' => 'biblionet-' . now()->format('Y-m-d-His'),
    'ingestion_started_at' => now(),
]);
```

**Step 74: Link raw records to provenance**

**Step 75: Update provenance stats on completion**
```php
$provenance->update([
    'ingestion_completed_at' => now(),
    'records_processed' => $count,
]);
```

**Step 76: Test with small fetch**
```bash
php artisan biblionet:fetch --limit=10
```

**Step 77: Verify in admin**
- Check RawIngestionRecords appear
- Verify JSON payload is viewable
- Check Provenance record created

**Step 78: Implement incremental sync**
- Store last sync timestamp
- Use modified_since parameter

**Step 79: Create scheduled task**
```php
// app/Console/Kernel.php
$schedule->command('biblionet:fetch --since=yesterday')
    ->dailyAt('03:00');
```

**Step 80: Test full fetch with pagination**
- Handle API timeouts
- Resume from failure
- Log progress

---

## Phase 5: Authority Matching

### Support Utilities (Steps 81-85)

**Step 81: Greek text normalizer**
```bash
touch app/Support/GreekTextNormalizer.php
```
```php
class GreekTextNormalizer
{
    public static function normalize(string $text): string
    {
        // Remove diacritics
        $text = Normalizer::normalize($text, Normalizer::FORM_D);
        $text = preg_replace('/\p{Mn}/u', '', $text);
        
        // Lowercase and trim
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $text)));
    }
    
    public static function transliterate(string $text): string
    {
        $map = [
            'α' => 'a', 'β' => 'v', 'γ' => 'g', 'δ' => 'd', 'ε' => 'e',
            'ζ' => 'z', 'η' => 'i', 'θ' => 'th', 'ι' => 'i', 'κ' => 'k',
            'λ' => 'l', 'μ' => 'm', 'ν' => 'n', 'ξ' => 'x', 'ο' => 'o',
            'π' => 'p', 'ρ' => 'r', 'σ' => 's', 'ς' => 's', 'τ' => 't',
            'υ' => 'y', 'φ' => 'f', 'χ' => 'ch', 'ψ' => 'ps', 'ω' => 'o'
        ];
        
        return strtr(self::normalize($text), $map);
    }
}
```

**Step 82: ISBN validator**
```bash
touch app/Support/IsbnValidator.php
```
- isValidIsbn10()
- isValidIsbn13()
- isbn10to13()
- normalize() - remove hyphens, validate

**Step 83: Unit tests for normalizer**
```bash
php artisan make:test Support/GreekTextNormalizerTest --unit
```

**Step 84: Unit tests for ISBN validator**
```bash
php artisan make:test Support/IsbnValidatorTest --unit
```

**Step 85: Verify tests pass**
```bash
php artisan test --filter=Support
```

### VIAF Client (Steps 86-92)

**Step 86: Create VIAF client**
```bash
touch app/Clients/ViafClient.php
```

**Step 87: Implement search by name**
```php
public function searchByName(string $name): array
{
    $response = Http::get('https://viaf.org/viaf/search', [
        'query' => 'local.personalNames all "' . $name . '"',
        'sortKeys' => 'holdingscount',
        'recordSchema' => 'BriefVIAF',
        'httpAccept' => 'application/json',
    ]);
    
    return $this->parseResponse($response->json());
}
```

**Step 88: Implement search with dates**
```php
public function searchByNameAndDates(
    string $name,
    ?int $birthYear = null,
    ?int $deathYear = null
): array
```

**Step 89: Parse VIAF response**
- Extract VIAF ID
- Extract linked authorities (LC, BNF, NLG)
- Extract dates
- Extract name variants

**Step 90: Implement caching**
```php
public function searchByName(string $name): array
{
    $cacheKey = 'viaf:search:' . md5($name);
    
    return Cache::remember($cacheKey, now()->addHours(24), function () use ($name) {
        return $this->doSearch($name);
    });
}
```

**Step 91: Handle errors gracefully**
- Timeout handling
- Rate limit detection
- Empty results

**Step 92: Test with known authors**
```php
$client = app(ViafClient::class);
$results = $client->searchByName('Kazantzakis');
// Should return VIAF ID: 17220308
```

### Wikidata Client (Steps 93-98)

**Step 93: Create Wikidata client**
```bash
touch app/Clients/WikidataClient.php
```

**Step 94: Implement entity search**
```php
public function searchPerson(string $name): array
{
    $response = Http::get('https://www.wikidata.org/w/api.php', [
        'action' => 'wbsearchentities',
        'search' => $name,
        'language' => 'en',
        'type' => 'item',
        'format' => 'json',
    ]);
    
    return $this->parseResults($response->json());
}
```

**Step 95: Implement SPARQL queries**
```php
public function searchAuthorSparql(string $name): array
{
    $query = <<<SPARQL
    SELECT ?item ?itemLabel ?viaf ?isni ?birthYear WHERE {
        ?item wdt:P31 wd:Q5 .
        ?item wdt:P106 wd:Q36180 .
        ?item rdfs:label ?label .
        FILTER(CONTAINS(LCASE(?label), "{$name}"))
        OPTIONAL { ?item wdt:P214 ?viaf }
        OPTIONAL { ?item wdt:P213 ?isni }
        OPTIONAL { ?item wdt:P569 ?birth }
        BIND(YEAR(?birth) AS ?birthYear)
        SERVICE wikibase:label { bd:serviceParam wikibase:language "en,el" }
    }
    LIMIT 10
    SPARQL;
    
    return $this->executeSparql($query);
}
```

**Step 96: Extract identifiers**
- Wikidata Q-ID
- VIAF (P214)
- ISNI (P213)
- Birth/death dates

**Step 97: Implement caching**

**Step 98: Test with known authors**

### Authority Matcher (Steps 99-105)

**Step 99: Create DTO for match results**
```bash
touch app/DTOs/AuthorityMatch.php
```
```php
readonly class AuthorityMatch
{
    public function __construct(
        public ?string $viafId,
        public ?string $isni,
        public ?string $wikidataId,
        public string $matchedName,
        public float $confidence,
        public string $source,  // 'viaf', 'wikidata', 'local'
        public array $nameVariants = [],
    ) {}
}
```

**Step 100: Create confidence scorer**
```bash
touch app/Services/ConfidenceScorer.php
```
```php
class ConfidenceScorer
{
    public function score(
        string $inputName,
        string $matchedName,
        ?int $inputBirthYear,
        ?int $matchedBirthYear,
        ?string $inputNationality,
        ?string $matchedNationality,
        int $additionalSourceCount
    ): float {
        $score = 0.0;
        
        // Name matching
        $normalizedInput = GreekTextNormalizer::normalize($inputName);
        $normalizedMatch = GreekTextNormalizer::normalize($matchedName);
        
        if ($normalizedInput === $normalizedMatch) {
            $score = 0.9;
        } elseif ($this->isTransliteration($inputName, $matchedName)) {
            $score = 0.7;
        } else {
            similar_text($normalizedInput, $normalizedMatch, $percent);
            $score = ($percent / 100) * 0.6;
        }
        
        // Bonuses
        if ($inputBirthYear && $matchedBirthYear && $inputBirthYear === $matchedBirthYear) {
            $score += 0.1;
        }
        
        if ($inputNationality && $matchedNationality && $inputNationality === $matchedNationality) {
            $score += 0.05;
        }
        
        if ($additionalSourceCount >= 2) {
            $score += 0.1;
        }
        
        return min(1.0, $score);
    }
}
```

**Step 101: Create authority matcher service**
```bash
touch app/Services/AuthorityMatcher.php
```

**Step 102: Implement matching logic**
```php
class AuthorityMatcher
{
    public function __construct(
        private ViafClient $viaf,
        private WikidataClient $wikidata,
        private ConfidenceScorer $scorer,
    ) {}
    
    public function findMatch(
        string $name,
        ?int $birthYear = null,
        ?string $nationality = null
    ): ?AuthorityMatch {
        // 1. Try VIAF first
        $viafResults = $this->viaf->searchByName($name);
        if ($best = $this->findBestViafMatch($viafResults, $name, $birthYear)) {
            return $best;
        }
        
        // 2. Try Wikidata
        $wdResults = $this->wikidata->searchAuthorSparql($name);
        if ($best = $this->findBestWikidataMatch($wdResults, $name, $birthYear)) {
            return $best;
        }
        
        return null;
    }
}
```

**Step 103: Implement best match selection**

**Step 104: Unit tests for confidence scorer**

**Step 105: Integration tests for authority matcher (mocked APIs)**

### Author Resolver (Steps 106-110)

**Step 106: Create author resolver**
```bash
touch app/Services/AuthorResolver.php
```

**Step 107: Implement local search first**
```php
public function resolve(
    string $name,
    ?int $birthYear = null,
    ?string $nationality = null
): Author {
    // 1. Check local name variants
    $variant = AuthorNameVariant::where('name', $name)->first();
    if ($variant) {
        return $variant->author;
    }
    
    // 2. Check by authority IDs if available
    // ...
    
    // 3. Try authority matching
    $match = $this->authorityMatcher->findMatch($name, $birthYear, $nationality);
    
    // 4. Create or link author
    // ...
}
```

**Step 108: Implement create-or-link logic**
- If match found with existing VIAF: return existing author
- If match found but new: create author with authority IDs
- If no match: create author, flag for review

**Step 109: Store name variant after resolution**
```php
$author->nameVariants()->create([
    'name' => $name,
    'script' => $this->detectScript($name),
    'source' => $source,
]);
```

**Step 110: Create review queue entries for low confidence**
```php
if ($match && $match->confidence < 0.8) {
    ReviewQueue::create([
        'entity_type' => 'author',
        'entity_id' => $author->id,
        'issue_type' => 'low_confidence_match',
        'details' => [
            'input_name' => $name,
            'matched_name' => $match->matchedName,
            'confidence' => $match->confidence,
            'viaf_id' => $match->viafId,
        ],
    ]);
    
    $author->update(['needs_review' => true]);
}
```

---

## Phase 6: Pipeline Services

### Parsers & DTOs (Steps 111-118)

**Step 111: Create BiblionetRecordDTO**
```bash
touch app/DTOs/BiblionetRecordDTO.php
```
```php
readonly class BiblionetRecordDTO
{
    public function __construct(
        public string $biblionetId,
        public ?string $isbn13,
        public ?string $isbn10,
        public string $title,
        public ?string $originalTitle,
        public array $contributors,  // [{name, role}, ...]
        public ?string $publisherName,
        public ?string $publisherId,
        public ?string $publicationDate,
        public ?int $pages,
        public array $themaCodes,
        public ?string $description,
        public ?string $language,
    ) {}
}
```

**Step 112: Create BiblionetParser**
```bash
touch app/Services/BiblionetParser.php
```

**Step 113: Implement parsing logic**
- Map BIBLIONET JSON fields to DTO
- Normalize ISBN
- Parse date formats
- Extract contributors with roles

**Step 114: Handle parsing errors**
- Missing required fields
- Invalid ISBN
- Unknown format values

**Step 115: Unit tests for parser**

**Step 116: Create PublisherResolver**
```bash
touch app/Services/PublisherResolver.php
```

**Step 117: Implement publisher matching**
- Search by name variants
- Fuzzy matching for typos
- Create if not found
- Store variant

**Step 118: Unit tests for publisher resolver**

### Work & Expression Resolution (Steps 119-128)

**Step 119: Create WorkResolver**
```bash
touch app/Services/WorkResolver.php
```

**Step 120: Implement work matching**
```php
public function resolve(
    string $title,
    string $language,
    array $authorIds,
    ?string $originalTitle = null
): Work {
    // 1. Search by authors + normalized title
    $work = $this->findByAuthorsAndTitle($authorIds, $title);
    if ($work) {
        return $work;
    }
    
    // 2. If this is a translation, try to find original work
    if ($originalTitle && $language !== $originalLanguage) {
        $work = $this->findOriginalWork($originalTitle, $authorIds);
        if ($work) {
            return $work;
        }
    }
    
    // 3. Create new work
    return $this->createWork($title, $language, $authorIds);
}
```

**Step 121: Implement author attachment with roles**

**Step 122: Implement subject assignment**

**Step 123: Unit tests for work resolver**

**Step 124: Create ExpressionResolver**
```bash
touch app/Services/ExpressionResolver.php
```

**Step 125: Implement expression matching**
```php
public function resolve(
    Work $work,
    string $language,
    string $title,
    string $expressionType,
    array $contributorIds = []
): Expression {
    $expression = Expression::where('work_id', $work->id)
        ->where('language', $language)
        ->where('expression_type', $expressionType)
        ->first();
    
    if ($expression) {
        return $expression;
    }
    
    return Expression::create([
        'work_id' => $work->id,
        'language' => $language,
        'title' => $title,
        'expression_type' => $expressionType,
    ]);
}
```

**Step 126: Attach contributors with roles**

**Step 127: Handle translator detection**
- If language differs from work's original_language
- expression_type = 'translation'
- Contributor with role = 'translator'

**Step 128: Unit tests for expression resolver**

### Edition Creation (Steps 129-135)

**Step 129: Create EditionCreator**
```bash
touch app/Services/EditionCreator.php
```

**Step 130: Implement ISBN uniqueness check**
```php
public function create(
    Expression $expression,
    Publisher $publisher,
    BiblionetRecordDTO $dto,
    Provenance $provenance
): Edition {
    // Check ISBN uniqueness
    if ($dto->isbn13) {
        $existing = Edition::where('isbn13', $dto->isbn13)->first();
        if ($existing) {
            return $this->updateExisting($existing, $dto, $provenance);
        }
    }
    
    // Create new edition
    return $this->createNew($expression, $publisher, $dto, $provenance);
}
```

**Step 131: Implement composite key check for ISBN-less**

**Step 132: Create provenance log entry**
```php
EditionProvenanceLog::create([
    'edition_id' => $edition->id,
    'provenance_id' => $provenance->id,
    'action' => $isNew ? 'created' : 'updated',
    'previous_data' => $isNew ? null : $previousData,
]);
```

**Step 133: Emit events (for future Elasticsearch)**
```php
event(new EditionCreated($edition));
// or
event(new EditionUpdated($edition, $changes));
```

**Step 134: Full pipeline integration**
```php
class ProcessRawRecordJob implements ShouldQueue
{
    public function handle(
        BiblionetParser $parser,
        AuthorResolver $authorResolver,
        PublisherResolver $publisherResolver,
        WorkResolver $workResolver,
        ExpressionResolver $expressionResolver,
        EditionCreator $editionCreator,
    ): void {
        $record = $this->rawRecord;
        $record->markProcessing();
        
        try {
            $dto = $parser->parse($record->payload);
            
            // Resolve all entities
            $authors = array_map(
                fn($c) => $authorResolver->resolve($c['name']),
                array_filter($dto->contributors, fn($c) => $c['role'] === 'author')
            );
            
            $publisher = $publisherResolver->resolve($dto->publisherName);
            
            $work = $workResolver->resolve(
                $dto->originalTitle ?? $dto->title,
                $dto->language ?? 'ell',
                collect($authors)->pluck('id')->toArray()
            );
            
            $expression = $expressionResolver->resolve(
                $work,
                $dto->language ?? 'ell',
                $dto->title,
                $dto->originalTitle ? 'translation' : 'original'
            );
            
            $edition = $editionCreator->create(
                $expression,
                $publisher,
                $dto,
                $record->provenance
            );
            
            $record->markCompleted($edition);
            
        } catch (Exception $e) {
            $record->markFailed($e->getMessage());
            throw $e;
        }
    }
}
```

**Step 135: Integration test for full pipeline**

---

## Phase 7: Search & Indexing

Steps 136-155: Elasticsearch setup, index mappings, indexing jobs, search service.

(Details to be expanded when Phase 6 is complete)

---

## Phase 8: Public API

Steps 156-175: REST endpoints, JSON resources, JSON-LD, authentication, rate limiting.

(Details to be expanded when Phase 7 is complete)

---

## Phase 9: Testing & Deployment

Steps 176-190: Comprehensive tests, Docker, production configuration.

(Details to be expanded when Phase 8 is complete)

---

## Phase 10: Federation

Steps 191-200: OAI-PMH, federation protocol, partner onboarding.

(Details to be expanded when Phase 9 is complete)

---

## Appendix: API Structures

### BIBLIONET Expected Response
```json
{
    "id": "12345",
    "isbn13": "9789600368567",
    "isbn10": "9600368562",
    "title": "Ο ήλιος του θανάτου",
    "original_title": "Blood Meridian",
    "contributors": [
        {"name": "Cormac McCarthy", "role": "author"},
        {"name": "Μιχάλης Μακρόπουλος", "role": "translator"}
    ],
    "publisher": {
        "id": "89",
        "name": "Εκδόσεις Καστανιώτη"
    },
    "publication_date": "2010-03-15",
    "pages": 432,
    "thema_codes": ["FBA", "1KBB"],
    "description": "..."
}
```

### VIAF Search Response
```json
{
    "searchRetrieveResponse": {
        "records": [
            {
                "record": {
                    "recordData": {
                        "viafID": "17220308",
                        "nameType": "Personal",
                        "mainHeadings": {
                            "data": [
                                {"text": "Kazantzakis, Nikos, 1883-1957"}
                            ]
                        }
                    }
                }
            }
        ]
    }
}
```

### Wikidata SPARQL Response
```json
{
    "results": {
        "bindings": [
            {
                "item": {"value": "http://www.wikidata.org/entity/Q185085"},
                "itemLabel": {"value": "Nikos Kazantzakis"},
                "viaf": {"value": "17220308"},
                "isni": {"value": "0000000121212016"},
                "birthYear": {"value": "1883"}
            }
        ]
    }
}
```