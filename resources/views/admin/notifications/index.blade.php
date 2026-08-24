@extends('admin.layouts.app')

@section('title', 'Notifications and Announcements')
@section('kicker', 'Communication')

@section('content')
<div class="split-grid">
    <section class="panel">
        <p class="panel-label">Create announcement</p>
        <h2 class="panel-title">Global Broadcast Bulletin</h2>
        <p class="panel-subtitle">Create a verified notification notice shown to all learners.</p>
        
        <form action="{{ route('admin.notifications.create') }}" method="POST">
            @csrf
            <div class="form-grid">
                <div class="field full">
                    <label>Announcement Title</label>
                    <input type="text" name="title" placeholder="e.g. Artemis 2.0 System Maintenance" required>
                </div>
                <div class="field full">
                    <label>Broadcast Message Body</label>
                    <textarea name="content" rows="4" placeholder="Write bulletin notes, alerts, or notifications here..." required></textarea>
                </div>
            </div>
            <div class="actions" style="margin-top: 20px;">
                <button class="btn btn-primary" type="submit" style="width: 100%;">Broadcast Announcement</button>
            </div>
        </form>
    </section>

    <aside class="panel">
        <p class="panel-label">System note</p>
        <h2 class="panel-title">Audience Scope</h2>
        <p class="panel-subtitle">Current broadcast scope rules:</p>
        <div class="list-stack">
            <div class="list-item">
                <strong>Public Notice Board</strong>
                <span class="muted">All announcements are displayed dynamically inside the learner's dashboard banner area.</span>
            </div>
            <div class="list-item">
                <strong>Administrative Logs</strong>
                <span class="muted">Every broadcast creates a verified audit record matching your Administrator ID.</span>
            </div>
        </div>
    </aside>
</div>

<section class="panel" style="margin-top: 18px;" data-ajax-table="announcements-table">
    <p class="panel-label">History</p>
    <h2 class="panel-title">Broadcast History</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Title</th><th>Audience</th><th>Admin Author</th><th>Date / Time</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($announcements as $row)
                    <tr>
                        <td><strong>{{ $row->title }}</strong><br><span class="muted">{{ Str::limit($row->content, 60) }}</span></td>
                        <td><span class="status info">All Learners</span></td>
                        <td>{{ $row->creator->name ?? 'System' }}</td>
                        <td>{{ $row->created_at->format('M d, Y h:i A') }}</td>
                        <td><span class="status success">Published</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted">No dynamic announcements sent yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding-top:1.25rem; border-top:1px solid var(--border);">
        {{ $announcements->links('pagination::bootstrap-4') }}
    </div>
</section>
@endsection
