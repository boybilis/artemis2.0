<section class="panel admin-dashboard-recent" data-ajax-table="recent-users">
    <p class="panel-label">User Management</p>
    <h2 class="panel-title">Recent Users</h2>
    <p class="panel-subtitle">The 10 latest accounts to join the platform.</p>

    <div class="table-wrap">
        <table class="data-table admin-compact-table">
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

    @if($recentUsers->hasPages())
        <nav class="recent-users-pagination" aria-label="Recent Users pagination">
            <button type="button" class="btn-ghost" data-recent-users-page="{{ $recentUsers->previousPageUrl() }}" @disabled($recentUsers->onFirstPage())>Previous</button>
            <span role="status">Page {{ $recentUsers->currentPage() }} of {{ $recentUsers->lastPage() }}</span>
            <button type="button" class="btn-ghost" data-recent-users-page="{{ $recentUsers->nextPageUrl() }}" @disabled(!$recentUsers->hasMorePages())>Next</button>
        </nav>
    @endif
    <p class="recent-users-error" role="alert" hidden></p>
</section>
