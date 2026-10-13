@extends('admin.layouts.app')

@section('title', 'Admin Settings')
@section('kicker', 'Configuration')

@section('content')
@if(Auth::user()->is_admin || strtolower(trim(Auth::user()->role ?? '')) === 'admin')
<section class="panel" style="margin-bottom:18px">
    <p class="panel-label">Test Banks</p><h2 class="panel-title">Simulation Access</h2>
    <p class="panel-subtitle">Simulation exams normally require 10 distinct passed Warm-up tests and 5 distinct passed Mastery tests. Enable an override to unlock all simulation exams in that Test Bank for learners with an active subscription. Turning it off restores the normal requirements.</p>
    @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
    @foreach($testBanks as $bank)
    <form method="POST" action="{{ route('admin.settings.simulation-access', $bank) }}" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;padding:1rem 0;border-bottom:1px solid var(--border)">
        @csrf @method('PUT')
        <div><strong>{{ $bank->title }}</strong><p>{{ $bank->course?->title }}</p></div>
        <label style="display:flex;align-items:center;gap:.6rem"><input type="hidden" name="simulation_unlocked_by_admin" value="0"><input type="checkbox" name="simulation_unlocked_by_admin" value="1" @checked($bank->simulation_unlocked_by_admin)> Unlock simulations for all subscribed learners</label>
        <button class="btn-primary" type="submit">Save</button>
    </form>
    @endforeach
</section>
@endif
<div class="page-grid two">
    <section class="panel">
        <p class="panel-label">Admin profile</p>
        <h2 class="panel-title">Profile Details</h2>
        <div class="form-grid">
            <div class="field"><label>Name</label><input value="Admin User"></div>
            <div class="field"><label>Email</label><input type="email" value="admin@example.com"></div>
            <div class="field"><label>Role</label><select><option>Super Admin</option><option>Content Manager</option><option>Support Admin</option></select></div>
            <div class="field"><label>Timezone</label><select><option>Asia/Singapore</option><option>UTC</option></select></div>
        </div>
        <div class="actions" style="margin-top: 14px;"><button class="btn btn-primary" type="button">Update profile</button></div>
    </section>

    <section class="panel">
        <p class="panel-label">Password</p>
        <h2 class="panel-title">Change Password</h2>
        <div class="form-grid">
            <div class="field full"><label>Current password</label><input type="password"></div>
            <div class="field full"><label>New password</label><input type="password"></div>
            <div class="field full"><label>Confirm new password</label><input type="password"></div>
        </div>
        <div class="actions" style="margin-top: 14px;"><button class="btn btn-primary" type="button">Change password</button></div>
    </section>
</div>

<div class="page-grid two" style="margin-top: 18px;">
    <section class="panel">
        <p class="panel-label">Roles and access</p>
        <h2 class="panel-title">Access Placeholder</h2>
        <p class="panel-subtitle">Use this area later for role-based permissions.</p>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Role</th><th>Permissions</th><th>Status</th></tr></thead>
                <tbody>
                    <tr><td>Super Admin</td><td>All modules</td><td><span class="status success">Enabled</span></td></tr>
                    <tr><td>Content Manager</td><td>Content, reports</td><td><span class="status warning">Placeholder</span></td></tr>
                    <tr><td>Support Admin</td><td>Users, notifications</td><td><span class="status warning">Placeholder</span></td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <p class="panel-label">System settings</p>
        <h2 class="panel-title">Learning Rules</h2>
        <div class="form-grid">
            <div class="field"><label>Voucher price</label><input value="299"></div>
            <div class="field"><label>Final exam pass rule</label><select><option>Perfect score required</option><option>80% passing score</option></select></div>
            <div class="field"><label>Certificate prefix</label><input value="LC-CERT"></div>
            <div class="field"><label>Topic unlock rule</label><select><option>Open access</option><option>Sequential topics</option></select></div>
        </div>
        <div class="actions" style="margin-top: 14px;"><button class="btn btn-primary" type="button">Save settings</button></div>
    </section>
</div>
@endsection
