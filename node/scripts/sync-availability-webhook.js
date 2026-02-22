/**
 * sync-availability-webhook.js
 *
 * Orchestrator for GitHub Actions: fetches room IDs from the Laravel webhook API,
 * then calls fetch-availability-fast.js in batches, accumulates all results,
 * and POSTs them to the sync-availability webhook endpoint.
 *
 * Usage:
 *   node sync-availability-webhook.js \
 *     webhookUrl=https://yourdomain.com/api/webhook/sync-availability \
 *     roomIdsUrl=https://yourdomain.com/api/webhook/room-ids \
 *     webhookSecret=your-secret \
 *     months=10 \
 *     batchSize=50 \
 *     delayMs=1500
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
const ROOM_IDS_URL   = args.roomIdsUrl   || '';
const WEBHOOK_SECRET = args.webhookSecret || '';
const MONTHS         = parseInt(args.months    || '10', 10);
const BATCH_SIZE     = parseInt(args.batchSize || '50', 10);
const DELAY_MS       = parseInt(args.delayMs   || '1500', 10);
const DATE_BATCH_MONTHS = 2;

if (!WEBHOOK_URL || !ROOM_IDS_URL || !WEBHOOK_SECRET) {
    console.error('[sync-avail] Missing required args: webhookUrl, roomIdsUrl, webhookSecret');
    process.exit(1);
}

function sleep(ms) {
    return new Promise(r => setTimeout(r, ms));
}

(async () => {
    try {
        // ── 1) Fetch room IDs from Laravel ──
        console.error('[sync-avail] Fetching room IDs...');
        const idsRes = await fetch(ROOM_IDS_URL, {
            headers: {
                'Accept': 'application/json',
                'X-Webhook-Secret': WEBHOOK_SECRET,
            },
        });

        if (!idsRes.ok) {
            console.error(`[sync-avail] Failed to fetch room IDs: ${idsRes.status}`);
            process.exit(1);
        }

        const idsData = await idsRes.json();
        const allExtIds = idsData.room_ids || [];
        console.error(`[sync-avail] Got ${allExtIds.length} room IDs`);

        if (allExtIds.length === 0) {
            console.error('[sync-avail] No rooms to process');
            process.exit(0);
        }

        // ── 2) Build date passes (2-month batches) ──
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
            const passUntil = passEnd < end ? passEnd.toISOString().split('T')[0] : end.toISOString().split('T')[0];
            datePasses.push({ from: passFrom, until: passUntil });
            cursor.setMonth(cursor.getMonth() + DATE_BATCH_MONTHS);
        }

        console.error(`[sync-avail] ${datePasses.length} date passes, ${BATCH_SIZE} rooms/batch, delay=${DELAY_MS}ms`);

        // ── 3) Accumulate results across all passes and batches ──
        const allResults = {}; // extId → days[]

        const roomBatches = [];
        for (let i = 0; i < allExtIds.length; i += BATCH_SIZE) {
            roomBatches.push(allExtIds.slice(i, i + BATCH_SIZE));
        }

        const fetchScript = path.join(__dirname, 'fetch-availability-fast.js');
        let totalCalls = 0;
        const totalExpected = datePasses.length * roomBatches.length;

        for (const pass of datePasses) {
            console.error(`\n═══ Date pass: ${pass.from} → ${pass.until} ═══`);

            for (let bi = 0; bi < roomBatches.length; bi++) {
                totalCalls++;
                const batch = roomBatches[bi];
                console.error(`  Batch ${bi + 1}/${roomBatches.length} (${batch.length} rooms) [${totalCalls}/${totalExpected}]`);

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
                        timeout: 600000, // 10 min per batch
                        maxBuffer: 100 * 1024 * 1024, // 100 MB
                    });

                    const results = JSON.parse(output.toString());

                    // Merge results — accumulate days across passes
                    for (const [extId, days] of Object.entries(results)) {
                        if (!Array.isArray(days) || days.length === 0) continue;
                        if (!allResults[extId]) {
                            allResults[extId] = [];
                        }
                        allResults[extId].push(...days);
                    }

                    console.error(`    ✓ Got data for ${Object.keys(results).length} rooms`);
                } catch (err) {
                    console.error(`    ✗ Batch failed: ${err.message}`);
                }
            }
        }

        console.error(`\n[sync-avail] Finished scraping. ${Object.keys(allResults).length} rooms have data.`);

        // ── 4) POST accumulated results to webhook in chunks ──
        const POST_CHUNK_SIZE = 50; // rooms per POST request
        const fromDate = new Date().toISOString().split('T')[0];
        const allExtIdKeys = Object.keys(allResults);

        const postChunks = [];
        for (let i = 0; i < allExtIdKeys.length; i += POST_CHUNK_SIZE) {
            const keys = allExtIdKeys.slice(i, i + POST_CHUNK_SIZE);
            const chunkResults = {};
            for (const k of keys) chunkResults[k] = allResults[k];
            postChunks.push(chunkResults);
        }

        console.error(`[sync-avail] Sending ${postChunks.length} chunks (${POST_CHUNK_SIZE} rooms each) to ${WEBHOOK_URL}...`);

        let anyFailed = false;

        for (let ci = 0; ci < postChunks.length; ci++) {
            const payload = {
                results: postChunks[ci],
                from: fromDate,
            };

            const payloadStr = JSON.stringify(payload);
            const sizeMB = (payloadStr.length / (1024 * 1024)).toFixed(1);
            console.error(`  Chunk ${ci + 1}/${postChunks.length} (${Object.keys(postChunks[ci]).length} rooms, ${sizeMB} MB)...`);

            try {
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

                if (!res.ok) {
                    console.error(`    ✗ Chunk ${ci + 1} failed: ${res.status} ${body}`);
                    anyFailed = true;
                } else {
                    console.error(`    ✓ Chunk ${ci + 1} OK: ${body.substring(0, 200)}`);
                }
            } catch (err) {
                console.error(`    ✗ Chunk ${ci + 1} fetch error: ${err.message}`);
                anyFailed = true;
            }
        }

        if (anyFailed) {
            console.error('[sync-avail] Some chunks failed!');
            process.exitCode = 1;
        } else {
            console.error(`[sync-avail] All ${postChunks.length} chunks sent successfully.`);
        }
    } catch (err) {
        console.error(`[sync-avail] Fatal error: ${err.message}`);
        process.exitCode = 1;
    }
})();
