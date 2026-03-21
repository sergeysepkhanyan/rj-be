<?php

namespace App\Mail;

use App\Models\GiftCardPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GiftCardBalanceDeductedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public GiftCardPurchase $purchase,
        public float $amountUsed,
    ) {}

    public function build(): self
    {
        $currency = $this->purchase->currency ?? 'AED';

        return $this->subject('Gift card balance update')
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.gift-card-balance-deducted')
            ->with([
                'code' => $this->purchase->code,
                'amountUsed' => number_format($this->amountUsed, 2),
                'remainingBalance' => number_format((float) $this->purchase->balance, 2),
                'currency' => $currency,
            ]);
    }
}
