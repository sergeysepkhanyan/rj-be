<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactMessageReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ContactMessage $contact)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('New Contact Message')
            ->replyTo($this->contact->email, $this->contact->name)
            ->view('emails.contact.received', [
                'contact' => $this->contact,
            ]);
    }
}

