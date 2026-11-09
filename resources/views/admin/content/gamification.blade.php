@extends('admin.layouts.app')

@section('title', 'Course Gamification')
@section('kicker', 'Content Management')

@section('content')
<style>
    .gamification-settings { color:var(--text); }
    .gamification-settings p { line-height:1.5; }
    .gamification-settings .gamification-help { color:var(--text-muted); }
    .gamification-picker { display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:end;gap:.75rem;max-width:48rem; }
    .gamification-form,.gamification-field { display:grid;gap:.5rem;min-width:0; }
    .gamification-form { margin-top:1.5rem;gap:1rem; }
    .gamification-grid,.gamification-toggles { display:grid;grid-template-columns:1fr;gap:.85rem; }
    .gamification-toggles label,.gamification-reward { background:var(--surface-solid);border:1px solid var(--border);border-radius:12px;padding:1rem;min-width:0; }
    .gamification-toggles label { display:flex;align-items:center;gap:.65rem;min-height:44px;cursor:pointer; }
    .gamification-toggles input { width:1.15rem;height:1.15rem;flex:none;accent-color:var(--brand-aqua-strong); }
    .gamification-reward { display:grid;align-content:start;gap:.85rem; }
    .gamification-reward h4,.gamification-form h3 { margin:0;overflow-wrap:anywhere; }
    .gamification-field label { font-size:.85rem;font-weight:700; }
    .gamification-field :is(select,input) { box-sizing:border-box;width:100%;min-width:0;min-height:44px;padding:.6rem .75rem;border:1px solid var(--border);border-radius:8px;background:var(--surface-solid);color:var(--text);font:inherit; }
    .gamification-settings :is(input,select,button):focus-visible { outline:2px solid var(--brand-aqua);outline-offset:2px; }
    .gamification-settings button { min-height:44px;margin:0; }
    @media(min-width:576px){.gamification-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(min-width:768px){.gamification-toggles{grid-template-columns:repeat(3,minmax(0,1fr))}}
    @media(min-width:1200px){.gamification-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}
    @media(max-width:575px){.gamification-picker{grid-template-columns:1fr}.gamification-settings button{width:100%}}
</style>
<section class="panel gamification-settings">
    <h2 class="panel-title">Review goals and rewards</h2>
    <p class="gamification-help">Configure points and badge milestones for each master course. These settings are saved for setup; learner rewards are not awarded or displayed yet.</p>
    @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
    @if($errors->any())
        <ul role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    @endif
    @if($courses->isNotEmpty())
        <form class="gamification-picker" method="GET" action="{{ route('admin.gamification.index') }}">
            <div class="gamification-field">
            <label for="gamification-course">Master course</label>
            <select id="gamification-course" name="course" required>
                @foreach($courses as $option)
                    <option value="{{ $option->id }}" @selected($course?->id === $option->id)>{{ $option->title }}</option>
                @endforeach
            </select>
            </div>
            <button class="btn-ghost" type="submit">Load settings</button>
        </form>
    @else
        <p>Add a master course before configuring rewards.</p>
    @endif
    @if($course)
        <form class="gamification-form" method="POST" action="{{ route('admin.gamification.update', $course) }}">
            @csrf @method('PUT')
            <h3>{{ $course->title }}</h3>
            <div class="gamification-toggles">
            @foreach(['enabled' => 'Enable gamification', 'points_enabled' => 'Enable points', 'badges_enabled' => 'Enable badges'] as $field => $label)
                <input type="hidden" name="{{ $field }}" value="0">
                <label><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $settings[$field] ?? ($field !== 'enabled')))>{{ $label }}</label>
            @endforeach
            </div>
            <p class="gamification-help">Set a value to 0 to turn off that reward. Badge thresholds are the number of qualifying completions.</p>
            <div class="gamification-grid">
                @foreach($activities as $key => [$label, $points, $badge, $description])
                    <section class="gamification-reward" aria-labelledby="reward-{{ $key }}">
                        <h4 id="reward-{{ $key }}">{{ $label }}</h4>
                        <p class="gamification-help" style="margin:0;font-size:.85rem">{{ $description }}</p>
                        <div class="gamification-field">
                        <label for="points-{{ $key }}">Points</label>
                        <input id="points-{{ $key }}" type="number" name="points[{{ $key }}]" min="0" max="10000" required value="{{ old('points.'.$key, $settings['points'][$key] ?? $points) }}">
                        </div>
                        <div class="gamification-field">
                        <label for="badge-{{ $key }}">Badge milestone</label>
                        <input id="badge-{{ $key }}" type="number" name="badges[{{ $key }}]" min="0" max="1000" required value="{{ old('badges.'.$key, $settings['badges'][$key] ?? $badge) }}">
                        </div>
                    </section>
                @endforeach
            </div>
            <div><button class="btn-primary" type="submit">Save settings</button></div>
        </form>
    @endif
</section>
@endsection
