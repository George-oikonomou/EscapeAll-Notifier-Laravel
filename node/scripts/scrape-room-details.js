/**
 * scrape-room-details.js
 *
 * Fetches each room's detail page from EscapeAll and extracts
 * enrichment data (description, difficulty, languages, video URL).
 *
 * 1. GETs room slugs from the webhook API
 * 2. Fetches /el/EscapeRoom/Details/{slug} for each room
 * 3. Parses HTML for enrichment data
 * 4. POSTs results to the webhook
 *
 * Usage (GitHub Actions):
 *   node scrape-room-details.js \
 *     webhookUrl=https://yourdomain.com/api/webhook/sync-room-details \
 *     webhookSecret=your-secret \
 *     delayMs=2000 waveSize=15 cooldownSec=30
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

const WEBHOOK_URL    = args.webhookUrl    || '';
const WEBHOOK_SECRET = args.webhookSecret || '';
const DELAY_MS       = parseInt(args.delayMs || '2000', 10);
const WAVE_SIZE      = parseInt(args.waveSize || '15', 10);
const COOLDOWN_SEC   = parseInt(args.cooldownSec || '30', 10);
const LANGUAGE       = args.language || 'el';

function sleep(ms) {
    return new Promise(r => setTimeout(r, ms));
}

/**
 * Parse a room detail HTML page to extract enrichment data.
 * Mirrors the logic from SyncRoomsFast.php → parseDetailHtml().
 */
