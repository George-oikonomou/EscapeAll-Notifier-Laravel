{{-- Global Search Styles - Include in <head> --}}
<style>
    /* Global Search */
    .global-search{position:relative; width:280px}
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
        backdrop-filter:blur(16px); max-height:400px; overflow-y:auto;
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
        display:flex; align-items:center; gap:.75rem; padding:.6rem 1rem;
        text-decoration:none; color:var(--text); transition:background .15s;
    }
    .search-result-item:hover{background:rgba(255,255,255,.06)}
    .search-result-img{
        width:44px; height:44px; border-radius:8px; object-fit:cover;
        background:rgba(255,255,255,.05); flex-shrink:0;
    }
    .search-result-img.company{border-radius:50%}
    .search-result-info{flex:1; min-width:0}
    .search-result-title{
        font-size:.85rem; font-weight:600; color:var(--text);
        white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    }
    .search-result-meta{
        font-size:.72rem; color:var(--muted); margin-top:2px;
        display:flex; align-items:center; gap:.5rem; flex-wrap:wrap;
    }
    .search-result-meta span{display:flex; align-items:center; gap:.2rem}
    .search-result-rating{color:#fbbf24}
    .search-no-results, .search-loading{
        padding:1.25rem; text-align:center; color:var(--muted); font-size:.9rem;
    }
    .search-loading::after{
        content:''; display:inline-block; width:14px; height:14px;
        border:2px solid var(--glass-border); border-top-color:var(--accent);
        border-radius:50%; animation:searchSpin .6s linear infinite; margin-left:.5rem;
    }
    @keyframes searchSpin{to{transform:rotate(360deg)}}
    @media(max-width:768px){
        .global-search{width:100%}
    }
</style>

