@extends('admin.layouts.app')

@section('title', 'User Profile')
@section('kicker', 'User Details')

@section('page_header_actions')
    <a class="btn-primary" href="{{ route('admin.users.index') }}">View Users List</a>
@endsection

@section('content')
@php($canEditProfile = Auth::user()->hasAdminAccess())
<style>
    .user-profile-heading { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem; }
    .user-profile-heading .panel-title { margin:0;overflow-wrap:anywhere; }
    .user-profile-actions { display:flex;align-items:center;flex-wrap:wrap;gap:.5rem;margin-inline-start:auto; }
    #user-profile-edit { display:inline-grid;place-items:center;width:44px;height:44px;padding:0;border:0;border-radius:8px;background:transparent;color:var(--text);cursor:pointer; }
    #user-profile-edit:hover { background:var(--border); }
    #user-profile-edit:focus-visible { outline:2px solid var(--brand-aqua); }
    .user-profile-heading [hidden] { display:none !important; }
    #user-profile-form { container-type:inline-size; }
    .user-profile-fields { display:grid;grid-template-columns:minmax(0,15rem) minmax(0,1fr);gap:1.25rem 2rem;align-items:start; }
    .user-profile-fields .field { min-width:0; }
    .user-profile-picture { display:grid;place-items:center;width:min(100%,13rem);aspect-ratio:1;overflow:hidden;border:1px solid var(--border);border-radius:16px;background:var(--surface-solid);color:var(--text-muted);font-size:.75rem; }
    .user-profile-picture img { width:100%;height:100%;object-fit:cover; }
    .user-profile-picture [hidden], #profile-picture-upload[hidden] { display:none !important; }
    #user-progress-retry[hidden] { display:none !important; }
    .user-subject-progress .data-table { min-width:0; }
    body .user-subject-progress .data-table thead th { background:#48515f;color:#fff; }
    .user-subject-progress .data-table :is(th,td) { padding:.55rem .65rem;overflow-wrap:anywhere; }
    .user-subject-progress .data-table tbody tr:nth-child(odd) td { background:var(--surface-solid); }
    .user-subject-progress .data-table tbody tr:nth-child(even) td { background:color-mix(in srgb,var(--surface-solid) 92%,var(--text)); }
    .user-progress-table { margin-top:1rem; }
    .user-progress-table nav { margin-top:.65rem; }
    .user-subject-progress .subject-progress-table tbody td { background:var(--surface-solid); }
    .user-subject-progress .subject-progress-table .subject-progress-alt td { background:color-mix(in srgb,var(--surface-solid) 92%,var(--text)); }
    .subject-progress-name,.subject-progress-meter { display:flex;align-items:center;gap:.55rem; }
    .subject-progress-meter progress { width:clamp(2.5rem,12vw,9rem);height:.55rem;accent-color:var(--brand-aqua-strong); }
    .subject-progress-expand { flex:none;width:2rem;height:2rem;border:0;border-radius:6px;background:transparent;color:var(--text);cursor:pointer; }
    .subject-progress-expand:hover { background:var(--border); }
    .subject-progress-expand:focus-visible { outline:2px solid var(--brand-aqua); }
    .subject-progress-expand span { display:inline-block; }
    .subject-progress-expand[aria-expanded="true"] span { transform:rotate(90deg); }
    .subject-progress-details[hidden] { display:none; }
    .subject-progress-toolbar { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.65rem; }
    .subject-progress-toolbar h3 { margin:0; }
    .subject-progress-toolbar [data-subject-progress-toggle-all] { margin-inline-start:auto; }
    .subject-progress-details dl { display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,11rem),1fr));gap:.75rem;margin:.25rem 0 .25rem clamp(1.25rem,4vw,2.5rem);padding:.25rem 0 .25rem .8rem;border-inline-start:2px solid var(--border); }
    .subject-progress-details dt { font-weight:700; }
    .subject-progress-details dd { margin:0; }
    @container (max-width:34rem) { .user-profile-fields { grid-template-columns:minmax(0,1fr); } }
