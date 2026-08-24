@extends('admin.layouts.app')

@section('title', 'Audit Logs')
@section('kicker', 'Security Trail')

@section('content')
<section class="panel" data-ajax-table="audit-logs-table">
    <div class="toolbar">
        <div>
            <p class="panel-label">System & Admin activity</p>
            <h2 class="panel-title">Security Audit Trail</h2>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Action Title</th><th>User / Admin</th><th>Event Description</th><th>Timestamp</th><th>IP Address</th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td><strong>{{ $log->action }}</strong></td>
                        <td>
                            @if ($log->user)
                                <strong>{{ $log->user->name }}</strong><br><span class="muted">{{ $log->user->email }}</span>
                            @else
                                <span class="muted">Guest / Anonymous</span>
                            @endif
                        </td>
                        <td>{{ $log->description }}</td>
                        <td>{{ $log->created_at->format('M d, Y h:i A') }}</td>
                        <td><code>{{ $log->ip_address }}</code></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted">No security logs recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding-top:1.25rem; border-top:1px solid var(--border);">
        {{ $logs->links('pagination::bootstrap-4') }}
    </div>
</section>
@endsection