function parseDetailHtml(html, slug) {
    const detail = {
        slug,
        description: null,
        difficulty: null,
        languages: null,
        video_url: null,
    };

    // ── Description from JSON-LD ──
    const headMatch = html.match(/<head[^>]*>([\s\S]*?)<\/head>/i);
    const searchIn = headMatch ? headMatch[1] : html;

    const ldMatch = searchIn.match(/<script[^>]*type=["']application\/ld\+json["'][^>]*>([\s\S]*?)<\/script>/i);
    if (ldMatch) {
        try {
            const ld = JSON.parse(ldMatch[1]);
            if (ld.description && ld.description.trim() !== '') {
                detail.description = ld.description.trim();
            }
        } catch (e) { /* skip */ }
    }

    // Fallback: <meta name="description">
    if (!detail.description) {
        const metaMatch = searchIn.match(/<meta\b[^>]*\bname=["']description["'][^>]*\bcontent=["'](.+?)["'][^>]*>/si)
            || searchIn.match(/<meta\b[^>]*\bcontent=["'](.+?)["'][^>]*\bname=["']description["'][^>]*>/si);
        if (metaMatch && metaMatch[1].trim()) {
            detail.description = metaMatch[1].trim();
        }
    }

    // ── Difficulty from progress bar ──
    const diffMatch = html.match(/Δυσκολία[\s\S]*?aria-valuenow=["'](\d+(?:\.\d+)?)["']/);
    if (diffMatch) {
        detail.difficulty = parseFloat(diffMatch[1]) / 10.0;
    }

    // ── Languages ──
    const langMatch = html.match(/Γλώσσες[\s\S]*?<div[^>]*class=["']col-sm-8["'][^>]*>([\s\S]*?)<\/div>/i);
    if (langMatch) {
        const langText = langMatch[1].replace(/<[^>]+>/g, '').trim();
        if (langText) {
            detail.languages = langText.split(',').map(s => s.trim()).filter(Boolean);
        }
    }

    // ── YouTube video URL ──
    const videoMatch = html.match(/iframe[^>]+src=["']([^"']*youtube[^"']*)["']/);
    if (videoMatch) {
        detail.video_url = videoMatch[1];
    }

    return detail;
}

(async () => {
    if (!WEBHOOK_URL) {
        console.error('Usage: node scrape-room-details.js webhookUrl=... webhookSecret=...');
        process.exit(1);
    }

    // ── Step 1: Get room slugs from webhook API ──
    const slugsUrl = WEBHOOK_URL.replace(/\/sync-room-details$/, '/room-slugs');
    console.error(`[room-details] Fetching room slugs from ${slugsUrl}...`);

    const slugsRes = await fetch(slugsUrl, {
        headers: {
            'Accept': 'application/json',
            'X-Webhook-Secret': WEBHOOK_SECRET,
        },
    });

    if (!slugsRes.ok) {
        console.error(`[room-details] Failed to fetch slugs: HTTP ${slugsRes.status}`);
        process.exit(1);
    }

    const slugsData = await slugsRes.json();
    const rooms = slugsData.rooms || [];
    console.error(`[room-details] Got ${rooms.length} rooms to process`);

    if (rooms.length === 0) {
        console.error('[room-details] No rooms to process');
        process.exit(0);
    }

    // ── Step 2: Launch browser for stealth requests ──
    const browser = await chromium.launch({ headless: true });
    const ctx = await browser.newContext({
        userAgent:
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 ' +
            '(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        locale: LANGUAGE === 'en' ? 'en-US' : 'el-GR',
    });
    const page = await ctx.newPage();

    try {
        // Visit homepage first to establish session
        await page.goto('https://www.escapeall.gr', { waitUntil: 'networkidle', timeout: 30000 });
        await page.waitForTimeout(2000);

        const results = [];
        let success = 0;
        let failed = 0;

        // ── Step 3: Fetch detail pages in waves ──
        const waves = [];
        for (let i = 0; i < rooms.length; i += WAVE_SIZE) {
            waves.push(rooms.slice(i, i + WAVE_SIZE));
        }

        console.error(`[room-details] ${waves.length} waves of ${WAVE_SIZE}, delay=${DELAY_MS}ms, cooldown=${COOLDOWN_SEC}s`);

        for (let wi = 0; wi < waves.length; wi++) {
            const wave = waves[wi];
            console.error(`\n═══ Wave ${wi + 1}/${waves.length} (${wave.length} rooms) ═══`);

            for (let ri = 0; ri < wave.length; ri++) {
                const { slug, external_id } = wave[ri];
                const url = `https://www.escapeall.gr/${LANGUAGE}/EscapeRoom/Details/${slug}`;

                try {
                    const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 15000 });

                    if (!response || response.status() !== 200) {
                        const status = response ? response.status() : 'no response';
                        console.error(`  ✗ [${success + failed + 1}/${rooms.length}] ${slug} → ${status}`);

                        // Retry on 429
                        if (response && response.status() === 429) {
                            console.error(`    ⏳ Rate limited, waiting 15s...`);
                            await sleep(15000);
                            const retry = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 15000 });
                            if (retry && retry.status() === 200) {
                                const html = await page.content();
                                const detail = parseDetailHtml(html, slug);
                                detail.external_id = external_id;
                                results.push(detail);
                                success++;
                                console.error(`  ✓ [${success + failed}/${rooms.length}] ${slug} → recovered on retry`);
                                await sleep(DELAY_MS);
                                continue;
                            }
                        }

                        failed++;
                        await sleep(DELAY_MS);
                        continue;
                    }

                    const html = await page.content();
                    const detail = parseDetailHtml(html, slug);
                    detail.external_id = external_id;
                    results.push(detail);
                    success++;
                    console.error(`  ✓ [${success + failed}/${rooms.length}] ${slug}`);
                } catch (err) {
                    console.error(`  ✗ [${success + failed + 1}/${rooms.length}] ${slug} → ${err.message}`);
                    failed++;
                }

                await sleep(DELAY_MS);
            }

            // Cooldown between waves (except last)
            if (wi < waves.length - 1) {
                console.error(`  ⏸ Cooldown ${COOLDOWN_SEC}s...`);
                await sleep(COOLDOWN_SEC * 1000);
            }
        }

        console.error(`\n[room-details] Done: ${success} success, ${failed} failed out of ${rooms.length}`);

        // ── Step 4: POST results to webhook ──
        if (results.length > 0) {
            console.error(`[room-details] POSTing ${results.length} room details to webhook...`);
            const payload = JSON.stringify({ details: results });
            console.error(`[room-details] Payload size: ${(payload.length / 1024 / 1024).toFixed(2)} MB`);

            const res = await fetch(WEBHOOK_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Webhook-Secret': WEBHOOK_SECRET,
                },
                body: payload,
            });

            const body = await res.text();
            console.error(`[room-details] Response ${res.status}: ${body}`);
            if (!res.ok) {
                process.exitCode = 1;
            }
        }
    } catch (err) {
        console.error('[room-details] Error: ' + err.message);
        process.exitCode = 1;
    } finally {
        await browser.close();
    }
})();
