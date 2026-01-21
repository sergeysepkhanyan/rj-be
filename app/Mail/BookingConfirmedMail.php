<?php

namespace App\Mail;

use App\Models\Booking;
use App\Http\Resources\BookingResource;
use App\Support\StripsMissingValues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\Resources\MissingValue;

class BookingConfirmedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, StripsMissingValues;

    public function __construct(public Booking $booking) {}

    public function build(): BookingConfirmedMail
    {
        $booking = $this->booking->load([
            'services.bookable',
            'services.master',
            'master',
            'cancelledBy',
        ]);

        $payload = (new BookingResource($booking))->resolve();
        $payload = $this->stripMissingValues($payload);

        $reference = $payload['reference'] ?? ('#' . ($payload['id'] ?? $booking->id));
        return $this->subject('Booking confirmed ' . $reference)
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.booking-confirmed')
            ->text('emails.booking-confirmed-text')
            ->with(['b' => $payload]);
    }
}
