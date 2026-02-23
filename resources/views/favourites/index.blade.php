<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Favourites • EscapeNotifier</title>
    <link rel="icon" href="/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css','resources/js/app.js'])
    @include('partials.search-styles')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        :root{
            --bg-1:#0b1020; --bg-2:#0a0f1c; --bg-3:#050814;
            --glass:rgba(13,18,30,.55); --glass-border:rgba(255,255,255,.08);
            --text:#e5e7eb; --muted:#9ca3af;
            --accent:#60a5fa; --accent2:#a78bfa; --accent3:#22d3ee;
            --danger:#ef4444;
        }
        *{box-sizing:border-box}
        html,body{height:100%}
        body{
            margin:0; color:var(--text); font-family:'Inter',ui-sans-serif,system-ui,-apple-system,sans-serif;
            background: radial-gradient(80% 60% at 20% 10%, rgba(96,165,250,.10), transparent 60%),
                        radial-gradient(60% 70% at 80% 0%, rgba(167,139,250,.12), transparent 65%),
                        radial-gradient(100% 80% at 50% 100%, rgba(34,211,238,.09), transparent 60%),
                        linear-gradient(180deg, var(--bg-1), var(--bg-2) 50%, var(--bg-3));
            background-attachment:fixed;
        }

        /* ── Nav ── */
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

        .container{max-width:1200px; margin:0 auto; padding:2rem 1.5rem; padding-bottom:4rem}

        /* ── Page Header ── */
        .page-header{
            display:flex; align-items:center; gap:1rem; margin-bottom:2rem;
        }
        .page-title{
            margin:0; font-weight:800; font-size:clamp(1.5rem,1.2rem+1.5vw,2.2rem);
            background:linear-gradient(90deg,#fff,#cbd5e1); -webkit-background-clip:text; background-clip:text; color:transparent;
            display:flex; align-items:center; gap:.75rem;
        }
        .page-title svg{flex-shrink:0}
        .page-count{
            color:var(--muted); font-size:.9rem; margin-left:auto;
            padding:.4rem .8rem; border-radius:10px;
            background:var(--glass); border:1px solid var(--glass-border);
        }

        /* ── Controls bar ── */
        .controls-bar{
            display:flex; flex-wrap:wrap; gap:.75rem; align-items:center;
            margin-bottom:1.25rem; padding:1rem 1.25rem; border-radius:14px;
            background:var(--glass); border:1px solid var(--glass-border);
            backdrop-filter:blur(14px);
        }
        .search-box{
            flex:1; min-width:220px; display:flex; align-items:center; gap:.5rem;
            padding:.55rem .9rem; border-radius:10px;
            background:rgba(0,0,0,.3); border:1px solid var(--glass-border);
        }
        .search-box input{
            flex:1; background:transparent; border:0; outline:0;
            color:var(--text); font-size:.9rem; font-family:inherit;
        }
        .search-box input::placeholder{color:var(--muted)}
        .sort-select{
            padding:.5rem .8rem; border-radius:10px; font-size:.85rem;
            background:rgba(0,0,0,.3); border:1px solid var(--glass-border);
            color:var(--text); cursor:pointer; font-family:inherit;
        }
        .sort-select:focus{outline:none; border-color:var(--accent)}
        .results-label{color:var(--muted); font-size:.85rem; margin-left:auto}

        /* ── Cards Grid ── */
        .cards-grid{
            display:grid;
            grid-template-columns:repeat(auto-fill, minmax(300px, 1fr));
            gap:1.25rem;
        }

        /* ── Room Card ── */
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
            background:conic-gradient(from 180deg, rgba(239,68,68,.2), rgba(167,139,250,.15), rgba(34,211,238,.2), rgba(239,68,68,.2));
            -webkit-mask:linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite:xor; mask-composite:exclude; opacity:0; transition:opacity .2s;
        }
        .room-card:hover::before{opacity:1}

        .card-image{
            position:relative; height:190px; overflow:hidden; background:rgba(0,0,0,.3);
        }
        .card-image img{
            width:100%; height:100%; object-fit:cover; transition:transform .4s;
        }
        .room-card:hover .card-image img{transform:scale(1.06)}
        .card-image-placeholder{
            width:100%; height:100%; display:flex; align-items:center; justify-content:center;
            font-size:3rem; background:linear-gradient(135deg, rgba(239,68,68,.08), rgba(167,139,250,.08));
        }
        /* Gradient overlay at image bottom */
        .card-image-overlay{
            position:absolute; bottom:0; left:0; right:0; height:70px;
            background:linear-gradient(transparent, rgba(5,8,20,.85));
            pointer-events:none;
        }
        .card-location-overlay{
            position:absolute; bottom:.6rem; left:.85rem;
            font-size:.75rem; color:rgba(255,255,255,.8); display:flex; align-items:center; gap:.3rem;
        }
        .card-rating{
            position:absolute; top:.75rem; right:.75rem; display:flex; align-items:center; gap:.25rem;
            padding:.3rem .6rem; border-radius:8px; font-weight:700; font-size:.85rem;
            background:rgba(0,0,0,.6); backdrop-filter:blur(6px); border:1px solid rgba(255,255,255,.1);
        }
        .card-rating svg{color:#fbbf24}

        .card-body{padding:1.1rem}
        .card-title{
            margin:0 0 .4rem; font-weight:700; font-size:1rem; color:#fff;
            display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;
        }
        .card-provider{
            color:var(--accent); font-size:.83rem; font-weight:500; margin-bottom:.55rem;
            display:flex; align-items:center; gap:.35rem;
        }
        .card-stats{
            display:flex; flex-wrap:wrap; gap:.45rem; margin-bottom:.7rem;
        }
        .card-stat{
            font-size:.72rem; padding:.2rem .5rem; border-radius:6px;
            background:rgba(0,0,0,.25); color:var(--muted);
            display:flex; align-items:center; gap:.25rem;
        }
        .card-categories{
            display:flex; flex-wrap:wrap; gap:.3rem; margin-bottom:.7rem;
        }
        .card-cat{
            font-size:.68rem; padding:.15rem .45rem; border-radius:5px;
            background:rgba(34,211,238,.08); border:1px solid rgba(34,211,238,.15); color:#67e8f9;
        }

        /* Difficulty mini-bar */
        .card-difficulty-bar{
            height:3px; border-radius:3px; background:rgba(255,255,255,.06); overflow:hidden; margin-bottom:.7rem;
        }
        .card-difficulty-fill{height:100%; border-radius:3px; transition:width .4s ease}

        .card-footer{
            display:flex; align-items:center; justify-content:space-between;
            padding-top:.65rem; border-top:1px solid var(--glass-border);
        }
        .card-escape-rate{font-size:.8rem; color:var(--muted); display:flex; align-items:center; gap:.3rem}

        /* ── Unfavourite Button ── */
        .unfav-btn{
            position:absolute; top:.75rem; left:.75rem; z-index:10;
            width:36px; height:36px; border-radius:50%;
            background:rgba(0,0,0,.6); backdrop-filter:blur(6px);
            border:1px solid rgba(255,255,255,.1);
            display:flex; align-items:center; justify-content:center;
            cursor:pointer; transition:background .2s, transform .2s;
        }
        .unfav-btn:hover{background:rgba(239,68,68,.4); transform:scale(1.1)}
        .unfav-btn svg{color:#ef4444; width:18px; height:18px}

        /* ── Empty State ── */
        .empty-state{
            text-align:center; padding:5rem 2rem;
            background:var(--glass); border:1px solid var(--glass-border);
            border-radius:18px; backdrop-filter:blur(14px);
        }
        .empty-icon{font-size:4.5rem; margin-bottom:1rem; animation:emptyPulse 2s ease-in-out infinite}
        @keyframes emptyPulse{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
        .empty-title{font-size:1.3rem; font-weight:700; color:#fff; margin:0 0 .5rem}
        .empty-text{color:var(--muted); margin:0 0 1.5rem; font-size:.95rem}
        .empty-btn{
            display:inline-flex; align-items:center; gap:.5rem;
            padding:.7rem 1.4rem; border-radius:12px;
            background:rgba(239,68,68,.2); border:1px solid rgba(239,68,68,.35);
            color:#fca5a5; font-weight:600; text-decoration:none; transition:all .2s;
        }
        .empty-btn:hover{transform:translateY(-2px); background:rgba(239,68,68,.3)}

        /* ── Hidden card animation ── */
        .room-card.hidden{display:none}

        @media(max-width:640px){
            .container{padding:1.25rem 1rem}
            .topnav{padding:.8rem 1rem; gap:1rem}
            .cards-grid{grid-template-columns:1fr}
            .page-header{flex-direction:column; align-items:flex-start; gap:.5rem}
            .page-count{margin-left:0}
            .controls-bar{flex-direction:column; gap:.5rem}
            .search-box{min-width:100%}
        }
    </style>
</head>
<body>

@include('partials.topnav')

<div class="container">
    <div class="page-header">
        <h1 class="page-title">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="#ef4444" stroke="#ef4444" stroke-width="2">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
            My Favourites
        </h1>
        <span class="page-count">{{ $rooms->count() }} {{ Str::plural('room', $rooms->count()) }}</span>
    </div>

    @if($rooms->isEmpty())
        <div class="empty-state">
            <div class="empty-icon">💔</div>
            <h2 class="empty-title">No favourites yet</h2>
            <p class="empty-text">Start exploring escape rooms and save the ones you love!</p>
            <a href="{{ route('home') }}" class="empty-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.2-4.2"/>
                </svg>
                Browse Rooms
            </a>
        </div>
    @else
        {{-- Search/Sort bar --}}
        <div class="controls-bar">
            <div class="search-box">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.2-4.2"/></svg>
                <input type="text" id="fav-search" placeholder="Search favourites..." autocomplete="off">
            </div>
            <select id="fav-sort" class="sort-select">
                <option value="title">Name A–Z</option>
                <option value="rating">Rating ↓</option>
                <option value="difficulty">Difficulty ↓</option>
            </select>
            <div class="results-label" id="fav-count">{{ $rooms->count() }} {{ Str::plural('room', $rooms->count()) }}</div>
        </div>

        <div class="cards-grid" id="fav-grid">
            @foreach($rooms as $room)
                <div class="room-card"
                 data-title="{{ strtolower($room->title) }}"
                 data-provider="{{ strtolower($room->provider) }}"
                 data-rating="{{ $room->rating ?? 0 }}"
                 data-difficulty="{{ $room->difficulty ?? 0 }}"
                 onclick="window.location='{{ route('rooms.show', $room) }}'">
                {{-- Unfavourite button --}}
                <button class="unfav-btn" onclick="event.stopPropagation(); toggleFavourite({{ $room->id }}, this)" title="Remove from favourites">
                    <svg viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                    </svg>
                </button>

                <div class="card-image">
                    @if($room->image_url)
                        <img src="{{ Str::startsWith($room->image_url, 'http') ? $room->image_url : 'https://www.escapeall.gr' . $room->image_url }}"
                             alt="{{ $room->title }}"
                             loading="lazy">
                    @else
                        <div class="card-image-placeholder">🔐</div>
                    @endif
                    <div class="card-image-overlay"></div>
                    @if($room->municipality)
                        <div class="card-location-overlay">📍 {{ $room->municipality->name }}</div>
                    @endif
                    @if($room->rating)
                        <div class="card-rating">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/></svg>
                            {{ number_format($room->rating, 1) }}
                        </div>
                    @endif
                </div>

                <div class="card-body">
                    <h3 class="card-title">{{ $room->title }}</h3>

                    <div class="card-provider">
                        @if($room->company)
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M9 21V8a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v13"/><path d="M3 21V10a1 1 0 0 1 1-1h2"/><path d="M18 21V10a1 1 0 0 0-1-1h-2"/></svg>
                            {{ $room->company->name }}
                        @else
                            {{ $room->provider }}
                        @endif
                    </div>

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

                    @php
                        $favCats = $room->categories ?? [];
                        if (is_string($favCats)) $favCats = json_decode($favCats, true) ?? [];
                    @endphp
                    @if(count($favCats) > 0)
                        <div class="card-categories">
                            @foreach(array_slice($favCats, 0, 3) as $cat)
                                <span class="card-cat">{{ $cat }}</span>
                            @endforeach
                            @if(count($favCats) > 3)
                                <span class="card-cat">+{{ count($favCats) - 3 }}</span>
                            @endif
                        </div>
                    @endif

                    @if($room->difficulty)
                        <div class="card-difficulty-bar">
                            <div class="card-difficulty-fill" style="width:{{ ($room->difficulty/10)*100 }}%; background:{{ $room->difficulty <= 3 ? '#22c55e' : ($room->difficulty <= 6 ? '#eab308' : ($room->difficulty <= 8 ? '#f97316' : '#ef4444')) }}"></div>
                        </div>
                    @endif

                    <div class="card-footer">
                        <div class="card-escape-rate">
                            @if($room->escape_rate)
                                🏆 {{ number_format($room->escape_rate) }}% escape rate
                            @elseif($room->reviews_count)
                                💬 {{ $room->reviews_count }} reviews
                            @endif
                        </div>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--muted)" stroke-width="2"><polyline points="9,18 15,12 9,6"/></svg>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="empty-state" id="fav-empty" style="display:none; margin-top:1.5rem">
            <div class="empty-icon" style="animation:none;opacity:.5">🔍</div>
            <h2 class="empty-title">No rooms found</h2>
            <p class="empty-text">Try a different search</p>
        </div>
    @endif
</div>

<script>
async function toggleFavourite(roomId, button) {
    try {
        const response = await fetch(`/favourites/${roomId}/toggle`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        });

        const data = await response.json();

        if (data.success && !data.is_favourited) {
            const card = button.closest('.room-card');
            card.style.transition = 'opacity .3s, transform .3s';
            card.style.opacity = '0';
            card.style.transform = 'scale(0.92) translateY(-4px)';
            setTimeout(() => {
                card.remove();
                recount();
            }, 300);
        }
    } catch (error) {
        console.error('Error toggling favourite:', error);
    }
}

function recount() {
    const grid = document.getElementById('fav-grid');
    if (!grid) return;
    const visible = grid.querySelectorAll('.room-card:not([style*="display: none"])');
    const countEl = document.getElementById('fav-count');
    if (countEl) countEl.textContent = `${visible.length} ${visible.length === 1 ? 'room' : 'rooms'}`;
    const emptyState = document.getElementById('fav-empty');
    if (emptyState) emptyState.style.display = visible.length === 0 ? 'block' : 'none';
    if (grid.querySelectorAll('.room-card').length === 0) location.reload();
}

// Search + sort for favourites
(function(){
    const searchInput = document.getElementById('fav-search');
    const sortSelect = document.getElementById('fav-sort');
    const grid = document.getElementById('fav-grid');
    if (!searchInput || !grid) return;
    const cards = Array.from(grid.querySelectorAll('.room-card'));

    function applyFilter() {
        const q = searchInput.value.toLowerCase().trim();
        const sortBy = sortSelect.value;

        let visible = cards.filter(c => {
            if (!q) return true;
            return (c.dataset.title || '').includes(q) || (c.dataset.provider || '').includes(q);
        });

        visible.sort((a, b) => {
            if (sortBy === 'rating') return (parseFloat(b.dataset.rating)||0) - (parseFloat(a.dataset.rating)||0);
            if (sortBy === 'difficulty') return (parseFloat(b.dataset.difficulty)||0) - (parseFloat(a.dataset.difficulty)||0);
            return (a.dataset.title||'').localeCompare(b.dataset.title||'');
        });

        cards.forEach(c => c.style.display = 'none');
        visible.forEach(c => { c.style.display = ''; grid.appendChild(c); });

        const countEl = document.getElementById('fav-count');
        if (countEl) countEl.textContent = `${visible.length} ${visible.length === 1 ? 'room' : 'rooms'}`;
        const emptyEl = document.getElementById('fav-empty');
        if (emptyEl) emptyEl.style.display = visible.length === 0 ? 'block' : 'none';
    }

    searchInput.addEventListener('input', applyFilter);
    sortSelect.addEventListener('change', applyFilter);
})();
</script>
@include('partials.search-script')
</body>
</html>

