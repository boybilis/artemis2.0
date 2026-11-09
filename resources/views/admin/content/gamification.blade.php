@extends('admin.layouts.app')

@section('title', 'Course Gamification')
@section('kicker', 'Content Management')

@section('content')
<section class="panel">
    <h2 class="panel-title">Review goals and rewards</h2>
    <p>Configure points and badge milestones for each master course. These settings are saved for setup; learner rewards are not awarded or displayed yet.</p>
    @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
    @if($errors->any())
        <ul role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    @endif
    @if($courses->isNotEmpty())
        <form method="GET" action="{{ route('admin.gamification.index') }}">
            <label for="gamification-course">Master course</label>
            <select id="gamification-course" name="course" required>
                @foreach($courses as $option)
                    <option value="{{ $option->id }}" @selected($course?->id === $option->id)>{{ $option->title }}</option>
                @endforeach
            </select>
            <button class="btn-ghost" type="submit">Load settings</button>
        </form>
    @else
        <p>Add a master course before configuring rewards.</p>
    @endif
    @if($course)
        <form method="POST" action="{{ route('admin.gamification.update', $course) }}" style="margin-top:1.25rem;display:grid;gap:1rem">
            @csrf @method('PUT')
            <h3>{{ $course->title }}</h3>
            @foreach(['enabled' => 'Enable gamification', 'points_enabled' => 'Enable points', 'badges_enabled' => 'Enable badges'] as $field => $label)
                <input type="hidden" name="{{ $field }}" value="0">
                <label><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $settings[$field] ?? ($field !== 'enabled')))>{{ $label }}</label>
            @endforeach
            <p>Set a value to 0 to turn off that reward. Badge thresholds are the number of qualifying completions.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,15rem),1fr));gap:1rem">
                @foreach($activities as $key => [$label, $points, $badge])
                    <fieldset class="panel" style="min-width:0">
                        <legend>{{ $label }}</legend>
                        <label for="points-{{ $key }}">Points</label>
                        <input id="points-{{ $key }}" type="number" name="points[{{ $key }}]" min="0" max="10000" required value="{{ old('points.'.$key, $settings['points'][$key] ?? $points) }}">
                        <label for="badge-{{ $key }}">Badge milestone</label>
                        <input id="badge-{{ $key }}" type="number" name="badges[{{ $key }}]" min="0" max="1000" required value="{{ old('badges.'.$key, $settings['badges'][$key] ?? $badge) }}">
                    </fieldset>
                @endforeach
            </div>
            <div><button class="btn-primary" type="submit">Save settings</button></div>
        </form>
    @endif
</section>
@endsection
