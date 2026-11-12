@extends('admin.layouts.app')

@section('title', (Auth::user()->hasAdminAccess()) ? 'Admin Dashboard' : ucfirst((string) Auth::user()->role).' Dashboard')
@section('kicker', 'Overview')


@section('content')
<style>
    .admin-dashboard-metrics { display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem; }
    .admin-dashboard-metrics .metric-card {
        --metric-start:var(--brand-ice);--metric-end:var(--brand-aqua);
        --metric-ink:var(--brand-dark-surface);
        min-width:0;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,max-content);align-items:center;gap:.65rem;
        border:1px solid rgba(255,255,255,.32);border-radius:12px;padding:.95rem;
        background:linear-gradient(120deg,rgba(255,255,255,.22),transparent 72%),linear-gradient(115deg,var(--metric-start),var(--metric-end));
        box-shadow:0 6px 18px rgba(25,52,79,.15),inset 0 0 12px rgba(255,255,255,.14);
    }
    .admin-dashboard-metrics .metric-card:nth-child(4n+2) { --metric-start:#ffe5d3;--metric-end:var(--brand-orange); }
    .admin-dashboard-metrics .metric-card:nth-child(4n+3) { --metric-start:var(--brand-white);--metric-end:#badfee; }
    .admin-dashboard-metrics .metric-card:nth-child(4n) { --metric-start:var(--brand-dark-surface);--metric-end:#23536c;--metric-ink:var(--brand-white); }
    .admin-dashboard-metrics .metric-card::before,.admin-dashboard-metrics .metric-card::after { content:none; }
    .admin-dashboard-metrics .metric-label { margin:0;color:var(--metric-ink);font-size:.78rem;letter-spacing:normal;text-transform:none; }
    .admin-dashboard-metrics .metric-value {
        margin:0;background:none;color:var(--metric-ink);-webkit-text-fill-color:currentColor;
        font-size:clamp(1.1rem,1.6vw,1.65rem);line-height:1.15;text-align:right;overflow-wrap:anywhere;font-variant-numeric:tabular-nums;
    }
    .admin-dashboard-metrics .metric-note { margin:.18rem 0 0;color:var(--metric-ink);font-size:.68rem; }
    .admin-dashboard-metrics .metric-unit { display:block;font-size:.62rem;letter-spacing:.08em; }
    .admin-dashboard-recent { margin-top:1rem; }
    body .admin-dashboard-recent.panel { background:var(--surface-solid);border-radius:16px;box-shadow:0 6px 18px rgba(25,52,79,.08); }
    .admin-dashboard-recent::before,.admin-dashboard-recent::after { content:none; }
    @media(max-width:1120px) { .admin-dashboard-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media(max-width:520px) { .admin-dashboard-metrics { grid-template-columns:minmax(0,1fr); } }
</style>
<div class="admin-dashboard-metrics">
    @foreach ($stats as $stat)
        <article class="metric-card">
            <div>
                <p class="metric-label">{{ $stat['label'] }}</p>
                <p class="metric-note">{{ $stat['note'] }}</p>
            </div>
            <p class="metric-value">
                @if(str_starts_with((string) $stat['value'], 'PHP '))
                    <small class="metric-unit">PHP</small>{{ substr($stat['value'], 4) }}
                @else
                    {{ $stat['value'] }}
                @endif
            </p>
        </article>
    @endforeach
</div>

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
@endsection
