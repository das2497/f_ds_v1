<x-emails.layout>
    <h2 style="margin:0 0 8px;color:#111827;">Your order is being fulfilled</h2>
    <p style="color:#64748B;font-size:14px;">Hi {{ $order->shipping_name }}, order <strong>#{{ $order->order_number }}</strong> has been sent to our fulfillment partner(s) and is being prepared for shipment.</p>
    <p style="color:#64748B;font-size:14px;">We'll let you know once tracking details are available.</p>
</x-emails.layout>
