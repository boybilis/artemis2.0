@extends('admin.layouts.app')

@section('title', (Auth::user()->is_admin || strtolower((string) Auth::user()->role) === 'admin') ? 'Admin Dashboard' : 'Instructor Dashboard')
@section('kicker', 'Overview')


@section('content')
<div class="stat-grid">
    @foreach ($stats as $stat)
        <article class="metric-card">
            <p class="metric-label">{{ $stat['label'] }}</p>
            <p class="metric-value">{{ $stat['value'] }}</p>
            <p class="metric-note">{{ $stat['note'] }}</p>
        </article>
    @endforeach
</div>

<div style="margin-top: 18px;">
    <section class="panel">
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
</div>
@endsection
