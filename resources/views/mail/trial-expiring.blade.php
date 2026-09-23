<!DOCTYPE html>
<html>
<body style="font-family:Arial,sans-serif;color:#1f2937;line-height:1.5">
    <h2>Your trial ends soon</h2>
    <p>The trial for <strong>{{ $companyName }}</strong> ends on
        <strong>{{ $endsAt->format('d M Y') }}</strong> — that's in {{ $daysLeft }} day(s).</p>
    <p>To keep your workspace active, contact our team to arrange payment. Your data
        is safe and nothing is deleted when a trial ends — the workspace is simply
        paused until the subscription is confirmed.</p>
    <p style="color:#6b7280;font-size:12px">HRM platform team.</p>
</body>
</html>
