<!doctype html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
<p>Hi {{ $name ?: 'there' }} 👋</p>

<p>Thanks for registering. Please verify your email to continue.</p>

<p>
    <a href="{{ $verifyUrl }}">
        Verify Email
    </a>
</p>

<p>This link expires in 60 minutes.</p>

<p>— RJ Team</p>
</body>
</html>

