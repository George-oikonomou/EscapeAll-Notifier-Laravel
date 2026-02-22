<?php

namespace App\Console\Commands;

use App\Models\Room;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

class SyncRoomsFast extends Command
{
    protected $signature = 'rooms:sync-fast
        {--wait-ms=5000 : Additional wait in ms for listing scrape}
        {--limit=0 : Max rooms to process (0 = all)}
        {--chunk=100 : DB write chunk size}
        {--skip-details : Skip detail page scraping}
        {--delay=1500 : Delay in ms between each detail request (default 1.5s)}
        {--wave-size=50 : Rooms per wave before cooldown}
        {--cooldown=30 : Seconds to wait between waves}
        {--cache-listing : Skip listing scrape, use existing sync-data/rooms-listing.json}
        {--from-cache : Use pre-existing JSON files from storage/app/sync-data/ instead of scraping}
        {--save-to-storage : Save raw scrape responses to storage/app/sync-data/ (always on by default)}
        {--show-logs : Display verbose output}';

    protected $description = 'Fast room sync — uses PHP HTTP requests for detail pages instead of Puppeteer';

    // ── User-Agent pool — rotated per wave ──
    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:126.0) Gecko/20100101 Firefox/126.0',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36 Edg/123.0.0.0',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36 OPR/108.0.0.0',
        'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:125.0) Gecko/20100101 Firefox/125.0',
    ];

    private const ACCEPT_LANGUAGES = [
        'el,en;q=0.9',
        'el-GR,el;q=0.9,en-US;q=0.8,en;q=0.7',
        'en-US,en;q=0.9,el;q=0.8',
        'el;q=0.9,en;q=0.8,de;q=0.7',
        'en-GB,en;q=0.9,el;q=0.8',
        'el,en-US;q=0.8,en;q=0.7,fr;q=0.6',
        'el-GR,el;q=0.8,en;q=0.6',
        'en,el;q=0.9',
    ];

    public function handle(): int
    {
        $showLogs    = (bool) $this->option('show-logs');
        $chunkSize   = max(1, (int) $this->option('chunk'));
        $limit       = (int) $this->option('limit');
        $waitMs      = (string) $this->option('wait-ms');
        $skipDetails = (bool) $this->option('skip-details');
        $delayMs     = max(500, (int) $this->option('delay'));
        $waveSize    = max(10, (int) $this->option('wave-size'));
        $cooldownSec = max(5, (int) $this->option('cooldown'));
        $cacheListing = (bool) $this->option('cache-listing');
        $fromCache   = (bool) $this->option('from-cache');
        $startTime   = microtime(true);

        $this->info('[rooms:sync-fast] Starting fast sync');
        $this->line("  Config: delay={$delayMs}ms, wave={$waveSize}, cooldown={$cooldownSec}s");

        // ─────────────────────────────────────────────
        // STEP 1: Run listing page scraper with live progress
        // ─────────────────────────────────────────────
        $syncDir = storage_path('app/sync-data');
        @mkdir($syncDir, 0775, true);
        $listingFile = $syncDir . '/rooms-listing.json';

        if (($fromCache || $cacheListing) && is_file($listingFile)) {
            $this->line('[Step 1] Using cached listing file');
        } else {
            $script = base_path('node/scripts/scrape-rooms.js');
            if (!is_file($script)) {
                $this->error("Listing scraper not found: {$script}");
                return Command::FAILURE;
            }

            $this->line('[Step 1] Scraping listing page via Node...');
            $t = microtime(true);
            $proc = new Process([
                'node', $script,
                'format=json',
                'out=' . $listingFile,
                'waitMs=' . $waitMs,
            ], base_path(), ['NODE_PATH' => base_path('node/automation/node_modules')]);
            $proc->setTimeout(300);

            // Stream stderr in real-time for progress
            $proc->run(function ($type, $buffer) {
                if ($type === Process::ERR) {
                    foreach (explode("\n", trim($buffer)) as $line) {
                        if (empty(trim($line))) continue;
                        $this->line("  📡 " . trim($line));
                    }
                }
            });

            if (!$proc->isSuccessful()) {
                $this->error('[Step 1] Listing scraper failed');
                $this->line($proc->getErrorOutput());
                return Command::FAILURE;
            }
            $this->info(sprintf('[Step 1] ✅ Listing done in %.1fs', microtime(true) - $t));
        }

        $json = @file_get_contents($listingFile);
        $data = json_decode($json, true);
        if (!is_array($data)) {
            $this->error('[Step 1] Invalid listing JSON');
            return Command::FAILURE;
        }

        $rooms = $data['rooms'] ?? (isset($data[0]) ? $data : []);
        $companiesJs = $data['companies'] ?? [];
        $count = count($rooms);
        $this->info("[Step 1] Found {$count} rooms, " . count($companiesJs) . " companies");

        // ─────────────────────────────────────────────
        // STEP 2: Fetch detail pages SEQUENTIALLY
        //         One at a time with human-like delay
        //         Waves of N, then long cooldown
        // ─────────────────────────────────────────────
        $detailMap = [];

        if (!$skipDetails && $count > 0) {
            $detailCacheFile = $syncDir . '/rooms-details.json';

            if ($fromCache && is_file($detailCacheFile)) {
                $this->line('[Step 2] Using cached details file');
                $cached = json_decode(file_get_contents($detailCacheFile), true);
                if (is_array($cached)) {
                    $detailMap = $cached;
                    $this->info('[Step 2] Loaded ' . count($detailMap) . ' cached details');
                }
            } else {
                $slugs = collect($rooms)->pluck('slug')->filter()->unique()->values()->all();

                if ($limit > 0) {
                    $slugs = array_slice($slugs, 0, $limit);
                }

                $totalSlugs = count($slugs);
                $waveCount = (int) ceil($totalSlugs / $waveSize);
                $waves = array_chunk($slugs, $waveSize);

                $estPerRoom = ($delayMs / 1000) + 0.5; // delay + request time
                $estCooldown = ($waveCount - 1) * $cooldownSec;
                $estTotal = ($totalSlugs * $estPerRoom) + $estCooldown;

                $this->newLine();
                $this->line("[Step 2] Fetching {$totalSlugs} detail pages SEQUENTIALLY");
                $this->line("  {$waveCount} waves of {$waveSize} | delay={$delayMs}ms | cooldown={$cooldownSec}s");
                $this->line(sprintf('  Estimated time: ~%.0f min', $estTotal / 60));
                $this->newLine();

                $t = microtime(true);
                $processed = 0;
                $success = 0;
                $failed = 0;
                $blank = 0;
                $retried = 0;
                $consecutive429 = 0;

                foreach ($waves as $wi => $waveSlugs) {
                    $waveNum = $wi + 1;

                    // Pick identity for this wave
                    $ua   = self::USER_AGENTS[$wi % count(self::USER_AGENTS)];
                    $lang = self::ACCEPT_LANGUAGES[$wi % count(self::ACCEPT_LANGUAGES)];

                    $uaShort = 'Unknown';
                    if (preg_match('/Chrome|Firefox|Safari|Edg|OPR/', $ua, $m)) {
                        $uaShort = $m[0];
                    }
                    $this->info("── Wave {$waveNum}/{$waveCount} ── " . count($waveSlugs) . " rooms | {$uaShort}");

                    $waveStart = microtime(true);
                    $consecutive429 = 0;

                    foreach ($waveSlugs as $si => $slug) {
                        $processed++;

                        // Random delay before each request (human-like pacing)
                        if ($si > 0) {
                            $jitter = rand(-300, 500); // add randomness
                            $actualDelay = max(300, $delayMs + $jitter);
                            usleep($actualDelay * 1000);
                        }

                        try {
                            $response = Http::timeout(15)
                                ->connectTimeout(10)
                                ->withHeaders([
                                    'User-Agent'       => $ua,
                                    'Accept'           => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                                    'Accept-Language'   => $lang,
                                    'Accept-Encoding'   => 'gzip, deflate, br',
                                    'Connection'        => 'keep-alive',
                                    'Cache-Control'     => 'no-cache',
                                ])
                                ->get("https://www.escapeall.gr/el/EscapeRoom/Details/{$slug}");
                        } catch (\Throwable $e) {
                            $failed++;
                            if ($showLogs) {
                                $this->warn("  ✗ [{$processed}/{$totalSlugs}] {$slug} → {$e->getMessage()}");
                            }
                            continue;
                        }

                        // ── Handle 429: backoff and retry ──
                        if ($response->status() === 429) {
                            $consecutive429++;

                            // If we keep getting 429s, do a long pause
                            if ($consecutive429 >= 3) {
                                $longPause = 60 + rand(0, 15);
                                $this->warn("  ⛔ 3+ consecutive 429s — long pause {$longPause}s...");
                                sleep($longPause);
                                $consecutive429 = 0;
                            }

                            $detail = $this->retryWithBackoff($slug, $ua, $lang, $showLogs, $processed, $totalSlugs);
                            if ($detail) {
                                $retried++;
                                $consecutive429 = 0; // reset on success
                                if ($detail['description'] || $detail['difficulty']) {
                                    $success++;
                                    $detailMap[$slug] = $detail;
                                } else {
                                    $blank++;
                                }
                            } else {
                                $failed++;
                            }
                            continue;
                        }

                        $consecutive429 = 0; // reset counter on non-429

                        if (!$response->successful()) {
                            $failed++;
                            if ($showLogs) {
                                $this->warn("  ✗ [{$processed}/{$totalSlugs}] {$slug} → {$response->status()}");
                            }
                            continue;
                        }

                        $html = $response->body();
                        $detail = $this->parseDetailHtml($html, $slug);

                        if ($detail['description'] || $detail['difficulty']) {
                            $success++;
                            $detailMap[$slug] = $detail;
                            if ($showLogs) {
                                $desc = $detail['description'] ? mb_substr($detail['description'], 0, 50) . '...' : '(no desc)';
                                $this->line("  ✓ [{$processed}/{$totalSlugs}] {$slug} → diff={$detail['difficulty']} | {$desc}");
                            }
                        } else {
                            $blank++;
                            if ($showLogs) {
                                $this->warn("  ○ [{$processed}/{$totalSlugs}] {$slug} → blank");
                            }
                        }

                        // Show mini-progress every 10 rooms
                        if ($processed % 10 === 0) {
                            $elapsed = microtime(true) - $t;
                            $avgPerRoom = $elapsed / $processed;
                            $eta = ($totalSlugs - $processed) * $avgPerRoom;
                            $this->line(sprintf(
                                '  ── %d/%d (✓%d ○%d ✗%d) | %.1fs elapsed | ETA: %.0fs (%.1f min)',
                                $processed, $totalSlugs, $success, $blank, $failed,
                                $elapsed, $eta, $eta / 60
                            ));
                        }
                    }

                    $waveElapsed = microtime(true) - $waveStart;
                    $totalElapsed = microtime(true) - $t;
                    $avgPerRoom = $totalElapsed / max($processed, 1);
                    $eta = ($totalSlugs - $processed) * $avgPerRoom;

                    $this->info(sprintf(
                        '  Wave %d done in %.1fs | %d/%d (✓%d ○%d ✗%d 🔁%d) | ETA: %.0fs (%.1f min)',
                        $waveNum, $waveElapsed, $processed, $totalSlugs,
                        $success, $blank, $failed, $retried, $eta, $eta / 60
                    ));

                    // Save progress after each wave (resume-friendly)
                    file_put_contents($detailCacheFile, json_encode($detailMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                    // ── Cooldown between waves ──
                    if ($wi < count($waves) - 1) {
                        $cooldown = $cooldownSec + rand(0, 5);
                        $this->line("  ⏸ Cooling down {$cooldown}s...");
                        sleep($cooldown);
                    }

                    $this->newLine();
                }

                $elapsed = microtime(true) - $t;
                $this->info(sprintf(
                    '[Step 2] ✅ Done in %.1fs (%.1f min) — ✓%d enriched, ○%d blank, ✗%d failed, 🔁%d retried',
                    $elapsed, $elapsed / 60, $success, $blank, $failed, $retried
                ));
            }
        } elseif ($skipDetails) {
            $this->line('[Step 2] Skipped (--skip-details)');
        }

        // ─────────────────────────────────────────────
        // STEP 3: Build rows by merging listing + detail data
        // ─────────────────────────────────────────────
        $this->line('[Step 3] Building rows...');
        $rows = [];
        foreach ($rooms as $item) {
            if (!is_array($item)) continue;
            $extId = (string) ($item['external_id'] ?? '');
            $title = (string) ($item['title'] ?? '');
            if ($extId === '' || $title === '') continue;

            $slug = $item['slug'] ?? '';
            $detail = $detailMap[$slug] ?? [];

            $durationMinutes = $item['duration_minutes'] ?? null;
            if ($durationMinutes !== null && $durationMinutes > 0 && $durationMinutes <= 5) {
                $durationMinutes = $durationMinutes * 60;
            }

            $rows[] = [
                'external_id'         => $extId,
                'title'               => $title,
                'slug'                => $slug ?: null,
                'label'               => $title . ' - ' . ($item['company_name'] ?? ''),
                'provider'            => $item['company_name'] ?? '',
                'company_external_id' => $item['company_external_id'] ?? '',
                'short_description'   => $item['short_description'] ?? null,
                'description'         => $detail['description'] ?? null,
                'rating'              => $item['rating'] ?? null,
                'reviews_count'       => $item['reviews_count'] ?? null,
                'duration_minutes'    => $durationMinutes,
                'min_players'         => $item['min_players'] ?? null,
                'max_players'         => $item['max_players'] ?? null,
                'escape_rate'         => $item['escape_rate'] ?? null,
                'difficulty'          => $detail['difficulty'] ?? null,
                'languages'           => $detail['languages'] ?? null,
                'video_url'           => $detail['video_url'] ?? null,
                'image_url'           => $item['image_url'] ?? null,
                'categories'          => $item['categories'] ?? [],
            ];
        }

        $rowCount = count($rows);
        $this->info("[Step 3] {$rowCount} valid rows");

        if ($limit > 0 && $rowCount > $limit) {
            $rows = array_slice($rows, 0, $limit);
            $rowCount = count($rows);
            $this->info("[Step 3] Applied limit → {$rowCount} rows");
        }

        if (empty($rows)) {
            $this->warn('[Step 3] No rows to process');
            return Command::SUCCESS;
        }

        // ─────────────────────────────────────────────
        // STEP 3b: Auto-upsert companies
        // ─────────────────────────────────────────────
        if (!empty($companiesJs)) {
            $jsUpCreated = 0;
            $jsUpUpdated = 0;
            foreach ($companiesJs as $jc) {
                $cid  = $jc['CompanyId'] ?? '';
                $name = $jc['DisplayName'] ?? '';
                if ($cid === '' || $name === '') continue;

                try {
                    $existing = Company::where('name', $name)->first();
                    $companyData = array_filter([
                        'name'                     => $name,
                        'logo_url'                 => $jc['LogoUrl'] ?? '',
                        'latitude'                 => $jc['Latitude'] ?? null,
                        'longitude'                => $jc['Longitude'] ?? null,
                        'address'                  => $jc['Address'] ?? '',
                        'full_address'             => $jc['FullAddress'] ?? '',
                        'municipality_external_id' => $jc['Municipalityid'] ?? null,
                    ], fn($v) => $v !== null && $v !== '');

                    if ($existing) {
                        $existing->update($companyData);
                        $jsUpUpdated++;
                    } else {
                        $model = Company::updateOrCreate(['external_id' => $cid], $companyData);
                        if ($model->wasRecentlyCreated) $jsUpCreated++;
                        else $jsUpUpdated++;
                    }
                } catch (\Throwable $e) {
                    $this->warn("[Step 3b] Company upsert failed for {$cid}: " . $e->getMessage());
                }
            }
            $this->info("[Step 3b] Companies → created={$jsUpCreated}, updated={$jsUpUpdated}");
        }

        // ─────────────────────────────────────────────
        // STEP 4: Resolve company_id
        // ─────────────────────────────────────────────
        $companyExtIds = array_values(array_unique(array_filter(
            array_map(fn($r) => $r['company_external_id'] ?? '', $rows)
        )));
        $companies = Company::whereIn('external_id', $companyExtIds)->pluck('id', 'external_id')->all();

        $companyNames = array_values(array_unique(array_filter(
            array_map(fn($r) => $r['provider'] ?? '', $rows)
        )));
        $companiesByName = Company::whereIn('name', $companyNames)->pluck('id', 'name')->all();

        $matched = count($companies);
        $total = count($companyExtIds);
        $this->line("[Step 4] Companies matched: {$matched}/{$total} by UUID, " . count($companiesByName) . ' by name');

        // ─────────────────────────────────────────────
        // STEP 5: Upsert rooms
        // ─────────────────────────────────────────────
        $created = 0;
        $updated = 0;
        $upsertFailed = 0;
        $chunks = array_chunk($rows, $chunkSize);
        $this->line("[Step 5] Upserting {$rowCount} rooms...");

        foreach ($chunks as $ci => $chunk) {
            $t = microtime(true);
            foreach ($chunk as $r) {
                try {
                    $companyId = $companies[$r['company_external_id']]
                        ?? $companiesByName[$r['provider']]
                        ?? null;

                    $updateData = [
                        'label'      => $r['label'],
                        'title'      => $r['title'],
                        'provider'   => $r['provider'],
                        'slug'       => $r['slug'],
                        'company_id' => $companyId,
                    ];

                    foreach (['short_description', 'description', 'image_url', 'video_url'] as $f) {
                        if (!empty($r[$f])) $updateData[$f] = $r[$f];
                    }
                    foreach (['rating', 'reviews_count', 'duration_minutes', 'min_players', 'max_players', 'escape_rate', 'difficulty'] as $f) {
                        if ($r[$f] !== null) $updateData[$f] = $r[$f];
                    }
                    if (!empty($r['languages'])) $updateData['languages'] = $r['languages'];
                    if (!empty($r['categories'])) $updateData['categories'] = $r['categories'];

                    $room = Room::updateOrCreate(['external_id' => $r['external_id']], $updateData);
                    $room->wasRecentlyCreated ? $created++ : $updated++;
                } catch (\Throwable $e) {
                    $upsertFailed++;
                    $this->error("[Step 5] Upsert failed for {$r['external_id']}: {$e->getMessage()}");
                }
            }
            $elapsed = microtime(true) - $t;
            $this->line(sprintf(
                '  [Chunk %d/%d] %.2fs (created=%d updated=%d failed=%d)',
                $ci + 1, count($chunks), $elapsed, $created, $updated, $upsertFailed
            ));
        }

        $totalElapsed = microtime(true) - $startTime;
        $this->newLine();
        $this->info(sprintf(
            '[rooms:sync-fast] ✅ Done in %.1fs (%.1f min) — %d rooms (created=%d updated=%d failed=%d)',
            $totalElapsed, $totalElapsed / 60, $rowCount, $created, $updated, $upsertFailed
        ));

        return Command::SUCCESS;
    }

    /**
     * Retry a single slug with exponential backoff on 429.
     */
    private function retryWithBackoff(string $slug, string $ua, string $lang, bool $showLogs, int $processed, int $totalSlugs): ?array
    {
        $maxRetries = 3;
        $backoffs = [8, 20, 45]; // seconds — generous waits

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $wait = $backoffs[$attempt - 1] + rand(0, 5);

            if ($showLogs) {
                $this->warn("  ⏳ [{$processed}/{$totalSlugs}] {$slug} → 429, retry {$attempt}/{$maxRetries} in {$wait}s...");
            }

            sleep($wait);

            // Use a different UA for each retry
            $retryUa   = self::USER_AGENTS[($attempt + 4) % count(self::USER_AGENTS)];
            $retryLang = self::ACCEPT_LANGUAGES[($attempt + 4) % count(self::ACCEPT_LANGUAGES)];

            try {
                $response = Http::timeout(15)
                    ->connectTimeout(10)
                    ->withHeaders([
                        'User-Agent'      => $retryUa,
                        'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                        'Accept-Language'  => $retryLang,
                        'Cache-Control'    => 'no-cache',
                    ])
                    ->get("https://www.escapeall.gr/el/EscapeRoom/Details/{$slug}");

                if ($response->successful()) {
                    $detail = $this->parseDetailHtml($response->body(), $slug);
                    if ($showLogs) {
                        $this->line("  ✓ [{$processed}/{$totalSlugs}] {$slug} → recovered on retry {$attempt}");
                    }
                    return $detail;
                }

                if ($response->status() !== 429) {
                    if ($showLogs) {
                        $this->warn("  ✗ [{$processed}/{$totalSlugs}] {$slug} → retry got {$response->status()}");
                    }
                    return null;
                }
            } catch (\Throwable $e) {
                if ($showLogs) {
                    $this->warn("  ✗ [{$processed}/{$totalSlugs}] {$slug} → retry error: {$e->getMessage()}");
                }
            }
        }

        if ($showLogs) {
            $this->error("  ✗ [{$processed}/{$totalSlugs}] {$slug} → all retries exhausted");
        }
        return null;
    }

    /**
     * Parse a room detail HTML page to extract enrichment data via regex.
     */
    private function parseDetailHtml(string $html, string $slug): array
    {
        $detail = [
            'slug'            => $slug,
            'description'     => null,
            'difficulty'      => null,
            'languages'       => null,
            'video_url'       => null,
            'area_id'         => null,
            'municipality_id' => null,
        ];

        // Description from JSON-LD (search only in <head> to avoid PCRE limits on huge pages)
        $headHtml = '';
        if (preg_match('/<head[^>]*>(.*?)<\/head>/si', $html, $hm)) {
            $headHtml = $hm[1];
        }
        $searchIn = $headHtml ?: $html;

        if (preg_match('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $searchIn, $m)) {
            $ld = json_decode($m[1], true);
            if (isset($ld['description']) && trim($ld['description']) !== '') {
                $detail['description'] = trim($ld['description']);
            }
        }

                // Fallback: <meta name="description"> (content may contain newlines)
        if (empty($detail['description'])) {
            $metaPattern    = '/<meta\b[^>]*\bname=[\"\']description[\"\'][^>]*\bcontent=[\"\'](.+?)[\"\'][^>]*>/si';
            $metaPatternAlt = '/<meta\b[^>]*\bcontent=[\"\'](.+?)[\"\'][^>]*\bname=[\"\']description[\"\'][^>]*>/si';
            if (preg_match($metaPattern, $searchIn, $m) || preg_match($metaPatternAlt, $searchIn, $m)) {
                $desc = trim($m[1]);
                if ($desc !== '') {
                    $detail['description'] = $desc;
                }
            }
        }

        // Difficulty from progress bar
        if (preg_match('/Δυσκολία.*?aria-valuenow=["\'](\d+(?:\.\d+)?)["\']/', $html, $m)) {
            $detail['difficulty'] = floatval($m[1]) / 10.0;
        }

        // Languages
        if (preg_match('/Γλώσσες.*?<div[^>]*class=["\']col-sm-8["\'][^>]*>(.*?)<\/div>/si', $html, $m)) {
            $langText = trim(strip_tags($m[1]));
            if (!empty($langText)) {
                $detail['languages'] = array_map('trim', explode(',', $langText));
            }
        }

        // YouTube video URL
        if (preg_match('/iframe[^>]+src=["\']([^"\']*youtube[^"\']*)["\']/', $html, $m)) {
            $detail['video_url'] = $m[1];
        }

        // Area / Municipality IDs
        if (preg_match('/show-map[^>]*data-areaid=["\']([^"\']*)["\']/', $html, $m)) {
            $detail['area_id'] = $m[1];
        }
        if (preg_match('/show-map[^>]*data-municipalityid=["\']([^"\']*)["\']/', $html, $m)) {
            $detail['municipality_id'] = $m[1];
        }

        return $detail;
    }
}
