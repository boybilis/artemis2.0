@extends('admin.layouts.app')

@section('title', (Auth::user()->hasAdminAccess()) ? 'Admin Dashboard' : ucfirst((string) Auth::user()->role).' Dashboard')
@section('kicker', 'Overview')


@section('content')
<style>
    .admin-dashboard-metrics { display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem; }
    .admin-dashboard-metrics .metric-card {
        --dashboard-accent:var(--brand-aqua);
        min-width:0;display:flex;flex-direction:column;gap:.55rem;
        border:1px solid var(--border);border-top:3px solid var(--dashboard-accent);
        border-radius:16px;padding:1.15rem;
        background:linear-gradient(145deg,var(--surface-solid),var(--brand-slate));
        box-shadow:0 6px 18px rgba(25,52,79,.08);
    }
    .admin-dashboard-metrics .metric-card:nth-child(4n+1) { --dashboard-accent:var(--brand-slate); }
    body:not(.light-mode) .admin-dashboard-metrics .metric-card:nth-child(4n+1) { --dashboard-accent:var(--brand-ice); }
    .admin-dashboard-metrics .metric-card:nth-child(4n) { --dashboard-accent:var(--brand-orange); }
    body.light-mode .admin-dashboard-metrics .metric-card { background:linear-gradient(145deg,var(--brand-white) 45%,var(--brand-ice)); }
    .admin-dashboard-metrics .metric-card::before,.admin-dashboard-metrics .metric-card::after { content:none; }
    .admin-dashboard-metrics .metric-label { display:flex;align-items:center;gap:.65rem;margin:0;color:var(--text); }
    .admin-dashboard-metrics .metric-icon {
        display:grid;place-items:center;flex:0 0 2rem;height:2rem;border-radius:8px;
        color:var(--text);background:color-mix(in srgb,var(--dashboard-accent) 15%,var(--surface-solid));
    }
    .admin-dashboard-metrics .metric-icon svg { width:17px;height:17px; }
    .admin-dashboard-metrics .metric-value {
        margin:0;background:none;color:var(--text);-webkit-text-fill-color:currentColor;
        font-size:clamp(1.3rem,1.75vw,1.85rem);line-height:1.15;overflow-wrap:anywhere;
    }
    .admin-dashboard-metrics .metric-note { margin:0; }
    .admin-dashboard-recent { margin-top:1rem; }
    body .admin-dashboard-recent.panel { background:var(--surface-solid);border-radius:16px;box-shadow:0 6px 18px rgba(25,52,79,.08); }
    .admin-dashboard-recent::before,.admin-dashboard-recent::after { content:none; }
    @media(max-width:1120px) { .admin-dashboard-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media(max-width:520px) { .admin-dashboard-metrics { grid-template-columns:minmax(0,1fr); } }
</style>
@php($metricIcons = ['users', 'user-check', 'book-check', 'clipboard-list', 'file-check', 'award', 'ticket', 'wallet'])
<div class="admin-dashboard-metrics">
    @foreach ($stats as $stat)
        <article class="metric-card">
            <p class="metric-label"><span class="metric-icon" aria-hidden="true"><i data-lucide="{{ $metricIcons[$loop->index] ?? 'chart-no-axes-column' }}"></i></span>{{ $stat['label'] }}</p>
            <p class="metric-value">{{ $stat['value'] }}</p>
            <p class="metric-note">{{ $stat['note'] }}</p>
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
