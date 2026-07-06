<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Stream 3 (marketing / promotions). MUST only be dispatched through
 * MarketingDispatcher::send() so the opt-in gate is enforced, and always
 * carries the recipient's unsubscribe link.
 */
class MarketingCampaignMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $bodyText,
        public string $unsubscribeUrl,
        public ?string $recipientName = null,
    ) {}

    public function build(): MarketingCampaignMail
    {
        return $this->subject($this->subjectLine)
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.marketing-campaign')
            ->with([
                'recipientName' => $this->recipientName ?: 'Valued Customer',
                'bodyText' => $this->bodyText,
                'unsubscribeUrl' => $this->unsubscribeUrl,
            ]);
    }
}
