<!DOCTYPE html>
<html>
<body style="font-family:Arial,sans-serif;color:#1f2937;line-height:1.5">
    <h2>Your subscription ends soon</h2>
    <p>The subscription for <strong>{{ $companyName }}</strong> ends on
        <strong>{{ $endsAt->format('d M Y') }}</strong> — that's in {{ $daysLeft }} day(s).</p>
    <p>Renew before the end date to avoid any interruption to your workspace.</p>
    <p style="color:#6b7280;font-size:12px">HRM platform team.</p>
</body>
</html>