</style>
<div class="split-grid">
    <section class="panel">
        <p class="panel-label">Profile</p>
        <div class="user-profile-heading">
            <h2 class="panel-title" id="user-profile-name">{{ $userStats['name'] }}</h2>
            <div class="user-profile-actions">
            @if($canEditProfile)
                <button type="button" id="user-profile-edit" aria-label="Edit profile" title="Edit profile" aria-controls="user-profile-form">
                    <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 3 5 5L8 21H3v-5zM13 6l5 5"/></svg>
                </button>
                <button class="btn btn-primary" type="submit" form="user-profile-form" id="user-profile-save" hidden>Save changes</button>
            @endif
            </div>
        </div>
        <p class="panel-subtitle">Learner account details and institutional affiliation.</p>

        <form id="user-profile-form" method="post" enctype="multipart/form-data" action="{{ route('admin.users.profile.update', $userStats['id']) }}">
        @csrf @method('PATCH')
        <div class="user-profile-fields">
          <div class="user-profile-identity-fields">
            <div class="field">
                <label>Profile picture</label>
                <div class="user-profile-picture">
                    <img id="profile-picture-image" alt="Profile picture" @if($profile['profile_picture_url']) src="{{ $profile['profile_picture_url'] }}" @else hidden @endif>
                    <span id="profile-picture-placeholder" @if($profile['profile_picture_url']) hidden @endif>No photo</span>
                </div>
                @if($canEditProfile)
                    <div id="profile-picture-upload" hidden>
                        <label for="profile-picture-file">Upload profile picture</label>
                        <input id="profile-picture-file" name="profile_picture" type="file" accept="image/jpeg,image/png,image/webp" disabled>
                        <small>JPG, PNG, or WebP. Up to 2 MB.</small>
                    </div>
                @endif
            </div>
            <div class="field">
                <label for="profile-first-name">First name</label>
                <input id="profile-first-name" name="first_name" maxlength="255" required readonly value="{{ $profile['first_name'] }}">
            </div>
            <div class="field">
                <label for="profile-middle-name">Middle name</label>
                <input id="profile-middle-name" name="middle_name" maxlength="255" readonly value="{{ $profile['middle_name'] }}">
            </div>
            <div class="field">
                <label for="profile-last-name">Last name</label>
                <input id="profile-last-name" name="last_name" maxlength="255" readonly value="{{ $profile['last_name'] }}">
            </div>
          </div>
          <div class="user-profile-contact-fields">
            <div class="field">
                <label for="profile-email">Email address (read only)</label>
                <input id="profile-email" type="email" readonly value="{{ $profile['email'] }}">
            </div>
            <div class="field">
                <label for="profile-phone">Phone number</label>
                <input id="profile-phone" name="phone" type="tel" maxlength="50" readonly value="{{ $profile['phone'] }}">
            </div>
            <div class="field">
                <label for="profile-birthdate">Birthdate</label>
                <input id="profile-birthdate" name="birthdate" type="date" readonly value="{{ $profile['birthdate'] }}">
            </div>
            <div class="field">
                <label for="profile-country">Country / Pricing Region</label>
                <select id="profile-country" name="country_code" required disabled>
                    <option value="PH" @selected($profile['country_code'] === 'PH')>Philippines</option>
                    <option value="INTL" @selected($profile['country_code'] === 'INTL')>International</option>
                </select>
            </div>
            <div class="field">
                <label for="profile-affiliation-type">Affiliation type</label>
                <input id="profile-affiliation-type" name="affiliation_type" maxlength="255" readonly value="{{ $profile['affiliation_type'] }}">
            </div>
            <div class="field">
                <label for="profile-affiliation">School / Institution / Company Name</label>
                <input id="profile-affiliation" name="affiliation_name" maxlength="255" readonly value="{{ $profile['affiliation_name'] }}">
            </div>
          </div>
        </div>
        <p id="user-profile-error" role="alert" style="color:var(--wrong)" hidden></p>
        </form>
    </section>

    <aside class="panel">
        <p class="panel-label">Account controls</p>
        <h2 class="panel-title">Status and Access</h2>
        <p class="panel-subtitle">Manage user active status and review progress.</p>

        <div class="list-stack">
            <div class="list-item">
                <strong>Current status</strong>
                <span class="status {{ $userStats['status'] === 'Active' ? 'success' : 'neutral' }}">{{ $userStats['status'] }}</span>
            </div>
            <div class="list-item">
                <strong>Verified Certification</strong>
                <strong>{{ $userStats['certificate'] }}</strong>
            </div>
            
            <form action="{{ route('admin.users.toggle', $userStats['id']) }}" method="POST" style="width: 100%;">
                @csrf
                <button class="btn {{ $userStats['status'] === 'Active' ? 'btn-danger' : 'btn-primary' }}" type="submit" style="width: 100%;">
                    {{ $userStats['status'] === 'Active' ? 'Deactivate account' : 'Activate account' }}
                </button>
            </form>
        </div>
    </aside>
</div>

<section class="panel user-subject-progress" style="margin-top:1rem" aria-labelledby="user-subject-progress-title">
    <p class="panel-label">Progress</p>
    <h2 class="panel-title" id="user-subject-progress-title">Student Progress</h2>
    <div class="field">
        <label for="user-progress-course">Enrolled course</label>
        <select id="user-progress-course" data-progress-url="{{ route('admin.users.show', $userStats['id']) }}" @disabled($enrolledCourses->isEmpty())>
            <option value="">Select a course</option>
            @foreach($enrolledCourses as $enrollment)
                <option value="{{ $enrollment['batch_id'] }}:{{ $enrollment['course_id'] }}">{{ $enrollment['label'] }}</option>
            @endforeach
        </select>
    </div>
    <p id="user-progress-message" class="muted" role="status">{{ $enrolledCourses->isEmpty() ? 'This learner has no active course enrollments.' : 'Select an enrolled course to load progress.' }}</p>
    <button type="button" class="btn-ghost" id="user-progress-retry" hidden>Retry</button>
    <div id="user-progress-results"></div>
