<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5;">
    <div style="max-width:600px; margin:0 auto; padding:24px; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size:16px; line-height:1.6; color:#18181b;">
        <div style="background-color:#ffffff; border-radius:8px; padding:32px;">
            {!! $body !!}
        </div>
        <p style="margin:24px 0 0; text-align:center; font-size:12px; color:#71717a;">
            {{ config('app.name') }}
        </p>
    </div>
</body>
</html>
