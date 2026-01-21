<!doctype html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
    <p>Hi,</p>
    <p>Click here to reset your password:</p>
    <p><a href="{{ $resetUrl }}">{{ $resetUrl }}</a></p>
    <p>This link expires in 60 minutes.</p>
    <p>If you did not request this, ignore this email.</p>
</body>
</html>
