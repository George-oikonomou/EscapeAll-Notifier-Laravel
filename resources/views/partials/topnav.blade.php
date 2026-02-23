{{-- Shared nav bar partial for all public pages --}}
<nav class="topnav">
    <a href="{{ route('home') }}" class="brand">EscapeNotifier</a>
    <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active' : '' }}">Rooms</a>
    <a href="{{ route('companies.index') }}" class="{{ request()->routeIs('companies.*') ? 'active' : '' }}">Companies</a>
    @auth
        <a href="{{ route('favourites.index') }}" class="{{ request()->routeIs('favourites.*') ? 'active' : '' }}" style="display:flex;align-items:center;gap:.35rem">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="{{ request()->routeIs('favourites.*') ? '#ef4444' : 'none' }}" stroke="{{ request()->routeIs('favourites.*') ? '#ef4444' : 'currentColor' }}" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            Favourites
        </a>
        <a href="{{ route('reminders.index') }}" class="{{ request()->routeIs('reminders.*') ? 'active' : '' }}" style="display:flex;align-items:center;gap:.35rem">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="{{ request()->routeIs('reminders.*') ? '#fbbf24' : 'none' }}" stroke="{{ request()->routeIs('reminders.*') ? '#fbbf24' : 'currentColor' }}" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            Reminders
        </a>
    @endauth

    <div style="flex:1"></div>

    <!-- Global Search -->
    <div class="global-search" id="globalSearch">
        <svg class="global-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.35-4.35"></path>
        </svg>
        <input type="text" class="global-search-input" id="globalSearchInput" placeholder="Search rooms, companies..." autocomplete="off">
        <button type="button" class="global-search-clear" id="globalSearchClear">&times;</button>
        <div class="search-results" id="searchResults"></div>
    </div>

    @auth
        <span style="color:var(--text);font-size:.88rem;font-weight:600">{{ Auth::user()->name }}</span>
        <form method="POST" action="{{ route('logout') }}" style="display:inline">
            @csrf
            <button type="submit" style="background:transparent;border:1px solid var(--glass-border);color:var(--muted);padding:.3rem .7rem;border-radius:8px;font-size:.82rem;cursor:pointer;font-family:inherit;transition:border-color .2s,color .2s">Log out</button>
        </form>
    @else
        <a href="{{ route('login') }}" style="padding:.3rem .7rem;border-radius:8px;font-size:.85rem;border:1px solid var(--glass-border)">Log in</a>
        <a href="{{ route('register') }}" style="padding:.3rem .7rem;border-radius:8px;font-size:.85rem;background:var(--accent);color:#fff !important;font-weight:600;border:1px solid transparent">Register</a>
    @endauth
</nav>
