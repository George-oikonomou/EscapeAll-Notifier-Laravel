<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class CheckEscapeAll extends Command
{
    protected $signature = 'escapeall:node
        {--from= : Start date YYYY-MM-DD}
        {--until= : End date YYYY-MM-DD}
        {--service-id= : ServiceId GUID}
        {--booked-by=1 : BookedBy, usually 1}
        {--language=el : Language code (el or en)}
        {--no-gifts : Send noGifts=true}
        {--show-logs : Display full Node output for debugging}';

    protected $description = 'Fetch EscapeAll availability using Node (Playwright stealth) and display only available slots';

    public function handle(): int
    {
        $from = (string) $this->option('from');
        $until = (string) $this->option('until');
        $serviceId = (string) $this->option('service-id');

        if ($from === '' || $until === '' || $serviceId === '') {
            $this->error('Required: --from YYYY-MM-DD --until YYYY-MM-DD --service-id GUID');
            return Command::INVALID;
        }

        $bookedBy = (string) $this->option('booked-by');
        $language = (string) $this->option('language');
        $noGifts = $this->option('no-gifts') ? 'true' : 'false';
        $showLogs = $this->option('show-logs');

        $script = base_path('node/automation/fetch-escapeall.js');
        if (!is_file($script)) {
            $this->error("Node script not found at: {$script}");
            return Command::FAILURE;
        }

        $fromDate = new \DateTime($from);
        $untilDate = new \DateTime($until);
        $combined = [];

        // Loop in 2-month batches
        while ($fromDate <= $untilDate) {
            $batchStart = clone $fromDate;
            $batchEnd = (clone $fromDate)->modify('+2 months -1 day');
            if ($batchEnd > $untilDate) $batchEnd = $untilDate;

            $args = [
                'node',
                $script,
                'from=' . $batchStart->format('Y-m-d'),
                'until=' . $batchEnd->format('Y-m-d'),
                "serviceId={$serviceId}",
                "bookedBy={$bookedBy}",
                "language={$language}",
                "noGifts={$noGifts}",
            ];

            $process = new \Symfony\Component\Process\Process($args, base_path());
            $process->setTimeout(120);
            $process->run();

            if (!$process->isSuccessful()) {
                $this->error("Batch {$batchStart->format('Y-m-d')}–{$batchEnd->format('Y-m-d')} failed:");
                $this->line($process->getErrorOutput());
                $fromDate = (clone $batchEnd)->modify('+1 day');
                continue;
            }

            $output = trim($process->getOutput());
            if ($showLogs) {
                $this->info("Node output {$batchStart->format('Y-m-d')}–{$batchEnd->format('Y-m-d')}:");
                $this->line($output);
                $this->newLine();
            }

            $start = strpos($output, '[');
            $end = strrpos($output, ']');
            if ($start === false || $end === false) {
                $this->warn("No JSON found for {$batchStart->format('Y-m-d')}–{$batchEnd->format('Y-m-d')}");
                $fromDate = (clone $batchEnd)->modify('+1 day');
                continue;
            }

            $jsonText = substr($output, $start, $end - $start + 1);
            $json = json_decode($jsonText, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->warn("Invalid JSON for {$batchStart->format('Y-m-d')}–{$batchEnd->format('Y-m-d')}");
                $fromDate = (clone $batchEnd)->modify('+1 day');
                continue;
            }

            $combined = array_merge($combined, $json);
            $fromDate = (clone $batchEnd)->modify('+1 day');
        }

        // Filter and print results
        $available = array_filter($combined, fn($d) => $d['HasAvailable'] ?? false);
        if (empty($available)) {
            $this->info('No available slots.');
            return Command::SUCCESS;
        }

        foreach ($available as $d) {
            $date = sprintf('%04d-%02d-%02d', $d['Year'] ?? 0, $d['Month'] ?? 0, $d['Day'] ?? 0);
            $timeSlots = $d['AvailabilityTimeSlotList'] ?? [];
            $timeSlots = array_filter($timeSlots, fn($t) => ($t['Quantity'] ?? 0) != 0);
            if (empty($timeSlots)) continue;

            foreach ($timeSlots as $slot) {
                preg_match('/\b\d{1,2}:\d{2}\b/', $slot['Name'] ?? '', $m);
                $time = $m[0] ?? 'unknown';
                $this->line("service-id = {$d['ServiceId']} | date = {$date} | time = {$time} | <fg=green>available</>");
            }
        }

        return Command::SUCCESS;
    }
}
