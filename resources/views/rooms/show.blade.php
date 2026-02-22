<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $room->title }} • {{ $room->provider }}</title>
    <meta name="description" content="{{ html_entity_decode($room->short_description ?: Str::limit($room->description, 160), ENT_QUOTES | ENT_HTML5, 'UTF-8') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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

        .container{max-width:1100px; margin:0 auto; padding:2rem 1.5rem; padding-bottom:4rem}

        /* ── Hero ── */
        .hero{
            position:relative; border-radius:22px; overflow:hidden;
            background:var(--glass); border:1px solid var(--glass-border);
            backdrop-filter:blur(18px); margin-bottom:1.5rem;
            box-shadow:0 10px 40px rgba(0,0,0,.5), inset 0 1px 0 rgba(255,255,255,.05);
        }
        .hero::before{
            content:""; position:absolute; inset:-2px; border-radius:24px; padding:2px; pointer-events:none;
            background:conic-gradient(from 180deg, rgba(96,165,250,.35), rgba(167,139,250,.25), rgba(34,211,238,.3), rgba(96,165,250,.35));
            -webkit-mask:linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite:xor; mask-composite:exclude; opacity:.3; filter:blur(6px); z-index:1;
        }
        .hero-img-wrap{position:relative; height:280px; overflow:hidden; background:rgba(0,0,0,.4)}
        .hero-img{width:100%; height:100%; object-fit:cover}
        .hero-img-placeholder{width:100%; height:100%; display:flex; align-items:center; justify-content:center;
            font-size:5rem; background:linear-gradient(135deg, rgba(96,165,250,.08), rgba(167,139,250,.08))}
        .hero-overlay{
            position:absolute; bottom:0; left:0; right:0;
            background:linear-gradient(transparent, rgba(5,8,20,.85)); padding:1.5rem 2rem .8rem;
        }
        .hero-rating{
            position:absolute; top:1rem; right:1rem; display:flex; align-items:center; gap:.35rem;
            padding:.4rem .75rem; border-radius:10px; font-weight:700; font-size:.95rem;
            background:rgba(0,0,0,.6); backdrop-filter:blur(8px); border:1px solid rgba(255,255,255,.1); z-index:2;
        }
        .hero-body{padding:1.25rem 2rem 1.5rem}
        .room-title{
            margin:0 0 .3rem; font-weight:800; font-size:clamp(1.5rem,1.2rem+1.5vw,2.2rem);
            background:linear-gradient(90deg,#fff,#cbd5e1); -webkit-background-clip:text; background-clip:text; color:transparent;
        }
        .room-subtitle{
            color:var(--muted); font-size:.95rem; display:flex; align-items:center; gap:.5rem; flex-wrap:wrap;
        }
        .room-subtitle a{color:var(--accent); text-decoration:none; font-weight:600}
        .room-subtitle a:hover{text-decoration:underline}

        /* ── Stats Bar ── */
        .stats-bar{
            display:grid; grid-template-columns:repeat(auto-fit, minmax(130px, 1fr)); gap:.75rem;
            margin-bottom:1.5rem;
        }
        .stat-card{
            text-align:center; padding:1rem .75rem; border-radius:16px;
            background:var(--glass); border:1px solid var(--glass-border);
            backdrop-filter:blur(14px);
        }
        .stat-icon{font-size:1.4rem; margin-bottom:.3rem}
        .stat-value{font-size:1.3rem; font-weight:800; color:#fff}
        .stat-label{font-size:.75rem; color:var(--muted); margin-top:.15rem; text-transform:uppercase; letter-spacing:.04em}

        /* ── Difficulty Bar ── */
        .difficulty-bar{margin-top:.35rem}
        .difficulty-track{
            height:6px; border-radius:3px; background:rgba(255,255,255,.08); overflow:hidden;
        }
        .difficulty-fill{
            height:100%; border-radius:3px; transition:width .6s ease;
        }

        /* ── Content Sections ── */
        .section{
            border-radius:18px; padding:1.5rem; margin-bottom:1.25rem;
            background:var(--glass); border:1px solid var(--glass-border);
            backdrop-filter:blur(14px);
        }
        .section-title{
            margin:0 0 1rem; font-weight:700; font-size:1.1rem; color:#fff;
            display:flex; align-items:center; gap:.5rem;
        }
        .description-text{
            color:var(--text); line-height:1.7; font-size:.95rem; white-space:pre-line;
        }

        /* ── Categories ── */
        .cats{display:flex; flex-wrap:wrap; gap:.4rem}
        .cat{
            font-size:.78rem; padding:.3rem .65rem; border-radius:8px;
            background:rgba(34,211,238,.1); border:1px solid rgba(34,211,238,.2); color:#67e8f9;
        }

        /* ── Languages ── */
        .langs{display:flex; flex-wrap:wrap; gap:.4rem}
        .lang{
            font-size:.78rem; padding:.3rem .65rem; border-radius:8px;
            background:rgba(167,139,250,.12); border:1px solid rgba(167,139,250,.25); color:#c4b5fd;
        }

        /* ── Video ── */
        .video-wrap{
            position:relative; width:100%; padding-bottom:56.25%; border-radius:14px; overflow:hidden;
            background:#000;
        }
        .video-wrap iframe{position:absolute; inset:0; width:100%; height:100%; border:0}

        /* ── Company Card ── */
        .company-link{
            display:flex; gap:1rem; align-items:center; padding:1rem; border-radius:14px;
            background:rgba(0,0,0,.2); border:1px solid var(--glass-border);
            text-decoration:none; color:var(--text); transition:border-color .2s, background .2s;
        }
        .company-link:hover{border-color:rgba(255,255,255,.15); background:rgba(255,255,255,.03)}
        .company-logo{
            width:52px; height:52px; border-radius:12px; object-fit:cover;
            background:rgba(255,255,255,.05); border:1px solid var(--glass-border); flex-shrink:0;
        }
        .company-logo-ph{
            width:52px; height:52px; border-radius:12px; display:flex; align-items:center; justify-content:center;
            background:rgba(96,165,250,.1); border:1px solid var(--glass-border); font-size:1.3rem; font-weight:700; color:var(--accent);
        }
        .company-name{font-weight:700; font-size:.95rem}
        .company-addr{color:var(--muted); font-size:.82rem; margin-top:.1rem}
        .company-arrow{margin-left:auto; color:var(--muted); font-size:1.2rem}

        /* ── Detail Rows ── */
        .detail-grid{display:grid; grid-template-columns:1fr 1fr; gap:.6rem}
        .detail-row{
            display:flex; gap:.5rem; align-items:center; padding:.6rem .85rem;
            border-radius:10px; background:rgba(0,0,0,.2); border:1px solid var(--glass-border);
        }
        .detail-row .dlabel{color:var(--muted); font-size:.82rem; min-width:90px}
        .detail-row .dvalue{color:#e2e8f0; font-size:.88rem; font-weight:500}

        /* ── Actions ── */
        .actions{display:flex; gap:.75rem; flex-wrap:wrap; margin-top:1.5rem}
        .btn{
            display:inline-flex; align-items:center; gap:.45rem; padding:.6rem 1.1rem;
            border-radius:12px; font-size:.88rem; font-weight:600; text-decoration:none;
            transition:transform .2s, box-shadow .2s; border:1px solid rgba(255,255,255,.15);
        }
        .btn:hover{transform:translateY(-1px); box-shadow:0 4px 16px rgba(0,0,0,.3)}
        .btn-primary{background:var(--accent); color:#0b1020}
        .btn-secondary{background:rgba(167,139,250,.2); color:#c4b5fd; border-color:rgba(167,139,250,.3)}
        .btn-ghost{background:transparent; color:var(--muted); border-color:var(--glass-border)}
        .btn-favourite{
            background:rgba(239,68,68,.15); color:#fca5a5; border-color:rgba(239,68,68,.3);
            transition:all .2s;
        }
        .btn-favourite:hover{background:rgba(239,68,68,.25)}
        .btn-favourite.active{background:rgba(239,68,68,.3); color:#ef4444}
        .btn-favourite svg{transition:transform .2s}
        .btn-favourite:hover svg{transform:scale(1.15)}

        /* Favourite button in hero */
        .hero-fav-btn{
            position:absolute; top:1rem; left:1rem; z-index:10;
            width:44px; height:44px; border-radius:50%;
            background:rgba(0,0,0,.6); backdrop-filter:blur(8px);
            border:1px solid rgba(255,255,255,.15);
            display:flex; align-items:center; justify-content:center;
            cursor:pointer; transition:all .2s;
        }
        .hero-fav-btn:hover{background:rgba(239,68,68,.3); transform:scale(1.1)}
        .hero-fav-btn svg{width:22px; height:22px; color:#fca5a5; transition:all .2s}
        .hero-fav-btn.active svg{color:#ef4444; fill:#ef4444}
        .hero-fav-btn:hover svg{transform:scale(1.1)}

        /* Reminder button in hero */
        .hero-reminder-btn{
            position:absolute; top:1rem; left:60px; z-index:10;
            width:44px; height:44px; border-radius:50%;
            background:rgba(0,0,0,.6); backdrop-filter:blur(8px);
            border:1px solid rgba(255,255,255,.15);
            display:flex; align-items:center; justify-content:center;
            cursor:pointer; transition:all .2s;
        }
        .hero-reminder-btn:hover{background:rgba(251,191,36,.3); transform:scale(1.1)}
        .hero-reminder-btn svg{width:22px; height:22px; color:#fcd34d; transition:all .2s}
        .hero-reminder-btn.active svg{color:#fbbf24; fill:#fbbf24}
        .hero-reminder-btn:hover svg{transform:scale(1.1)}

        /* Reminder action button */
        .btn-reminder{
            background:rgba(251,191,36,.15); color:#fcd34d; border-color:rgba(251,191,36,.3);
            transition:all .2s;
        }
        .btn-reminder:hover{background:rgba(251,191,36,.25)}
        .btn-reminder.active{background:rgba(251,191,36,.3); color:#fbbf24}
        .btn-reminder svg{transition:transform .2s}
        .btn-reminder:hover svg{transform:scale(1.15)}

        /* Coming soon badge */
        .coming-soon-badge{
            display:inline-flex; align-items:center; gap:.35rem;
            padding:.35rem .7rem; border-radius:10px; font-size:.85rem; font-weight:600;
            background:rgba(251,191,36,.15); border:1px solid rgba(251,191,36,.3); color:#fbbf24;
        }

        /* ── Availability Calendar ── */
        .cal-section{position:relative; overflow:hidden}
        .cal-header{display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem}
        .cal-month{font-weight:700; font-size:1.05rem; color:#fff; min-width:160px; text-align:center}
        .cal-nav{
            width:36px; height:36px; border-radius:10px; border:1px solid var(--glass-border);
            background:rgba(255,255,255,.04); color:var(--muted); cursor:pointer;
            display:flex; align-items:center; justify-content:center;
            font-size:1.1rem; transition:all .2s;
        }
        .cal-nav:hover{background:rgba(96,165,250,.15); color:#fff; border-color:rgba(96,165,250,.3)}
        .cal-weekdays{display:grid; grid-template-columns:repeat(7,1fr); gap:4px; margin-bottom:6px}
        .cal-wd{text-align:center; font-size:.7rem; color:var(--muted); text-transform:uppercase; letter-spacing:.04em; padding:4px 0; font-weight:600}
        .cal-grid{display:grid; grid-template-columns:repeat(7,1fr); gap:4px; min-height:300px; position:relative}
        .cal-grid-overlay{
            position:absolute; inset:0; z-index:5; border-radius:10px;
            background:rgba(5,8,20,.6); backdrop-filter:blur(2px);
            display:flex; align-items:center; justify-content:center;
            opacity:0; pointer-events:none; transition:opacity .2s ease;
        }
        .cal-grid-overlay.visible{opacity:1; pointer-events:auto}
        .cal-day{
            position:relative; text-align:center; padding:10px 4px; border-radius:10px;
            font-size:.85rem; color:var(--muted); cursor:default; transition:all .25s;
            border:1px solid transparent; min-height:42px;
        }
        .cal-day.empty{pointer-events:none}
        .cal-day.today{color:#fff; font-weight:700; border-color:rgba(96,165,250,.25)}
        .cal-day.has-slots{
            color:#fff; cursor:pointer; background:rgba(96,165,250,.08);
            border-color:rgba(96,165,250,.15);
        }
        .cal-day.has-slots::after{
            content:""; position:absolute; bottom:5px; left:50%; transform:translateX(-50%);
            width:5px; height:5px; border-radius:50%;
            background:var(--accent); box-shadow:0 0 6px rgba(96,165,250,.6);
            animation:calPulse 2s ease-in-out infinite;
        }
        .cal-day.has-slots:hover{
            background:rgba(96,165,250,.2); border-color:rgba(96,165,250,.4);
            transform:translateY(-1px); box-shadow:0 4px 12px rgba(96,165,250,.15);
        }
        .cal-day.selected{
            background:linear-gradient(135deg, rgba(96,165,250,.25), rgba(167,139,250,.2));
            border-color:rgba(96,165,250,.4); color:#fff; font-weight:700;
            box-shadow:0 0 20px rgba(96,165,250,.15);
        }
        .cal-day.selected::after{display:none}
        @keyframes calPulse{
            0%,100%{opacity:1; transform:translateX(-50%) scale(1)}
            50%{opacity:.4; transform:translateX(-50%) scale(1.3)}
        }

        /* Time slots panel */
        .cal-slots{
            margin-top:1rem; overflow:hidden;
            max-height:0; opacity:0;
            transition:max-height .4s cubic-bezier(.4,0,.2,1), opacity .3s ease, margin-top .3s ease;
        }
        .cal-slots.open{max-height:400px; opacity:1}
        .cal-slots-date{font-size:.85rem; color:var(--muted); margin-bottom:.6rem; font-weight:500}
        .cal-slots-grid{display:flex; flex-wrap:wrap; gap:.4rem}
        .cal-slot{
            font-size:.82rem; padding:.4rem .7rem; border-radius:8px; font-weight:600;
            background:rgba(34,211,238,.08); border:1px solid rgba(34,211,238,.2); color:#67e8f9;
            transition:all .2s; cursor:default;
            animation:slotIn .3s ease backwards;
        }
        .cal-slot:hover{background:rgba(34,211,238,.15); transform:translateY(-1px)}
        @keyframes slotIn{from{opacity:0; transform:translateY(6px) scale(.95)}to{opacity:1; transform:translateY(0) scale(1)}}

        .cal-empty-msg{color:var(--muted); font-size:.85rem; text-align:center; padding:1.5rem 0}
        .cal-loading{display:flex; align-items:center; justify-content:center; gap:.5rem; padding:2rem 0; color:var(--muted); font-size:.85rem}
        .cal-spinner{width:18px; height:18px; border:2px solid var(--glass-border); border-top-color:var(--accent); border-radius:50%; animation:spin .6s linear infinite}
        @keyframes spin{to{transform:rotate(360deg)}}

        /* Refresh button */
        .cal-title-row{display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem}
        .cal-title-row .section-title{margin:0}
        .cal-refresh{
            display:inline-flex; align-items:center; gap:.35rem;
            padding:.35rem .7rem; border-radius:8px; font-size:.78rem; font-weight:600;
            background:rgba(96,165,250,.1); border:1px solid rgba(96,165,250,.2); color:var(--accent);
            cursor:pointer; transition:all .25s;
        }
        .cal-refresh:hover{background:rgba(96,165,250,.2); border-color:rgba(96,165,250,.35); transform:translateY(-1px)}
        .cal-refresh:active{transform:scale(.97)}
        .cal-refresh.spinning svg{animation:spin .8s linear infinite}
        .cal-refresh:disabled{opacity:.5; cursor:not-allowed; transform:none}
        .cal-refresh svg{width:14px; height:14px; transition:transform .2s}

        /* Progress bar */
        .cal-progress-wrap{
            overflow:hidden; max-height:0; opacity:0;
            transition:max-height .4s ease, opacity .3s ease, margin .3s ease;
            margin:0;
        }
        .cal-progress-wrap.active{max-height:80px; opacity:1; margin-bottom:1rem}
        .cal-progress-track{
            height:4px; border-radius:4px; background:rgba(255,255,255,.06);
            overflow:hidden; position:relative;
        }
        .cal-progress-fill{
            height:100%; border-radius:4px; width:0%;
            background:linear-gradient(90deg, var(--accent), var(--accent2), var(--accent3));
            background-size:200% 100%;
            animation:progressShimmer 2s ease-in-out infinite;
            transition:width .5s cubic-bezier(.4,0,.2,1);
            box-shadow:0 0 8px rgba(96,165,250,.4);
        }
        @keyframes progressShimmer{0%{background-position:200% 0}100%{background-position:0 0}}
        .cal-progress-status{
            font-size:.75rem; color:var(--muted); margin-top:.4rem;
            display:flex; align-items:center; gap:.4rem;
        }
        .cal-progress-pct{color:var(--accent); font-weight:700; min-width:28px; text-align:right}

        @media(max-width:640px){
            .hero-img-wrap{height:200px}
            .hero-body{padding:1rem 1.25rem}
            .stats-bar{grid-template-columns:repeat(2, 1fr)}
            .detail-grid{grid-template-columns:1fr}
            .container{padding:1.25rem 1rem}
            .topnav{padding:.8rem 1rem; gap:1rem}
            .cal-day{padding:8px 2px; font-size:.78rem; min-height:36px}
            .cal-nav{width:32px; height:32px}
        }
    </style>
</head>
<body>

@include('partials.topnav')

<div class="container">
    {{-- ═══════════════════ HERO ═══════════════════ --}}
    <div class="hero">
        <div class="hero-img-wrap">
            @auth
                @php
                    $isFavourited = Auth::user()->hasFavourited($room);
                    $hasReminder = Auth::user()->hasReminder($room);
                @endphp
                <button class="hero-fav-btn {{ $isFavourited ? 'active' : '' }}"
                        id="hero-fav-btn"
                        onclick="toggleFavourite({{ $room->id }})"
                        title="{{ $isFavourited ? 'Remove from favourites' : 'Add to favourites' }}">
                    <svg viewBox="0 0 24 24" fill="{{ $isFavourited ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                    </svg>
                </button>
                <button class="hero-reminder-btn {{ $hasReminder ? 'active' : '' }}"
                        id="hero-reminder-btn"
                        onclick="toggleReminder({{ $room->id }})"
                        title="{{ $hasReminder ? 'Remove reminder' : 'Set reminder' }}">
                    <svg viewBox="0 0 24 24" fill="{{ $hasReminder ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                </button>
            @endauth
            @if($room->image_url)
                <img class="hero-img"
                     src="{{ Str::startsWith($room->image_url, 'http') ? $room->image_url : 'https://www.escapeall.gr' . $room->image_url }}"
                     alt="{{ $room->title }}">
            @else
                <div class="hero-img-placeholder">🔐</div>
            @endif
            @if($room->rating)
                <div class="hero-rating">⭐ {{ number_format($room->rating, 1) }}
                    @if($room->reviews_count)
                        <span style="color:var(--muted);font-size:.8rem;font-weight:400">({{ $room->reviews_count }})</span>
                    @endif
                </div>
            @endif
            <div class="hero-overlay"></div>
        </div>
        <div class="hero-body">
            <h1 class="room-title">
                {{ $room->title }}
                @if($room->is_coming_soon)
                    <span class="coming-soon-badge">🚀 Coming Soon</span>
                @endif
            </h1>
            <div class="room-subtitle">
                @if($room->company)
                    <span>by</span>
                    <a href="{{ route('companies.show', $room->company) }}">{{ $room->provider }}</a>
                @else
                    <span>by {{ $room->provider }}</span>
                @endif
                @if($room->municipality)
                    <span>•</span>
                    <span>📍 {{ $room->municipality->name }}</span>
                @endif
            </div>
        </div>
    </div>

    {{-- ═══════════════════ STATS BAR ═══════════════════ --}}
    <div class="stats-bar">
        @if($room->duration_display)
            <div class="stat-card">
                <div class="stat-icon">⏱</div>
                <div class="stat-value">{{ $room->duration_display }}′</div>
                <div class="stat-label">Duration</div>
            </div>
        @endif
        @if($room->min_players || $room->max_players)
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-value">{{ $room->min_players }}–{{ $room->max_players }}</div>
                <div class="stat-label">Players</div>
            </div>
        @endif
        @if($room->escape_rate)
            <div class="stat-card">
                <div class="stat-icon">🏆</div>
                <div class="stat-value">{{ $room->escape_rate }}%</div>
                <div class="stat-label">Escape Rate</div>
            </div>
        @endif
        @if($room->difficulty)
            <div class="stat-card">
                <div class="stat-icon">💀</div>
                <div class="stat-value">{{ $room->difficulty }}/10</div>
                <div class="stat-label">Difficulty</div>
                <div class="difficulty-bar">
                    <div class="difficulty-track">
                        <div class="difficulty-fill" style="width:{{ ($room->difficulty / 10) * 100 }}%;
                            background:linear-gradient(90deg,
                                {{ $room->difficulty <= 3 ? '#22c55e' : ($room->difficulty <= 6 ? '#eab308' : ($room->difficulty <= 8 ? '#f97316' : '#ef4444')) }},
                                {{ $room->difficulty <= 3 ? '#4ade80' : ($room->difficulty <= 6 ? '#facc15' : ($room->difficulty <= 8 ? '#fb923c' : '#f87171')) }}
                            )"></div>
                    </div>
                </div>
            </div>
        @endif
        @if($room->rating)
            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-value">{{ number_format($room->rating, 1) }}</div>
                <div class="stat-label">Rating{{ $room->reviews_count ? ' ('.$room->reviews_count.')' : '' }}</div>
            </div>
        @endif
    </div>

    {{-- ═══════════════════ AVAILABILITY CALENDAR ═══════════════════ --}}
    <div class="section cal-section" id="availability-calendar">
        <div class="cal-title-row">
            <h2 class="section-title">📅 Availability</h2>
            <button class="cal-refresh" id="cal-refresh-btn" title="Refresh from EscapeAll">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/>
                    <path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/>
                </svg>
                Refresh
            </button>
        </div>
        <div class="cal-progress-wrap" id="cal-progress-wrap">
            <div class="cal-progress-track">
                <div class="cal-progress-fill" id="cal-progress-fill"></div>
            </div>
            <div class="cal-progress-status">
                <span class="cal-progress-pct" id="cal-progress-pct">0%</span>
                <span id="cal-progress-msg">Preparing...</span>
            </div>
        </div>
        <div class="cal-header">
            <button class="cal-nav" id="cal-prev" title="Previous month">‹</button>
            <div class="cal-month" id="cal-month-label"></div>
            <button class="cal-nav" id="cal-next" title="Next month">›</button>
        </div>
        <div class="cal-weekdays">
            <div class="cal-wd">Mon</div>
            <div class="cal-wd">Tue</div>
            <div class="cal-wd">Wed</div>
            <div class="cal-wd">Thu</div>
            <div class="cal-wd">Fri</div>
            <div class="cal-wd">Sat</div>
            <div class="cal-wd">Sun</div>
        </div>
        <div class="cal-grid" id="cal-grid"></div>
        <div class="cal-slots" id="cal-slots">
            <div class="cal-slots-date" id="cal-slots-date"></div>
            <div class="cal-slots-grid" id="cal-slots-grid"></div>
        </div>
    </div>

    {{-- ═══════════════════ DESCRIPTION ═══════════════════ --}}
    @if($room->description || $room->short_description)
        <div class="section">
            <h2 class="section-title">📖 Description</h2>
            <div class="description-text">
                {!! nl2br(e(html_entity_decode($room->description ?: $room->short_description, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) !!}
            </div>
            @if($room->description && $room->short_description && $room->description !== $room->short_description)
                <details style="margin-top:.75rem">
                    <summary style="color:var(--accent); cursor:pointer; font-size:.85rem">Short description</summary>
                    <p style="color:var(--muted); font-size:.88rem; margin-top:.5rem">{!! nl2br(e(html_entity_decode($room->short_description, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) !!}</p>
                </details>
            @endif
        </div>
    @endif

    {{-- ═══════════════════ CATEGORIES & LANGUAGES ═══════════════════ --}}
    @php
        $categories = $room->categories ?? [];
        $languages = $room->languages ?? [];

        // Handle case where data might be a JSON string instead of array
        if (is_string($categories) && !empty($categories)) {
            $decoded = json_decode($categories, true);
            $categories = is_array($decoded) ? $decoded : [];
        }
        if (is_string($languages) && !empty($languages)) {
            $decoded = json_decode($languages, true);
            $languages = is_array($decoded) ? $decoded : [];
        }

        // Ensure they are arrays
        if (!is_array($categories)) {
            $categories = [];
        }
        if (!is_array($languages)) {
            $languages = [];
        }
    @endphp
    @if(!empty($categories) || !empty($languages))
        <div class="section">
            @if(!empty($categories))
                <h2 class="section-title">🏷️ Categories</h2>
                <div class="cats">
                    @foreach($categories as $cat)
                        <span class="cat">{{ $cat }}</span>
                    @endforeach
                </div>
            @endif

            @if(!empty($languages))
                <h2 class="section-title" style="margin-top:{{ !empty($categories) ? '1.25rem' : '0' }}">🌐 Languages</h2>
                <div class="langs">
                    @foreach($languages as $lang)
                        <span class="lang">{{ $lang }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- ═══════════════════ VIDEO ═══════════════════ --}}
    @if($room->video_url)
        <div class="section">
            <h2 class="section-title">🎬 Video</h2>
            <div class="video-wrap">
                <iframe src="{{ $room->video_url }}"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen loading="lazy"></iframe>
            </div>
        </div>
    @endif

    {{-- ═══════════════════ COMPANY INFO ═══════════════════ --}}
    @if($room->company)
        <div class="section">
            <h2 class="section-title">🏢 Company</h2>
            <a class="company-link" href="{{ route('companies.show', $room->company) }}">
                @if($room->company->logo_url)
                    <img class="company-logo"
                         src="{{ Str::startsWith($room->company->logo_url, 'http') ? $room->company->logo_url : 'https://www.escapeall.gr' . $room->company->logo_url }}"
                         alt="{{ $room->company->name }}">
                @else
                    <div class="company-logo-ph">{{ mb_substr($room->company->name, 0, 1) }}</div>
                @endif
                <div>
                    <div class="company-name">{{ $room->company->name }}</div>
                    @if($room->company->address)
                        <div class="company-addr">📍 {{ $room->company->address }}</div>
                    @endif
                </div>
                <div class="company-arrow">→</div>
            </a>
        </div>
    @endif

    {{-- ═══════════════════ DETAILS ═══════════════════ --}}
    <div class="section">
        <h2 class="section-title">📋 Details</h2>
        <div class="detail-grid">
            @if($room->slug)
                <div class="detail-row"><span class="dlabel">Slug</span><span class="dvalue">{{ $room->slug }}</span></div>
            @endif
            @if($room->external_id)
                <div class="detail-row"><span class="dlabel">External ID</span><span class="dvalue" style="font-size:.75rem;word-break:break-all">{{ $room->external_id }}</span></div>
            @endif
            @if($room->duration_display)
                <div class="detail-row"><span class="dlabel">Duration</span><span class="dvalue">{{ $room->formatted_duration }}</span></div>
            @endif
            @if($room->min_players && $room->max_players)
                <div class="detail-row"><span class="dlabel">Players</span><span class="dvalue">{{ $room->min_players }} – {{ $room->max_players }}</span></div>
            @endif
            @if($room->escape_rate)
                <div class="detail-row"><span class="dlabel">Escape Rate</span><span class="dvalue">{{ $room->escape_rate }}%</span></div>
            @endif
            @if($room->difficulty)
                <div class="detail-row"><span class="dlabel">Difficulty</span><span class="dvalue">{{ $room->difficulty }} / 10</span></div>
            @endif
            @if($room->rating)
                <div class="detail-row"><span class="dlabel">Rating</span><span class="dvalue">{{ number_format($room->rating, 1) }}{{ $room->reviews_count ? ' ('.$room->reviews_count.' reviews)' : '' }}</span></div>
            @endif
            @if($room->provider)
                <div class="detail-row"><span class="dlabel">Provider</span><span class="dvalue">{{ $room->provider }}</span></div>
            @endif
        </div>
    </div>

    {{-- ═══════════════════ ACTIONS ═══════════════════ --}}
    <div class="actions">
        @auth
            @php
                $isFavouritedAction = Auth::user()->hasFavourited($room);
                $hasReminderAction = Auth::user()->hasReminder($room);
            @endphp
            <button class="btn btn-favourite {{ $isFavouritedAction ? 'active' : '' }}"
                    id="action-fav-btn"
                    onclick="toggleFavourite({{ $room->id }})">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="{{ $isFavouritedAction ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
                <span id="fav-text">{{ $isFavouritedAction ? 'Favourited' : 'Add to Favourites' }}</span>
            </button>
            <button class="btn btn-reminder {{ $hasReminderAction ? 'active' : '' }}"
                    id="action-reminder-btn"
                    onclick="toggleReminder({{ $room->id }})">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="{{ $hasReminderAction ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
                <span id="reminder-text">{{ $hasReminderAction ? 'Reminder Set' : ($room->is_coming_soon ? 'Notify When Available' : 'Set Reminder') }}</span>
            </button>
        @endauth
        @if($room->slug)
            <a class="btn btn-primary"
               href="https://www.escapeall.gr/el/EscapeRoom/Details/{{ $room->slug }}"
               target="_blank" rel="noopener">
                🔗 View on EscapeAll
            </a>
        @endif
        @if($room->company && $room->company->latitude && $room->company->longitude)
            <a class="btn btn-secondary"
               href="https://www.google.com/maps?q={{ $room->company->latitude }},{{ $room->company->longitude }}"
               target="_blank" rel="noopener">
                📍 Google Maps
            </a>
        @endif
        <a class="btn btn-ghost" href="{{ route('home') }}">
            ← All Rooms
        </a>
    </div>
</div>

@auth
<script>
async function toggleFavourite(roomId) {
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

        if (data.success) {
            const heroBtn = document.getElementById('hero-fav-btn');
            const actionBtn = document.getElementById('action-fav-btn');
            const favText = document.getElementById('fav-text');

            if (data.is_favourited) {
                // Added to favourites
                if (heroBtn) {
                    heroBtn.classList.add('active');
                    heroBtn.querySelector('svg').setAttribute('fill', 'currentColor');
                    heroBtn.title = 'Remove from favourites';
                }
                if (actionBtn) {
                    actionBtn.classList.add('active');
                    actionBtn.querySelector('svg').setAttribute('fill', 'currentColor');
                }
                if (favText) favText.textContent = 'Favourited';
            } else {
                // Removed from favourites
                if (heroBtn) {
                    heroBtn.classList.remove('active');
                    heroBtn.querySelector('svg').setAttribute('fill', 'none');
                    heroBtn.title = 'Add to favourites';
                }
                if (actionBtn) {
                    actionBtn.classList.remove('active');
                    actionBtn.querySelector('svg').setAttribute('fill', 'none');
                }
                if (favText) favText.textContent = 'Add to Favourites';
            }
        }
    } catch (error) {
        console.error('Error toggling favourite:', error);
    }
}

async function toggleReminder(roomId) {
    try {
        const response = await fetch(`/reminders/${roomId}/toggle`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        });

        const data = await response.json();

        if (data.success) {
            const heroBtn = document.getElementById('hero-reminder-btn');
            const actionBtn = document.getElementById('action-reminder-btn');
            const reminderText = document.getElementById('reminder-text');

            if (data.has_reminder) {
                // Added reminder
                if (heroBtn) {
                    heroBtn.classList.add('active');
                    heroBtn.querySelector('svg').setAttribute('fill', 'currentColor');
                    heroBtn.title = 'Remove reminder';
                }
                if (actionBtn) {
                    actionBtn.classList.add('active');
                    actionBtn.querySelector('svg').setAttribute('fill', 'currentColor');
                }
                if (reminderText) reminderText.textContent = 'Reminder Set';
            } else {
                // Removed reminder
                if (heroBtn) {
                    heroBtn.classList.remove('active');
                    heroBtn.querySelector('svg').setAttribute('fill', 'none');
                    heroBtn.title = 'Set reminder';
                }
                if (actionBtn) {
                    actionBtn.classList.remove('active');
                    actionBtn.querySelector('svg').setAttribute('fill', 'none');
                }
                if (reminderText) reminderText.textContent = '{{ $room->is_coming_soon ? "Notify When Available" : "Set Reminder" }}';
            }
        }
    } catch (error) {
        console.error('Error toggling reminder:', error);
    }
}
</script>
@endauth

<script>
(function(){
    const ROOM_ID = {{ $room->id }};
    const MONTH_NAMES = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const cache = {}; // { 'YYYY-MM': { 'YYYY-MM-DD': ['HH:MM',...] } }

    let currentYear, currentMonth, selectedDate = null;

    // DOM refs
    const grid      = document.getElementById('cal-grid');
    const label      = document.getElementById('cal-month-label');
    const slotsWrap  = document.getElementById('cal-slots');
    const slotsDate  = document.getElementById('cal-slots-date');
    const slotsGrid  = document.getElementById('cal-slots-grid');
    const prevBtn    = document.getElementById('cal-prev');
    const nextBtn    = document.getElementById('cal-next');

    // Init to current month
    const now = new Date();
    currentYear  = now.getFullYear();
    currentMonth = now.getMonth(); // 0-indexed

    prevBtn.addEventListener('click', () => { navigate(-1); });
    nextBtn.addEventListener('click', () => { navigate(1); });

    function navigate(dir) {
        selectedDate = null;
        slotsWrap.classList.remove('open');
        currentMonth += dir;
        if (currentMonth < 0)  { currentMonth = 11; currentYear--; }
        if (currentMonth > 11) { currentMonth = 0;  currentYear++; }
        renderMonth();
    }

    function monthKey() {
        return currentYear + '-' + String(currentMonth + 1).padStart(2, '0');
    }

    async function fetchMonth(key) {
        if (cache[key]) return cache[key];

        try {
            const res = await fetch(`/rooms/${ROOM_ID}/availability?month=${key}`);
            const data = await res.json();
            cache[key] = data;
            return data;
        } catch (e) {
            console.error('Failed to fetch availability:', e);
            return {};
        }
    }

    let renderId = 0; // prevents stale fetches from overwriting

    async function renderMonth() {
        const thisRender = ++renderId;
        const key = monthKey();
        label.textContent = MONTH_NAMES[currentMonth] + ' ' + currentYear;

        // If data isn't cached, show overlay + disable nav
        const needsFetch = !cache[key];
        let overlay = grid.querySelector('.cal-grid-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'cal-grid-overlay';
            overlay.innerHTML = '<div class="cal-spinner"></div>';
            grid.appendChild(overlay);
        }
        if (needsFetch) {
            overlay.classList.add('visible');
            prevBtn.disabled = true;
            nextBtn.disabled = true;
        }

        const data = await fetchMonth(key);

        // If user navigated away during fetch, abandon this render
        if (thisRender !== renderId) return;

        overlay.classList.remove('visible');
        prevBtn.disabled = false;
        nextBtn.disabled = false;

        const totalDays = Object.keys(data).length;

        // Clear everything except overlay
        grid.innerHTML = '';
        grid.appendChild(overlay);

        const firstDay = new Date(currentYear, currentMonth, 1);
        const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
        // Monday = 0, Sunday = 6
        let startDow = firstDay.getDay() - 1;
        if (startDow < 0) startDow = 6;

        const todayStr = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0') + '-' + String(now.getDate()).padStart(2,'0');

        // Empty cells before day 1
        for (let i = 0; i < startDow; i++) {
            const empty = document.createElement('div');
            empty.className = 'cal-day empty';
            grid.appendChild(empty);
        }

        // Day cells
        for (let d = 1; d <= daysInMonth; d++) {
            const dateStr = currentYear + '-' + String(currentMonth+1).padStart(2,'0') + '-' + String(d).padStart(2,'0');
            const cell = document.createElement('div');
            cell.className = 'cal-day';
            cell.textContent = d;

            if (dateStr === todayStr) cell.classList.add('today');

            if (data[dateStr] && data[dateStr].length > 0) {
                cell.classList.add('has-slots');
                cell.title = data[dateStr].length + ' slot(s) available';
                cell.addEventListener('click', () => selectDay(dateStr, data[dateStr], cell));
            }

            grid.appendChild(cell);
        }

        // Pad with empty cells to always fill 6 rows (42 cells)
        const totalCells = startDow + daysInMonth;
        const remainder = totalCells % 7;
        const padCount = remainder === 0 ? (totalCells < 42 ? 7 : 0) : (42 - totalCells);
        for (let i = 0; i < padCount; i++) {
            const empty = document.createElement('div');
            empty.className = 'cal-day empty';
            grid.appendChild(empty);
        }

        // If no data at all for this month
        if (totalDays === 0) {
            const msg = document.createElement('div');
            msg.className = 'cal-empty-msg';
            msg.style.gridColumn = '1 / -1';
            msg.textContent = 'No available slots this month';
            grid.appendChild(msg);
        }
    }

    function selectDay(dateStr, times, cell) {
        // Toggle off if already selected
        if (selectedDate === dateStr) {
            selectedDate = null;
            cell.classList.remove('selected');
            slotsWrap.classList.remove('open');
            return;
        }

        // Deselect previous
        selectedDate = dateStr;
        grid.querySelectorAll('.cal-day.selected').forEach(el => el.classList.remove('selected'));
        cell.classList.add('selected');

        // Format date nicely
        const d = new Date(dateStr + 'T00:00:00');
        const dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        slotsDate.textContent = dayNames[d.getDay()] + ', ' + d.getDate() + ' ' + MONTH_NAMES[d.getMonth()] + ' ' + d.getFullYear();

        // Render time chips with staggered animation
        slotsGrid.innerHTML = '';
        times.forEach((t, i) => {
            const chip = document.createElement('span');
            chip.className = 'cal-slot';
            chip.textContent = t;
            chip.style.animationDelay = (i * 0.04) + 's';
            slotsGrid.appendChild(chip);
        });

        slotsWrap.classList.add('open');
    }

    // ── Refresh from EscapeAll ──
    const refreshBtn    = document.getElementById('cal-refresh-btn');
    const progressWrap  = document.getElementById('cal-progress-wrap');
    const progressFill  = document.getElementById('cal-progress-fill');
    const progressPct   = document.getElementById('cal-progress-pct');
    const progressMsg   = document.getElementById('cal-progress-msg');
    let isRefreshing = false;

    refreshBtn.addEventListener('click', () => {
        if (isRefreshing) return;
        startRefresh();
    });

    async function startRefresh() {
        isRefreshing = true;
        refreshBtn.classList.add('spinning');
        refreshBtn.disabled = true;
        progressFill.style.width = '0%';
        progressPct.textContent = '0%';
        progressMsg.textContent = 'Preparing...';
        progressWrap.classList.add('active');

        try {
            const res = await fetch(`/rooms/${ROOM_ID}/refresh-availability`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'text/event-stream',
                },
            });

            const reader = res.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop(); // keep incomplete line

                let currentEvent = null;
                for (const line of lines) {
                    if (line.startsWith('event: ')) {
                        currentEvent = line.slice(7).trim();
                    } else if (line.startsWith('data: ') && currentEvent) {
                        try {
                            const data = JSON.parse(line.slice(6));
                            handleEvent(currentEvent, data);
                        } catch (e) {}
                        currentEvent = null;
                    }
                }
            }
        } catch (e) {
            progressMsg.textContent = 'Error: ' + e.message;
            progressPct.textContent = '⚠';
        }

        refreshBtn.classList.remove('spinning');
        refreshBtn.disabled = false;
        isRefreshing = false;

        // Auto-hide progress bar after 4s
        setTimeout(() => {
            progressWrap.classList.remove('active');
        }, 4000);
    }

    function handleEvent(event, data) {
        if (event === 'progress') {
            const pct = data.progress || 0;
            progressFill.style.width = pct + '%';
            progressPct.textContent = pct + '%';
            progressMsg.textContent = data.message || '';

            if (data.step === 'done') {
                progressMsg.textContent = `✓ ${data.total} slots synced (${data.created} new, ${data.deleted} removed)`;
                // Clear cache and reload calendar
                Object.keys(cache).forEach(k => delete cache[k]);
                selectedDate = null;
                slotsWrap.classList.remove('open');
                renderMonth();
            }
        } else if (event === 'error') {
            progressMsg.textContent = '⚠ ' + (data.message || 'Failed');
            progressPct.textContent = '⚠';
        }
    }

    // Initial render
    renderMonth();
})();
</script>

@include('partials.search-script')
</body>
</html>
