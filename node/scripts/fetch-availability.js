/**
 * fetch-escapeall.js
 *
 * Fetches availability for a single room using advanced stealth techniques:
 *   - User-agent rotation
 *   - Random viewport sizes
 *   - Randomized delays with jitter
 *   - Stealth plugin to avoid bot detection
 *   - Realistic browser fingerprinting
 *   - Human-like mouse movements and scrolling
 *   - Referrer spoofing
 *   - Retry with exponential backoff
 *
 * Usage:
 *   node fetch-escapeall.js from=2026-02-17 until=2026-04-17 serviceId=YOUR-GUID
 *
 * Options:
 *   from          Start date YYYY-MM-DD
 *   until         End date YYYY-MM-DD
 *   serviceId     Room service GUID
 *   bookedBy      Usually 1 (default)
 *   language      el or en (default: el)
 *   noGifts       true or false (default: false)
 *   waitMs        Base wait time in ms (default: 5000)
 *   maxRetries    Max retry attempts (default: 3)
 */
const { chromium } = require('playwright-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');

// Enable stealth with all evasions
chromium.use(StealthPlugin());

// ── User-Agent pool (realistic, recent browsers) ──
const USER_AGENTS = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:126.0) Gecko/20100101 Firefox/126.0',
    'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36 Edg/123.0.0.0',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_4) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.3 Safari/605.1.15',
];

// ── Timezone pool (Greek users) ──
const TIMEZONES = ['Europe/Athens', 'Europe/Bucharest', 'Europe/Sofia'];

// ── Screen resolutions pool ──
const SCREENS = [
    { width: 1920, height: 1080 },
    { width: 1680, height: 1050 },
    { width: 1536, height: 864 },
    { width: 1440, height: 900 },
    { width: 1366, height: 768 },
    { width: 2560, height: 1440 },
];

