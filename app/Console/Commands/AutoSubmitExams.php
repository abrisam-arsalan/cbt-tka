<?php

namespace App\Console\Commands;

use App\Services\ExamTimerService;
use App\Services\PresenceService;
use Illuminate\Console\Command;

class AutoSubmitExams extends Command
{
    protected $signature = 'cbt:auto-submit';
    protected $description = 'Proses auto-submit attempt yang melewati batas waktu dan membersihkan presence basi.';

    public function handle(ExamTimerService $timer, PresenceService $presence): int
    {
        $expired = $timer->markExpiredPass();
        $this->info("Marked expired: {$expired}");

        $submitted = $timer->submitOverduePass();
        $this->info("Auto-submitted: {$submitted}");

        $pruned = $presence->prune();
        $this->info("Pruned presence: {$pruned}");

        return Command::SUCCESS;
    }
}