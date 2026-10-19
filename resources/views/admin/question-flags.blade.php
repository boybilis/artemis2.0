@extends('admin.layouts.app')
@section('title', 'Flagged Questions')
@section('content')
<style>
.flag-table-wrap{overflow-x:auto;border:1px solid var(--border);border-radius:12px;margin-top:20px}
.flag-table{width:100%;border-collapse:collapse;text-align:left;font-size:.9rem}
.flag-table th,.flag-table td{padding:16px;border-bottom:1px solid var(--border)}
.flag-table th{font-size:.75rem;text-transform:uppercase;color:var(--text-muted);white-space:nowrap}
.flag-table tbody tr:last-child td{border-bottom:0}
.flag-detail-text{white-space:pre-wrap;overflow-wrap:anywhere;margin:16px 0}
.flag-reply-field{margin-top:24px}
.flag-reply-field .form-label{display:block;margin-bottom:8px;font-size:.95rem;font-weight:600}
.flag-reply-field .form-control{display:block;width:100%;box-sizing:border-box;min-height:160px;padding:12px 14px;font:inherit;line-height:1.5;color:var(--text);background:var(--surface-solid,#fff);border:1px solid var(--border,#ced4da);border-radius:8px;resize:vertical;transition:border-color .15s ease-in-out,box-shadow .15s ease-in-out}
.flag-reply-field .form-control:focus{border-color:#08a991;outline:0;box-shadow:0 0 0 .2rem rgba(8,169,145,.2)}
.flag-reply-field .form-text{display:block;margin-top:8px;color:var(--text-muted);font-size:.8rem}
</style>
<section class="panel"><h2 class="panel-title">Flagged Questions</h2><p class="muted">View a flagged question to answer the learner’s query.</p>
@if(session('success'))<p role="status">{{ session('success') }}</p>@endif
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
<div class="flag-table-wrap"><table class="flag-table"><thead><tr><th>Learner’s name</th><th>Quiz name</th><th>Quiz type</th><th>Exam date taken</th><th>Date flagged</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@forelse($flags as $flag)
<tr><td><strong>{{ $flag->user->name }}</strong></td><td>{{ $flag->exam_details['title'] }}</td><td>{{ $flag->exam_details['type'] }}</td><td>{{ $flag->exam_details['taken_at'] ? \Carbon\Carbon::parse($flag->exam_details['taken_at'])->timezone('Asia/Manila')->format('M j, Y g:i A') : 'Unavailable' }}</td><td>{{ $flag->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</td><td>{{ $flag->reply ? 'Answered' : 'Awaiting reply' }}</td><td><button type="button" class="btn-ghost" onclick="openModal('flagModal{{ $flag->id }}')">View</button></td></tr>
@empty<tr><td colspan="7">No flagged questions yet.</td></tr>@endforelse
</tbody></table></div>
{{ $flags->links() }}</section>
@foreach($flags as $flag)
<div id="flagModal{{ $flag->id }}" class="admin-modal" role="dialog" aria-modal="true" aria-labelledby="flagTitle{{ $flag->id }}">
<form method="POST" action="{{ route('admin.question-flags.reply', $flag) }}" class="admin-modal-content" style="max-width:750px">@csrf @method('PUT')
<div class="admin-modal-header"><h3 id="flagTitle{{ $flag->id }}" class="admin-modal-title">Flagged Question</h3><button type="button" class="admin-modal-close" aria-label="Close" onclick="closeModal('flagModal{{ $flag->id }}')">&times;</button></div>
<div class="admin-modal-body">
<p><strong>{{ $flag->user->name }}</strong> · {{ $flag->exam_details['title'] }} · {{ $flag->exam_details['type'] }}</p>
<p><strong>Question ID:</strong> {{ $flag->question_snapshot['questionId'] ?? $flag->question_snapshot['id'] ?? 'Unavailable for this earlier review' }}</p>
<p class="flag-detail-text"><strong>Question:</strong> {{ $flag->question_snapshot['question'] ?? '' }}</p>
<p class="flag-detail-text"><strong>Correct answer:</strong> {{ $flag->question_snapshot['correctAnswer'] ?? '' }}</p>
<p class="flag-detail-text"><strong>Reason for flagging:</strong> {{ $flag->reason }}</p>
@if($flag->replied_at)<p class="muted">Replied by {{ $flag->responder?->name ?? 'Management' }} · {{ $flag->replied_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>@endif
<div class="flag-reply-field mb-3"><label class="form-label" for="reply{{ $flag->id }}">Reply to learner</label><textarea id="reply{{ $flag->id }}" class="form-control" name="reply" rows="5" maxlength="10000" placeholder="Write your explanation or answer here…" aria-describedby="replyHelp{{ $flag->id }}" required>{{ $flag->reply }}</textarea><small id="replyHelp{{ $flag->id }}" class="form-text">Your reply will be visible to the learner in Flagged Questions.</small></div>
</div><div class="admin-modal-footer"><button type="button" class="btn-ghost" onclick="closeModal('flagModal{{ $flag->id }}')">Cancel</button><button class="btn-primary">{{ $flag->reply ? 'Update Reply' : 'Send Reply' }}</button></div>
</form></div>
@endforeach
<script>
function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.returnFocus = document.activeElement;
    modal.classList.add('open');
    window.markStaffFlagRead?.(id.replace('flagModal',''));
    modal.querySelector('textarea')?.focus();
}
function closeModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.remove('open');
    modal.returnFocus?.focus();
}
document.querySelectorAll('[id^="flagModal"]').forEach(modal => {
    modal.addEventListener('click', event => { if (event.target === modal) closeModal(modal.id); });
    modal.addEventListener('keydown', event => { if (event.key === 'Escape') closeModal(modal.id); });
});
</script>
@endsection
