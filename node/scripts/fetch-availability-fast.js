/**
 * fetch-escapeall-fast.js
 *
 * Lightweight variant of fetch-escapeall.js optimised for the notification
 * command.  Launches a single stealth browser, visits the homepage once
 * (just long enough to set cookies), then makes API calls for ALL rooms
 * in sequence — no scrolling, no mouse movements, minimal waits.
 *
 * The original fetch-escapeall.js is kept for the daily full sync where
 * extra stealth measures are preferable.
 *
 * Usage:
 *   node fetch-escapeall-fast.js \
 *     from=2026-02-18 until=2026-05-18 \
 *     serviceIds=GUID1,GUID2,GUID3 \
 *     language=el
 *
 * Output (stdout):
 *   JSON object keyed by serviceId, e.g.:
 *   {
 *     "GUID1": [ { Day, Month, Year, HasAvailable, ... }, ... ],
 *     "GUID2": [ ... ]
 *   }
 *
 * Options:
 *   from          Start date YYYY-MM-DD
 *   until         End date YYYY-MM-DD
 *   serviceIds    Comma-separated list of room GUIDs
 *   language      el or en (default: el)
 *   bookedBy      Usually 1 (default)
 *   noGifts       true or false (default: false)
 *   delayMs       Delay between API calls in ms (default: 1500)
 */
const { chromium } = require('playwright-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');

chromium.use(StealthPlugin());

const USER_AGENTS = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:126.0) Gecko/20100101 Firefox/126.0',
    'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
];

function pickRandom(arr) {
    return arr[Math.floor(Math.random() * arr.length)];
}

function sleep(ms) {
    return new Promise(r => setTimeout(r, ms));
}

(async () => {
    // ── Parse args ──
    const args = Object.fromEntries(process.argv.slice(2).map(a => a.split('=')));
    const {
        from,
        until,
        serviceIds: serviceIdsCsv,
        language = 'el',
        bookedBy = '1',
        noGifts = 'false',
        delayMs = '1500',
    } = args;

    if (!from || !until || !serviceIdsCsv) {
        console.error('[fast] Missing required args: from, until, serviceIds');
        process.exit(1);
    }

    const serviceIds = serviceIdsCsv.split(',').filter(Boolean);
    const delay = parseInt(delayMs, 10);

    console.error(`[fast] ${serviceIds.length} room(s), ${from} → ${until}, delay=${delay}ms`);

    // ── Launch browser once ──
    const userAgent = pickRandom(USER_AGENTS);
    const browser = await chromium.launch({
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-blink-features=AutomationControlled',
            '--disable-dev-shm-usage',
        ],
    });

    const context = await browser.newContext({
        userAgent,
        viewport: { width: 1366, height: 768 },
        locale: language === 'en' ? 'en-US' : 'el-GR',
        timezoneId: 'Europe/Athens',
        extraHTTPHeaders: {
            'Accept-Language': language === 'en' ? 'en-US,en;q=0.9' : 'el-GR,el;q=0.9,en;q=0.8',
        },
    });

    const page = await context.newPage();

    try {
        // ── Visit homepage once to set cookies ──
        console.error('[fast] Opening homepage for cookies...');
        await page.goto('https://www.escapeall.gr/', {
            waitUntil: 'domcontentloaded',
            timeout: 30000,
        });
        await sleep(2000 + Math.random() * 1000);
        console.error('[fast] Cookies set, starting API calls...');

        // ── Fetch each room ──
        const results = {};

        for (let i = 0; i < serviceIds.length; i++) {
            const sid = serviceIds[i];
            const qs = new URLSearchParams({ from, until, ServiceId: sid, bookedBy, language, noGifts });
            const apiUrl = 'https://www.escapeall.gr/api/Reservation?' + qs.toString();

            console.error(`[fast] [${i + 1}/${serviceIds.length}] Fetching ${sid.substring(0, 8)}...`);

            try {
                const result = await page.evaluate(async (url) => {
                    const res = await fetch(url, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json, text/plain, */*',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'include',
                    });
                    return await res.text();
                }, apiUrl);

                const json = JSON.parse(result);
                if (Array.isArray(json)) {
                    results[sid] = json;
                    console.error(`[fast]   ✓ ${json.length} day(s)`);
                } else {
                    console.error(`[fast]   ✗ Response not an array`);
                    results[sid] = [];
                }
            } catch (err) {
                console.error(`[fast]   ✗ Error: ${err.message}`);
                results[sid] = [];
            }

            // Small delay between calls (not the last one)
            if (i < serviceIds.length - 1) {
                await sleep(delay + Math.random() * 500);
            }
        }

        // ── Output all results at once ──
        console.log(JSON.stringify(results));

    } catch (err) {
        console.error(`[fast] Fatal error: ${err.message}`);
        process.exit(1);
    } finally {
        await context.close();
        await browser.close();
    }
})();
