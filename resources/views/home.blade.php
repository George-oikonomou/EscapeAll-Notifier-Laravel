<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EscapeAll Notifier</title>
    <link rel="icon" href="/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css','resources/js/app.js'])
    <style>
        :root{
            --bg-1:#0b1020; --bg-2:#0a0f1c; --bg-3:#050814;
            --glass:rgba(13,18,30,.55); --glass-border:rgba(255,255,255,.08);
            --text:#e5e7eb; --muted:#9ca3af;
            --accent:#60a5fa; --accent2:#a78bfa; --accent3:#22d3ee;
        }
        *{box-sizing:border-box}
        html,body{height:100%;margin:0}
        body{
            color:var(--text);
            background: radial-gradient(80% 60% at 20% 10%, rgba(96,165,250,.10), transparent 60%),
                        radial-gradient(60% 70% at 80% 0%, rgba(167,139,250,.12), transparent 65%),
                        radial-gradient(100% 80% at 50% 100%, rgba(34,211,238,.09), transparent 60%),
                        linear-gradient(180deg, var(--bg-1), var(--bg-2) 50%, var(--bg-3));
            background-attachment: fixed;
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif;
        }
        .topnav{
            display:flex; align-items:center; gap:1.5rem; padding:.8rem 2rem;
            background:rgba(5,8,20,.7); border-bottom:1px solid var(--glass-border);
            backdrop-filter:blur(12px); position:sticky; top:0; z-index:50;
        }
        .topnav .brand{font-weight:800; font-size:1.1rem; text-decoration:none;
            background:linear-gradient(90deg,#60a5fa,#a78bfa); -webkit-background-clip:text; background-clip:text; color:transparent;}
        .topnav a{color:var(--muted); text-decoration:none; font-size:.9rem; transition:color .2s}
        .topnav a:hover{color:#fff}
        .topnav a.active{color:#fff; font-weight:600}

        .container{max-width:1400px; margin:0 auto; padding:2rem 1.5rem}

        /* Header */
        .page-header{margin-bottom:2rem}
        .page-title{
            margin:0 0 .5rem; font-weight:800; font-size:clamp(1.8rem,1.4rem+2vw,2.8rem);
            background:linear-gradient(90deg,#fff,#cbd5e1); -webkit-background-clip:text; background-clip:text; color:transparent;
        }
        .page-subtitle{color:var(--muted); margin:0; font-size:1rem}

        /* Controls */
        .controls{
            display:flex; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;
            padding:1.25rem; border-radius:18px;
            background:var(--glass); border:1px solid var(--glass-border);
            backdrop-filter:blur(14px);
        }
        .search-box{
            flex:1; min-width:280px; display:flex; gap:.6rem; align-items:center;
            padding:.7rem 1rem; border-radius:12px;
            background:rgba(0,0,0,.3); border:1px solid var(--glass-border);
        }
        .search-box input{
            flex:1; background:transparent; border:0; outline:0;
            color:var(--text); font-size:.95rem; font-family:inherit;
        }
        .search-box input::placeholder{color:var(--muted)}

        .filter-group{display:flex; flex-wrap:wrap; gap:.5rem; align-items:center}
        .filter-label{color:var(--muted); font-size:.85rem; margin-right:.25rem}
        .filter-select{
            padding:.5rem .8rem; border-radius:10px; font-size:.85rem;
            background:rgba(0,0,0,.3); border:1px solid var(--glass-border);
            color:var(--text); cursor:pointer; font-family:inherit;
        }
        .filter-select:focus{outline:none; border-color:var(--accent)}
        .filter-select option{background:#1a1a2e; color:var(--text)}

        .filter-chips{
            display:flex; flex-wrap:wrap; gap:.4rem;
            padding:1rem; margin-bottom:1rem; border-radius:14px;
            background:var(--glass); border:1px solid var(--glass-border);
            max-height:200px; overflow-y:auto;
        }
        .filter-chip{
            padding:.4rem .75rem; border-radius:20px; font-size:.8rem;
            background:rgba(96,165,250,.08); border:1px solid rgba(96,165,250,.15);
            color:#93c5fd; cursor:pointer; transition:all .2s;
            white-space:nowrap;
        }
        .filter-chip:hover{background:rgba(96,165,250,.18); border-color:rgba(96,165,250,.3)}
        .filter-chip.active{background:rgba(96,165,250,.3); border-color:rgba(96,165,250,.5); color:#fff; font-weight:600}

        .results-count{
            color:var(--muted); font-size:.85rem;
            padding:.35rem .7rem; border-radius:20px;
            background:transparent;
            white-space:nowrap;
        }

        /* Cards Grid */
        .cards-grid{
            display:grid;
            grid-template-columns:repeat(auto-fill, minmax(300px, 1fr));
            gap:1.25rem;
        }

        /* Room Card */
        .room-card{
            position:relative; border-radius:18px; overflow:hidden;
            background:var(--glass); border:1px solid var(--glass-border);
            backdrop-filter:blur(14px);
            transition:transform .2s, border-color .2s, box-shadow .2s;
            cursor:pointer;
        }
        .room-card:hover{
            transform:translateY(-4px);
            border-color:rgba(255,255,255,.15);
            box-shadow:0 12px 40px rgba(0,0,0,.4);
        }
        .room-card::before{
            content:""; position:absolute; inset:-1px; border-radius:19px; padding:1px; pointer-events:none;
            background:conic-gradient(from 180deg, rgba(96,165,250,.2), rgba(167,139,250,.15), rgba(34,211,238,.2), rgba(96,165,250,.2));
            -webkit-mask:linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite:xor; mask-composite:exclude; opacity:0; transition:opacity .2s;
        }
        .room-card:hover::before{opacity:1}

        .card-image{position:relative; height:180px; overflow:hidden; background:rgba(0,0,0,.3)}
        .card-image img{width:100%; height:100%; object-fit:cover; transition:transform .3s}
        .room-card:hover .card-image img{transform:scale(1.05)}
        .card-image-placeholder{
            width:100%; height:100%; display:flex; align-items:center; justify-content:center;
            font-size:3rem; background:linear-gradient(135deg, rgba(96,165,250,.08), rgba(167,139,250,.08));
        }

        .card-badges{position:absolute; top:.75rem; left:.75rem; display:flex; flex-wrap:wrap; gap:.35rem; max-width:calc(100% - 80px)}
        .card-badge{
            padding:.2rem .5rem; border-radius:6px; font-size:.7rem; font-weight:600;
            backdrop-filter:blur(6px);
        }
        .badge-coming-soon{background:rgba(251,191,36,.25); border:1px solid rgba(251,191,36,.4); color:#fcd34d}
        .badge-horror{background:rgba(239,68,68,.25); border:1px solid rgba(239,68,68,.4); color:#fca5a5}
        .badge-actor{background:rgba(167,139,250,.25); border:1px solid rgba(167,139,250,.4); color:#c4b5fd}
        .badge-kids{background:rgba(34,197,94,.25); border:1px solid rgba(34,197,94,.4); color:#86efac}
        .badge-thriller{background:rgba(236,72,153,.25); border:1px solid rgba(236,72,153,.4); color:#f9a8d4}
        .badge-vr{background:rgba(14,165,233,.25); border:1px solid rgba(14,165,233,.4); color:#7dd3fc}
        .badge-adults{background:rgba(239,68,68,.25); border:1px solid rgba(239,68,68,.4); color:#fca5a5}
        .badge-outdoor{background:rgba(234,179,8,.25); border:1px solid rgba(234,179,8,.4); color:#fde047}

        .card-rating{
            position:absolute; top:.75rem; right:.75rem; display:flex; align-items:center; gap:.25rem;
            padding:.3rem .6rem; border-radius:8px; font-weight:700; font-size:.85rem;
            background:rgba(0,0,0,.6); backdrop-filter:blur(6px); border:1px solid rgba(255,255,255,.1);
        }
        .card-rating svg{color:#fbbf24; width:14px; height:14px}

        .card-body{padding:1.15rem}
        .card-title{
            margin:0 0 .4rem; font-weight:700; font-size:1.05rem; color:#fff;
            display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;
        }
        .card-provider{
            color:var(--accent); font-size:.85rem; font-weight:500; margin-bottom:.5rem;
            display:flex; align-items:center; gap:.35rem;
        }
        .card-location{
            color:var(--muted); font-size:.8rem; margin-bottom:.6rem;
            display:flex; align-items:center; gap:.3rem;
        }
        .card-stats{
            display:flex; flex-wrap:wrap; gap:.6rem; font-size:.78rem; color:var(--muted);
        }
        .card-stat{
            display:flex; align-items:center; gap:.25rem;
            padding:.2rem .5rem; border-radius:6px;
            background:rgba(0,0,0,.2);
        }

        .card-categories{
            display:flex; flex-wrap:wrap; gap:.3rem; margin-top:.7rem; max-height:52px; overflow:hidden;
        }
        .card-cat{
            font-size:.68rem; padding:.15rem .45rem; border-radius:5px;
            background:rgba(34,211,238,.08); border:1px solid rgba(34,211,238,.15); color:#67e8f9;
        }

        /* Empty state */
        .empty-state{
            text-align:center; padding:4rem 2rem;
            background:var(--glass); border:1px solid var(--glass-border);
            border-radius:18px;
        }
        .empty-icon{font-size:4rem; margin-bottom:1rem; opacity:.5}
        .empty-title{font-size:1.3rem; font-weight:700; color:#fff; margin:0 0 .5rem}
        .empty-text{color:var(--muted); margin:0}

        /* Global Search */
        .global-search{position:relative; width:280px}
        .global-search-input{
            width:100%; padding:.5rem 1rem .5rem 2.5rem;
            background:rgba(255,255,255,.05); border:1px solid var(--glass-border);
            border-radius:10px; color:var(--text); font-size:.9rem;
            font-family:inherit; outline:none; transition:all .2s;
        }
        .global-search-input::placeholder{color:var(--muted)}
        .global-search-input:focus{
            background:rgba(255,255,255,.08); border-color:var(--accent);
            box-shadow:0 0 0 3px rgba(96,165,250,.15);
        }
        .global-search-icon{
            position:absolute; left:.85rem; top:50%; transform:translateY(-50%);
            color:var(--muted); pointer-events:none; font-size:.9rem;
        }
        .global-search-clear{
            position:absolute; right:.75rem; top:50%; transform:translateY(-50%);
            background:none; border:none; color:var(--muted); cursor:pointer;
            font-size:1rem; padding:0; display:none;
        }
        .global-search-clear.visible{display:block}
        .global-search-clear:hover{color:var(--text)}
        .search-results{
            position:absolute; top:calc(100% + 8px); left:0; right:0;
            background:rgba(15,20,35,.98); border:1px solid var(--glass-border);
            border-radius:12px; box-shadow:0 8px 32px rgba(0,0,0,.5);
            backdrop-filter:blur(16px); max-height:400px; overflow-y:auto;
            display:none; z-index:100;
        }
        .search-results.active{display:block}
        .search-results-section{padding:.5rem 0}
        .search-results-section:not(:last-child){border-bottom:1px solid var(--glass-border)}
        .search-results-label{
            padding:.5rem 1rem; font-size:.7rem; text-transform:uppercase;
            color:var(--muted); font-weight:600; letter-spacing:.05em;
        }
        .search-result-item{
            display:flex; align-items:center; gap:.75rem; padding:.6rem 1rem;
            text-decoration:none; color:var(--text); transition:background .15s;
        }
        .search-result-item:hover{background:rgba(255,255,255,.06)}
        .search-result-img{
            width:44px; height:44px; border-radius:8px; object-fit:cover;
            background:rgba(255,255,255,.05); flex-shrink:0;
        }
        .search-result-img.company{border-radius:50%}
        .search-result-info{flex:1; min-width:0}
        .search-result-title{
            font-size:.85rem; font-weight:600; color:var(--text);
            white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
        }
        .search-result-meta{
            font-size:.72rem; color:var(--muted); margin-top:2px;
            display:flex; align-items:center; gap:.5rem; flex-wrap:wrap;
        }
        .search-result-meta span{display:flex; align-items:center; gap:.2rem}
        .search-result-rating{color:#fbbf24}
        .search-no-results, .search-loading{
            padding:1.25rem; text-align:center; color:var(--muted); font-size:.9rem;
        }
        .search-loading::after{
            content:''; display:inline-block; width:14px; height:14px;
            border:2px solid var(--glass-border); border-top-color:var(--accent);
            border-radius:50%; animation:spin .6s linear infinite; margin-left:.5rem;
        }
        @keyframes spin{to{transform:rotate(360deg)}}

        @media(max-width:768px){
            .container{padding:1rem .75rem}
            .topnav{padding:.6rem 1rem; gap:.6rem; flex-wrap:wrap}
            .topnav .brand{font-size:1rem}
            .topnav a{font-size:.82rem}
            .controls{flex-direction:column; padding:1rem; gap:.75rem}
            .search-box{min-width:100%}
            .filter-group{width:100%}
            .filter-select{flex:1}
            .cards-grid{grid-template-columns:1fr}
            .global-search{width:100%; order:10}
            .page-title{font-size:1.6rem !important}
            .page-subtitle{font-size:.88rem}
            .filter-chips{padding:.75rem; gap:.35rem}
            .filter-chip{font-size:.75rem; padding:.3rem .6rem}
            .card-image{height:160px}
        }
        @media(max-width:480px){
            .topnav{padding:.5rem .75rem; gap:.5rem}
            .container{padding:.75rem .5rem}
            .controls{padding:.75rem}
            .card-body{padding:.9rem}
            .card-stats{gap:.4rem}
            .card-stat{font-size:.72rem; padding:.15rem .35rem}
        }
    </style>
</head>
<body>

@include('partials.topnav')

<div class="container">
    <div class="page-header">
        <h1 class="page-title">🔐 Escape Rooms</h1>
        <p class="page-subtitle">Browse {{ $rooms->count() }} escape rooms across Greece</p>
    </div>

    <div class="controls">
        <div class="search-box">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M21 21l-4.2-4.2" stroke="#9ca3af" stroke-width="1.5" stroke-linecap="round"/><circle cx="11" cy="11" r="7" stroke="#9ca3af" stroke-width="1.5"/></svg>
            <input id="search" type="text" placeholder="Search rooms, providers, locations..." autocomplete="off">
        </div>

        <div class="filter-group">
            <span class="filter-label">Location:</span>
            <select id="filter-location" class="filter-select">
                <option value="">All Locations</option>
                @foreach($municipalities as $externalId => $name)
                    <option value="{{ $externalId }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div class="filter-group">
            <span class="filter-label">Sort:</span>
            <select id="filter-sort" class="filter-select">
                <option value="title">Name A-Z</option>
                <option value="rating">Rating ↓</option>
                <option value="difficulty">Difficulty ↓</option>
            </select>
        </div>

        <div class="results-count" id="results-count">{{ $rooms->count() }} rooms</div>
    </div>

    <div class="filter-chips" id="category-filters">
        <div class="filter-chip active" data-category="" data-negative="false" data-negates="" onclick="toggleCategory(this)">🎯 All</div>
        @foreach($categories as $cat)
            <div class="filter-chip"
                 data-category="{{ $cat['slug'] }}"
                 data-name="{{ strtolower($cat['name']) }}"
                 data-negative="{{ $cat['is_negative'] ? 'true' : 'false' }}"
                 data-negates="{{ $cat['negates_slug'] ?? '' }}"
                 data-aliases="{{ strtolower(implode(',', $cat['aliases'] ?? [])) }}"
                 onclick="toggleCategory(this)">
                {{ $cat['emoji'] }} {{ $cat['name'] }}
            </div>
        @endforeach
    </div>

    <div class="cards-grid" id="rooms-grid" style="margin-top:1.5rem">
        @foreach($rooms as $room)
            @php
                $roomCategories = $room->categories ?? [];
                if (is_string($roomCategories)) {
                    $roomCategories = json_decode($roomCategories, true) ?? [];
                }

                // Map Greek category names to slugs for filtering
                $categorySlugs = [];
                foreach ($roomCategories as $catName) {
                    $catKey = strtolower($catName);
                    if (isset($categoryLookup[$catKey])) {
                        $categorySlugs[] = $categoryLookup[$catKey]['slug'];
                    }
                }

                $isHorror = in_array('Τρόμου', $roomCategories);
                $hasActor = in_array('Ηθοποιός', $roomCategories) || in_array('Με Ηθοποιό', $roomCategories);
                $isKids = in_array('Για Παιδιά', $roomCategories) || in_array('Kids Friendly', $roomCategories);
                $isComingSoon = in_array('Σύντομα κοντά σας', $roomCategories);
                $isPsychThriller = in_array('Ψυχολογικό Θρίλερ', $roomCategories);
                $isAction = in_array('Δράσης', $roomCategories);
                $isVR = in_array('Εικονική Πραγματικότητα', $roomCategories) || in_array('VR', $roomCategories);
                $isAdultsOnly = in_array('Ενήλικες Μόνο', $roomCategories);
                $isOutdoor = in_array('Εξωτερικού Χώρου', $roomCategories);

                // Get municipality through company's external_id
                $municipalityExtId = $room->company->municipality_external_id ?? '';
                $municipalityName = $municipalityLookup[$municipalityExtId]->name ?? '';
            @endphp
            <div class="room-card"
                 data-title="{{ strtolower($room->title) }}"
                 data-provider="{{ strtolower($room->provider) }}"
                 data-location="{{ strtolower($municipalityName) }}"
                 data-municipality-id="{{ $municipalityExtId }}"
                 data-categories="{{ strtolower(implode(',', $roomCategories)) }}"
                 data-category-slugs="{{ implode(',', $categorySlugs) }}"
                 data-rating="{{ $room->rating ?? 0 }}"
                 data-difficulty="{{ $room->difficulty ?? 0 }}"
                 data-id="{{ $room->id }}"
                 onclick="window.location='{{ route('rooms.show', $room) }}'">

                <div class="card-image">
                    @if($room->image_url)
                        <img src="{{ Str::startsWith($room->image_url, 'http') ? $room->image_url : 'https://www.escapeall.gr' . $room->image_url }}"
                             alt="{{ $room->title }}" loading="lazy">
                    @else
                        <div class="card-image-placeholder">🔐</div>
                    @endif

                    <div class="card-badges">
                        @if($isComingSoon)
                            <span class="card-badge badge-coming-soon">🔜 Coming Soon</span>
                        @endif
                        @if($isHorror)
                            <span class="card-badge badge-horror">💀 Horror</span>
                        @endif
                        @if($hasActor)
                            <span class="card-badge badge-actor">🎭 Actor</span>
                        @endif
                        @if($isKids)
                            <span class="card-badge badge-kids">👶 Kids</span>
                        @endif
                        @if($isPsychThriller)
                            <span class="card-badge badge-thriller">🧠 Thriller</span>
                        @endif
                        @if($isVR)
                            <span class="card-badge badge-vr">🥽 VR</span>
                        @endif
                        @if($isAdultsOnly)
                            <span class="card-badge badge-adults">🔞 Adults</span>
                        @endif
                        @if($isOutdoor)
                            <span class="card-badge badge-outdoor">☀️ Outdoor</span>
                        @endif
                    </div>

                    @if($room->rating)
                        <div class="card-rating">
                            <svg viewBox="0 0 24 24" fill="currentColor"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/></svg>
                            {{ number_format($room->rating, 1) }}
                        </div>
                    @endif
                </div>

                <div class="card-body">
                    <h3 class="card-title">{{ $room->title }}</h3>
                    <div class="card-provider">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M9 21V8a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v13"/></svg>
                        {{ $room->provider }}
                    </div>
                    @if($municipalityName)
                        <div class="card-location">📍 {{ $municipalityName }}</div>
                    @endif

                    <div class="card-stats">
                        @if($room->duration_display)
                            <span class="card-stat">⏱️ {{ $room->formatted_duration }}</span>
                        @endif
                        @if($room->min_players && $room->max_players)
                            <span class="card-stat">👥 {{ $room->min_players }}-{{ $room->max_players }}</span>
                        @endif
                        @if($room->difficulty)
                            <span class="card-stat">💀 {{ $room->difficulty }}/10</span>
                        @endif
                        @if($room->escape_rate)
                            <span class="card-stat">🎯 {{ number_format($room->escape_rate) }}%</span>
                        @endif
                    </div>

                    @if(count($roomCategories) > 0)
                        <div class="card-categories">
                            @foreach(array_slice($roomCategories, 0, 4) as $cat)
                                <span class="card-cat">{{ $cat }}</span>
                            @endforeach
                            @if(count($roomCategories) > 4)
                                <span class="card-cat">+{{ count($roomCategories) - 4 }}</span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="empty-state" id="empty-state" style="display:none; margin-top:1.5rem">
        <div class="empty-icon">🔍</div>
        <h2 class="empty-title">No rooms found</h2>
        <p class="empty-text">Try adjusting your search or filters</p>
    </div>
</div>

<script>
(function(){
    const searchInput = document.getElementById('search');
    const locationFilter = document.getElementById('filter-location');
    const sortFilter = document.getElementById('filter-sort');
    const resultsCount = document.getElementById('results-count');
    const grid = document.getElementById('rooms-grid');
    const emptyState = document.getElementById('empty-state');
    const cards = Array.from(document.querySelectorAll('.room-card'));
    const categoryChips = document.querySelectorAll('.filter-chip');

    let activeCategory = '';
    let isNegativeFilter = false;
    let negatesSlug = '';
    let categoryAliases = [];

    window.toggleCategory = function(chip) {
        categoryChips.forEach(c => c.classList.remove('active'));
        chip.classList.add('active');
        activeCategory = chip.dataset.category || '';
        isNegativeFilter = chip.dataset.negative === 'true';
        negatesSlug = chip.dataset.negates || '';
        categoryAliases = (chip.dataset.aliases || '').split(',').filter(a => a);
        filterAndSort();
    };

    // Set "All" as active by default
    categoryChips[0]?.classList.add('active');

    // Build a map of slug -> Greek names for negative filtering
    const slugToGreekNames = {
        'actor': ['ηθοποιός', 'με ηθοποιό'],
        'horror': ['τρόμου'],
        'has-score': ['σκορ', 'έχει σκορ', 'score'],
    };

    function filterAndSort() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        const municipalityId = locationFilter.value;
        const sortBy = sortFilter.value;

        let visibleCards = cards.filter(card => {
            const title = card.dataset.title || '';
            const provider = card.dataset.provider || '';
            const loc = card.dataset.location || '';
            const categories = card.dataset.categories || ''; // Greek names, comma-separated, lowercase
            const categorySlugs = card.dataset.categorySlugs || '';
            const cardMunicipalityId = card.dataset.municipalityId || '';

            // Search filter
            const matchesSearch = !searchTerm ||
                title.includes(searchTerm) ||
                provider.includes(searchTerm) ||
                loc.includes(searchTerm) ||
                categories.includes(searchTerm);

            // Location filter
            const matchesLocation = !municipalityId || cardMunicipalityId === municipalityId;

            // Category filter
            let matchesCategory = true;
            if (activeCategory) {
                if (isNegativeFilter && negatesSlug) {
                    // Negative filter: show rooms that DON'T have the negated category
                    const greekNames = slugToGreekNames[negatesSlug] || [];
                    const hasCat = categorySlugs.split(',').includes(negatesSlug) ||
                                   greekNames.some(name => categories.includes(name));
                    matchesCategory = !hasCat;
                } else {
                    // Positive filter: show rooms that HAVE this category
                    // Check by slug first
                    let hasCat = categorySlugs.split(',').includes(activeCategory);

                    // If not found by slug, check by aliases in Greek names
                    if (!hasCat && categoryAliases.length > 0) {
                        hasCat = categoryAliases.some(alias => categories.includes(alias.toLowerCase()));
                    }

                    // Also check slugToGreekNames for this category
                    if (!hasCat) {
                        const greekNames = slugToGreekNames[activeCategory] || [];
                        hasCat = greekNames.some(name => categories.includes(name));
                    }

                    matchesCategory = hasCat;
                }
            }

            return matchesSearch && matchesLocation && matchesCategory;
        });

        // Sort
        visibleCards.sort((a, b) => {
            switch(sortBy) {
                case 'rating':
                    return (parseFloat(b.dataset.rating) || 0) - (parseFloat(a.dataset.rating) || 0);
                case 'difficulty':
                    return (parseFloat(b.dataset.difficulty) || 0) - (parseFloat(a.dataset.difficulty) || 0);
                default:
                    return (a.dataset.title || '').localeCompare(b.dataset.title || '');
            }
        });

        // Hide all, show filtered
        cards.forEach(card => card.style.display = 'none');
        visibleCards.forEach(card => {
            card.style.display = '';
            grid.appendChild(card);
        });

        // Update count
        resultsCount.textContent = `${visibleCards.length} room${visibleCards.length !== 1 ? 's' : ''}`;

        // Show empty state if no results
        emptyState.style.display = visibleCards.length === 0 ? 'block' : 'none';
        grid.style.display = visibleCards.length === 0 ? 'none' : 'grid';
    }

    searchInput.addEventListener('input', filterAndSort);
    locationFilter.addEventListener('change', filterAndSort);
    sortFilter.addEventListener('change', filterAndSort);

    // Initial filter
    filterAndSort();

    // Enter key opens first visible card
    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const firstVisible = cards.find(c => c.style.display !== 'none');
            if (firstVisible) firstVisible.click();
        }
    });
})();
</script>

<!-- Global Search Script -->
<script>
(function() {
    const globalSearchInput = document.getElementById('globalSearchInput');
    const searchResults = document.getElementById('searchResults');
    const searchClear = document.getElementById('globalSearchClear');
    const globalSearch = document.getElementById('globalSearch');

    if (!globalSearchInput) return;

    let debounceTimer;
    let currentQuery = '';

    globalSearchInput.addEventListener('input', function() {
        const query = this.value.trim();
        currentQuery = query;
        searchClear.classList.toggle('visible', query.length > 0);
        clearTimeout(debounceTimer);

        if (query.length < 2) {
            searchResults.classList.remove('active');
            return;
        }

        searchResults.innerHTML = '<div class="search-loading">Searching</div>';
        searchResults.classList.add('active');
        debounceTimer = setTimeout(() => performGlobalSearch(query), 250);
    });

    searchClear.addEventListener('click', function() {
        globalSearchInput.value = '';
        searchClear.classList.remove('visible');
        searchResults.classList.remove('active');
        globalSearchInput.focus();
    });

    document.addEventListener('click', function(e) {
        if (!globalSearch.contains(e.target)) {
            searchResults.classList.remove('active');
        }
    });

    globalSearchInput.addEventListener('focus', function() {
        if (this.value.trim().length >= 2 && searchResults.innerHTML.trim()) {
            searchResults.classList.add('active');
        }
    });

    globalSearchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            searchResults.classList.remove('active');
            this.blur();
        }
    });

    async function performGlobalSearch(query) {
        if (query !== currentQuery) return;
        try {
            const response = await fetch(`/search?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            if (query !== currentQuery) return;
            renderGlobalResults(data);
        } catch (error) {
            searchResults.innerHTML = '<div class="search-no-results">Search failed</div>';
        }
    }

    function renderGlobalResults(data) {
        const { rooms, companies } = data;
        if (rooms.length === 0 && companies.length === 0) {
            searchResults.innerHTML = '<div class="search-no-results">No results found</div>';
            return;
        }

        let html = '';
        if (rooms.length > 0) {
            html += '<div class="search-results-section"><div class="search-results-label">🚪 Rooms</div>';
            rooms.forEach(room => {
                const imgSrc = room.image_url ? (room.image_url.startsWith('http') ? room.image_url : 'https://www.escapeall.gr' + room.image_url) : '/favicon.ico';
                html += `<a href="${room.url}" class="search-result-item">
                    <img src="${imgSrc}" alt="" class="search-result-img" onerror="this.src='/favicon.ico'">
                    <div class="search-result-info">
                        <div class="search-result-title">${escapeHtml(room.title)}</div>
                        <div class="search-result-meta">
                            <span class="search-result-rating">★ ${room.rating || '-'}</span>
                            <span>👥 ${room.players}</span>
                            <span>${escapeHtml(room.provider || '')}</span>
                        </div>
                    </div>
                </a>`;
            });
            html += '</div>';
        }
        if (companies.length > 0) {
            html += '<div class="search-results-section"><div class="search-results-label">🏢 Companies</div>';
            companies.forEach(company => {
                const logoSrc = company.logo_url ? (company.logo_url.startsWith('http') ? company.logo_url : 'https://www.escapeall.gr' + company.logo_url) : '/favicon.ico';
                html += `<a href="${company.url}" class="search-result-item">
                    <img src="${logoSrc}" alt="" class="search-result-img company" onerror="this.src='/favicon.ico'">
                    <div class="search-result-info">
                        <div class="search-result-title">${escapeHtml(company.name)}</div>
                        <div class="search-result-meta">
                            <span>🚪 ${company.rooms_count} rooms</span>
                            ${company.address ? `<span>📍 ${escapeHtml(company.address)}</span>` : ''}
                        </div>
                    </div>
                </a>`;
            });
            html += '</div>';
        }
        searchResults.innerHTML = html;
        searchResults.classList.add('active');
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();
</script>
</body>
</html>