// ── Random helpers ──
function randomInt(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

function randomFloat(min, max) {
    return Math.random() * (max - min) + min;
}

function randomDelay(baseMs, jitterPercent = 0.5) {
    const jitter = baseMs * jitterPercent * randomFloat(-1, 1);
    return Math.max(1000, baseMs + jitter + randomInt(500, 2000));
}

function pickRandom(arr) {
    return arr[randomInt(0, arr.length - 1)];
}

function sleep(ms) {
    return new Promise(r => setTimeout(r, ms));
}

// ── Human-like mouse movement ──
async function humanMouseMove(page, toX, toY, steps = null) {
    const numSteps = steps || randomInt(10, 25);
    const currentPos = { x: randomInt(0, 100), y: randomInt(0, 100) };

    for (let i = 0; i <= numSteps; i++) {
        const progress = i / numSteps;
        // Ease-in-out curve
        const eased = progress < 0.5
            ? 2 * progress * progress
            : 1 - Math.pow(-2 * progress + 2, 2) / 2;

        const x = currentPos.x + (toX - currentPos.x) * eased + randomInt(-3, 3);
        const y = currentPos.y + (toY - currentPos.y) * eased + randomInt(-3, 3);

        await page.mouse.move(x, y);
        await sleep(randomInt(5, 20));
    }
}

// ── Human-like scrolling ──
async function humanScroll(page) {
    const scrolls = randomInt(2, 5);
    for (let i = 0; i < scrolls; i++) {
        const scrollAmount = randomInt(100, 400);
        await page.mouse.wheel(0, scrollAmount);
        await sleep(randomInt(300, 800));
    }
    // Sometimes scroll back up a bit
    if (Math.random() > 0.5) {
        await page.mouse.wheel(0, -randomInt(50, 150));
        await sleep(randomInt(200, 500));
    }
}

// ── Main function with retry logic ──
async function fetchAvailability(params, attempt = 1) {
    const {
        from,
        until,
        serviceId,
        bookedBy = '1',
        language = 'el',
        noGifts = 'false',
        waitMs = '5000',
        maxRetries = '3'
    } = params;

    const baseWaitMs = parseInt(waitMs, 10);
    const maxRetryCount = parseInt(maxRetries, 10);

    const qs = new URLSearchParams({ from, until, ServiceId: serviceId, bookedBy, language, noGifts });
    const apiUrl = 'https://www.escapeall.gr/api/Reservation?' + qs.toString();
    const homeUrl = 'https://www.escapeall.gr/';
    const roomPageUrl = `https://www.escapeall.gr/${language === 'en' ? 'en' : 'el'}/Companies`;

    // Random browser config
    const userAgent = pickRandom(USER_AGENTS);
    const screen = pickRandom(SCREENS);
    const timezone = pickRandom(TIMEZONES);
    const viewport = {
        width: screen.width - randomInt(0, 200),
        height: screen.height - randomInt(100, 200)
    };

    console.error(`[escapeall] Attempt ${attempt}/${maxRetryCount}`);
    console.error(`[escapeall] UA: ${userAgent.substring(0, 60)}...`);
    console.error(`[escapeall] Viewport: ${viewport.width}x${viewport.height}, TZ: ${timezone}`);

    const browser = await chromium.launch({
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-blink-features=AutomationControlled',
            '--disable-dev-shm-usage',
            '--disable-web-security',
            '--disable-features=IsolateOrigins,site-per-process',
            `--window-size=${screen.width},${screen.height}`
        ]
    });

    const context = await browser.newContext({
        userAgent: userAgent,
        viewport: viewport,
        locale: language === 'en' ? 'en-US' : 'el-GR',
        timezoneId: timezone,
        geolocation: { latitude: 37.9838 + randomFloat(-0.1, 0.1), longitude: 23.7275 + randomFloat(-0.1, 0.1) },
        permissions: ['geolocation'],
        colorScheme: Math.random() > 0.3 ? 'light' : 'dark',
        deviceScaleFactor: Math.random() > 0.7 ? 2 : 1,
        hasTouch: Math.random() > 0.8,
        extraHTTPHeaders: {
            'Accept-Language': language === 'en' ? 'en-US,en;q=0.9' : 'el-GR,el;q=0.9,en;q=0.8',
            'sec-ch-ua': '"Chromium";v="125", "Not.A/Brand";v="24"',
            'sec-ch-ua-mobile': '?0',
            'sec-ch-ua-platform': '"Windows"',
        }
    });

    const page = await context.newPage();

    try {
        // ─── Step 1: Visit homepage naturally ───
        console.error(`[escapeall] Opening homepage...`);
        await page.goto(homeUrl, { waitUntil: 'domcontentloaded', timeout: 60000 });

        // Initial wait (like a human reading the page)
        const initialWait = randomDelay(baseWaitMs);
        console.error(`[escapeall] Browsing homepage for ${Math.round(initialWait/1000)}s...`);
        await sleep(initialWait);

        // ─── Step 2: Human-like interactions ───
        // Move mouse around
        await humanMouseMove(page, randomInt(200, 600), randomInt(150, 400));
        await sleep(randomInt(300, 700));

        // Scroll the page
        await humanScroll(page);
        await sleep(randomInt(500, 1500));

        // Maybe click on something non-critical (like hovering over menu)
        try {
            const menuItems = await page.$$('nav a, .nav-link, .menu-item');
            if (menuItems.length > 0) {
                const randomMenu = menuItems[randomInt(0, Math.min(menuItems.length - 1, 3))];
                const box = await randomMenu.boundingBox();
                if (box) {
                    await humanMouseMove(page, box.x + box.width / 2, box.y + box.height / 2);
                    await sleep(randomInt(200, 500));
                }
            }
        } catch (e) {
            // Ignore menu interaction errors
        }

        // ─── Step 3: Maybe visit another page first (appear more natural) ───
        if (Math.random() > 0.5) {
            console.error(`[escapeall] Visiting companies page first...`);
            await page.goto(roomPageUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });
            await sleep(randomDelay(baseWaitMs * 0.5));
            await humanScroll(page);
            await sleep(randomInt(1000, 2000));
        }

        // ─── Step 4: Final wait before API call ───
        const preApiWait = randomDelay(baseWaitMs * 0.7);
        console.error(`[escapeall] Pre-API wait ${Math.round(preApiWait/1000)}s...`);
        await sleep(preApiWait);

        // ─── Step 5: Fetch the API ───
        console.error(`[escapeall] Fetching API for serviceId=${serviceId.substring(0, 8)}...`);

        const result = await page.evaluate(async (url) => {
            // Add slight delay inside browser context too
            await new Promise(r => setTimeout(r, Math.random() * 500 + 200));

            const res = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json, text/plain, */*',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache',
                },
                credentials: 'include',
            });
            return await res.text();
        }, apiUrl);

        // ─── Step 6: Parse and validate result ───
        try {
            const json = JSON.parse(result);

            // Check if we got blocked (empty or error response)
            if (!Array.isArray(json)) {
                throw new Error('Response is not an array');
            }

            // Output JSON to stdout (this is what PHP reads)
            console.log(JSON.stringify(json, null, 2));
            console.error(`[escapeall] ✓ Got ${json.length} day(s) of data`);

            await context.close();
            await browser.close();
            return true;

        } catch (parseErr) {
            console.error(`[escapeall] ✗ Parse error: ${parseErr.message}`);
            console.error(`[escapeall] Response preview: ${result.slice(0, 200)}`);

            await context.close();
            await browser.close();

            // Retry logic
            if (attempt < maxRetryCount) {
                const backoffMs = Math.pow(2, attempt) * 10000 + randomInt(5000, 15000);
                console.error(`[escapeall] Retrying in ${Math.round(backoffMs/1000)}s...`);
                await sleep(backoffMs);
                return fetchAvailability(params, attempt + 1);
            }

            return false;
        }

    } catch (err) {
        console.error(`[escapeall] Error: ${err.message}`);

        await context.close();
        await browser.close();

        // Retry logic
        if (attempt < maxRetryCount) {
            const backoffMs = Math.pow(2, attempt) * 10000 + randomInt(5000, 15000);
            console.error(`[escapeall] Retrying in ${Math.round(backoffMs/1000)}s...`);
            await sleep(backoffMs);
            return fetchAvailability(params, attempt + 1);
        }

        return false;
    }
}

// ── Entry point ──
(async () => {
    const args = process.argv.slice(2);
    const params = Object.fromEntries(args.map(a => a.split('=')));

    const success = await fetchAvailability(params);
    process.exit(success ? 0 : 1);
})();
