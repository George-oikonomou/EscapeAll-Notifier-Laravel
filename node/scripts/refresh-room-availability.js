/**
 * refresh-room-availability.js
 *
 * Scrapes availability for a SINGLE room and POSTs results to the webhook.
 * Triggered by the on-demand GitHub Actions workflow.
 *
 * Usage:
 *   node refresh-room-availability.js \
 *     webhookUrl=https://yourdomain.com/api/webhook/sync-availability \
 *     webhookSecret=your-secret \
 *     serviceId=some-uuid \
 *     months=10
 */

const { execFileSync } = require('child_process');
const path = require('path');

const args = Object.fromEntries(
    process.argv.slice(2).map(a => {
        const [k, ...v] = a.split('=');
        return [k, v.join('=')];
    })
);

const WEBHOOK_URL    = args.webhookUrl    || '';
const WEBHOOK_SECRET = args.webhookSecret || '';
const SERVICE_ID     = args.serviceId     || '';
const MONTHS         = parseInt(args.months || '10', 10);
const DELAY_MS       = parseInt(args.delayMs || '1500', 10);
const DATE_BATCH_MONTHS = 2;

if (!WEBHOOK_URL || !WEBHOOK_SECRET || !SERVICE_ID) {
    console.error('[refresh] Missing required args: webhookUrl, webhookSecret, serviceId');
    process.exit(1);
}

(async () => {
    try {
        // Build date passes
        const datePasses = [];
        const now = new Date();
        now.setHours(0, 0, 0, 0);
        const end = new Date(now);
        end.setMonth(end.getMonth() + MONTHS);

        const cursor = new Date(now);
        while (cursor < end) {
            const passFrom = cursor.toISOString().split('T')[0];
            const passEnd = new Date(cursor);
            passEnd.setMonth(passEnd.getMonth() + DATE_BATCH_MONTHS);
            const passUntil = passEnd < end
                ? passEnd.toISOString().split('T')[0]
                : end.toISOString().split('T')[0];
            datePasses.push({ from: passFrom, until: passUntil });
            cursor.setMonth(cursor.getMonth() + DATE_BATCH_MONTHS);
        }

        console.error(`[refresh] Refreshing room ${SERVICE_ID} — ${datePasses.length} date passes`);

        const allResults = {};
        allResults[SERVICE_ID] = [];

        const fetchScript = path.join(__dirname, 'fetch-availability-fast.js');

        for (const pass of datePasses) {
            console.error(`  Date pass: ${pass.from} → ${pass.until}`);

            try {
                const output = execFileSync('node', [
                    fetchScript,
                    `from=${pass.from}`,
                    `until=${pass.until}`,
                    `serviceIds=${SERVICE_ID}`,
                    'language=el',
                    'bookedBy=1',
                    'noGifts=false',
                    `delayMs=${DELAY_MS}`,
                ], {
                    cwd: path.dirname(fetchScript),
                    env: {
                        ...process.env,
                        NODE_PATH: path.join(__dirname, '..', 'automation', 'node_modules'),
                    },
                    timeout: 120000,
                    maxBuffer: 50 * 1024 * 1024,
                });

                const results = JSON.parse(output.toString());

                for (const [extId, days] of Object.entries(results)) {
                    if (!Array.isArray(days) || days.length === 0) continue;
                    if (!allResults[extId]) allResults[extId] = [];
                    allResults[extId].push(...days);
                }

                console.error(`    ✓ Got data`);
            } catch (err) {
                console.error(`    ✗ Failed: ${err.message}`);
            }
        }

        // POST to webhook
        const fromDate = new Date().toISOString().split('T')[0];
        const payload = { results: allResults, from: fromDate };
        const payloadStr = JSON.stringify(payload);

        console.error(`[refresh] POSTing to ${WEBHOOK_URL}...`);

        const res = await fetch(WEBHOOK_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Webhook-Secret': WEBHOOK_SECRET,
            },
            body: payloadStr,
        });

        const body = await res.text();
        console.error(`[refresh] Response ${res.status}: ${body}`);

        if (!res.ok) {
            process.exitCode = 1;
        }
    } catch (err) {
        console.error(`[refresh] Fatal: ${err.message}`);
        process.exitCode = 1;
    }
})();
