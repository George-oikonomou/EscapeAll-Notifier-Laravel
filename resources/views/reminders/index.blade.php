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
        .reminder-list{display:flex; flex-direction:column; gap:1rem}
        .reminder-card{
            display:flex; gap:1.25rem; padding:1.25rem; border-radius:16px;
            background:var(--glass); border:1px solid var(--glass-border);
            backdrop-filter:blur(14px); transition:border-color .2s;
        }
        .reminder-card:hover{border-color:rgba(255,255,255,.15)}
        .reminder-image{
            width:120px; height:90px; border-radius:12px; overflow:hidden; flex-shrink:0;
            background:rgba(0,0,0,.3);
        }
        .reminder-image img{width:100%; height:100%; object-fit:cover}
        .reminder-image-placeholder{
            width:100%; height:100%; display:flex; align-items:center; justify-content:center;
            font-size:2rem; background:linear-gradient(135deg, rgba(96,165,250,.08), rgba(167,139,250,.08));
        }
        .reminder-content{flex:1; min-width:0}
        .reminder-title{
            margin:0 0 .4rem; font-weight:700; font-size:1.1rem; color:#fff;
            display:flex; align-items:center; gap:.5rem;
        }
        .reminder-title a{color:inherit; text-decoration:none}
        .reminder-title a:hover{text-decoration:underline}
        .coming-soon-badge{
            font-size:.7rem; padding:.15rem .5rem; border-radius:6px;
            background:rgba(251,191,36,.15); border:1px solid rgba(251,191,36,.3); color:#fbbf24;
        }
        .reminder-meta{color:var(--muted); font-size:.88rem; margin-bottom:.6rem}
        .reminder-type{
            display:inline-flex; align-items:center; gap:.35rem;
            font-size:.82rem; padding:.25rem .6rem; border-radius:8px;
            background:rgba(251,191,36,.1); border:1px solid rgba(251,191,36,.2); color:#fcd34d;
        }
        .reminder-date{color:var(--accent); font-weight:500; margin-left:.5rem}
        .reminder-actions{display:flex; align-items:center; gap:.5rem; margin-left:auto}
        .btn-remove{
            padding:.5rem .8rem; border-radius:10px; font-size:.82rem;
            background:rgba(239,68,68,.15); border:1px solid rgba(239,68,68,.25);
            color:#fca5a5; cursor:pointer; transition:all .2s;
        }
        .btn-remove:hover{background:rgba(239,68,68,.25)}
        .btn-view{
            padding:.5rem .8rem; border-radius:10px; font-size:.82rem;
            background:rgba(96,165,250,.15); border:1px solid rgba(96,165,250,.25);
            color:#93c5fd; text-decoration:none; transition:all .2s;
        }
        .btn-view:hover{background:rgba(96,165,250,.25)}
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
            .reminder-card{flex-direction:column}
            .reminder-image{width:100%; height:150px}
            .reminder-actions{margin-left:0; margin-top:.75rem}
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
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9,22 9,12 15,12 15,22"/>
                </svg>
                Browse Rooms
            </a>
        </div>
    @else
        <div class="reminder-list">
            @foreach($reminders as $reminder)
                <div class="reminder-card" id="reminder-{{ $reminder->id }}">
                    <div class="reminder-image">
                        @if($reminder->room->image_url)
                            <img src="{{ Str::startsWith($reminder->room->image_url, 'http') ? $reminder->room->image_url : 'https://www.escapeall.gr' . $reminder->room->image_url }}"
                                 alt="{{ $reminder->room->title }}" loading="lazy">
                        @else
                            <div class="reminder-image-placeholder">🔐</div>
                        @endif
                    </div>
                    <div class="reminder-content">
                        <h3 class="reminder-title">
                            <a href="{{ route('rooms.show', $reminder->room) }}">{{ $reminder->room->title }}</a>
                            @if($reminder->room->is_coming_soon)
                                <span class="coming-soon-badge">Coming Soon</span>
                            @endif
                        </h3>
                        <div class="reminder-meta">
                            {{ $reminder->room->provider }}
                            @if($reminder->room->municipality)
                                • {{ $reminder->room->municipality->name }}
                            @endif
                        </div>
                        <div class="reminder-type">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                            </svg>
                            {{ $reminder->type_label }}
                            @if($reminder->type === 'specific_day' && $reminder->remind_at)
                                <span class="reminder-date">{{ $reminder->remind_at->format('M j, Y') }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="reminder-actions">
                        <a href="{{ route('rooms.show', $reminder->room) }}" class="btn-view">View Room</a>
                        <button class="btn-remove" onclick="removeReminder({{ $reminder->room->id }}, {{ $reminder->id }})">Remove</button>
                    </div>
                </div>
            @endforeach
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
            card.style.transform = 'translateX(20px)';
            setTimeout(() => {
                card.remove();
                const countEl = document.querySelector('.page-count');
                const currentCount = parseInt(countEl.textContent) - 1;
                countEl.textContent = `${currentCount} ${currentCount === 1 ? 'reminder' : 'reminders'}`;
                if (currentCount === 0) location.reload();
            }, 300);
        }
    } catch (error) {
        console.error('Error removing reminder:', error);
    }
}
</script>
@include('partials.search-script')
</body>
</html>

