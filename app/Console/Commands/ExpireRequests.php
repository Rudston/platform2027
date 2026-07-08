<?php

namespace App\Console\Commands;

use App\Models\Communication\Request;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class ExpireRequests extends Command
{
    protected $signature = 'requests:expire';

    protected $description = 'Mark pending requests whose approval token has passed its expiry as expired.';

    public function handle(): int
    {
        $count = 0;

        Request::query()
            ->where('status', 'pending')
            ->where('token_expires_at', '<', now())
            // chunkById is safe here: it paginates by id, so flipping the
            // filtered `status` column mid-run never skips or re-processes rows.
            ->chunkById(100, function (Collection $requests) use (&$count): void {
                foreach ($requests as $request) {
                    $request->update(['status' => 'expired']);
                    $count++;
                }
            });

        $this->info("{$count} requests marked as expired");

        return self::SUCCESS;
    }
}
