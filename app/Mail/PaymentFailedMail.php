<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Order;
use App\Support\StripsMissingValues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentFailedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, StripsMissingValues;

    public function __construct(
        public Order $order,
        public ?Booking $booking = null,
        public ?string $failureReason = null
    ) {}

    public function build(): self
    {
        $order = $this->order->load(['user', 'orderable']);

        $customerEmail = $order->user?->email ?? ($order->meta['customer_email'] ?? null);
        $customerName = $order->user?->name ?? ($order->meta['customer_name'] ?? 'Customer');

        $bookingData = null;
        if ($this->booking) {
            $this->booking->load(['services.bookable', 'services.master']);
            $bookingData = [
                'id' => $this->booking->id,
                'reference' => $this->booking->reference,
                'date' => $this->booking->date instanceof \Carbon\Carbon
                    ? $this->booking->date->format('Y-m-d')
                    : $this->booking->date,
                'startTime' => $this->booking->start_time,
                'endTime' => $this->booking->end_time,
                'serviceName' => $this->booking->services->first()?->bookable?->name ?? 'Service',
            ];
        }

        $payload = [
            'orderId' => $order->id,
            'orderReference' => $order->reference,
            'amount' => (float) $order->amount,
            'currency' => $order->currency ?? 'AED',
            'customerName' => $customerName,
            'failureReason' => $this->failureReason ?? 'Payment could not be processed',
            'booking' => $bookingData,
            'createdAt' => $order->created_at?->format('d M Y, H:i'),
        ];

        $reference = $order->reference ?? ('#' . $order->id);
        $subject = 'Payment Failed for Order ' . $reference;

        return $this->subject($subject)
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.payment-failed')
            ->with(['data' => $payload]);
    }
}
