<x-emails.layout>
    <h2 style="margin:0 0 8px;color:#111827;">We've received your order</h2>
    <p style="color:#64748B;font-size:14px;">Hi {{ $order->shipping_name }}, thanks for shopping with Shobley.</p>
    <p style="font-size:16px;font-weight:700;color:#2563EB;margin:16px 0 4px;">Order #{{ $order->order_number }}</p>
    <p style="color:#64748B;font-size:14px;">We're waiting for your payment to be confirmed — you'll get another email as soon as that happens.</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px;border-top:1px solid #E2E8F0;padding-top:16px;">
        @foreach ($order->items as $item)
            <tr>
                <td style="padding:4px 0;font-size:14px;">{{ $item->product_title }} &times;{{ $item->quantity }}</td>
                <td style="padding:4px 0;font-size:14px;text-align:right;">${{ number_format($item->line_total, 2) }}</td>
            </tr>
        @endforeach
        <tr><td style="padding-top:12px;font-weight:700;font-size:15px;">Total</td><td style="padding-top:12px;font-weight:700;font-size:15px;text-align:right;">${{ number_format($order->total, 2) }}</td></tr>
    </table>
</x-emails.layout>
