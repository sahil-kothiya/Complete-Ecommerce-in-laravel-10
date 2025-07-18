<?php

namespace App\Console\Commands;

use App\Models\Discount;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncDiscountStatus extends Command
{
    protected $signature = 'discounts:sync-status';
    protected $description = 'Activate and deactivate discounts based on start and end time';

    public function handle()
    {
        $now = Carbon::now();

        // Auto-activate eligible discounts
        Discount::where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->where('is_active', false)
            ->update(['is_active' => true]);

        // Auto-deactivate expired discounts
        Discount::where('ends_at', '<', $now)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $this->info('Discount status sync complete.');
    }
}
