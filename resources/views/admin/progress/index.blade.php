@extends('admin.layouts.app')

@section('title', 'Progress and Records')
@section('kicker', 'Learning Records')

@section('content')
<div class="stat-grid">
    <article class="metric-card">
        <p class="metric-label">Completed topics</p>
        <p class="metric-value">{{ \App\Models\UserProgress::count() }}</p>
        <p class="metric-note">Total topic completions</p>
    </article>
    <article class="metric-card">
        <p class="metric-label">Average quiz score</p>
        @php
            $sumScore = \App\Models\QuizAttempt::sum('score');
            $sumTotal = \App\Models\QuizAttempt::sum('total');
            $avgQuiz = $sumTotal > 0 ? round(($sumScore / $sumTotal) * 100) : 0;
        @endphp
        <p class="metric-value">{{ $avgQuiz }}%</p>
        <p class="metric-note">Across all student submissions</p>
    </article>
    <article class="metric-card">
        <p class="metric-label">Exam attempts</p>
        <p class="metric-value">{{ \App\Models\QuizAttempt::whereNull('topic_id')->count() }}</p>
        <p class="metric-note">Final course examination attempts</p>
    </article>
    <article class="metric-card">
        <p class="metric-label">Eligible users</p>
        <p class="metric-value">{{ \App\Models\User::where('is_admin', false)->count() }}</p>
        <p class="metric-note">Registered learners</p>
    </article>
</div>

<section class="panel" style="margin-top: 18px;" data-ajax-table="attempts-table">
    <div class="toolbar">
        <div>
            <p class="panel-label">Attempts Log</p>
            <h2 class="panel-title">LMS Quiz & Exam Activity</h2>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Learner</th><th>Assessment Scope</th><th>Score Details</th><th>Result Status</th><th>Date Taken</th><th>Action</th></tr></thead>
            <tbody>
                @forelse ($attempts as $record)
                    <tr>
                        <td>
                            <strong>{{ $record->user->name ?? 'N/A' }}</strong><br>
                            <span class="muted">{{ $record->user->email ?? 'N/A' }}</span>
                        </td>
                        <td>
                            @if ($record->topic)
                                <span class="status info">{{ $record->topic->title }} Quiz</span>
                            @else
                                <span class="status warning">Final Course Examination</span>
                            @endif
                        </td>
                        <td><strong>{{ $record->score }} / {{ $record->total }}</strong></td>
                        <td>
                            <span class="status {{ $record->passed ? 'success' : 'neutral' }}">
                                {{ $record->passed ? 'Passed' : 'Failed' }}
                            </span>
                        </td>
                        <td>{{ $record->created_at->format('M d, Y h:i A') }}</td>
                        <td>
                            @if ($record->user)
                                <a href="{{ route('admin.users.show', $record->user->id) }}" class="btn btn-muted">View profile</a>
                            @else
                                <span class="muted">N/A</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="muted">No student quiz attempts recorded in database.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding-top:1.25rem; border-top:1px solid var(--border);">
        {{ $attempts->links('pagination::bootstrap-4') }}
    </div>
</section>
@endsection
