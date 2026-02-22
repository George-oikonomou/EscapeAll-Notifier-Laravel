{{-- Global Search Script - Include before </body> --}}
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

