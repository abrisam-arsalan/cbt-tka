<?php

namespace App\Console\Commands;

use App\Services\StudentSyncService;
use Illuminate\Console\Command;

class SyncStudentsFromPortal extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cbt:sync-students';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronkan data siswa dari database Portal Panglima ke CBT TKA';

    /**
     * Execute the console command.
     */
    public function handle(StudentSyncService $syncService): int
    {
        $this->info('Memulai sinkronisasi data siswa dari Portal Panglima...');

        $res = $syncService->sync();

        if (!empty($res['errors'])) {
            foreach ($res['errors'] as $err) {
                $this->error($err);
            }
        }

        $this->info("Sinkronisasi selesai! Total di Portal: {$res['total']}, Berhasil diproses: {$res['synced']} (Baru: {$res['created']}, Diperbarui: {$res['updated']}).");

        return 0;
    }
}
