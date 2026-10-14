@extends('admin.layouts.app')
@section('title', 'Flagged Questions')
@section('content')
<section class="panel"><h2 class="panel-title">Flagged Questions</h2><p class="muted">Answer learners’ questions about their completed tests.</p>
@if(session('success'))<p role="status">{{ session('success') }}</p>@endif
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
@forelse($flags as $flag)
<article style="padding:1.25rem 0;border-bottom:1px solid var(--border)">
<h3>{{ $flag->user->name }} · {{ $flag->reply ? 'Answered' : 'Awaiting reply' }}</h3>
<p class="muted">{{ $flag->created_at->format('M j, Y g:i A') }}</p>
<p style="white-space:pre-wrap"><strong>Question:</strong> {{ $flag->question_snapshot['question'] ?? '' }}</p>
<p style="white-space:pre-wrap"><strong>Correct answer:</strong> {{ $flag->question_snapshot['correctAnswer'] ?? '' }}</p>
<p style="white-space:pre-wrap"><strong>Reason for flagging:</strong> {{ $flag->reason }}</p>
@if($flag->replied_at)<p class="muted">Replied by {{ $flag->responder?->name ?? 'Management' }} · {{ $flag->replied_at->format('M j, Y g:i A') }}</p>@endif
<form method="POST" action="{{ route('admin.question-flags.reply', $flag) }}">@csrf @method('PUT')
<label for="reply{{ $flag->id }}">Reply to learner</label><textarea id="reply{{ $flag->id }}" class="form-control" name="reply" rows="3" maxlength="10000" required>{{ $flag->reply }}</textarea>
<button class="btn-primary" style="margin-top:12px">{{ $flag->reply ? 'Update Reply' : 'Send Reply' }}</button></form></article>
@empty<p>No flagged questions yet.</p>@endforelse
{{ $flags->links() }}</section>
@endsection
