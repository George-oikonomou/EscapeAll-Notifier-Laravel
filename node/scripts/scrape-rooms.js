/**
 * scrape-rooms.js
 *
 * Playwright-based scraper for EscapeAll room listings.
 *
 * Usage (file mode):
 *   node scrape-rooms.js format=json out=./rooms.json waitMs=5000
 *
 * Usage (webhook mode - GitHub Actions):
 *   node scrape-rooms.js format=json waitMs=5000 \
 *     webhookUrl=https://yourdomain.com/api/webhook/sync-rooms \
 *     webhookSecret=your-secret
 */

const { chromium } = require('playwright-extra');
const stealth = require('puppeteer-extra-plugin-stealth')();
chromium.use(stealth);

const fs = require('fs');

/* ── Parse CLI args ──────────────────────────────────────────────── */
const args = {};
process.argv.slice(2).forEach(arg => {
    const [k, ...rest] = arg.split('=');
    args[k] = rest.join('=');
});

const outFile = args.out || null;
const waitMs = parseInt(args.waitMs || '5000', 10);
const format = args.format || 'json';
const webhookUrl    = args.webhookUrl    || '';
const webhookSecret = args.webhookSecret || '';

// One room card on the listing page (EscapeAll redesign, Oct 2026).
const CARD_SELECTOR = 'article.ea-result';

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();

    console.error('[fetch-rooms] Loading /el/EscapeRooms/attica ...');
    await page.goto('https://www.escapeall.gr/el/EscapeRooms/attica', {
        waitUntil: 'networkidle',
        timeout: 90000,
    });
    await page.waitForTimeout(waitMs);

    // ── Scroll to bottom to load ALL rooms ──
    let previousCount = 0;
    let stableRounds = 0;
    const maxScrollAttempts = 120;
    for (let i = 0; i < maxScrollAttempts; i++) {
        const currentCount = await page.evaluate((sel) =>
            document.querySelectorAll(sel).length
        , CARD_SELECTOR);
        console.error(`[fetch-rooms] Scroll #${i + 1}: ${currentCount} rooms loaded`);
        if (currentCount === previousCount) {
            stableRounds++;
            if (stableRounds >= 10) {
                console.error('[fetch-rooms] No new rooms after 10 scrolls, done loading.');
                break;
            }
        } else {
            stableRounds = 0;
        }
        previousCount = currentCount;
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
        await page.waitForTimeout(3000);
    }

    // ── Extract `companies` JS variable ──
    const companiesJsVar = await page.evaluate(() => {
        if (typeof companies !== 'undefined') return companies;
        return [];
    });
    console.error(`[fetch-rooms] Extracted companies JS variable: ${companiesJsVar.length} companies`);

    // ── Build slug→serviceId map from companies (authoritative source) ──
    const slugToServiceId = {};
    for (const company of companiesJsVar) {
        if (!company.Services) continue;
        for (const svc of company.Services) {
            if (!svc.Url || !svc.Id) continue;
            const m = svc.Url.match(/\/Details\/([^?#]+)/);
            if (m) {
                slugToServiceId[m[1]] = svc.Id;
            }
        }
    }
    console.error(`[fetch-rooms] Built slug→serviceId map: ${Object.keys(slugToServiceId).length} entries`);

    // ── Extract all room data from cards ──
    const rooms = await page.evaluate((sel) => {
        const results = [];
        const cards = document.querySelectorAll(sel);

        cards.forEach(card => {
            try {
                // --- Image URL ---
                const img = card.querySelector('picture img, img.img-responsive');
                let imageUrl = '';
                if (img) {
                    imageUrl = img.getAttribute('src') || '';
                }

                // --- Title and slug ---
                const titleLink = card.querySelector('.ea-result-title a');
                const title = titleLink ? titleLink.textContent.trim() : '';
                let slug = '';
                if (titleLink) {
                    const href = titleLink.getAttribute('href') || '';
                    const slugMatch = href.match(/\/Details\/([^?#]+)/);
                    if (slugMatch) slug = slugMatch[1];
                }

                // --- Company info from .show-map ---
                const mapEl = card.querySelector('.show-map');
                let companyName = '';
                let companyExternalId = '';
                let latitude = null;
                let longitude = null;
                let address = '';
                if (mapEl) {
                    companyName = mapEl.getAttribute('data-company') || '';
                    companyExternalId = mapEl.getAttribute('data-company-id') || '';
                    const lat = mapEl.getAttribute('data-latitude');
                    const lng = mapEl.getAttribute('data-longitude');
                    if (lat) latitude = parseFloat(lat);
                    if (lng) longitude = parseFloat(lng);
                    address = mapEl.getAttribute('data-address') || '';
                }

                // --- Rating ---
                const ratingEl = card.querySelector('.ea-result-rating-value');
                let rating = null;
                if (ratingEl) {
                    const ratingText = ratingEl.textContent.trim().replace(',', '.');
                    const parsed = parseFloat(ratingText);
                    if (!isNaN(parsed)) rating = parsed;
                }

                // --- Reviews count (sibling .ea-result-top-list is the "Top List" count, not reviews) ---
                const reviewsEl = card.querySelector('.ea-result-rating-count > span:not(.ea-result-top-list)');
                let reviewsCount = null;
                if (reviewsEl) {
                    const m = reviewsEl.textContent.trim().match(/(\d+)/);
                    if (m) reviewsCount = parseInt(m[1], 10);
                }

                // --- Short description ---
                const descEl = card.querySelector('.ea-result-description');
                const shortDescription = descEl ? descEl.textContent.trim() : '';

                // --- Categories from service-icons ---
                const categories = [];
                const iconContainer = card.querySelector('.service-icons');
                if (iconContainer) {
                    const spans = iconContainer.querySelectorAll(':scope > div > span');
                    spans.forEach(span => {
                        if (span.querySelector('.fa-trophy')) return;
                        const text = span.textContent.trim();
                        if (!text) return;
                        if (/^\d+\s*Τρόπαια$/i.test(text.replace(/\n/g, ' ').trim())) return;
                        if (/^[🖐️💀👻🎃]+/.test(text)) return;
                        categories.push(text);
                    });
                }

                // --- Duration, Players, Escape Rate ---
                // Facts are <li>s identified by icon; the escape-rate one is omitted for some rooms.
                const factText = (icon) => {
                    const li = card.querySelector(`.ea-result-facts li:has(> i.${icon})`);
                    return li ? li.textContent.trim() : '';
                };
                let durationMinutes = null;
                let minPlayers = null;
                let maxPlayers = null;
                let escapeRate = null;

                const durText = factText('fa-clock-o');
                if (durText) {
                    const durMatch = durText.match(/(\d+)/);
                    if (durMatch) durationMinutes = parseInt(durMatch[1], 10);
                }
                const playersText = factText('fa-user');
                if (playersText) {
                    const playersMatch = playersText.match(/(\d+)\s*-\s*(\d+)/);
                    if (playersMatch) {
                        minPlayers = parseInt(playersMatch[1], 10);
                        maxPlayers = parseInt(playersMatch[2], 10);
                    } else {
                        const singleMatch = playersText.match(/(\d+)/);
                        if (singleMatch) {
                            minPlayers = parseInt(singleMatch[1], 10);
                            maxPlayers = minPlayers;
                        }
                    }
                }
                const escText = factText('fa-sign-out').replace(',', '.');
                if (escText) {
                    const escMatch = escText.match(/([\d.]+)/);
                    if (escMatch) escapeRate = parseFloat(escMatch[1]);
                }

                // --- Detect "Coming Soon" rooms ---
                const cardText = card.textContent || '';
                const hasComingSoon = cardText.includes('Σύντομα κοντά σας') ||
                                     cardText.includes('Coming Soon') ||
                                     cardText.includes('coming soon') ||
                                     categories.includes('Σύντομα κοντά σας');

                const finalCategories = [...categories];
                if (hasComingSoon && !categories.includes('Σύντομα κοντά σας')) {
                    finalCategories.push('Σύντομα κοντά σας');
                }

                // --- Company link text ---
                const companyLink = card.querySelector('a.ea-result-company');
                const companyLinkText = companyLink ? companyLink.textContent.trim() : '';

                if (slug) {
                    results.push({
                        slug,
                        title,
                        company_name: companyLinkText || companyName,
                        company_external_id: companyExternalId,
                        rating,
                        reviews_count: reviewsCount,
                        short_description: shortDescription,
                        categories: finalCategories,
                        duration_minutes: durationMinutes,
                        min_players: minPlayers,
                        max_players: maxPlayers,
                        escape_rate: escapeRate,
                        image_url: imageUrl,
                        latitude,
                        longitude,
                        address,
                    });
                }
            } catch (e) {
                // Skip malformed cards
            }
        });

        return results;
    }, CARD_SELECTOR);

    console.error(`[fetch-rooms] Extracted ${rooms.length} rooms (from cards)`);

    // ── Assign external_id from companies Services (authoritative booking ID) ──
    let matched = 0;
    let fallback = 0;
    for (const room of rooms) {
        if (slugToServiceId[room.slug]) {
            room.external_id = slugToServiceId[room.slug];
            matched++;
        } else {
            // Fallback: extract from image URL path
            const imgMatch = (room.image_url || '').match(/\/ServicesPhotos\/([a-f0-9-]{36})\//i);
            room.external_id = imgMatch ? imgMatch[1] : '';
            if (room.external_id) {
                console.error(`[fetch-rooms] WARN: "${room.title}" not in companies, using photo ID as fallback`);
                fallback++;
            }
        }
    }
    console.error(`[fetch-rooms] Assigned IDs: ${matched} from companies, ${fallback} from photo fallback`);

    await browser.close();

    const output = JSON.stringify({ rooms, companies: companiesJsVar }, null, 2);
    if (outFile) {
        fs.writeFileSync(outFile, output, 'utf-8');
        console.error(`[fetch-rooms] Written to ${outFile}`);
    }
    if (format === 'json') {
        process.stdout.write(output);
    }

    if (rooms.length === 0) {
        console.error(`[fetch-rooms] ERROR: no room cards matched "${CARD_SELECTOR}" — EscapeAll layout probably changed. Not POSTing.`);
        process.exitCode = 1;
        return;
    }

    /* ─────────────── Webhook POST (GitHub Actions mode) ─────────── */
    if (webhookUrl) {
        console.error(`[webhook] POSTing ${rooms.length} rooms to ${webhookUrl} ...`);
        const res = await fetch(webhookUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Webhook-Secret': webhookSecret,
            },
            body: JSON.stringify({ rooms, companies: companiesJsVar }),
        });
        const body = await res.text();
        console.error(`[webhook] Response ${res.status}: ${body}`);
        if (!res.ok) {
            process.exitCode = 1;
        }
    }
})();