</section>
<script>
(() => {
    const course = document.getElementById('user-progress-course');
    const results = document.getElementById('user-progress-results');
    const message = document.getElementById('user-progress-message');
    const retry = document.getElementById('user-progress-retry');
    let pending, lastUrl;
    const load = async url => {
        pending?.abort();
        const request = new AbortController();
        pending = request;
        lastUrl = url;
        message.textContent = 'Loading progress…';
        retry.hidden = true;
        results.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, {
                headers: {Accept:'text/html','X-Requested-With':'XMLHttpRequest','X-Admin-Table':'user-course-progress'},
                credentials: 'same-origin', signal: request.signal,
            });
            if (!response.ok) throw new Error();
            const html = new DOMParser().parseFromString(await response.text(), 'text/html');
            const fragment = html.querySelector('[data-user-progress-tables]');
            if (!fragment) throw new Error();
            if (request.signal.aborted) return;
            results.replaceChildren(fragment);
            message.textContent = '';
        } catch {
            if (!request.signal.aborted) {
                message.textContent = 'Unable to load progress. Please try again.';
                retry.hidden = false;
            }
        } finally {
            if (!request.signal.aborted) results.removeAttribute('aria-busy');
        }
    };
    course.addEventListener('change', () => {
        pending?.abort();
        results.replaceChildren();
        results.removeAttribute('aria-busy');
        retry.hidden = true;
        if (!course.value) {
            message.textContent = 'Select an enrolled course to load progress.';
            return;
        }
        const [batchId, courseId] = course.value.split(':');
        const url = new URL(course.dataset.progressUrl);
        url.search = new URLSearchParams({batch_id:batchId,course_id:courseId});
        load(url.href);
    });
    const setSubjectExpanded = (button, expanded) => {
        const details = document.getElementById(button.getAttribute('aria-controls'));
        if (!details) return;
        details.hidden = !expanded;
        button.setAttribute('aria-expanded', String(expanded));
    };
    results.addEventListener('click', event => {
        const expand = event.target.closest('.subject-progress-expand');
        const toggleAll = event.target.closest('[data-subject-progress-toggle-all]');
        if (expand || toggleAll) {
            const controls = [...results.querySelectorAll('.subject-progress-expand')];
            const isExpanded = button => button.getAttribute('aria-expanded') === 'true';
            if (expand) setSubjectExpanded(expand, !isExpanded(expand));
            else {
                const expandAll = !controls.every(isExpanded);
                controls.forEach(button => setSubjectExpanded(button, expandAll));
            }
            const allButton = results.querySelector('[data-subject-progress-toggle-all]');
            if (allButton) allButton.textContent = controls.every(isExpanded) ? 'Collapse all' : 'Expand all';
            return;
        }
        const link = event.target.closest('.pagination a');
        if (!link) return;
        event.preventDefault();
        load(link.href);
    });
    retry.addEventListener('click', () => load(lastUrl));
})();
</script>
@if($canEditProfile)
<script>
(() => {
const form = document.getElementById('user-profile-form');
const button = document.getElementById('user-profile-save');
const edit = document.getElementById('user-profile-edit');
const pictureInput = document.getElementById('profile-picture-file');
const setEditing = enabled => {
    form.querySelectorAll('input[name]:not([type="hidden"]):not([type="file"])').forEach(field => { field.readOnly = !enabled; });
    document.getElementById('profile-country').disabled = !enabled;
    document.getElementById('profile-picture-upload').hidden = !enabled;
    pictureInput.disabled = !enabled;
    edit.hidden = enabled;
    button.hidden = !enabled;
    (enabled ? document.getElementById('profile-first-name') : edit).focus({preventScroll:true});
};
edit.addEventListener('click', () => setEditing(true));
form.addEventListener('submit', async event => {
    event.preventDefault();
    const error = document.getElementById('user-profile-error');
    if (button.hidden || button.disabled) return;
    const data = new FormData(form);
    if (!pictureInput.files.length) data.delete('profile_picture');
    button.disabled = true;
    error.hidden = true;
    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {Accept:'application/json'},
            body: data,
        });
        const result = await response.json();
        if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message);
        document.getElementById('user-profile-name').textContent = result.name;
        if (pictureInput.files.length && result.profile_picture_url) {
            const image = document.getElementById('profile-picture-image');
            image.src = result.profile_picture_url + '?v=' + Date.now();
            image.hidden = false;
            document.getElementById('profile-picture-placeholder').hidden = true;
        }
        pictureInput.value = '';
        setEditing(false);
        showAdminToast(result.message, 'success');
    } catch (failure) {
        error.textContent = failure.message || 'Unable to save the profile.';
        error.hidden = false;
    } finally {
        button.disabled = false;
    }
});
})();
</script>
@endif
@endsection
