<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'EscapeNotifier') }}</title>

        <link rel="icon" href="/favicon.ico">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            :root{
                --bg-1:#0b1020; --bg-2:#0a0f1c; --bg-3:#050814;
                --glass:rgba(13,18,30,.55); --glass-border:rgba(255,255,255,.08);
                --text:#e5e7eb; --muted:#9ca3af;
                --accent:#60a5fa; --accent2:#a78bfa; --accent3:#22d3ee;
            }
            *{box-sizing:border-box}
            body{
                margin:0; color:var(--text); font-family:'Inter',ui-sans-serif,system-ui,-apple-system,sans-serif !important;
                background: radial-gradient(80% 60% at 20% 10%, rgba(96,165,250,.10), transparent 60%),
                            radial-gradient(60% 70% at 80% 0%, rgba(167,139,250,.12), transparent 65%),
                            radial-gradient(100% 80% at 50% 100%, rgba(34,211,238,.09), transparent 60%),
                            linear-gradient(180deg, var(--bg-1), var(--bg-2) 50%, var(--bg-3)) !important;
                background-attachment:fixed !important;
            }
            .ea-topnav{
                display:flex; align-items:center; gap:1.5rem; padding:.8rem 2rem;
                background:rgba(5,8,20,.7); border-bottom:1px solid var(--glass-border);
                backdrop-filter:blur(12px); position:sticky; top:0; z-index:50;
            }
            .ea-topnav .brand{font-weight:800; font-size:1.1rem; text-decoration:none;
                background:linear-gradient(90deg,#60a5fa,#a78bfa); -webkit-background-clip:text; background-clip:text; color:transparent;}
            .ea-topnav a{color:var(--muted); text-decoration:none; font-size:.9rem; transition:color .2s}
            .ea-topnav a:hover{color:#fff}
            .ea-topnav .spacer{flex:1}
            .ea-topnav .user-name{color:var(--text); font-size:.88rem; font-weight:600}
            .ea-topnav .logout-btn{
                background:transparent; border:1px solid var(--glass-border); color:var(--muted);
                padding:.35rem .75rem; border-radius:8px; font-size:.82rem; cursor:pointer; font-family:inherit;
                transition:border-color .2s, color .2s;
            }
            .ea-topnav .logout-btn:hover{border-color:rgba(255,255,255,.2); color:#fff}

            /* Global Search */
            .global-search{position:relative; width:320px}
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
                backdrop-filter:blur(16px); max-height:480px; overflow-y:auto;
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
                display:flex; align-items:center; gap:.75rem; padding:.65rem 1rem;
                text-decoration:none; color:var(--text); transition:background .15s;
            }
            .search-result-item:hover{background:rgba(255,255,255,.06)}
            .search-result-img{
                width:48px; height:48px; border-radius:8px; object-fit:cover;
                background:rgba(255,255,255,.05); flex-shrink:0;
            }
            .search-result-img.company{border-radius:50%}
            .search-result-info{flex:1; min-width:0}
            .search-result-title{
                font-size:.9rem; font-weight:600; color:var(--text);
                white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
            }
            .search-result-meta{
                font-size:.75rem; color:var(--muted); margin-top:2px;
                display:flex; align-items:center; gap:.5rem; flex-wrap:wrap;
            }
            .search-result-meta span{display:flex; align-items:center; gap:.25rem}
            .search-result-rating{color:#fbbf24}
            .search-no-results{
                padding:1.5rem; text-align:center; color:var(--muted); font-size:.9rem;
            }
            .search-loading{
                padding:1.5rem; text-align:center; color:var(--muted); font-size:.9rem;
            }
            .search-loading::after{
                content:''; display:inline-block; width:16px; height:16px;
                border:2px solid var(--glass-border); border-top-color:var(--accent);
                border-radius:50%; animation:spin .6s linear infinite; margin-left:.5rem;
            }
            @keyframes spin{to{transform:rotate(360deg)}}

            .ea-container{max-width:1100px; margin:0 auto; padding:2rem 1.5rem}
            /* Override Tailwind bg for content */
            .min-h-screen{background:transparent !important}
            .bg-gray-100{background:transparent !important}
            .bg-white{background:var(--glass) !important; border:1px solid var(--glass-border); backdrop-filter:blur(14px)}
            .shadow{box-shadow:0 4px 20px rgba(0,0,0,.3) !important}
            .text-gray-800{color:var(--text) !important}
            .text-gray-600{color:var(--muted) !important}

            /* ─── Mobile responsive (authenticated layout) ─── */
            @media(max-width:768px){
                .ea-topnav{padding:.6rem 1rem; gap:.6rem; flex-wrap:wrap}
                .ea-topnav .brand{font-size:1rem}
                .ea-topnav a{font-size:.82rem}
                .ea-topnav .user-name{font-size:.8rem}
                .ea-topnav .logout-btn{font-size:.78rem; padding:.3rem .6rem}
                .global-search{width:100%; order:10}
                .ea-container{padding:1.25rem 1rem}
            }
            @media(max-width:480px){
                .ea-topnav{padding:.5rem .75rem; gap:.5rem}
                .ea-container{padding:.75rem .5rem}
            }
        </style>
    </head>
    <body>
        <nav class="ea-topnav">
            <a href="{{ route('home') }}" class="brand">EscapeNotifier</a>
            <a href="{{ route('home') }}">Rooms</a>
            <a href="{{ route('companies.index') }}">Companies</a>

            <!-- Global Search -->
            <div class="global-search" id="globalSearch">
                <svg class="global-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
                <input type="text" class="global-search-input" id="globalSearchInput" placeholder="Search rooms, companies..." autocomplete="off">
                <button type="button" class="global-search-clear" id="globalSearchClear">&times;</button>

                <div class="search-results" id="searchResults">
                    <!-- Results populated by JS -->
                </div>
            </div>

            <div class="spacer"></div>
            <span class="user-name">{{ Auth::user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}" style="display:inline">
                @csrf
                <button type="submit" class="logout-btn">Log out</button>
            </form>
        </nav>

        @isset($header)
            <div class="ea-container">
                <div style="padding:1rem 0">{{ $header }}</div>
            </div>
        @endisset

        <main class="ea-container">
            {{ $slot }}
        </main>

        <script>
        (function() {
            const searchInput = document.getElementById('globalSearchInput');
            const searchResults = document.getElementById('searchResults');
            const searchClear = document.getElementById('globalSearchClear');
            const globalSearch = document.getElementById('globalSearch');

            let debounceTimer;
            let currentQuery = '';

            // Debounced search
            searchInput.addEventListener('input', function() {
                const query = this.value.trim();
                currentQuery = query;

                // Show/hide clear button
                searchClear.classList.toggle('visible', query.length > 0);

                clearTimeout(debounceTimer);

                if (query.length < 2) {
                    searchResults.classList.remove('active');
                    return;
                }

                // Show loading
                searchResults.innerHTML = '<div class="search-loading">Searching</div>';
                searchResults.classList.add('active');

                debounceTimer = setTimeout(() => performSearch(query), 250);
            });

            // Clear button
            searchClear.addEventListener('click', function() {
                searchInput.value = '';
                searchClear.classList.remove('visible');
                searchResults.classList.remove('active');
                searchInput.focus();
            });

            // Close on click outside
            document.addEventListener('click', function(e) {
                if (!globalSearch.contains(e.target)) {
                    searchResults.classList.remove('active');
                }
            });

            // Show results on focus if has content
            searchInput.addEventListener('focus', function() {
                if (this.value.trim().length >= 2 && searchResults.innerHTML.trim()) {
                    searchResults.classList.add('active');
                }
            });

            // Keyboard navigation
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    searchResults.classList.remove('active');
                    this.blur();
                }
            });

            async function performSearch(query) {
                if (query !== currentQuery) return; // Stale request

                try {
                    const response = await fetch(`/search?q=${encodeURIComponent(query)}`);
                    const data = await response.json();

                    if (query !== currentQuery) return; // Stale request

                    renderResults(data);
                } catch (error) {
                    console.error('Search error:', error);
                    searchResults.innerHTML = '<div class="search-no-results">Search failed. Please try again.</div>';
                }
            }

            function renderResults(data) {
                const { rooms, companies } = data;

                if (rooms.length === 0 && companies.length === 0) {
                    searchResults.innerHTML = '<div class="search-no-results">No results found</div>';
                    searchResults.classList.add('active');
                    return;
                }

                let html = '';

                // Rooms section
                if (rooms.length > 0) {
                    html += '<div class="search-results-section">';
                    html += '<div class="search-results-label">🚪 Rooms</div>';
                    rooms.forEach(room => {
                        const imgSrc = room.image_url
                            ? (room.image_url.startsWith('http') ? room.image_url : 'https://www.escapeall.gr' + room.image_url)
                            : '/favicon.ico';
                        html += `
                            <a href="${room.url}" class="search-result-item">
                                <img src="${imgSrc}" alt="" class="search-result-img" onerror="this.src='/favicon.ico'">
                                <div class="search-result-info">
                                    <div class="search-result-title">${escapeHtml(room.title)}</div>
                                    <div class="search-result-meta">
                                        <span class="search-result-rating">★ ${room.rating || '-'}</span>
                                        <span>👥 ${room.players}</span>
                                        <span>${escapeHtml(room.provider || room.company_name || '')}</span>
                                    </div>
                                </div>
                            </a>
                        `;
                    });
                    html += '</div>';
                }

                // Companies section
                if (companies.length > 0) {
                    html += '<div class="search-results-section">';
                    html += '<div class="search-results-label">🏢 Companies</div>';
                    companies.forEach(company => {
                        const logoSrc = company.logo_url
                            ? (company.logo_url.startsWith('http') ? company.logo_url : 'https://www.escapeall.gr' + company.logo_url)
                            : '/favicon.ico';
                        html += `
                            <a href="${company.url}" class="search-result-item">
                                <img src="${logoSrc}" alt="" class="search-result-img company" onerror="this.src='/favicon.ico'">
                                <div class="search-result-info">
                                    <div class="search-result-title">${escapeHtml(company.name)}</div>
                                    <div class="search-result-meta">
                                        <span>🚪 ${company.rooms_count} room${company.rooms_count !== 1 ? 's' : ''}</span>
                                        ${company.address ? `<span>📍 ${escapeHtml(company.address)}</span>` : ''}
                                    </div>
                                </div>
                            </a>
                        `;
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
