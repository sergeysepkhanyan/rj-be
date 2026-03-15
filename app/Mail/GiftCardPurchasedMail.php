<?php

namespace App\Mail;

use App\Models\GiftCardPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GiftCardPurchasedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public GiftCardPurchase $purchase, public string $type = 'buyer') {}

    public function envelope(): Envelope
    {
        $subject = $this->type === 'buyer'
            ? 'Your Gift Card Purchase - R&J Beauty Lounge'
            : 'You Received a Gift Card - R&J Beauty Lounge';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.gift-card-purchased',
            with: [
                'purchase' => $this->purchase,
                'giftCard' => $this->purchase->giftCard,
                'type' => $this->type,
                'code' => $this->purchase->code,
                'amount' => number_format((float) $this->purchase->amount, 2),
                'currency' => $this->purchase->currency,
                'recipientName' => $this->purchase->recipient_name,
                'buyerName' => $this->purchase->buyer_name,
                'expiresAt' => $this->purchase->expires_at->format('F j, Y'),
            ],
        );
    }
}
