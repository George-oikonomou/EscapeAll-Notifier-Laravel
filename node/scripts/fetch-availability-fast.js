/**
 * fetch-availability-fast.js
 *
 * Fetches room availability from the EscapeAll API.
 * Launches Playwright once to grab cookies, then makes API calls
 * from within the browser context using fetch.
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

/* ── Format date for API (YYYY-M-D, no zero-padding) ────────────── */
function formatApiDate(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    const day   = d.getDate();
    const month = d.getMonth() + 1;
    const year  = d.getFullYear();
    return `${year}-${month}-${day}`;
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
                // Correct API endpoint: /api/Reservation
                // Params: from (YYYY-M-D), until (YYYY-M-D), ServiceId, bookedBy, language, noGifts, queueToken
                const url = `https://www.escapeall.gr/api/Reservation?` +
                    `from=${apiFrom}` +
                    `&until=${apiUntil}` +
                    `&ServiceId=${serviceId}` +
                    `&bookedBy=${BOOKED_BY}` +
                    `&language=${LANGUAGE}` +
                    `&noGifts=${NO_GIFTS}` +
                    `&queueToken=`;

                const res = await page.evaluate(async ({ fetchUrl }) => {
                    try {
                        const r = await fetch(fetchUrl, {
                            method: 'GET',
                            headers: {
                                'Accept': 'application/json, text/plain, */*',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                        });
                        if (!r.ok) {
                            const text = await r.text();
                            return { error: r.status, body: text.substring(0, 200) };
                        }
                        return { data: await r.json(), status: r.status };
                    } catch (e) {
                        return { error: e.message };
                    }
                }, { fetchUrl: url });

                if (res.data) {
                    results[serviceId] = res.data;
                    console.error(`  [${i + 1}/${SERVICE_IDS.length}] ${serviceId}: ${Array.isArray(res.data) ? res.data.length : '?'} days`);
                } else {
                    console.error(`  [${i + 1}/${SERVICE_IDS.length}] ${serviceId}: error ${res.error}`);
                    if (res.body) console.error(`    Response body: ${res.body}`);
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
