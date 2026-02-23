<?php

namespace App\Mail;

use App\Models\Booking;
use App\Http\Resources\BookingResource;
use App\Support\StripsMissingValues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewBookingAdminNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, StripsMissingValues;

    public function __construct(
        public Booking $booking
    ) {}

    public function build(): self
    {
        $booking = $this->booking->load([
            'services.bookable',
            'services.master',
            'master',
            'user',
        ]);

        $payload = (new BookingResource($booking))->resolve();
        $payload = $this->stripMissingValues($payload);

        $customerName = $booking->customer_name ?? $booking->user?->name ?? 'Guest';
        $customerEmail = $booking->customer_email ?? $booking->user?->email ?? 'N/A';
        $customerPhone = $booking->customer_phone ?? $booking->user?->mobile ?? 'N/A';

        $payload['customer'] = [
            'name' => $customerName,
            'email' => $customerEmail,
            'phone' => $customerPhone,
        ];

        $reference = $payload['reference'] ?? ('#' . ($payload['id'] ?? $booking->id));
        $subject = 'New Booking Received ' . $reference . ' - ' . ($payload['date'] ?? '') . ' ' . ($payload['startTime'] ?? '');

        return $this->subject($subject)
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.new-booking-admin')
            ->with(['b' => $payload]);
    }
}
