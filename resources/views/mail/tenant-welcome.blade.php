<!DOCTYPE html>
<html>
<body style="font-family:Arial,sans-serif;color:#1f2937;line-height:1.5">
    <h2>Welcome to your HRM workspace</h2>
    <p>Hi {{ $adminName }},</p>
    <p>Your workspace for <strong>{{ $companyName }}</strong> is live.</p>
    <table style="border-collapse:collapse">
        <tr><td style="padding:4px 12px 4px 0"><strong>Login URL</strong></td><td><a href="{{ $loginUrl }}">{{ $loginUrl }}</a></td></tr>
        <tr><td style="padding:4px 12px 4px 0"><strong>Email</strong></td><td>{{ $adminEmail }}</td></tr>
        <tr><td style="padding:4px 12px 4px 0"><strong>Temporary password</strong></td><td><code>{{ $password }}</code></td></tr>
    </table>
    <p>Please sign in and change your password. First-run checklist:</p>
    <ol>
        <li>Add your departments, designations and branches</li>
        <li>Invite employees (or import them)</li>
        <li>Review the attendance policy, shifts and working days</li>
        <li>Configure leave types and opening balances</li>
    </ol>
    <p style="color:#6b7280;font-size:12px">Sent by the HRM platform team.</p>
</body>
</html>
