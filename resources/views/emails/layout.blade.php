{{-- Shared DIDN email shell: logo header, content section, unsubscribe + footer. --}}
@php
    $organizationName = config('services.resend.from_name', 'Direct Impact Development Network');
    $logoUrl = rtrim((string) config('services.resend.public_website_url', 'https://www.directimpactnetwork.org'), '/').'/logo.png';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>{{ $subject ?? $organizationName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <div style="width:100%;background-color:#f4f4f5;padding:32px 0;">
        <div style="max-width:600px;margin:0 auto;background-color:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e4e4e7;">
            <div style="background-color:#0b3d2e;padding:28px 32px;text-align:center;">
                <img src="{{ $logoUrl }}" alt="{{ $organizationName }}" style="max-width:180px;height:auto;border:0;display:inline-block;">
            </div>
            <div style="padding:32px;color:#18181b;font-size:15px;line-height:1.6;">
                @yield('content')
            </div>
            <div style="padding:20px 32px;border-top:1px solid #e4e4e7;text-align:center;font-size:12px;color:#71717a;">
                @isset($unsubscribeUrl)
                    <p style="margin:0 0 12px;">
                        <a href="{{ $unsubscribeUrl }}" style="color:#71717a;text-decoration:underline;">Unsubscribe from this newsletter</a>
                    </p>
                @endisset
                <p style="margin:0;">
                    {{ $organizationName }} &middot; directimpactnetwork.org
                </p>
            </div>
        </div>
    </div>
</body>
</html>