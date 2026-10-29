<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderCancelledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public float $refundAmount, public bool $fullCancellation = true) {}

    public function envelope(): Envelope
    {
        $subject = $this->fullCancellation
            ? "Order #{$this->order->order_number} has been cancelled"
            : "A refund was issued for order #{$this->order->order_number}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order-cancelled');
    }
}
