<?php

namespace App\Console\Commands;

use App\Services\Signature\SadLifecycleService;
use Illuminate\Console\Command;

class ExpireSadSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'signature:expire-sad-sessions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire stale SAD authorization sessions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = app(SadLifecycleService::class)->expireOldSessions();
        $this->info("Expired {$count} stale SAD session(s).");

        return self::SUCCESS;
    }
}
