<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;color:#1f2937;line-height:1.6">
    <h2>Platform weekly digest</h2>
    <p>Snapshot for <strong>{{ $metric->metric_date->format('d M Y') }}</strong>.</p>
    <table style="border-collapse:collapse;font-size:14px">
        <tr><td style="padding:3px 16px 3px 0">Total tenants</td><td><strong>{{ $metric->total_tenants }}</strong> ({{ $metric->active_tenants }} active, {{ $metric->suspended_tenants }} suspended)</td></tr>
        <tr><td style="padding:3px 16px 3px 0">New this month</td><td><strong>{{ $metric->new_tenants_month }}</strong></td></tr>
        <tr><td style="padding:3px 16px 3px 0">Churned this month</td><td>{{ $metric->churned_tenants_month }}</td></tr>
        <tr><td style="padding:3px 16px 3px 0">Open enquiries</td><td>{{ $metric->open_enquiries }}</td></tr>
        <tr><td style="padding:3px 16px 3px 0">MRR (est.)</td><td><strong>{{ number_format($metric->mrr, 2) }}</strong></td></tr>
        <tr><td style="padding:3px 16px 3px 0">ARR (est.)</td><td>{{ number_format($metric->arr, 2) }}</td></tr>
        @if($metric->avg_enquiry_to_provision_days !== null)
        <tr><td style="padding:3px 16px 3px 0">Avg enquiry → live</td><td>{{ $metric->avg_enquiry_to_provision_days }} days</td></tr>
        @endif
    </table>
    <p style="color:#6b7280;font-size:12px">HRM platform.</p>
</body></html>
