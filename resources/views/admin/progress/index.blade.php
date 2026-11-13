@extends('admin.layouts.app')

@section('title', 'Progress')
@section('kicker', 'Learning Records')

@section('content')
    @include('admin.partials.student-progress-card', [
        'progressUrl' => route('admin.progress.index'),
        'studentSearchUrl' => route('admin.progress.students'),
        'enrolledCourses' => collect(),
    ])
@endsection
