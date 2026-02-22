/**
 * fetch-companies.js
 *
 * Scrapes company data (with geo, address, municipality) from the
 * EscapeAll Companies page: https://www.escapeall.gr/el/Companies
 *
 * Each company card has a ".show-map" button with rich data-* attributes:
 *   data-latitude, data-longitude, data-municipalityid, data-areaid,
 *   data-title, data-address
 *
 * The company external_id is extracted from the logo image path:
 *   /Images/CompaniesPhotos/{external_id}/...
 *
 * Also extracts municipalities (areas) from the areasArray JS variable.
 *
 * Usage:
 *   node fetch-companies.js [options]
 *
 * Options (key=value):
 *   format   = json (default) | html
 *   out      = path to write JSON output
 *   language = el (default) | en
 *   waitMs   = extra wait in ms after page load (default 5000)
 */

const { chromium } = require('playwright-extra');
const stealth = require('puppeteer-extra-plugin-stealth')();
chromium.use(stealth);

const fs = require('fs');
const path = require('path');

/* ── Parse CLI args ──────────────────────────────────────────────── */
const args = {};
process.argv.slice(2).forEach(a => {
    const [k, ...rest] = a.split('=');
    args[k] = rest.join('=');
});

const FORMAT      = args.format      || 'json';
const OUT_FILE    = args.out          || '';
const LANGUAGE    = args.language     || 'el';
const WAIT_MS     = parseInt(args.waitMs || '5000', 10);
const WEBHOOK_URL    = args.webhookUrl    || '';
const WEBHOOK_SECRET = args.webhookSecret || '';

const BASE_URL = `https://www.escapeall.gr/${LANGUAGE}/Companies`;

/* ── Main ────────────────────────────────────────────────────────── */
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
        await page.goto(BASE_URL, { waitUntil: 'networkidle', timeout: 30000 });
        await page.waitForTimeout(WAIT_MS);

        /* ─────────────────────── Extract companies from DOM ─────────── */
        const companies = await page.evaluate(() => {
            const buttons = document.querySelectorAll('.show-map');
            const results = [];

            buttons.forEach(btn => {
                // Find the parent company card to get the logo URL
                let card = btn.closest('.col-md-6, .col-sm-6');
                let logoUrl = '';
                let externalId = '';

                if (card) {
                    // Logo image: /Images/CompaniesPhotos/{uuid}/...
                    const img = card.querySelector('img[itemprop="logo"]')
                             || card.querySelector('picture img')
                             || card.querySelector('img');
                    if (img) {
                        logoUrl = img.getAttribute('src') || '';
                    }
                    // Also check <source> for webp
                    const source = card.querySelector('picture source');
                    if (source) {
                        const srcset = source.getAttribute('srcset') || '';
                        if (srcset && !logoUrl) logoUrl = srcset;
                    }

                    // Extract external_id from logo path
                    const match = logoUrl.match(/CompaniesPhotos\/([0-9a-f-]{36})\//i);
                    if (match) {
                        externalId = match[1];
                    }
                }

                const lat = btn.getAttribute('data-latitude');
                const lng = btn.getAttribute('data-longitude');

                results.push({
                    external_id: externalId,
                    name: btn.getAttribute('data-title') || '',
                    logo_url: logoUrl,
                    latitude: lat ? parseFloat(lat) : null,
                    longitude: lng ? parseFloat(lng) : null,
                    address: btn.getAttribute('data-address') || '',
                    full_address: btn.getAttribute('data-address') || '',
                    municipality_external_id: btn.getAttribute('data-municipalityid') || '',
                    area_external_id: btn.getAttribute('data-areaid') || '',
                });
            });

            return results;
        });

        /* ───────────── Extract areas/municipalities from areasArray ──── */
        const areas = await page.evaluate(() => {
            if (typeof areasArray === 'undefined' || !Array.isArray(areasArray)) return [];
            return areasArray
                .filter(item => !item.dataService && !item.dataCompany)
                .map(item => ({
                    external_id: item.id || '',
                    name: item.text || item.label || '',
                }));
        });

        /* ─────────────────────────── Output ─────────────────────────── */
        const payload = { companies, areas };

        if (FORMAT === 'json') {
            const json = JSON.stringify(payload, null, 2);
            if (OUT_FILE) {
                const dir = path.dirname(OUT_FILE);
                if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
                fs.writeFileSync(OUT_FILE, json, 'utf-8');
                process.stdout.write(
                    `Wrote companies=${companies.length}, areas=${areas.length} → ${OUT_FILE}\n`
                );
            } else {
                process.stdout.write(json + '\n');
            }
        } else {
            // Simple HTML table output
            let html = '<h2>Companies</h2><table border="1"><tr>' +
                '<th>ID</th><th>Name</th><th>Lat</th><th>Lng</th>' +
                '<th>Address</th><th>Municipality ID</th><th>Logo</th></tr>';
            companies.forEach(c => {
                html += `<tr>
                    <td>${c.external_id}</td>
                    <td>${c.name}</td>
                    <td>${c.latitude || ''}</td>
                    <td>${c.longitude || ''}</td>
                    <td>${c.address}</td>
                    <td>${c.municipality_external_id}</td>
                    <td><img src="https://www.escapeall.gr${c.logo_url}" width="80"></td>
                </tr>`;
            });
            html += '</table>';
            html += '<h2>Areas</h2><table border="1"><tr><th>ID</th><th>Name</th></tr>';
            areas.forEach(a => {
                html += `<tr><td>${a.external_id}</td><td>${a.name}</td></tr>`;
            });
            html += '</table>';

            if (OUT_FILE) {
                fs.writeFileSync(OUT_FILE, html, 'utf-8');
                process.stdout.write(`Wrote HTML → ${OUT_FILE}\n`);
            } else {
                process.stdout.write(html + '\n');
            }
        }

        /* ─────────────── Webhook POST (GitHub Actions mode) ─────────── */
        if (WEBHOOK_URL) {
            process.stderr.write(`[webhook] POSTing to ${WEBHOOK_URL} ...\n`);
            const res = await fetch(WEBHOOK_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Webhook-Secret': WEBHOOK_SECRET,
                },
                body: JSON.stringify(payload),
            });
            const body = await res.text();
            process.stderr.write(`[webhook] Response ${res.status}: ${body}\n`);
            if (!res.ok) {
                process.exitCode = 1;
            }
        }
    } catch (err) {
        process.stderr.write('[fetch-companies] Error: ' + err.message + '\n');
        process.exitCode = 1;
    } finally {
        await browser.close();
    }
})();

