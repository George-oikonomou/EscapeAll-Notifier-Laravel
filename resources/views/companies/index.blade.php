<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Companies • EscapeAll Notifier</title>
    <meta name="description" content="Browse all escape room companies in Attica. Find locations, ratings, and rooms.">
    <link rel="icon" href="/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css','resources/js/app.js'])
    @include('partials.search-styles')
    <style>
        :root{
            --bg-1:#0b1020; --bg-2:#0a0f1c; --bg-3:#050814;
            --glass: rgba(13,18,30,.55);
            --glass-border: rgba(255,255,255,.08);
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
            background-attachment: fixed;
        }

        /* ── Nav ── */
        .topnav{
            display:flex; align-items:center; gap:1.5rem; padding:.8rem 2rem;
            background:rgba(5,8,20,.7); border-bottom:1px solid var(--glass-border);
            backdrop-filter:blur(12px); position:sticky; top:0; z-index:50;
        }
        .topnav .brand{font-weight:800; font-size:1.1rem; color:#fff; text-decoration:none;
            background:linear-gradient(90deg,#60a5fa,#a78bfa); -webkit-background-clip:text; background-clip:text; color:transparent;}
        .topnav a{color:var(--muted); text-decoration:none; font-size:.9rem; transition:color .2s}
        .topnav a:hover{color:#fff}
        .topnav a.active{color:#fff; font-weight:600}

        /* ── Container ── */
        .container{max-width:1200px; margin:0 auto; padding:2rem 1.5rem}

        /* ── Page Header ── */
        .page-header{margin-bottom:2rem}
        .page-title{
            margin:0 0 .5rem; font-weight:800; font-size:clamp(1.6rem,1.2rem+2vw,2.4rem);
            background:linear-gradient(90deg,#fff,#cbd5e1); -webkit-background-clip:text; background-clip:text; color:transparent;
        }
        .page-subtitle{color:var(--muted); margin:0}

        /* ── Search ── */
        .search-box{
            display:flex; gap:.6rem; align-items:center; padding:.7rem 1rem; border-radius:14px;
            background:rgba(0,0,0,.3); border:1px solid var(--glass-border); margin-bottom:1.5rem; max-width:500px;
        }
        .search-box svg{flex-shrink:0}
        .search-box input{flex:1; background:transparent; border:0; outline:0; color:var(--text); font-size:1rem; font-family:inherit}
        .search-count{color:var(--muted); font-size:.85rem; margin-bottom:1rem}

        /* ── Company Grid ── */
        .company-grid{
            display:grid; grid-template-columns:repeat(auto-fill, minmax(320px, 1fr)); gap:1.25rem;
        }
        .company-card{
            position:relative; border-radius:18px; padding:1.25rem;
            background:var(--glass); border:1px solid var(--glass-border);
            backdrop-filter:blur(14px); transition:border-color .25s, transform .25s, box-shadow .25s;
            text-decoration:none; color:var(--text); display:block; overflow:hidden;
        }
        .company-card::before{
            content:""; position:absolute; inset:-1px; border-radius:19px; padding:1px; pointer-events:none;
            background:conic-gradient(from 220deg, rgba(96,165,250,.25), rgba(167,139,250,.18), rgba(34,211,238,.2), rgba(96,165,250,.25));
            -webkit-mask:linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite:xor; mask-composite:exclude; opacity:0; transition:opacity .25s;
        }
        .company-card:hover{border-color:rgba(255,255,255,.12); transform:translateY(-3px); box-shadow:0 12px 40px rgba(0,0,0,.4)}
        .company-card:hover::before{opacity:.5}

        .card-top{display:flex; gap:1rem; align-items:center; margin-bottom:.75rem}
        .company-logo{
            width:56px; height:56px; border-radius:14px; object-fit:cover;
            background:rgba(255,255,255,.05); border:1px solid var(--glass-border); flex-shrink:0;
        }
        .company-name{font-weight:700; font-size:1.05rem; line-height:1.3}
        .company-address{color:var(--muted); font-size:.82rem; margin-top:.15rem; line-height:1.4}

        .card-stats{display:flex; gap:1rem; margin-top:.5rem}
        .stat{
            display:flex; align-items:center; gap:.35rem; font-size:.8rem; padding:.3rem .65rem;
            border-radius:10px; background:rgba(0,0,0,.25); border:1px solid var(--glass-border);
        }
        .stat-value{color:#fff; font-weight:600}
        .stat-label{color:var(--muted)}
        .stat .icon{font-size:.9rem}

        @media(max-width:640px){
            .company-grid{grid-template-columns:1fr}
            .container{padding:1.5rem 1rem}
        }
    </style>
</head>
<body>

@include('partials.topnav')

<div class="container">
    <div class="page-header">
        <h1 class="page-title">Escape Room Companies</h1>
        <p class="page-subtitle">{{ $companies->count() }} companies across Attica</p>
    </div>

    <div class="search-box">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M21 21l-4.2-4.2" stroke="#9ca3af" stroke-width="1.5" stroke-linecap="round"/><circle cx="11" cy="11" r="7" stroke="#9ca3af" stroke-width="1.5"/></svg>
        <input id="q" type="text" placeholder="Search companies…" autocomplete="off">
    </div>
    <div class="search-count" id="count"></div>

    <div class="company-grid" id="grid">
        @foreach($companies as $c)
            <a class="company-card"
               href="{{ route('companies.show', $c) }}"
               data-name="{{ strtolower($c->name) }}"
               data-address="{{ strtolower($c->address) }}">
                <div class="card-top">
                    @if($c->logo_url)
                        <img class="company-logo"
                             src="{{ Str::startsWith($c->logo_url, 'http') ? $c->logo_url : 'https://www.escapeall.gr' . $c->logo_url }}"
                             alt="{{ $c->name }}"
                             loading="lazy">
                    @else
                        <div class="company-logo" style="display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:700;color:var(--accent)">
                            {{ mb_substr($c->name, 0, 1) }}
                        </div>
                    @endif
                    <div>
                        <div class="company-name">{{ $c->name }}</div>
                        @if($c->address)
                            <div class="company-address">📍 {{ $c->address }}</div>
                        @endif
                    </div>
                </div>
                <div class="card-stats">
                    <div class="stat">
                        <span class="icon">🚪</span>
                        <span class="stat-value">{{ $c->rooms_count }}</span>
                        <span class="stat-label">{{ $c->rooms_count === 1 ? 'room' : 'rooms' }}</span>
                    </div>
                    @if($c->rooms_avg_rating)
                        <div class="stat">
                            <span class="icon">⭐</span>
                            <span class="stat-value">{{ number_format($c->rooms_avg_rating, 1) }}</span>
                            <span class="stat-label">avg rating</span>
                        </div>
                    @endif
                </div>
            </a>
        @endforeach
    </div>
</div>

<script>
(function(){
    const q = document.getElementById('q');
    const cards = Array.from(document.querySelectorAll('.company-card'));
    const countEl = document.getElementById('count');

    function filter(){
        const s = (q.value||'').toLowerCase().trim();
        let visible = 0;
        cards.forEach(c => {
            const match = !s || (c.dataset.name||'').includes(s) || (c.dataset.address||'').includes(s);
            c.style.display = match ? '' : 'none';
            if(match) visible++;
        });
        countEl.textContent = `${visible} / ${cards.length}`;
    }
    q.addEventListener('input', filter);
    filter();
})();
</script>
@include('partials.search-script')
</body>
</html>
