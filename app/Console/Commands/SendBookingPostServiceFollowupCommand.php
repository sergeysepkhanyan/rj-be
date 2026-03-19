<?php

namespace App\Console\Commands;

use App\Mail\BookingPostServiceFollowupMail;
use App\Models\Booking;
use App\Support\BookingLatestServiceEnd;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendBookingPostServiceFollowupCommand extends Command
{
    protected $signature = 'bookings:send-post-service-followup';

    protected $description = 'Send thank-you / review email 1h after the last booked service ends (paid bookings only)';

    public function handle(): int
    {
        $delayHours = max(0, (int) config('booking.post_service_followup_delay_hours', 1));
        $threshold = now()->utc()->subHours($delayHours);

        $query = Booking::query()
            ->onlyBookings()
            ->where('payment_status', 'paid')
            ->whereNotIn('status', ['cancelled'])
            ->whereNull('post_service_followup_sent_at')
            ->whereNotNull('customer_email')
            ->where('customer_email', '!=', '')
            ->with(['services']);

        $sent = 0;

        $query->chunkById(100, function ($bookings) use ($threshold, &$sent) {
            foreach ($bookings as $booking) {
                $latestEnd = BookingLatestServiceEnd::latestEndUtc($booking);
                if ($latestEnd === null) {
                    continue;
                }

                if ($latestEnd->gt($threshold)) {
                    continue;
                }

                try {
                    DB::transaction(function () use ($booking, &$sent) {
                        $locked = Booking::query()
                            ->whereKey($booking->id)
                            ->whereNull('post_service_followup_sent_at')
                            ->lockForUpdate()
                            ->first();

                        if (! $locked) {
                            return;
                        }

                        $email = trim((string) $locked->customer_email);
                        if ($email === '') {
                            return;
                        }

                        Mail::to($email)->queue(new BookingPostServiceFollowupMail($locked));

                        $locked->forceFill(['post_service_followup_sent_at' => now()])->save();
                        $sent++;
                    });
                } catch (\Throwable $e) {
                    $this->error("Booking {$booking->id}: {$e->getMessage()}");
                }
            }
        });

        if ($sent > 0) {
            $this->info("Queued post-service follow-up for {$sent} booking(s).");
        }

        return Command::SUCCESS;
    }
}
