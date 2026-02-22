/**
 * fetch-rooms.js
 *
 * Scrapes the /el/EscapeRooms/attica listing page for rich room data.
 * Outputs JSON: { rooms: [...], companies: [...] }
 *
 * - Fully scrolls the page to load all rooms (373+)
 * - Extracts the `companies` JS variable (company→rooms mapping)
 * - Cleans categories (filters trophy/award noise)
 *
 * Usage:
 *   node fetch-rooms.js [out=/path/to/output.json] [waitMs=5000]
 */
const { chromium } = require('playwright-extra');
const stealth = require('puppeteer-extra-plugin-stealth')();
chromium.use(stealth);
const fs = require('fs');

// Parse CLI args
const args = Object.fromEntries(
    process.argv.slice(2).map(a => {
        const [k, ...v] = a.split('=');
        return [k, v.join('=')];
    })
);

const outFile = args.out || null;
const waitMs = parseInt(args.waitMs || '5000', 10);
const format = args.format || 'json';
const webhookUrl    = args.webhookUrl    || '';
const webhookSecret = args.webhookSecret || '';

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
        const currentCount = await page.evaluate(() =>
            document.querySelectorAll('.panel-body > .row.service').length
        );
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
    const rooms = await page.evaluate(() => {
        const results = [];
        const cards = document.querySelectorAll('.panel-body > .row.service');

        cards.forEach(card => {
            try {
                // --- Image URL (for display only, NOT for external_id) ---
                const img = card.querySelector('picture img, img.img-responsive');
                let imageUrl = '';
                if (img) {
                    imageUrl = img.getAttribute('src') || '';
                }

                // --- Title and slug ---
                const titleLink = card.querySelector('h4 a');
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
                const ratingEl = card.querySelector('.compact-rating');
                let rating = null;
                if (ratingEl) {
                    const ratingText = ratingEl.textContent.trim().replace(',', '.');
                    const parsed = parseFloat(ratingText);
                    if (!isNaN(parsed)) rating = parsed;
                }

                // --- Reviews count ---
                const reviewsEl = card.querySelector('.reviews');
                let reviewsCount = null;
                if (reviewsEl) {
                    const m = reviewsEl.textContent.trim().match(/(\d+)/);
                    if (m) reviewsCount = parseInt(m[1], 10);
                }

                // --- Short description ---
                const descEl = card.querySelector('.short-description');
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
                const statCols = card.querySelectorAll('.time-players-escape-time .col-xs-4');
                let durationMinutes = null;
                let minPlayers = null;
                let maxPlayers = null;
                let escapeRate = null;

                if (statCols.length >= 1) {
                    const durText = statCols[0].textContent.trim();
                    const durMatch = durText.match(/(\d+)/);
                    if (durMatch) durationMinutes = parseInt(durMatch[1], 10);
                }
                if (statCols.length >= 2) {
                    const playersText = statCols[1].textContent.trim();
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
                if (statCols.length >= 3) {
                    const escText = statCols[2].textContent.trim().replace(',', '.');
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
                const companyLink = card.querySelector('a.company');
                const companyLinkText = companyLink ? companyLink.querySelector('span')?.textContent.trim() : '';

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
    });

    console.error(`[fetch-rooms] Extracted ${rooms.length} rooms (from cards)`);

    // ── Assign external_id from companies Services (authoritative booking ID) ──
    let matched = 0;
    let fallback = 0;
    for (const room of rooms) {
        if (slugToServiceId[room.slug]) {
            room.external_id = slugToServiceId[room.slug];
            matched++;
        } else {
            // Fallback: extract from image URL path (almost always the same, but not authoritative)
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

