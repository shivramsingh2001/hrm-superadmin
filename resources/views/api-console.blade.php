@extends('layouts.app')
@section('title', 'API Console')

@section('content')
<p class="text-secondary small">A minimal client for <code>/api/v1/super-admin/*</code>. The full
    SPA is a later deliverable — this proves the API and is handy for support. Auth uses the JWT
    (separate secret), not your panel session.</p>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-header bg-white fw-semibold">1 · Get a token</div><div class="card-body">
            <input id="email" class="form-control form-control-sm mb-2" placeholder="email" value="{{ auth()->user()->email }}">
            <input id="password" type="password" class="form-control form-control-sm mb-2" placeholder="password">
            <input id="code" class="form-control form-control-sm mb-2" placeholder="6-digit TOTP code" maxlength="6">
            <button class="btn btn-sm btn-primary w-100" onclick="login()">Login</button>
            <div id="tokenState" class="small mt-2 text-secondary">no token</div>
        </div></div>

        <div class="card"><div class="card-header bg-white fw-semibold">2 · Call an endpoint</div><div class="card-body">
            <div class="d-flex gap-1 mb-2">
                <select id="method" class="form-select form-select-sm" style="width:90px">
                    <option>GET</option><option>POST</option><option>PUT</option><option>PATCH</option><option>DELETE</option>
                </select>
                <input id="path" class="form-control form-control-sm" value="dashboard">
            </div>
            <textarea id="body" class="form-control form-control-sm mb-2" rows="4" placeholder='JSON body (POST/PUT/PATCH)'></textarea>
            <button class="btn btn-sm btn-primary w-100" onclick="call()">Send</button>
            <div class="mt-2 small">
                Quick:
                <a href="#" onclick="q('GET','dashboard')">dashboard</a> ·
                <a href="#" onclick="q('GET','tenants')">tenants</a> ·
                <a href="#" onclick="q('GET','plans')">plans</a> ·
                <a href="#" onclick="q('GET','enquiries')">enquiries</a> ·
                <a href="#" onclick="q('GET','audit-logs')">audit</a> ·
                <a href="#" onclick="q('GET','roles?tenant_id=7')">roles?tenant_id=7</a>
            </div>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card"><div class="card-header bg-white fw-semibold d-flex justify-content-between">
            <span id="respStatus">Response</span></div>
            <div class="card-body p-0"><pre id="resp" style="margin:0;padding:14px;max-height:70vh;overflow:auto;font-size:12px">—</pre></div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const BASE = '/api/v1/super-admin/';
let token = sessionStorage.getItem('sa_api_token') || '';
if (token) document.getElementById('tokenState').textContent = 'token loaded from this tab';

async function login() {
    const r = await fetch(BASE + 'auth/login', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            email: email.value, password: password.value, code: code.value
        })
    });
    const j = await r.json();
    show(r.status, j);
    if (j.data && j.data.access_token) {
        token = j.data.access_token;
        sessionStorage.setItem('sa_api_token', token);
        tokenState.textContent = 'token OK · ' + (j.data.admin ? j.data.admin.role : '');
    }
}
function q(m, p) { method.value = m; path.value = p; call(); return false; }
async function call() {
    const opts = { method: method.value, headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' } };
    if (['POST', 'PUT', 'PATCH'].includes(method.value) && body.value.trim()) {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = body.value;
    }
    const r = await fetch(BASE + path.value, opts);
    let j; try { j = await r.json(); } catch (e) { j = await r.text(); }
    show(r.status, j);
}
function show(status, j) {
    respStatus.textContent = 'HTTP ' + status;
    document.getElementById('resp').textContent = typeof j === 'string' ? j : JSON.stringify(j, null, 2);
}
</script>
@endsection
