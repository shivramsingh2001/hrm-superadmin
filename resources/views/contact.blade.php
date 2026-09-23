<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Talk to us · HRM Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body{background:#f4f6fa;} .box{max-width:560px;margin:6vh auto;}</style>
</head>
<body>
<div class="box">
    <h3 class="mb-1">Get your HRM workspace</h3>
    <p class="text-secondary">Tell us about your company and our team will set you up.</p>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('contact') }}">
            @csrf
            {{-- honeypot --}}
            <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px">

            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Company name *</label>
                    <input name="company_name" value="{{ old('company_name') }}" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Your name *</label>
                    <input name="contact_name" value="{{ old('contact_name') }}" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Work email *</label>
                    <input type="email" name="work_email" value="{{ old('work_email') }}" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Phone</label>
                    <input name="phone" value="{{ old('phone') }}" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Country</label>
                    <input name="country" value="{{ old('country', 'India') }}" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Employees</label>
                    <input type="number" name="employee_count" value="{{ old('employee_count') }}" min="1" max="50000" class="form-control"></div>
                <div class="col-12"><label class="form-label">Plan of interest</label>
                    <select name="plan_interest" class="form-select">
                        <option value="">— not sure —</option>
                        @foreach($plans as $slug => $name)<option value="{{ $slug }}" @selected(old('plan_interest')===$slug)>{{ $name }}</option>@endforeach
                    </select></div>
                <div class="col-12"><label class="form-label">Message</label>
                    <textarea name="message" class="form-control" rows="3" maxlength="1000">{{ old('message') }}</textarea></div>
            </div>
            <button class="btn btn-primary mt-3">Send enquiry</button>
        </form>
    </div></div>
</div>
</body>
</html>
