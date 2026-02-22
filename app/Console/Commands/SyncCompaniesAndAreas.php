<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Municipality;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class SyncCompaniesAndAreas extends Command
{
    protected $signature = 'ea:sync-companies-areas
        {--language=el : Language for scraping (el or en)}
        {--wait-ms=6000 : Additional wait in ms before scraping}
        {--limit=0 : Max number of records to process (0 = all)}
        {--chunk=100 : Process chunk size for DB writes}
        {--save-to-storage : Save raw API response to storage/app/sync-data/companies.json (always on)}
        {--show-logs : Display Node output}';

    protected $description = 'Fetch companies (with geo/address) and municipalities from the Companies page and upsert them';

    public function handle(): int
    {
        $script = base_path('node/scripts/scrape-companies.js');
        if (!is_file($script)) {
            $this->error("Node script not found at: {$script}");
            return Command::FAILURE;
        }

        $language = (string) $this->option('language');
        $waitMs = (string) $this->option('wait-ms');
        $limit = (int) $this->option('limit');
        $chunkSize = (int) $this->option('chunk');
        if ($chunkSize <= 0) { $chunkSize = 100; }
        $showLogs = (bool) $this->option('show-logs');

        $syncDir = storage_path('app/sync-data');
        @mkdir($syncDir, 0775, true);
        $outFile = $syncDir . '/companies.json';

        $args = [
            'node',
            $script,
            'format=json',
            'out=' . $outFile,
            'language=' . $language,
            'waitMs=' . $waitMs,
        ];

        $this->line('[ea:sync-companies-areas] Running node script...');
        $process = new Process($args, base_path(), ['NODE_PATH' => base_path('node/automation/node_modules')]);
        $process->setTimeout(180);
        $process->run();

        if ($showLogs) {
            $this->line("[ea] Node STDOUT:\n" . $process->getOutput());
            $this->line("[ea] Node STDERR:\n" . $process->getErrorOutput());
        }

        if (!$process->isSuccessful()) {
            $this->error('[ea] Node process failed');
            $this->line($process->getErrorOutput());
            return Command::FAILURE;
        }

        if (!is_file($outFile)) {
            $this->error('[ea] Expected output JSON not found: ' . $outFile);
            return Command::FAILURE;
        }

        $data = json_decode(file_get_contents($outFile), true);
        if (!is_array($data)) {
            $this->error('[ea] Invalid JSON payload');
            return Command::FAILURE;
        }

        $companies = is_array($data['companies'] ?? null) ? $data['companies'] : [];
        $areas = is_array($data['areas'] ?? null) ? $data['areas'] : [];

        // Optional limits
        if ($limit > 0) {
            $companies = array_slice($companies, 0, $limit);
            $areas = array_slice($areas, 0, $limit);
        }

        // ── 1) Upsert municipalities FIRST (companies FK depends on these) ──
        $aCreated = 0; $aUpdated = 0; $aFailed = 0;
        foreach (array_chunk($areas, $chunkSize) as $idx => $chunk) {
            $this->line("[ea] Areas chunk " . ($idx + 1) . " (" . count($chunk) . ")");
            foreach ($chunk as $a) {
                try {
                    $model = Municipality::updateOrCreate(
                        ['external_id' => (string)($a['external_id'] ?? ($a['id'] ?? ''))],
                        ['name' => (string)($a['name'] ?? ($a['label'] ?? ''))]
                    );
                    if ($model->wasRecentlyCreated) $aCreated++; else $aUpdated++;
                } catch (\Throwable $e) {
                    $aFailed++;
                    $this->error('[ea] Area upsert failed for ' . ($a['external_id'] ?? 'unknown') . ': ' . $e->getMessage());
                }
            }
        }
        $this->info(sprintf('[ea] Areas → created=%d, updated=%d, failed=%d', $aCreated, $aUpdated, $aFailed));

        // Build a lookup of known municipality external_ids for fast existence checks
        $knownMunicipalities = Municipality::query()->pluck('external_id')->flip()->all();
        $autoCreatedMunicipalities = 0;

        // ── 2) Upsert companies (auto-create missing municipalities on the fly) ──
        $cCreated = 0; $cUpdated = 0; $cFailed = 0;
        foreach (array_chunk($companies, $chunkSize) as $idx => $chunk) {
            $this->line("[ea] Companies chunk " . ($idx + 1) . " (" . count($chunk) . ")");
            foreach ($chunk as $c) {
                try {
                    $munExtId = (string)($c['municipality_external_id'] ?? '');

                    // Auto-create municipality if it doesn't exist yet
                    if ($munExtId !== '' && !isset($knownMunicipalities[$munExtId])) {
                        Municipality::create([
                            'external_id' => $munExtId,
                            'name' => 'Unknown (' . $munExtId . ')',
                        ]);
                        $knownMunicipalities[$munExtId] = true;
                        $autoCreatedMunicipalities++;
                        $this->warn("[ea] Auto-created missing municipality: {$munExtId}");
                    }

                    $model = Company::updateOrCreate(
                        ['external_id' => (string)($c['external_id'] ?? '')],
                        [
                            'name' => (string)($c['name'] ?? ''),
                            'logo_url' => (string)($c['logo_url'] ?? ''),
                            'latitude' => $c['latitude'] ?? null,
                            'longitude' => $c['longitude'] ?? null,
                            'address' => (string)($c['address'] ?? ''),
                            'full_address' => (string)($c['full_address'] ?? ''),
                            'municipality_external_id' => $munExtId ?: null,
                        ]
                    );
                    if ($model->wasRecentlyCreated) $cCreated++; else $cUpdated++;
                } catch (\Throwable $e) {
                    $cFailed++;
                    $this->error('[ea] Company upsert failed for ' . ($c['external_id'] ?? 'unknown') . ': ' . $e->getMessage());
                }
            }
        }
        $this->info(sprintf('[ea] Companies → created=%d, updated=%d, failed=%d', $cCreated, $cUpdated, $cFailed));
        if ($autoCreatedMunicipalities > 0) {
            $this->warn(sprintf('[ea] Auto-created %d missing municipalities', $autoCreatedMunicipalities));
        }

        return Command::SUCCESS;
    }
}
