<section class="panel admin-dashboard-recent" data-ajax-table="recent-users">
    <p class="panel-label">User Management</p>
    <h2 class="panel-title">Recent Users</h2>
    <p class="panel-subtitle">Latest learners who joined the platform.</p>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Role</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentUsers as $user)
                    <tr>
                        <td style="font-weight: 600;">{{ $user->name }}</td>
                        <td>
                            @if($user->isAssistantAdmin())
                                <span class="status warning">Asst. Admin</span>
                            @elseif($user->role === 'admin' || $user->is_admin)
                                <span class="status warning">Admin</span>
                            @elseif(strtolower($user->role) === 'instructor')
                                <span class="status info">Instructor</span>
                            @else
                                <span class="status success">{{ ucfirst($user->role ?? 'student') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="2">No recent users to display.</td></tr>
                @endforelse
            </tbody>
    </table>
    </div>

    <div style="padding: 1.5rem; border-top: 1px solid var(--border);">
        {{ $recentUsers->links('pagination::bootstrap-4') }}
    </div>
</section>
