<?php

namespace App\Mail;

use App\Models\Booking;
use App\Http\Resources\BookingResource;
use App\Support\StripsMissingValues;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\Resources\MissingValue;

class BookingCancelledMail extends Mailable
{
    use Queueable, SerializesModels, StripsMissingValues;

    public function __construct(public Booking $booking) {}

    public function build(): BookingCancelledMail
    {
        $booking = $this->booking->load([
            'services.bookable',
            'services.master',
            'master',
            'cancelledBy',
        ]);

        $payload = (new BookingResource($booking))->resolve();
        $payload = $this->stripMissingValues($payload);
        return $this->subject('Booking cancelled #' . ($payload['id'] ?? $booking->id))
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.booking-cancelled')
            ->text('emails.booking-cancelled-text')
            ->with(['b' => $payload]);
    }
}

