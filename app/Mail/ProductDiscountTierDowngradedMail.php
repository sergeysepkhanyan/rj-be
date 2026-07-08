<?php

namespace App\Mail;

use App\Models\ProductDiscountTier;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProductDiscountTierDowngradedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public ?ProductDiscountTier $newTier,
        public ProductDiscountTier $previousTier,
    ) {}

    public function build(): self
    {
        $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
        $newDiscount = $this->newTier ? $fmt($this->newTier->discount_percentage) : null;

        return $this->subject('Your store discount has changed')
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.product-discount-tier-downgraded')
            ->with([
                'userName' => $this->user->name ?? 'there',
                'previousDiscount' => $fmt($this->previousTier->discount_percentage),
                'newDiscount' => $newDiscount, // null → no discount tier anymore
                'shopUrl' => rtrim((string) config('app.frontend_url'), '/') . '/en/store',
            ]);
    }
}
