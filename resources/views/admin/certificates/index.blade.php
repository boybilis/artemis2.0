@extends('admin.layouts.app')

@section('title', 'Certificate Management')
@section('kicker', 'Credentials')

@section('content')
<div class="stat-grid">
    <article class="metric-card">
        <p class="metric-label">Issued certificates</p>
        <p class="metric-value">{{ $certificateStats['issued'] }}</p>
        <p class="metric-note">All time</p>
    </article>
    <article class="metric-card">
        <p class="metric-label">Verified</p>
        <p class="metric-value">{{ $certificateStats['issued'] }}</p>
        <p class="metric-note">Publicly valid</p>
    </article>
    <article class="metric-card">
        <p class="metric-label">Revoked</p>
        <p class="metric-value">0</p>
        <p class="metric-note">Admin disabled</p>
    </article>
    <article class="metric-card">
        <p class="metric-label">Pending exam</p>
        <p class="metric-value">{{ max(0, $certificateStats['learners'] - $certificateStats['issued']) }}</p>
        <p class="metric-note">Registered learners</p>
    </article>
</div>

<section class="panel" style="margin-top: 18px;" data-ajax-table="certificates-table">
    <div class="toolbar">
        <div>
            <p class="panel-label">Issued list</p>
            <h2 class="panel-title">Certificates</h2>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Certificate code</th><th>User</th><th>Email</th><th>Issue date</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($certificates as $certificate)
                    <tr>
                        <td><strong>{{ $certificate->code }}</strong></td>
                        <td>{{ $certificate->user->name ?? 'N/A' }}</td>
                        <td>{{ $certificate->user->email ?? 'N/A' }}</td>
                        <td>{{ $certificate->issued_at ? \Carbon\Carbon::parse($certificate->issued_at)->format('M d, Y h:i A') : 'N/A' }}</td>
                        <td><span class="status success">Verified</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted">No certificates have been issued yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding-top:1.25rem; border-top:1px solid var(--border);">
        {{ $certificates->links('pagination::bootstrap-4') }}
    </div>
</section>
@endsection
