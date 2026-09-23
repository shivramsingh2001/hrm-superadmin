@extends('layouts.app')
@section('title', 'Edit ' . $tenant->company_name)

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3" style="max-width:900px">
        <div>
            <h2 class="mb-0" style="font-size:1rem;font-weight:600">Edit tenant</h2>
            <span class="text-secondary" style="font-size:.74rem">{{ $tenant->company_name }} · <code>{{ $tenant->subdomain }}</code></span>
        </div>
        <a href="{{ route('tenants.show', $tenant) }}" class="btn btn-sm btn-outline-secondary">← Back to tenant</a>
    </div>
    <div style="max-width:900px">
        @include('tenants._edit_form')
    </div>
@endsection
