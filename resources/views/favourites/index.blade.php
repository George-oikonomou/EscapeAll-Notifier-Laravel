<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Favourites • EscapeAll</title>
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

        /* ── Cards Grid ── */
        .cards-grid{
            display:grid;
            grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));
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
            background:conic-gradient(from 180deg, rgba(96,165,250,.2), rgba(167,139,250,.15), rgba(34,211,238,.2), rgba(96,165,250,.2));
            -webkit-mask:linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite:xor; mask-composite:exclude; opacity:0; transition:opacity .2s;
        }
        .room-card:hover::before{opacity:1}

        .card-image{
            position:relative; height:180px; overflow:hidden; background:rgba(0,0,0,.3);
        }
        .card-image img{
            width:100%; height:100%; object-fit:cover; transition:transform .3s;
        }
        .room-card:hover .card-image img{transform:scale(1.05)}
        .card-image-placeholder{
            width:100%; height:100%; display:flex; align-items:center; justify-content:center;
            font-size:3rem; background:linear-gradient(135deg, rgba(96,165,250,.08), rgba(167,139,250,.08));
        }
        .card-rating{
            position:absolute; top:.75rem; right:.75rem; display:flex; align-items:center; gap:.25rem;
            padding:.3rem .6rem; border-radius:8px; font-weight:700; font-size:.85rem;
            background:rgba(0,0,0,.6); backdrop-filter:blur(6px); border:1px solid rgba(255,255,255,.1);
        }
        .card-rating svg{color:#fbbf24}

        .card-body{padding:1.25rem}
        .card-title{
            margin:0 0 .5rem; font-weight:700; font-size:1.1rem; color:#fff;
            display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;
        }
        .card-provider{
            color:var(--accent); font-size:.88rem; font-weight:500; margin-bottom:.6rem;
            display:flex; align-items:center; gap:.4rem;
        }
        .card-meta{
            display:flex; flex-wrap:wrap; gap:.5rem; margin-bottom:.75rem;
        }
        .card-chip{
            font-size:.72rem; padding:.2rem .5rem; border-radius:6px;
            background:rgba(34,211,238,.1); border:1px solid rgba(34,211,238,.2); color:#67e8f9;
        }
        .card-chip.location{
            background:rgba(167,139,250,.1); border-color:rgba(167,139,250,.2); color:#c4b5fd;
        }

        .card-footer{
            display:flex; align-items:center; justify-content:space-between;
            padding-top:.75rem; border-top:1px solid var(--glass-border);
        }
        .card-stats{
            display:flex; gap:1rem; font-size:.82rem; color:var(--muted);
        }
        .card-stats span{display:flex; align-items:center; gap:.3rem}

        /* ── Unfavourite Button ── */
        .unfav-btn{
            position:absolute; top:.75rem; left:.75rem; z-index:10;
            width:36px; height:36px; border-radius:50%;
            background:rgba(0,0,0,.6); backdrop-filter:blur(6px);
            border:1px solid rgba(255,255,255,.1);
            display:flex; align-items:center; justify-content:center;
            cursor:pointer; transition:background .2s, transform .2s;
        }
        .unfav-btn:hover{background:rgba(239,68,68,.3); transform:scale(1.1)}
        .unfav-btn svg{color:#ef4444; width:18px; height:18px}

        /* ── Empty State ── */
        .empty-state{
            text-align:center; padding:4rem 2rem;
            background:var(--glass); border:1px solid var(--glass-border);
            border-radius:18px; backdrop-filter:blur(14px);
        }
        .empty-icon{font-size:4rem; margin-bottom:1rem; opacity:.5}
        .empty-title{font-size:1.3rem; font-weight:700; color:#fff; margin:0 0 .5rem}
        .empty-text{color:var(--muted); margin:0 0 1.5rem}
        .empty-btn{
            display:inline-flex; align-items:center; gap:.5rem;
            padding:.7rem 1.3rem; border-radius:12px;
            background:var(--accent); color:#0b1020; font-weight:600;
            text-decoration:none; transition:transform .2s;
        }
        .empty-btn:hover{transform:translateY(-2px)}

        @media(max-width:640px){
            .container{padding:1.25rem 1rem}
            .topnav{padding:.8rem 1rem; gap:1rem}
            .cards-grid{grid-template-columns:1fr}
            .page-header{flex-direction:column; align-items:flex-start; gap:.5rem}
            .page-count{margin-left:0}
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
            <p class="empty-text">Start exploring escape rooms and add your favourites!</p>
            <a href="{{ route('home') }}" class="empty-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9,22 9,12 15,12 15,22"/>
                </svg>
                Browse Rooms
            </a>
        </div>
    @else
        <div class="cards-grid">
            @foreach($rooms as $room)
                <div class="room-card" onclick="window.location='{{ route('rooms.show', $room) }}'">
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

                        @if($room->rating)
                            <div class="card-rating">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                    <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/>
                                </svg>
                                {{ number_format($room->rating, 1) }}
                            </div>
                        @endif
                    </div>

                    <div class="card-body">
                        <h3 class="card-title">{{ $room->title }}</h3>

                        <div class="card-provider">
                            @if($room->company)
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 21h18"/>
                                    <path d="M9 21V8a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v13"/>
                                    <path d="M3 21V10a1 1 0 0 1 1-1h2"/>
                                    <path d="M18 21V10a1 1 0 0 0-1-1h-2"/>
                                </svg>
                                {{ $room->company->name }}
                            @else
                                {{ $room->provider }}
                            @endif
                        </div>

                        <div class="card-meta">
                            @if($room->municipality)
                                <span class="card-chip location">📍 {{ $room->municipality->name }}</span>
                            @endif
                            @if($room->duration_display)
                                <span class="card-chip">⏱️ {{ $room->formatted_duration }}</span>
                            @endif
                            @if($room->min_players && $room->max_players)
                                <span class="card-chip">👥 {{ $room->min_players }}-{{ $room->max_players }}</span>
                            @endif
                        </div>

                        <div class="card-footer">
                            <div class="card-stats">
                                @if($room->difficulty)
                                    <span title="Difficulty">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 2v20M2 12h20"/>
                                        </svg>
                                        {{ number_format($room->difficulty, 1) }}/10
                                    </span>
                                @endif
                                @if($room->escape_rate)
                                    <span title="Escape Rate">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                            <polyline points="22,4 12,14.01 9,11.01"/>
                                        </svg>
                                        {{ number_format($room->escape_rate) }}%
                                    </span>
                                @endif
                            </div>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--muted)" stroke-width="2">
                                <polyline points="9,18 15,12 9,6"/>
                            </svg>
                        </div>
                    </div>
                </div>
            @endforeach
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
            // Remove the card from the grid with animation
            const card = button.closest('.room-card');
            card.style.transition = 'opacity .3s, transform .3s';
            card.style.opacity = '0';
            card.style.transform = 'scale(0.9)';
            setTimeout(() => {
                card.remove();
                // Update count
                const countEl = document.querySelector('.page-count');
                const currentCount = parseInt(countEl.textContent) - 1;
                countEl.textContent = `${currentCount} ${currentCount === 1 ? 'room' : 'rooms'}`;

                // Show empty state if no more cards
                if (currentCount === 0) {
                    location.reload();
                }
            }, 300);
        }
    } catch (error) {
        console.error('Error toggling favourite:', error);
    }
}
</script>
@include('partials.search-script')
</body>
</html>

