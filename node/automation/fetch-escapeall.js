// fetch-escapeall.js
const { chromium } = require('playwright-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');

// enable stealth
chromium.use(StealthPlugin());

(async () => {
    const args = process.argv.slice(2);
    const params = Object.fromEntries(args.map(a => a.split('=')));
    const { from, until, serviceId, bookedBy = '1', language = 'el', noGifts = 'false' } = params;

    const qs = new URLSearchParams({ from, until, ServiceId: serviceId, bookedBy, language, noGifts });
    const apiUrl = 'https://www.escapeall.gr/api/Reservation?' + qs.toString();
    const homeUrl = 'https://www.escapeall.gr/';

    const browser = await chromium.launch({
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-blink-features=AutomationControlled',
            '--disable-dev-shm-usage',
            '--window-size=1280,800'
        ]
    });

    const page = await browser.newPage();

    try {
        console.log('Opening homepage stealthily...');
        await page.goto(homeUrl, { waitUntil: 'domcontentloaded', timeout: 60000 });
        await page.waitForTimeout(10000);

        console.log('Fetching API...');
        const result = await page.evaluate(async (url) => {
            const res = await fetch(url, { headers: { 'Accept': 'application/json, text/plain, */*' } });
            return await res.text();
        }, apiUrl);

        try {
            const json = JSON.parse(result);
            console.log(JSON.stringify(json, null, 2));
        } catch {
            console.error('Blocked or invalid JSON');
            console.log(result.slice(0, 300));
        }
    } catch (err) {
        console.error('Error:', err);
    } finally {
        await browser.close();
    }
})();
