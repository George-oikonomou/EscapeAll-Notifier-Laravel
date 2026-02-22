/**
 * fetch-availability-fast.js
 *
 * Fetches room availability from the EscapeAll API.
 * Launches Playwright once to grab cookies, then makes API calls
 * from within the browser context using XMLHttpRequest (which the
 * server recognises as a valid AJAX request).
 *
 * Usage:
 *   node fetch-availability-fast.js \
 *     from=2026-02-22 until=2026-04-22 \
 *     serviceIds=uuid1,uuid2,uuid3 \
 *     language=el bookedBy=1 noGifts=false delayMs=1500
 */

const { chromium } = require('playwright-extra');
const stealth = require('puppeteer-extra-plugin-stealth')();
chromium.use(stealth);

/* ── Parse CLI args ──────────────────────────────────────────────── */
const args = {};
process.argv.slice(2).forEach(arg => {
    const [k, ...rest] = arg.split('=');
    args[k] = rest.join('=');
});

const FROM_DATE   = args.from       || '';
const UNTIL_DATE  = args.until      || '';
const SERVICE_IDS = (args.serviceIds || '').split(',').filter(Boolean);
const LANGUAGE    = args.language    || 'el';
const BOOKED_BY   = args.bookedBy   || '1';
const NO_GIFTS    = args.noGifts    || 'false';
const DELAY_MS    = parseInt(args.delayMs || '1500', 10);

if (!FROM_DATE || !UNTIL_DATE || SERVICE_IDS.length === 0) {
    console.error('Usage: node fetch-availability-fast.js from=YYYY-MM-DD until=YYYY-MM-DD serviceIds=id1,id2,...');
    process.exit(1);
}

function sleep(ms) {
    return new Promise(r => setTimeout(r, ms));
}

/* ── Format date for API ─────────────────────────────────────────── */
function formatApiDate(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    const day   = d.getDate();
    const month = d.getMonth() + 1;
    const year  = d.getFullYear();
    return `${month}/${day}/${year}`;
}

(async () => {
    const browser = await chromium.launch({ headless: true });
    const ctx = await browser.newContext({
        userAgent:
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 ' +
            '(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        locale: LANGUAGE === 'en' ? 'en-US' : 'el-GR',
    });
    const page = await ctx.newPage();

    try {
        // Load homepage to establish session cookies
        console.error('[fetch-avail] Loading homepage for cookies...');
        await page.goto('https://www.escapeall.gr', { waitUntil: 'networkidle', timeout: 30000 });
        await page.waitForTimeout(2000);

        const results = {};

        const apiFrom  = formatApiDate(FROM_DATE);
        const apiUntil = formatApiDate(UNTIL_DATE);

        console.error(`[fetch-avail] Fetching ${SERVICE_IDS.length} services, ${apiFrom} → ${apiUntil}`);

        for (let i = 0; i < SERVICE_IDS.length; i++) {
            const serviceId = SERVICE_IDS[i];
            try {
                const url = `https://www.escapeall.gr/api/Reservation/` +
                    `GetAvailabilityForServiceBetweenDates?` +
                    `serviceId=${serviceId}` +
                    `&from=${encodeURIComponent(apiFrom)}` +
                    `&to=${encodeURIComponent(apiUntil)}` +
                    `&bookedBy=${BOOKED_BY}` +
                    `&noGifts=${NO_GIFTS}` +
                    `&language=${LANGUAGE}`;

                // Use XMLHttpRequest inside the browser context.
                // This sends proper AJAX headers (X-Requested-With, Referer, Origin)
                // which the server requires. A plain fetch() or page.goto() navigation
                // doesn't set these, causing the server to return 404.
                const res = await page.evaluate(async ({ fetchUrl }) => {
                    return new Promise((resolve) => {
                        const xhr = new XMLHttpRequest();
                        xhr.open('GET', fetchUrl, true);
                        xhr.setRequestHeader('Accept', 'application/json');
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                        xhr.onreadystatechange = function () {
                            if (xhr.readyState === 4) {
                                if (xhr.status >= 200 && xhr.status < 300) {
                                    try {
                                        resolve(JSON.parse(xhr.responseText));
                                    } catch (e) {
                                        resolve({ error: 'parse_error' });
                                    }
                                } else {
                                    resolve({ error: xhr.status });
                                }
                            }
                        };
                        xhr.onerror = function () {
                            resolve({ error: 'network_error' });
                        };
                        xhr.send();
                    });
                }, { fetchUrl: url });

                if (res && !res.error) {
                    results[serviceId] = res;
                    console.error(`  [${i + 1}/${SERVICE_IDS.length}] ${serviceId}: ${Array.isArray(res) ? res.length : '?'} days`);
                } else {
                    console.error(`  [${i + 1}/${SERVICE_IDS.length}] ${serviceId}: error ${res?.error || 'unknown'}`);
                    results[serviceId] = [];
                }
            } catch (err) {
                console.error(`  [${i + 1}/${SERVICE_IDS.length}] ${serviceId}: ${err.message}`);
                results[serviceId] = [];
            }

            if (i < SERVICE_IDS.length - 1) {
                await sleep(DELAY_MS);
            }
        }

        // Output JSON to stdout
        process.stdout.write(JSON.stringify(results));
    } catch (err) {
        console.error('[fetch-avail] Error: ' + err.message);
        process.exitCode = 1;
    } finally {
        await browser.close();
    }
})();
