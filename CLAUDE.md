cat > CLAUDE.md << 'EOF'
# EU Bibliographic Catalog

## What This Is

An EU-first, federated bibliographic catalog implementing the FRBR data model (Work → Expression → Edition). Primary data source is BIBLIONET API for Greek books. The critical challenge is author deduplication through external authority control (VIAF, Wikidata, ISNI).

## Stack

- Laravel 13
- PostgreSQL
- Redis (queues, caching - not yet configured)
- Elasticsearch (search - not yet configured)
- External APIs: BIBLIONET, VIAF, Wikidata

## Project Structure
```
app/
├── Models/          # Eloquent models
├── Services/        # Business logic
├── Clients/         # External API clients
├── DTOs/            # Data transfer objects
├── Support/         # Utilities (text normalization, etc.)
├── Events/          # Domain events
├── Jobs/            # Queue jobs
├── Http/
│   ├── Controllers/Api/
│   └── Resources/   # JSON serialization
└── Console/Commands/
```

## Database Schema

FRBR hierarchy:
- **works** - Abstract intellectual creation
- **expressions** - Specific realization (translation, adaptation)
- **editions** - Physical/digital manifestation (ISBN, format)

Supporting tables:
- **authors** / **author_name_variants** - With authority IDs (VIAF, ISNI, Wikidata)
- **publishers** / **publisher_name_variants**
- **thema_subjects** / **work_subjects** - Hierarchical classification
- **provenance** / **edition_provenance_log** - Data lineage tracking
- **review_queue** - Items needing human review
- **raw_ingestion_records** - Staging for incoming data

## Key Commands
```bash
php artisan migrate              # Run migrations
php artisan tinker               # Interactive REPL
php artisan test                 # Run tests
php artisan db:table --counts    # Show table stats
```

## The Hard Problem

Author deduplication. Same author appears as:
- Νίκος Καζαντζάκης
- Nikos Kazantzakis
- Kazantzakis, N.

We resolve via VIAF/Wikidata authority matching with confidence scoring.
Records with authority_confidence < 0.8 go to review queue.

## Current Status

Phase 1 complete: Foundation and schema.
See docs/progress.md for detailed status.
EOF
