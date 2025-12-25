
<p><strong>Name:</strong> {{ $contact->name }}</p>
<p><strong>Email:</strong> {{ $contact->email }}</p>
<p><strong>Phone:</strong> {{ $contact->phone ?? '-' }}</p>
<p><strong>Message:</strong></p>
<p>{{ nl2br(e($contact->message)) }}</p>

<hr>
<p><small>IP: {{ $contact->ip ?? '-' }}</small></p>
<p><small>User Agent: {{ $contact->user_agent ?? '-' }}</small></p>
<p><small>Created: {{ $contact->created_at }}</small></p>
