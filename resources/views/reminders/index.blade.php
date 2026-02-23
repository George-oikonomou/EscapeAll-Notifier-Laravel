<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Reminders • EscapeAll</title>
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
            --warning:#fbbf24;
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
        .page-header{display:flex; align-items:center; gap:1rem; margin-bottom:2rem}
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
        .results-label{color:var(--muted); font-size:.85rem; margin-left:auto}

        .reminder-list{display:flex; flex-direction:column; gap:1rem}
        .reminder-card{
            display:grid;
            grid-template-columns:150px 1fr auto;
            border-radius:16px; overflow:hidden;
            background:var(--glass); border:1px solid var(--glass-border);
            backdrop-filter:blur(14px); transition:border-color .2s, box-shadow .2s;
        }
        .reminder-card:hover{
            border-color:rgba(251,191,36,.2);
            box-shadow:0 8px 32px rgba(0,0,0,.35);
        }
        .reminder-image{
            position:relative; height:100%; min-height:110px; overflow:hidden;
            background:rgba(0,0,0,.3); flex-shrink:0;
        }
        .reminder-image img{width:100%; height:100%; object-fit:cover; transition:transform .4s}
        .reminder-card:hover .reminder-image img{transform:scale(1.06)}
        .reminder-image-placeholder{
            width:100%; height:100%; display:flex; align-items:center; justify-content:center;
            font-size:2.5rem; background:linear-gradient(135deg, rgba(251,191,36,.08), rgba(167,139,250,.08));
        }
        .reminder-image-overlay{
            position:absolute; inset:0;
            background:linear-gradient(to right, transparent 50%, rgba(5,8,20,.6));
            pointer-events:none;
        }
        .reminder-content{
            padding:1.1rem 1rem; flex:1; min-width:0; display:flex; flex-direction:column; gap:.4rem;
        }
        .reminder-title{
            margin:0; font-weight:700; font-size:1rem; color:#fff;
            display:flex; align-items:center; gap:.5rem; flex-wrap:wrap;
        }
        .reminder-title a{color:inherit; text-decoration:none}
        .reminder-title a:hover{color:var(--accent)}
        .coming-soon-badge{
            font-size:.68rem; padding:.12rem .45rem; border-radius:6px;
            background:rgba(251,191,36,.15); border:1px solid rgba(251,191,36,.3); color:#fbbf24;
        }
        .reminder-provider{color:var(--accent); font-size:.82rem; font-weight:500;
            display:flex; align-items:center; gap:.3rem;}
        .reminder-location{color:var(--muted); font-size:.8rem;
            display:flex; align-items:center; gap:.3rem;}
        .reminder-type-row{display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; margin-top:auto; padding-top:.4rem}
        .reminder-type{
            display:inline-flex; align-items:center; gap:.35rem;
            font-size:.78rem; padding:.22rem .55rem; border-radius:8px;
            background:rgba(251,191,36,.1); border:1px solid rgba(251,191,36,.2); color:#fcd34d;
        }
        .reminder-date-badge{
            font-size:.78rem; padding:.22rem .55rem; border-radius:8px;
            background:rgba(96,165,250,.1); border:1px solid rgba(96,165,250,.2); color:#93c5fd;
        }
        /* Actions column */
        .reminder-actions{
            display:flex; flex-direction:column; align-items:center; justify-content:center;
            gap:.5rem; padding:.9rem .85rem;
            border-left:1px solid var(--glass-border);
            background:rgba(0,0,0,.12);
        }
        .btn-view{
            display:flex; align-items:center; gap:.35rem;
            padding:.5rem .8rem; border-radius:10px; font-size:.78rem;
            background:rgba(96,165,250,.15); border:1px solid rgba(96,165,250,.25);
            color:#93c5fd; text-decoration:none; transition:all .2s; white-space:nowrap;
        }
        .btn-view:hover{background:rgba(96,165,250,.28)}
        .btn-remove{
            display:flex; align-items:center; gap:.35rem;
            padding:.5rem .8rem; border-radius:10px; font-size:.78rem;
            background:rgba(239,68,68,.12); border:1px solid rgba(239,68,68,.22);
            color:#fca5a5; cursor:pointer; transition:all .2s; white-space:nowrap;
        }
        .btn-remove:hover{background:rgba(239,68,68,.25)}

        .empty-state{
            text-align:center; padding:5rem 2rem;
            background:var(--glass); border:1px solid var(--glass-border);
            border-radius:18px; backdrop-filter:blur(14px);
        }
        .empty-icon{font-size:4.5rem; margin-bottom:1rem; animation:emptyPulse 2s ease-in-out infinite}
        @keyframes emptyPulse{0%,100%{transform:scale(1)}50%{transform:scale(1.1)}}
        .empty-title{font-size:1.3rem; font-weight:700; color:#fff; margin:0 0 .5rem}
        .empty-text{color:var(--muted); margin:0 0 1.5rem; font-size:.95rem}
        .empty-btn{
            display:inline-flex; align-items:center; gap:.5rem;
            padding:.7rem 1.4rem; border-radius:12px;
            background:rgba(251,191,36,.18); border:1px solid rgba(251,191,36,.3);
            color:#fcd34d; font-weight:600; text-decoration:none; transition:all .2s;
        }
        .empty-btn:hover{transform:translateY(-2px); background:rgba(251,191,36,.28)}

        @media(max-width:640px){
            .container{padding:1.25rem 1rem}
            .topnav{padding:.8rem 1rem; gap:1rem}
            .reminder-card{grid-template-columns:1fr; grid-template-rows:150px auto auto}
            .reminder-image{min-height:150px}
            .reminder-actions{flex-direction:row; justify-content:flex-start; border-left:none; border-top:1px solid var(--glass-border)}
            .reminder-image-overlay{background:linear-gradient(to bottom, transparent 50%, rgba(5,8,20,.6))}
        }
    </style>
</head>
<body>

@include('partials.topnav')

<div class="container">
    <div class="page-header">
        <h1 class="page-title">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="#fbbf24" stroke="#fbbf24" stroke-width="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            My Reminders
        </h1>
        <span class="page-count">{{ $reminders->count() }} {{ Str::plural('reminder', $reminders->count()) }}</span>
    </div>

    @if($reminders->isEmpty())
        <div class="empty-state">
            <div class="empty-icon">🔔</div>
            <h2 class="empty-title">No reminders yet</h2>
            <p class="empty-text">Set reminders for rooms you want to book or for upcoming releases!</p>
            <a href="{{ route('home') }}" class="empty-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.2-4.2"/>
                </svg>
                Browse Rooms
            </a>
        </div>
    @else
        {{-- Search bar --}}
        <div class="controls-bar">
            <div class="search-box">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.2-4.2"/></svg>
                <input type="text" id="rem-search" placeholder="Search reminders..." autocomplete="off">
            </div>
            <div class="results-label" id="rem-count">{{ $reminders->count() }} {{ Str::plural('reminder', $reminders->count()) }}</div>
        </div>

        <div class="reminder-list" id="rem-list">
            @foreach($reminders as $reminder)
                <div class="reminder-card" id="reminder-{{ $reminder->id }}"
                     data-title="{{ strtolower($reminder->room->title) }}"
                     data-provider="{{ strtolower($reminder->room->provider) }}">
                    <div class="reminder-image">
                        @if($reminder->room->image_url)
                            <img src="{{ Str::startsWith($reminder->room->image_url, 'http') ? $reminder->room->image_url : 'https://www.escapeall.gr' . $reminder->room->image_url }}"
                                 alt="{{ $reminder->room->title }}" loading="lazy">
                        @else
                            <div class="reminder-image-placeholder">🔐</div>
                        @endif
                        <div class="reminder-image-overlay"></div>
                    </div>
                    <div class="reminder-content">
                        <h3 class="reminder-title">
                            <a href="{{ route('rooms.show', $reminder->room) }}">{{ $reminder->room->title }}</a>
                            @if($reminder->room->is_coming_soon)
                                <span class="coming-soon-badge">Coming Soon</span>
                            @endif
                        </h3>
                        <div class="reminder-provider">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M9 21V8a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v13"/></svg>
                            {{ $reminder->room->provider }}
                        </div>
                        @if($reminder->room->municipality)
                            <div class="reminder-location">
                                📍 {{ $reminder->room->municipality->name }}
                            </div>
                        @endif
                        <div class="reminder-type-row">
                            <span class="reminder-type">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                                </svg>
                                {{ $reminder->type_label }}
                            </span>
                            @if($reminder->type === 'specific_day' && $reminder->remind_at)
                                <span class="reminder-date-badge">📅 {{ $reminder->remind_at->format('M j, Y') }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="reminder-actions">
                        <a href="{{ route('rooms.show', $reminder->room) }}" class="btn-view">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            View
                        </a>
                        <button class="btn-remove" onclick="removeReminder({{ $reminder->room->id }}, {{ $reminder->id }})">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                            Remove
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="empty-state" id="rem-empty" style="display:none; margin-top:1.5rem">
            <div class="empty-icon" style="animation:none;opacity:.5">🔍</div>
            <h2 class="empty-title">No reminders found</h2>
            <p class="empty-text">Try a different search</p>
        </div>
    @endif
</div>

<script>
async function removeReminder(roomId, reminderId) {
    try {
        const response = await fetch(`/reminders/${roomId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        });

        const data = await response.json();

        if (data.success) {
            const card = document.getElementById(`reminder-${reminderId}`);
            card.style.transition = 'opacity .3s, transform .3s';
            card.style.opacity = '0';
            card.style.transform = 'translateX(16px)';
            setTimeout(() => {
                card.remove();
                const all = document.querySelectorAll('.reminder-card');
                const countEl = document.getElementById('rem-count');
                if (countEl) countEl.textContent = `${all.length} ${all.length === 1 ? 'reminder' : 'reminders'}`;
                if (all.length === 0) location.reload();
            }, 300);
        }
    } catch (error) {
        console.error('Error removing reminder:', error);
    }
}

// Search
(function() {
    const searchInput = document.getElementById('rem-search');
    const list = document.getElementById('rem-list');
    if (!searchInput || !list) return;
    const cards = Array.from(list.querySelectorAll('.reminder-card'));

    searchInput.addEventListener('input', function() {
        const q = this.value.toLowerCase().trim();
        let visible = 0;
        cards.forEach(c => {
            const match = !q || (c.dataset.title||'').includes(q) || (c.dataset.provider||'').includes(q);
            c.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        const countEl = document.getElementById('rem-count');
        if (countEl) countEl.textContent = `${visible} ${visible === 1 ? 'reminder' : 'reminders'}`;
        const emptyEl = document.getElementById('rem-empty');
        if (emptyEl) emptyEl.style.display = visible === 0 ? 'block' : 'none';
    });
})();
</script>
@include('partials.search-script')
</body>
</html>

