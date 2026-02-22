/**
 * notify-availability-webhook.js
 *
 * Orchestrator for GitHub Actions (runs every 20 min):
 *   1) GET /api/webhook/reminder-room-ids → rooms with active reminders
 *   2) Scrape availability for those rooms via fetch-availability-fast.js
 *   3) POST /api/webhook/notify-availability → Laravel compares, emails, syncs
 *
 * Usage:
 *   node notify-availability-webhook.js \
 *     webhookUrl=https://yourdomain.com/api/webhook/notify-availability \
 *     roomIdsUrl=https://yourdomain.com/api/webhook/reminder-room-ids \
 *     webhookSecret=your-secret \
 *     months=3 batchSize=20 delayMs=1500
 */

const { execFileSync } = require('child_process');
const path = require('path');

// ── Parse args ──
const args = Object.fromEntries(
    process.argv.slice(2).map(a => {
        const [k, ...v] = a.split('=');
        return [k, v.join('=')];
    })
);

const WEBHOOK_URL    = args.webhookUrl    || '';
const ROOM_IDS_URL   = args.roomIdsUrl    || '';
const WEBHOOK_SECRET = args.webhookSecret || '';
const MONTHS         = parseInt(args.months    || '3', 10);
const BATCH_SIZE     = parseInt(args.batchSize || '20', 10);
const DELAY_MS       = parseInt(args.delayMs   || '1500', 10);
const DATE_BATCH_MONTHS = 2;

if (!WEBHOOK_URL || !ROOM_IDS_URL || !WEBHOOK_SECRET) {
    console.error('[notify-avail] Missing required args: webhookUrl, roomIdsUrl, webhookSecret');
    process.exit(1);
}

(async () => {
    try {
        // ── 1) Fetch room IDs with active reminders ──
        console.error('[notify-avail] Fetching reminder room IDs...');
        const idsRes = await fetch(ROOM_IDS_URL, {
            headers: {
                'Accept': 'application/json',
                'X-Webhook-Secret': WEBHOOK_SECRET,
            },
        });

        if (!idsRes.ok) {
            console.error(`[notify-avail] Failed to fetch room IDs: ${idsRes.status}`);
            process.exit(1);
        }

        const idsData = await idsRes.json();
        const allExtIds = idsData.room_ids || [];
        console.error(`[notify-avail] Got ${allExtIds.length} rooms with reminders`);

        if (allExtIds.length === 0) {
            console.error('[notify-avail] No rooms with reminders — nothing to do');
            process.exit(0);
        }

        // ── 2) Build date passes (2-month windows covering 3 months ahead) ──
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

        console.error(`[notify-avail] ${datePasses.length} date passes, ${BATCH_SIZE} rooms/batch`);

        // ── 3) Scrape availability ──
        const allResults = {};

        const roomBatches = [];
        for (let i = 0; i < allExtIds.length; i += BATCH_SIZE) {
            roomBatches.push(allExtIds.slice(i, i + BATCH_SIZE));
        }

        const fetchScript = path.join(__dirname, 'fetch-availability-fast.js');

        for (const pass of datePasses) {
            console.error(`\n═══ Date pass: ${pass.from} → ${pass.until} ═══`);

            for (let bi = 0; bi < roomBatches.length; bi++) {
                const batch = roomBatches[bi];
                console.error(`  Batch ${bi + 1}/${roomBatches.length} (${batch.length} rooms)`);

                try {
                    const output = execFileSync('node', [
                        fetchScript,
                        `from=${pass.from}`,
                        `until=${pass.until}`,
                        `serviceIds=${batch.join(',')}`,
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
                        timeout: 300000, // 5 min per batch
                        maxBuffer: 100 * 1024 * 1024,
                    });

                    const results = JSON.parse(output.toString());

                    for (const [extId, days] of Object.entries(results)) {
                        if (!Array.isArray(days) || days.length === 0) continue;
                        if (!allResults[extId]) allResults[extId] = [];
                        allResults[extId].push(...days);
                    }

                    console.error(`    ✓ Got data for ${Object.keys(results).length} rooms`);
                } catch (err) {
                    console.error(`    ✗ Batch failed: ${err.message}`);
                }
            }
        }

        console.error(`\n[notify-avail] Scraped ${Object.keys(allResults).length} rooms. POSTing to webhook...`);

        // ── 4) POST results to Laravel ──
        const payload = { results: allResults };
        const payloadStr = JSON.stringify(payload);
        const sizeMB = (payloadStr.length / (1024 * 1024)).toFixed(2);
        console.error(`[notify-avail] Payload size: ${sizeMB} MB`);

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
        console.error(`[notify-avail] Response ${res.status}: ${body}`);

        if (!res.ok) {
            process.exitCode = 1;
        }
    } catch (err) {
        console.error(`[notify-avail] Fatal error: ${err.message}`);
        process.exitCode = 1;
    }
})();
