<x-emails.layout>
    @if ($fullCancellation)
        <h2 style="margin:0 0 8px;color:#111827;">Order cancelled</h2>
        <p style="color:#64748B;font-size:14px;">Hi {{ $order->shipping_name }}, order <strong>#{{ $order->order_number }}</strong> has been cancelled as requested.</p>
    @else
        <h2 style="margin:0 0 8px;color:#111827;">Refund issued</h2>
        <p style="color:#64748B;font-size:14px;">Hi {{ $order->shipping_name }}, a refund has been issued for order <strong>#{{ $order->order_number }}</strong>.</p>
    @endif
    <p style="font-size:20px;font-weight:700;color:#16A34A;margin:16px 0;">${{ number_format($refundAmount, 2) }} refunded</p>
    <p style="color:#64748B;font-size:14px;">This will appear on your original payment method within 5–10 business days.</p>
</x-emails.layout>
