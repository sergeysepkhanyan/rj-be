<?php

namespace App\Mail;

use App\Models\GiftCardPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GiftCardBalanceRestoredMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public GiftCardPurchase $purchase,
        public float $amountRestored,
    ) {}

    public function build(): self
    {
        $currency = $this->purchase->currency ?? 'AED';

        return $this->subject('Gift card balance restored')
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.gift-card-balance-restored')
            ->with([
                'code' => $this->purchase->code,
                'amountRestored' => number_format($this->amountRestored, 2),
                'remainingBalance' => number_format((float) $this->purchase->balance, 2),
                'currency' => $currency,
            ]);
    }
}
