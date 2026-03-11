@php
    $logoUrl = config('mail.logo_url') ?? asset('images/rj.png');
@endphp
<img src="{{ $logoUrl }}" alt="{{ config('app.name', 'Romeo & Juliet Beauty Lounge') }}" style="max-width:180px; height:auto; display:block;" width="180">
