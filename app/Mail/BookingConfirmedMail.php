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
use Illuminate\Support\Facades\URL;

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

        // Multi-service (batch) appointment → aggregate ALL bookings in the batch
        // into a single email: one combined services list and the batch grand totals.
        if ($booking->batch_id) {
            $batchBookings = Booking::where('batch_id', $booking->batch_id)
                ->with(['services.bookable', 'services.master', 'master'])
                ->orderBy('id')
                ->get();

            if ($batchBookings->count() > 1) {
                $allServices = [];
                $baseTotal = 0.0;
                $vatTotal = 0.0;
                $linesTotal = 0.0;
                $grandTotal = 0.0;

                foreach ($batchBookings as $b) {
                    $p = $this->stripMissingValues((new BookingResource($b))->resolve());
                    foreach (($p['services'] ?? []) as $svc) {
                        $allServices[] = $svc;
                    }
                    $baseTotal  += (float) ($p['vat']['baseTotal'] ?? 0);
                    $vatTotal   += (float) ($p['vat']['vatTotal'] ?? 0);
                    $linesTotal += (float) ($p['vat']['finalTotalFromLines'] ?? 0);
                    $grandTotal += (float) ($p['totalPrice'] ?? 0);
                }

                $payload['services'] = $allServices;
                $payload['vat'] = array_merge(is_array($payload['vat'] ?? null) ? $payload['vat'] : [], [
                    'baseTotal'           => round($baseTotal, 2),
                    'vatTotal'            => round($vatTotal, 2),
                    'finalTotalFromLines' => round($linesTotal, 2),
                ]);
                $payload['totalPrice'] = round($grandTotal, 2);
            }
        }

        $reference = $payload['reference'] ?? ('#' . ($payload['id'] ?? $booking->id));
        $addToCalendarUrl = URL::temporarySignedRoute(
            'booking.calendar.ics',
            now()->addDays(30),
            ['booking' => $booking]
        );

        return $this->subject('Booking confirmed ' . $reference)
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.booking-confirmed')
            ->text('emails.booking-confirmed-text')
            ->with([
                'b' => $payload,
                'addToCalendarUrl' => $addToCalendarUrl,
            ]);
    }
}
