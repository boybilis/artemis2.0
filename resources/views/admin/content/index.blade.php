@extends('admin.layouts.app')

@section('title', 'Content Management')
@section('kicker', 'Lessons and Exams')

@section('content')
<div class="tabs">
    <a class="tab active" href="{{ route('admin.content.index') }}">Overview</a>
    <a class="tab" href="{{ route('admin.content.topics') }}">Topics</a>
    <a class="tab" href="{{ route('admin.content.quizzes') }}">Assessments</a>
</div>

<div class="page-grid two">
    <article class="panel">
        <p class="panel-label">Topics</p>
        <h2 class="panel-title">{{ $topics->count() }} Topics</h2>
        <p class="panel-subtitle">Manage topic titles, sorting, active status, videos, and PDFs.</p>
        <a class="btn btn-primary" href="{{ route('admin.content.topics') }}">Manage topics</a>
    </article>
    <article class="panel">
        <p class="panel-label">Assessments</p>
        <h2 class="panel-title">{{ \App\Models\QuizQuestion::count() }} Exam Questions</h2>
        <p class="panel-subtitle">Manage course question banks, options, answers, and assessment rules.</p>
        <a class="btn btn-primary" href="{{ route('admin.content.quizzes') }}">Manage quizzes</a>
    </article>
</div>

<section class="panel" style="margin-top: 18px;">
    <p class="panel-label">Database Schema Status</p>
    <h2 class="panel-title">Active Relational Tables</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Table Name</th><th>Record Count</th><th>Status</th><th>User-side effect</th></tr></thead>
            <tbody>
                <tr><td><code>topics</code></td><td>{{ $topics->count() }} records</td><td><span class="status success">Active</span></td><td>Dashboard topic cards</td></tr>

                <tr><td><code>quiz_questions</code></td><td>{{ \App\Models\QuizQuestion::whereNotNull('topic_id')->count() }} records</td><td><span class="status success">Active</span></td><td>Topic quiz flow</td></tr>
                <tr><td><code>final_exam</code> (topic_id = null)</td><td>{{ \App\Models\QuizQuestion::whereNull('topic_id')->count() }} records</td><td><span class="status success">Active</span></td><td>Final course examination</td></tr>
            </tbody>
        </table>
    </div>
</section>
@endsection
