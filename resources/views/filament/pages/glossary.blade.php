<x-filament-panels::page>
    <div class="space-y-12 max-w-4xl">

        {{-- ── FRBR Model ─────────────────────────────────────────────── --}}
        <section>
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">The FRBR Data Model</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Every record in this catalog fits into one of three levels of the
                    <strong class="text-gray-700 dark:text-gray-300">Functional Requirements for Bibliographic Records</strong>
                    hierarchy. Understanding these three levels is the key to understanding how everything connects.
                </p>
            </div>

            {{-- Three-card hierarchy with connecting arrows --}}
            <div class="flex flex-col sm:flex-row items-stretch gap-0">

                {{-- Work --}}
                <div class="flex-1 rounded-l-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 p-5">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-emerald-600 text-white text-sm font-bold">1</span>
                        <span class="text-xs font-bold uppercase tracking-widest text-emerald-700 dark:text-emerald-400">Work</span>
                    </div>
                    <div class="font-semibold text-gray-900 dark:text-white mb-2">Abstract creation</div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        The intellectual or artistic concept — independent of language, format, or publisher.
                        <em>Crime and Punishment</em> is one Work, regardless of how many translations or editions exist.
                    </p>
                    <div class="mt-3 text-xs text-emerald-700 dark:text-emerald-400 font-mono bg-emerald-100 dark:bg-emerald-900/50 rounded px-2 py-1 inline-block">
                        original_title, oclc_work_id
                    </div>
                </div>

                {{-- Arrow --}}
                <div class="hidden sm:flex items-center justify-center w-8 shrink-0 bg-gray-100 dark:bg-gray-800 border-y border-gray-200 dark:border-gray-700">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>
                <div class="flex sm:hidden items-center justify-center h-8 bg-gray-100 dark:bg-gray-800 border-x border-gray-200 dark:border-gray-700">
                    <svg class="w-4 h-4 text-gray-400 rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>

                {{-- Expression --}}
                <div class="flex-1 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 p-5">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-600 text-white text-sm font-bold">2</span>
                        <span class="text-xs font-bold uppercase tracking-widest text-blue-700 dark:text-blue-400">Expression</span>
                    </div>
                    <div class="font-semibold text-gray-900 dark:text-white mb-2">Specific realisation</div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        A particular version of a Work: the Greek translation by Αριστέα Παρίση is one Expression;
                        a French translation by another hand is a different Expression of the same Work.
                    </p>
                    <div class="mt-3 text-xs text-blue-700 dark:text-blue-400 font-mono bg-blue-100 dark:bg-blue-900/50 rounded px-2 py-1 inline-block">
                        language, title, contributors
                    </div>
                </div>

                {{-- Arrow --}}
                <div class="hidden sm:flex items-center justify-center w-8 shrink-0 bg-gray-100 dark:bg-gray-800 border-y border-gray-200 dark:border-gray-700">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>
                <div class="flex sm:hidden items-center justify-center h-8 bg-gray-100 dark:bg-gray-800 border-x border-gray-200 dark:border-gray-700">
                    <svg class="w-4 h-4 text-gray-400 rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>

                {{-- Edition --}}
                <div class="flex-1 rounded-r-2xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 p-5">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-500 text-white text-sm font-bold">3</span>
                        <span class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Edition</span>
                    </div>
                    <div class="font-semibold text-gray-900 dark:text-white mb-2">Physical manifestation</div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        A concrete object with an ISBN: the 2010 Καστανιώτης paperback (9789600368567).
                        One Expression can have many Editions — reprints, formats, publishers.
                    </p>
                    <div class="mt-3 text-xs text-gray-600 dark:text-gray-400 font-mono bg-gray-100 dark:bg-gray-700/50 rounded px-2 py-1 inline-block">
                        isbn, format, publisher, price
                    </div>
                </div>
            </div>
        </section>

        {{-- ── Authority Control ───────────────────────────────────────── --}}
        <section>
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Authority Control</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    The process of assigning a single, canonical identity to an author across all name forms and data sources.
                    Without authority control, <em>"Nikos Kazantzakis"</em>, <em>"Ν. Καζαντζάκης"</em>,
                    and <em>"Kazantzakis, N."</em> would be treated as three different people.
                </p>
            </div>

            <div class="space-y-3">
                @foreach ([
                    [
                        'term'    => 'VIAF',
                        'badge'   => 'Primary',
                        'badge_color' => 'emerald',
                        'full'    => 'Virtual International Authority File',
                        'url'     => 'https://viaf.org',
                        'desc'    => 'A joint project of national libraries worldwide (Library of Congress, BnF, DNB, NLG, and dozens more). VIAF aggregates authority records from national catalogues into one cluster per person. This is our primary authority source — a VIAF ID uniquely identifies a person globally.',
                        'example' => 'viaf_id: 17220308',
                        'example_label' => 'Nikos Kazantzakis',
                    ],
                    [
                        'term'    => 'ISNI',
                        'badge'   => 'ISO 27729',
                        'badge_color' => 'blue',
                        'full'    => 'International Standard Name Identifier',
                        'url'     => 'https://isni.org',
                        'desc'    => 'A 16-digit code assigned to public identities of creators and contributors. More stable than VIAF but narrower coverage. Used alongside VIAF for cross-referencing.',
                        'example' => 'isni: 0000000121212016',
                        'example_label' => 'Nikos Kazantzakis',
                    ],
                    [
                        'term'    => 'Wikidata',
                        'badge'   => 'Q-IDs',
                        'badge_color' => 'violet',
                        'full'    => 'Wikidata (Wikimedia Foundation)',
                        'url'     => 'https://www.wikidata.org',
                        'desc'    => 'A free, collaborative knowledge graph. Every notable person gets a Q-ID. Wikidata links VIAF, ISNI, Wikipedia, and hundreds of other databases — making it an excellent hub for cross-referencing all authority IDs.',
                        'example' => 'wikidata_id: Q185085',
                        'example_label' => 'Nikos Kazantzakis',
                    ],
                    [
                        'term'    => 'OCLC Work ID',
                        'badge'   => 'Works',
                        'badge_color' => 'amber',
                        'full'    => 'Online Computer Library Center Work Identifier',
                        'url'     => 'https://www.worldcat.org',
                        'desc'    => "OCLC's identifier for an abstract Work in WorldCat — the world's largest library catalogue. Stored on the Work model to link our records to the global library network.",
                        'example' => 'oclc_work_id: 1234567',
                        'example_label' => 'Example Work',
                    ],
                ] as $item)
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="text-base font-bold text-gray-900 dark:text-white">{{ $item['term'] }}</span>
                                @php
                                    $colors = [
                                        'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
                                        'blue'    => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
                                        'violet'  => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-400',
                                        'amber'   => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
                                    ];
                                @endphp
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $colors[$item['badge_color']] }}">
                                    {{ $item['badge'] }}
                                </span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $item['full'] }}</span>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">{{ $item['desc'] }}</p>
                            <div class="flex items-center gap-2">
                                <code class="text-xs font-mono bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-2 py-1 rounded">{{ $item['example'] }}</code>
                                <span class="text-xs text-gray-400">— {{ $item['example_label'] }}</span>
                            </div>
                        </div>
                        <a href="{{ $item['url'] }}" target="_blank"
                           class="shrink-0 inline-flex items-center gap-1 text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline">
                            Visit
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </a>
                    </div>
                </div>
                @endforeach
            </div>
        </section>

        {{-- ── Subject Classification ──────────────────────────────────── --}}
        <section>
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Subject Classification</h2>
            </div>

            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <span class="text-base font-bold text-gray-900 dark:text-white">Thema</span>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400">EDItEUR</span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">Subject Category Scheme</span>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            The international subject classification standard for books. Hierarchical: top-level codes
                            break down into progressively specific sub-codes. ~2,500 codes total. Replaces BIC in most European markets.
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="flex items-center gap-1.5 text-xs">
                                <code class="font-mono bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-2 py-1 rounded">F</code>
                                <span class="text-gray-400">Fiction &amp; related items</span>
                            </div>
                            <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                            <div class="flex items-center gap-1.5 text-xs">
                                <code class="font-mono bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-2 py-1 rounded">FB</code>
                                <span class="text-gray-400">Modern &amp; contemporary fiction</span>
                            </div>
                            <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                            <div class="flex items-center gap-1.5 text-xs">
                                <code class="font-mono bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-2 py-1 rounded">FBA</code>
                                <span class="text-gray-400">Literary fiction</span>
                            </div>
                        </div>
                    </div>
                    <a href="https://www.editeur.org/151/Thema/" target="_blank"
                       class="shrink-0 inline-flex items-center gap-1 text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline">
                        Visit
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>
                </div>
            </div>
        </section>

        {{-- ── Confidence Scoring ──────────────────────────────────────── --}}
        <section>
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Confidence Scoring</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    When the system matches an incoming author name against VIAF or Wikidata,
                    it assigns a score from <strong class="text-gray-700 dark:text-gray-300">0.0</strong> (no match)
                    to <strong class="text-gray-700 dark:text-gray-300">1.0</strong> (certain match).
                    This score drives the automated vs. manual review decision.
                </p>
            </div>

            <div class="space-y-3">
                <div class="flex items-start gap-4 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/30 p-5">
                    <div class="shrink-0 flex flex-col items-center justify-center w-16 h-16 rounded-full bg-emerald-600 text-white font-bold">
                        <span class="text-xs">score</span>
                        <span class="text-lg leading-tight">≥0.8</span>
                    </div>
                    <div>
                        <div class="font-semibold text-emerald-800 dark:text-emerald-300 mb-1">High Confidence — Auto-accepted</div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Name, birth/death dates, and nationality all align with the authority record.
                            Authority IDs (VIAF, ISNI, Wikidata) are stored automatically. No human action needed.
                        </p>
                    </div>
                </div>

                <div class="flex items-start gap-4 rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/30 p-5">
                    <div class="shrink-0 flex flex-col items-center justify-center w-16 h-16 rounded-full bg-amber-500 text-white font-bold">
                        <span class="text-xs">score</span>
                        <span class="text-lg leading-tight">0.5–0.8</span>
                    </div>
                    <div>
                        <div class="font-semibold text-amber-800 dark:text-amber-300 mb-1">Uncertain — Flagged for Review</div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Name matches but metadata conflicts (e.g. wrong dates, different nationality).
                            Record is created and placed in the <strong class="text-gray-700 dark:text-gray-300">Review Queue</strong> for a cataloger to confirm or correct.
                        </p>
                    </div>
                </div>

                <div class="flex items-start gap-4 rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/30 p-5">
                    <div class="shrink-0 flex flex-col items-center justify-center w-16 h-16 rounded-full bg-red-600 text-white font-bold">
                        <span class="text-xs">score</span>
                        <span class="text-lg leading-tight">&lt;0.5</span>
                    </div>
                    <div>
                        <div class="font-semibold text-red-800 dark:text-red-300 mb-1">Low Confidence — Created without IDs</div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Weak or no match found in authority files. Author record is created but without any authority IDs.
                            The record is flagged <code class="text-xs bg-red-100 dark:bg-red-900/50 px-1 rounded">needs_review = true</code> for manual lookup.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        {{-- ── Data Sources ────────────────────────────────────────────── --}}
        <section>
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Data Sources</h2>
            </div>

            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <span class="text-base font-bold text-gray-900 dark:text-white">BIBLIONET</span>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-400">Phase 4</span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">Greek National Bibliography</span>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Maintained by the Book Information Centre (BIC Greece / elivip.gr). Primary source for
                            Greek-language books. Provides title, ISBN, contributors, publisher, and Thema codes via API.
                            This is our main ingestion target — scheduled for Phase 4.
                        </p>
                    </div>
                    <a href="https://biblionet.gr" target="_blank"
                       class="shrink-0 inline-flex items-center gap-1 text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline">
                        Visit
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>
                </div>
            </div>
        </section>

        {{-- ── Pipeline Terms ──────────────────────────────────────────── --}}
        <section>
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Ingestion Pipeline Terms</h2>
            </div>

            <dl class="space-y-0 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                @foreach ([
                    ['term' => 'Raw Ingestion Record',  'desc' => 'The unprocessed payload exactly as received from a source API (e.g. BIBLIONET). Stored verbatim in the database before any parsing or deduplication. Status moves through: pending → processing → completed / failed.'],
                    ['term' => 'Provenance',             'desc' => 'A record of one ingestion batch: which source, when it started and completed, and how many records were created, updated, or failed. Every edition carries a provenance reference so you always know where data came from.'],
                    ['term' => 'Edition Provenance Log', 'desc' => 'An immutable audit trail entry on a specific edition. Records each time an edition was created or updated, by which batch, and what the previous values were. Enables data lineage tracing.'],
                    ['term' => 'Review Queue',           'desc' => 'A holding area for records the system could not confidently process automatically. Catalogers review these items, confirm or correct the suggested match, and resolve or ignore each entry.'],
                    ['term' => 'Authority Resolver',    'desc' => 'The service (Phase 5) that takes an incoming author name and queries VIAF and Wikidata to find a canonical identity. Returns a confidence score and, if confident enough, links the author to their global authority record.'],
                ] as $i => $item)
                <div class="flex items-start gap-4 p-5 {{ $i > 0 ? 'border-t border-gray-100 dark:border-gray-800' : '' }} bg-white dark:bg-gray-900">
                    <span class="mt-1 shrink-0 w-2 h-2 rounded-full bg-gray-300 dark:bg-gray-600"></span>
                    <div>
                        <dt class="font-semibold text-gray-900 dark:text-white mb-1">{{ $item['term'] }}</dt>
                        <dd class="text-sm text-gray-600 dark:text-gray-400">{{ $item['desc'] }}</dd>
                    </div>
                </div>
                @endforeach
            </dl>
        </section>

    </div>
</x-filament-panels::page>
