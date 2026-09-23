@extends('layouts.app')
@section('title', 'Feature Registry')

@section('content')
@php($isSuper = auth()->user()->isSuperadmin())

<style>
    .fr-wrap { font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    .fr-head { display: flex; align-items: center; justify-content: space-between;
        padding: .55rem .9rem; background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; margin-bottom: .75rem; }
    .fr-head h2 { font-size: .92rem; font-weight: 600; margin: 0; color: #1f2937; }
    .fr-head .sub { font-size: .72rem; color: #9ca3af; }
    table.fr-table { font-size: .8rem; margin: 0; }
    table.fr-table thead th { font-size: .66rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
        color: #9ca3af; background: #fafbfc; border-bottom: 1px solid #e5e7eb; padding: .55rem .7rem; white-space: nowrap; }
    table.fr-table tbody td { padding: .55rem .7rem; vertical-align: middle; color: #374151; }
    table.fr-table tbody tr:hover { background: #fafbfc; }
    table.fr-table code { font-size: .72rem; color: #4338ca; background: #eef2ff; padding: .1rem .35rem; border-radius: .3rem; }
    table.fr-table tr.table-warning td { background: #fffbeb; }
    table.fr-table .sr { color: #adb3ba; width: 2.6rem; white-space: nowrap; }
</style>

<div class="fr-wrap">
    <div class="fr-head">
        <div>
            <h2>Feature Registry</h2>
            <span class="sub">{{ $rows->count() }} of {{ count($config) }} config keys seeded · source of truth is <code style="font-size:.68rem">config/features.php</code></span>
        </div>
        @if($isSuper)
            <form method="POST" action="{{ route('feature-registry.reseed') }}">@csrf
                <button class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat"></i> Re-seed from config</button></form>
        @endif
    </div>

    @if(count($missing) || count($orphans))
        <div class="alert alert-warning py-2 small mb-3">
            @if(count($missing))<div><strong>In config, not seeded:</strong> {{ implode(', ', $missing) }} — click "Re-seed".</div>@endif
            @if(count($orphans))<div><strong>Orphan rows (key gone from config):</strong> {{ implode(', ', $orphans) }} — deprecate or remove them in code.</div>@endif
        </div>
    @endif

    <div class="card"><div class="table-responsive">
        <table class="table fr-table align-middle mb-0">
            <thead><tr><th class="sr">Sr. No.</th><th>Key</th><th>Name</th><th>Module</th><th>Default</th><th>State</th><th>Replaced by</th>@if($isSuper)<th class="text-end">Manage</th>@endif</tr></thead>
            <tbody>
            @foreach($config as $key => $meta)
                @php($row = $rows->get($key))
                <tr class="{{ $row ? '' : 'table-warning' }}">
                    <td class="sr">{{ $loop->iteration }}</td>
                    <td><code>{{ $key }}</code></td>
                    <td class="fw-semibold">{{ $meta['name'] }}</td>
                    <td class="text-secondary">{{ $meta['module'] }}</td>
                    <td>{!! ($meta['default'] ?? false) ? '<span class="text-success">on</span>' : '<span class="text-secondary">off</span>' !!}</td>
                    <td>
                        @if(!$row)<span class="badge bg-warning text-dark">not seeded</span>
                        @elseif($row->deprecated_at)<span class="badge bg-danger">deprecated {{ $row->deprecated_at->format('d M Y') }}</span>
                        @elseif(!$row->is_active)<span class="badge bg-secondary">inactive</span>
                        @else<span class="badge bg-success">active</span>@endif
                    </td>
                    <td class="text-secondary">{{ $row?->replaced_by ?? '—' }}</td>
                    @if($isSuper)
                    <td class="text-end text-nowrap">
                        @if($row)
                        <form method="POST" action="{{ route('feature-registry.update', $key) }}" class="d-inline-flex gap-1 justify-content-end">
                            @csrf
                            @if($row->deprecated_at)
                                <button name="action" value="undeprecate" class="btn btn-sm btn-outline-success">Restore</button>
                            @else
                                <select name="replaced_by" class="form-select form-select-sm" style="width:130px">
                                    <option value="">replaced by…</option>
                                    @foreach(array_keys($config) as $k)@if($k !== $key)<option value="{{ $k }}">{{ $k }}</option>@endif @endforeach
                                </select>
                                <button name="action" value="deprecate" class="btn btn-sm btn-outline-danger">Deprecate</button>
                                <button name="action" value="{{ $row->is_active ? 'deactivate' : 'activate' }}" class="btn btn-sm btn-outline-secondary">{{ $row->is_active ? 'Disable' : 'Enable' }}</button>
                            @endif
                        </form>
                        @endif
                    </td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
    </div></div>
</div>
@endsection
