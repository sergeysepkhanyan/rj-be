<?php

namespace App\Mail;

use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Support\StripsMissingValues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingPostServiceFollowupMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, StripsMissingValues;

    public function __construct(public Booking $booking) {}

    public function build(): self
    {
        $booking = $this->booking->load([
            'services.bookable',
            'services.master',
            'master',
        ]);

        $payload = (new BookingResource($booking))->resolve();
        $payload = $this->stripMissingValues($payload);

        $reference = $payload['reference'] ?? ('#'.($payload['id'] ?? $booking->id));

        return $this->subject(__('mail.booking_post_service.subject', ['reference' => $reference]))
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.booking-post-service-followup')
            ->text('emails.booking-post-service-followup-text')
            ->with([
                'b' => $payload,
            ]);
    }
}
