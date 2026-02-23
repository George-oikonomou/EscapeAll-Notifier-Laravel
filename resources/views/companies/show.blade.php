<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $company->name }} • EscapeNotifier</title>
    <meta name="description" content="{{ $company->name }} — {{ $totalRooms }} escape rooms at {{ $company->address }}">
    <link rel="icon" href="/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css','resources/js/app.js'])
    @include('partials.search-styles')
    <style>
        :root{
            --bg-1:#0b1020; --bg-2:#0a0f1c; --bg-3:#050814;
            --glass:rgba(13,18,30,.55); --glass-border:rgba(255,255,255,.08);
            --text:#e5e7eb; --muted:#9ca3af;
            --accent:#60a5fa; --accent2:#a78bfa; --accent3:#22d3ee;
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

        .container{max-width:1200px; margin:0 auto; padding:2rem 1.5rem}

        /* ── Hero Card ── */
        .hero{
            position:relative; border-radius:22px; padding:2rem; overflow:hidden;
            background:var(--glass); border:1px solid var(--glass-border);
            backdrop-filter:blur(18px); margin-bottom:2rem;
            box-shadow:0 10px 40px rgba(0,0,0,.5), inset 0 1px 0 rgba(255,255,255,.05);
        }
        .hero::before{
            content:""; position:absolute; inset:-2px; border-radius:24px; padding:2px; pointer-events:none;
            background:conic-gradient(from 180deg, rgba(96,165,250,.35), rgba(167,139,250,.25), rgba(34,211,238,.3), rgba(96,165,250,.35));
            -webkit-mask:linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite:xor; mask-composite:exclude; opacity:.3; filter:blur(6px);
        }
        .hero-top{display:flex; gap:1.5rem; align-items:flex-start; flex-wrap:wrap}
        .hero-logo{
            width:90px; height:90px; border-radius:18px; object-fit:cover;
            background:rgba(255,255,255,.05); border:1px solid var(--glass-border); flex-shrink:0;
        }
        .hero-logo-placeholder{
            width:90px; height:90px; border-radius:18px; display:flex; align-items:center; justify-content:center;
            background:rgba(96,165,250,.1); border:1px solid var(--glass-border); font-size:2.2rem; font-weight:800; color:var(--accent);
        }
        .hero-info{flex:1; min-width:200px}
        .hero-name{
            margin:0 0 .25rem; font-weight:800; font-size:clamp(1.5rem,1.2rem+1.5vw,2.2rem);
            background:linear-gradient(90deg,#fff,#cbd5e1); -webkit-background-clip:text; background-clip:text; color:transparent;
        }
        .hero-address{color:var(--muted); font-size:.95rem; margin-bottom:.75rem; line-height:1.5}

        /* ── Stat Pills ── */
        .stat-row{display:flex; gap:.75rem; flex-wrap:wrap; margin-top:.5rem}
        .pill{
            display:inline-flex; align-items:center; gap:.4rem; padding:.45rem .85rem;
            border-radius:12px; font-size:.85rem;
            background:rgba(0,0,0,.3); border:1px solid var(--glass-border);
        }
        .pill .icon{font-size:1rem}
        .pill .val{color:#fff; font-weight:700}
        .pill .lbl{color:var(--muted)}

        /* ── Action Btns ── */
        .actions{display:flex; gap:.75rem; flex-wrap:wrap; margin-top:1rem}
        .btn{
            display:inline-flex; align-items:center; gap:.45rem; padding:.55rem 1rem;
            border-radius:12px; font-size:.85rem; font-weight:600; text-decoration:none;
            transition:transform .2s, box-shadow .2s; border:1px solid rgba(255,255,255,.15);
        }
        .btn:hover{transform:translateY(-1px); box-shadow:0 4px 16px rgba(0,0,0,.3)}
        .btn-primary{background:var(--accent); color:#0b1020}
        .btn-secondary{background:rgba(167,139,250,.2); color:#c4b5fd; border-color:rgba(167,139,250,.3)}

        /* ── Section ── */
        .section-title{
            font-weight:700; font-size:1.2rem; margin:0 0 1rem;
            color:#fff; display:flex; align-items:center; gap:.5rem;
        }

        /* ── Room Cards Grid ── */
        .rooms-grid{
            display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:1.25rem;
        }
        .room-card{
            position:relative; border-radius:16px; overflow:hidden;
            background:var(--glass); border:1px solid var(--glass-border);
            backdrop-filter:blur(14px); transition:border-color .25s, transform .25s, box-shadow .25s;
            text-decoration:none; color:var(--text); display:flex; flex-direction:column;
        }
        .room-card:hover{border-color:rgba(255,255,255,.12); transform:translateY(-3px); box-shadow:0 12px 40px rgba(0,0,0,.4)}

        .room-img-wrap{position:relative; height:160px; overflow:hidden; background:rgba(0,0,0,.3)}
        .room-img{width:100%; height:100%; object-fit:cover; transition:transform .4s}
        .room-card:hover .room-img{transform:scale(1.05)}
        .room-img-placeholder{width:100%; height:100%; display:flex; align-items:center; justify-content:center;
            font-size:2.5rem; background:linear-gradient(135deg, rgba(96,165,250,.1), rgba(167,139,250,.1))}

        .room-rating-badge{
            position:absolute; top:.75rem; right:.75rem; padding:.25rem .6rem;
            border-radius:8px; font-size:.8rem; font-weight:700;
            background:rgba(0,0,0,.6); backdrop-filter:blur(8px); border:1px solid rgba(255,255,255,.1);
        }

        .room-body{padding:1rem 1.15rem 1.15rem; flex:1; display:flex; flex-direction:column}
        .room-title{font-weight:700; font-size:1rem; margin-bottom:.4rem; line-height:1.3}
        .room-cats{display:flex; flex-wrap:wrap; gap:.3rem; margin-bottom:.6rem}
        .room-cat{
            font-size:.68rem; padding:.15rem .45rem; border-radius:6px;
            background:rgba(34,211,238,.1); border:1px solid rgba(34,211,238,.2); color:#67e8f9;
        }
        .room-stats{display:flex; gap:.65rem; flex-wrap:wrap; margin-top:auto; padding-top:.5rem}
        .room-stat{font-size:.78rem; color:var(--muted); display:flex; align-items:center; gap:.25rem}
        .room-stat span{color:#cbd5e1; font-weight:600}

        @media(max-width:640px){
            .hero-top{flex-direction:column; align-items:center; text-align:center}
            .stat-row{justify-content:center}
            .actions{justify-content:center}
            .rooms-grid{grid-template-columns:1fr}
            .container{padding:1.5rem 1rem}
        }
    </style>
</head>
<body>

@include('partials.topnav')

<div class="container">
    {{-- HERO CARD --}}
    <div class="hero">
        <div class="hero-top">
            @if($company->logo_url)
                <img class="hero-logo"
                     src="{{ Str::startsWith($company->logo_url, 'http') ? $company->logo_url : 'https://www.escapeall.gr' . $company->logo_url }}"
                     alt="{{ $company->name }}">
            @else
                <div class="hero-logo-placeholder">{{ mb_substr($company->name, 0, 1) }}</div>
            @endif
            <div class="hero-info">
                <h1 class="hero-name">{{ $company->name }}</h1>
                @if($company->full_address)
                    <div class="hero-address">📍 {{ $company->full_address }}</div>
                @elseif($company->address)
                    <div class="hero-address">📍 {{ $company->address }}</div>
                @endif

                <div class="stat-row">
                    <div class="pill">
                        <span class="icon">🚪</span>
                        <span class="val">{{ $totalRooms }}</span>
                        <span class="lbl">{{ $totalRooms === 1 ? 'room' : 'rooms' }}</span>
                    </div>
                    @if($avgRating)
                        <div class="pill">
                            <span class="icon">⭐</span>
                            <span class="val">{{ number_format($avgRating, 1) }}</span>
                            <span class="lbl">avg rating</span>
                        </div>
                    @endif
                    @if($company->municipality_external_id)
                        <div class="pill">
                            <span class="icon">🏙️</span>
                            <span class="lbl">{{ $company->municipality_external_id }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="actions">
            @if($company->latitude && $company->longitude)
                <a class="btn btn-primary"
                   href="https://www.google.com/maps?q={{ $company->latitude }},{{ $company->longitude }}"
                   target="_blank" rel="noopener">
                    📍 Open in Google Maps
                </a>
            @endif
            <a class="btn btn-secondary" href="{{ route('companies.index') }}">
                ← All Companies
            </a>
        </div>
    </div>

    {{-- ROOMS LIST --}}
    @if($company->rooms->isNotEmpty())
        <h2 class="section-title">🚪 Escape Rooms ({{ $totalRooms }})</h2>
        <div class="rooms-grid">
            @foreach($company->rooms as $room)
                <a class="room-card" href="{{ route('rooms.show', $room) }}">
                    <div class="room-img-wrap">
                        @if($room->image_url)
                            <img class="room-img"
                                 src="{{ Str::startsWith($room->image_url, 'http') ? $room->image_url : 'https://www.escapeall.gr' . $room->image_url }}"
                                 alt="{{ $room->title }}" loading="lazy">
                        @else
                            <div class="room-img-placeholder">🔐</div>
                        @endif
                        @if($room->rating)
                            <div class="room-rating-badge">⭐ {{ number_format($room->rating, 1) }}</div>
                        @endif
                    </div>
                    <div class="room-body">
                        <div class="room-title">{{ $room->title }}</div>
                        @if(!empty($room->categories))
                            <div class="room-cats">
                                @foreach(array_slice($room->categories, 0, 4) as $cat)
                                    <span class="room-cat">{{ $cat }}</span>
                                @endforeach
                            </div>
                        @endif
                        <div class="room-stats">
                            @if($room->duration_display)
                                <div class="room-stat">⏱ <span>{{ $room->formatted_duration }}</span></div>
                            @endif
                            @if($room->min_players || $room->max_players)
                                <div class="room-stat">👥 <span>{{ $room->min_players }}–{{ $room->max_players }}</span></div>
                            @endif
                            @if($room->escape_rate)
                                <div class="room-stat">🏆 <span>{{ $room->escape_rate }}%</span></div>
                            @endif
                            @if($room->difficulty)
                                <div class="room-stat">💀 <span>{{ $room->difficulty }}/10</span></div>
                            @endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <p style="color:var(--muted); text-align:center; margin-top:2rem">No rooms found for this company.</p>
    @endif
</div>

@include('partials.search-script')
</body>
</html>
