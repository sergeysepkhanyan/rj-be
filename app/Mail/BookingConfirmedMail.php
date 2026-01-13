<?php

namespace App\Mail;

use App\Models\Booking;
use App\Http\Resources\BookingResource;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking
    ) {}

    public function build(): BookingConfirmedMail
    {
        $booking = $this->booking->load([
            'services.bookable',
            'services.master',
            'master',
        ]);

        $payload = (new BookingResource($booking))->resolve();

        $subject = 'Booking confirmed #' . ($payload['id'] ?? $booking->id);

        return $this->from(
            config('mail.from.address'),
            config('mail.from.name')
        )
            ->subject('Booking confirmed #' . ($payload['id'] ?? $booking->id))
            ->view('emails.booking-confirmed')
            ->text('emails.booking-confirmed-text')
            ->with([
                'b' => $payload,
            ]);
    }
}
