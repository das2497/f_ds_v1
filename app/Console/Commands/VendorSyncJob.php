<?php

namespace App\Console\Commands;

use App\Services\Fulfillment\FulfillmentEngine;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:vendor-sync-job')]
#[Description('Submit paid orders past their 2-hour hold to the right vendor(s); retries never duplicate a submission.')]
class VendorSyncJob extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(FulfillmentEngine $engine): int
    {
        $count = $engine->submitReadyOrders();

        $this->info("Processed {$count} order(s) past their hold window.");

        return self::SUCCESS;
    }
}
