<?php

namespace App\Mail;

use App\Models\Booking;
use App\Http\Resources\BookingResource;
use App\Support\StripsMissingValues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingRescheduledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, StripsMissingValues;

    public function __construct(
        public Booking $booking,
        public ?string $previousDate = null,
        public ?string $previousStartTime = null,
        public ?string $previousEndTime = null
    ) {}

    public function build(): self
    {
        $booking = $this->booking->load([
            'services.bookable',
            'services.master',
            'master',
        ]);

        $payload = (new BookingResource($booking))->resolve();
        $payload = $this->stripMissingValues($payload);

        $payload['previousDate'] = $this->previousDate;
        $payload['previousStartTime'] = $this->previousStartTime;
        $payload['previousEndTime'] = $this->previousEndTime;

        $reference = $payload['reference'] ?? ('#' . ($payload['id'] ?? $booking->id));

        return $this->subject('Booking Rescheduled ' . $reference)
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.booking-rescheduled')
            ->text('emails.booking-rescheduled-text')
            ->with(['b' => $payload]);
    }
}
