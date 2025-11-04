<x-mail::message>
    # Dear {{ $user->name }},

    You’ve been granted **administrator status** for **Romeo & Juliet Beauty Lounge**.

    Use the link below to access your admin panel:

    <x-mail::button :url="$actionUrl">
        Access Admin Panel
    </x-mail::button>

    **One-Time Passcode:** {{ $password }}

    This passcode is valid for **one-time use only**. Please do not share it with others.

    Warm regards,<br>
    **Romeo & Juliet Beauty Lounge Team**
</x-mail::message>
