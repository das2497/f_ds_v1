<x-emails.layout>
    <h2 style="margin:0 0 8px;color:#111827;">Payment confirmed</h2>
    <p style="color:#64748B;font-size:14px;">Hi {{ $order->shipping_name }}, your payment for order <strong>#{{ $order->order_number }}</strong> has been verified.</p>
    @if ($order->release_at)
        <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:10px;padding:14px 18px;margin:16px 0;color:#92400E;font-size:14px;">
            You can still cancel free of charge until <strong>{{ $order->release_at->format('g:i A, M j') }}</strong>. After that, your order goes to our fulfillment partners.
        </div>
    @endif
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:12px;border-top:1px solid #E2E8F0;padding-top:16px;">
        <tr><td style="font-size:14px;color:#64748B;">Subtotal</td><td style="font-size:14px;text-align:right;">${{ number_format($order->subtotal, 2) }}</td></tr>
        <tr><td style="font-size:14px;color:#64748B;">Shipping</td><td style="font-size:14px;text-align:right;">${{ number_format($order->shipping_total, 2) }}</td></tr>
        <tr><td style="font-size:14px;color:#64748B;">Tax</td><td style="font-size:14px;text-align:right;">${{ number_format($order->tax_total, 2) }}</td></tr>
        <tr><td style="padding-top:8px;font-weight:700;font-size:15px;">Total Paid</td><td style="padding-top:8px;font-weight:700;font-size:15px;text-align:right;">${{ number_format($order->total, 2) }}</td></tr>
    </table>
</x-emails.layout>
