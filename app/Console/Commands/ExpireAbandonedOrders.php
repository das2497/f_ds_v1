<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * A checkout that's abandoned before payment (tab closed, card declined and
 * never retried) leaves its Order stuck at pending_payment forever —
 * nothing else ever revisits it. FulfillmentEngine only touches status
 * `paid`, so these are harmless to the vendor pipeline, but they pile up as
 * dead weight in admin. No payment was ever captured for them, so there's
 * nothing to refund — just close them out.
 */
#[Signature('app:expire-abandoned-orders')]
#[Description('Cancel pending_payment orders that were never paid for, past a grace window.')]
class ExpireAbandonedOrders extends Command
{
    private const ABANDON_AFTER_HOURS = 24;

    public function handle(): int
    {
        $count = 0;

        Order::where('status', 'pending_payment')
            ->where('created_at', '<=', now()->subHours(self::ABANDON_AFTER_HOURS))
            ->orderBy('id')
            ->chunkById(50, function ($orders) use (&$count) {
                foreach ($orders as $order) {
                    if ($this->expire($order)) {
                        $count++;
                    }
                }
            });

        $this->info("Expired {$count} abandoned order(s).");

        return self::SUCCESS;
    }

    /**
     * Locked so a Stripe webhook that marks this exact order paid in the
     * instant between the query above and this lock always wins — we only
     * ever cancel an order still genuinely unpaid at the moment we touch it.
     */
    private function expire(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            $locked = Order::where('id', $order->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== 'pending_payment') {
                return false;
            }

            $locked->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            return true;
        });
    }
}
