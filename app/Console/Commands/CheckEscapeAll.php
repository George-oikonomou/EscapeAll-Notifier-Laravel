<?php

namespace App\Console\Commands; // or App\Http\Controllers;

use Illuminate\Console\Command;

class CheckEscapeAll extends Command
{
    protected $signature = 'escapeall:check';
    protected $description = 'Run the Playwright automation script';

    public function handle()
    {
        $command = 'node node/automation/fetch-escapeall.js 2>&1';
        exec($command, $output, $status);

        if ($status === 0) {
            $this->info("Script ran successfully:");
        } else {
            $this->error("Script failed with exit code $status");
        }

        foreach ($output as $line) {
            $this->line($line);
        }
    }
}
