<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\VendorApiException;
use App\Http\Controllers\Controller;
use App\Mail\OrderCancelledMail;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Refund;
use App\Services\Analytics\EventTracker;
use App\Services\Payment\StripeRefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class RefundController extends Controller
{
    public function __construct(
        private readonly StripeRefundService $refundService,
        private readonly EventTracker $tracker,
    ) {}

    public function index(): View
    {
        $refunds = Refund::with('order')->latest()->paginate(20);

        $stats = [
            'pending' => Refund::where('status', 'pending')->count(),
            'last_30_days' => (float) Refund::where('status', 'succeeded')
                ->where('created_at', '>=', now()->subDays(30))
                ->sum('amount'),
        ];

        return view('admin.refunds.index', compact('refunds', 'stats'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_number' => ['required', 'exists:orders,order_number'],
            'type' => ['required', 'in:full,partial'],
            'amount' => ['required_if:type,partial', 'nullable', 'numeric', 'min:0.01'],
        ]);

        $order = Order::where('order_number', $validated['order_number'])->firstOrFail();
        $alreadyRefunded = $order->totalRefunded();
        $remaining = round((float) $order->total - $alreadyRefunded, 2);

        abort_if($remaining <= 0, 422, 'This order is already fully refunded.');

        $amount = $validated['type'] === 'full' ? $remaining : min((float) $validated['amount'], $remaining);

        try {
            $refund = $this->refundService->refund($order, $amount, 'Admin-issued '.$validated['type'].' refund', $request->user()->id);
        } catch (VendorApiException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::record(
            'Refund Issued',
            "Order {$order->order_number}: \${$amount} {$validated['type']} refund ({$refund->status})"
        );

        if ($refund->status === 'succeeded') {
            Mail::to($order->email)->queue(new OrderCancelledMail($order, $amount, fullCancellation: false));
            $this->tracker->trackRefund($order, $amount);
        }

        return back()->with('status', "Refund of \${$amount} processed for {$order->order_number}.");
    }
}
