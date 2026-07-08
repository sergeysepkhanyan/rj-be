<?php

namespace App\Mail;

use App\Models\ProductDiscountTier;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProductDiscountTierUpgradedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public ProductDiscountTier $tier,
    ) {}

    public function build(): self
    {
        $discount = rtrim(rtrim(number_format((float) $this->tier->discount_percentage, 2), '0'), '.');

        return $this->subject("You've unlocked a {$discount}% store discount!")
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.product-discount-tier-upgraded')
            ->with([
                'userName' => $this->user->name ?? 'there',
                'discount' => $discount,
                'shopUrl' => rtrim((string) config('app.frontend_url'), '/') . '/en/store',
            ]);
    }
}
