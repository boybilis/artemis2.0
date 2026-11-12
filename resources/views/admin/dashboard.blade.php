@extends('admin.layouts.app')

@section('title', (Auth::user()->hasAdminAccess()) ? 'Admin Dashboard' : ucfirst((string) Auth::user()->role).' Dashboard')
@section('kicker', 'Overview')


@section('content')
<style>
    .admin-dashboard-metrics { display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem; }
    body .admin-dashboard-metrics .metric-card {
        --metric-ink:var(--brand-ice);
        min-width:0;min-height:8.5rem;display:flex;flex-direction:column;justify-content:center;gap:.45rem;
        border:1px solid rgba(255,255,255,.32);border-radius:16px;padding:1.3rem;
        background:var(--brand-slate);
        box-shadow:0 8px 20px rgba(25,52,79,.13);
    }
    body.light-mode .admin-dashboard-metrics .metric-card {
        --metric-ink:var(--brand-slate);
        background:var(--brand-white);
        border-color:var(--border);
    }
    .admin-dashboard-metrics .metric-card::before,.admin-dashboard-metrics .metric-card::after { content:none; }
    .admin-dashboard-metrics .metric-label { margin:0;color:var(--metric-ink);font-size:.78rem;letter-spacing:normal;text-transform:none; }
    .admin-dashboard-metrics .metric-value {
        margin:0;background:none;color:var(--metric-ink);-webkit-text-fill-color:currentColor;
        font-size:clamp(1.35rem,2vw,1.95rem);line-height:1.15;overflow-wrap:anywhere;font-variant-numeric:tabular-nums;
    }
    .admin-dashboard-metrics .metric-note { margin:0;color:var(--metric-ink);font-size:.78rem; }
    .admin-dashboard-insights { display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,1fr);gap:1rem;margin-top:1rem;align-items:start; }
    body .admin-dashboard-insights .panel { min-width:0;background:var(--surface-solid);border-radius:16px;box-shadow:0 6px 18px rgba(25,52,79,.08); }
    .admin-dashboard-insights .panel::before,.admin-dashboard-insights .panel::after { content:none; }
    .registration-chart-scroll { overflow-x:auto; }
    .registration-chart { display:grid;grid-auto-flow:column;grid-auto-columns:minmax(2rem,1fr);gap:.35rem;min-width:max-content;margin:0;padding:0;list-style:none; }
    .registration-month { min-width:0;display:grid;grid-template-rows:11rem auto;gap:.5rem;text-align:center;font-size:.72rem;color:var(--text); }
    .registration-track { display:flex;align-items:flex-end;justify-content:center;padding-top:1.4rem;border-bottom:1px solid var(--border); }
    .registration-bar { position:relative;width:75%;max-width:2.5rem;border-radius:5px 5px 0 0;background:var(--brand-aqua); }
    .registration-count { position:absolute;bottom:100%;left:50%;transform:translateX(-50%);padding-bottom:.2rem;font-weight:700;font-variant-numeric:tabular-nums; }
    @media(max-width:1120px) {
        .admin-dashboard-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .admin-dashboard-insights { grid-template-columns:minmax(0,1fr); }
    }
    @media(max-width:520px) { .admin-dashboard-metrics { grid-template-columns:minmax(0,1fr); } }
</style>
<div class="admin-dashboard-metrics">
    @foreach ($stats as $stat)
        <article class="metric-card">
            <p class="metric-label">{{ $stat['label'] }}</p>
            <p class="metric-value">{{ $stat['value'] }}</p>
            <p class="metric-note">{{ $stat['note'] }}</p>
        </article>
    @endforeach
</div>

<div class="admin-dashboard-insights">
    <section class="panel admin-dashboard-recent">
        <p class="panel-label">User Management</p>
        <h2 class="panel-title">Recent Users</h2>
        <p class="panel-subtitle">Latest learners who joined the platform.</p>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentUsers as $user)
                        <tr>
                            <td style="font-weight: 600;">{{ $user->name }}</td>
                            <td style="color: var(--text-muted);">{{ $user->email }}</td>
                            <td>
                                @if($user->role === 'admin' || $user->is_admin)
                                    <span class="status warning">Admin</span>
                                @elseif(strtolower($user->role) === 'instructor')
                                    <span class="status info">Instructor</span>
                                @else
                                    <span class="status success">{{ ucfirst($user->role ?? 'student') }}</span>
                                @endif
                            </td>
                            <td style="color: var(--text-muted);">{{ $user->created_at->format('M d, Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
        </table>
        </div>
        
        <div style="padding: 1.5rem; border-top: 1px solid var(--border);">
            {{ $recentUsers->links('pagination::bootstrap-4') }}
        </div>
    </section>
    <section class="panel" aria-labelledby="monthly-registrations-title">
        <p class="panel-label">{{ $registrationYear }} overview</p>
        <h2 class="panel-title" id="monthly-registrations-title">Monthly Registrations</h2>
        <p class="panel-subtitle">New accounts from January through {{ last($monthlyRegistrations)['label'] }}.</p>
        @php($chartMax = max(1, max(array_column($monthlyRegistrations, 'count'))))
        <div class="registration-chart-scroll" tabindex="0" aria-label="Monthly registrations bar chart">
            <ol class="registration-chart" role="list">
                @foreach($monthlyRegistrations as $registration)
                    <li class="registration-month">
                        <div class="registration-track">
                            <div class="registration-bar" style="height:{{ $registration['count'] / $chartMax * 100 }}%">
                                <span class="registration-count">{{ number_format($registration['count']) }}</span>
                            </div>
                        </div>
                        <abbr title="{{ $registration['label'] }}">{{ $registration['month'] }}</abbr>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>
</div>
@endsection
