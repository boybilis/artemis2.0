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
                <strong>Learning progress</strong>
                <div class="progress-track"><div class="progress-fill" style="width: {{ $userStats['progress'] }}%;"></div></div>
                <span class="muted">{{ $userStats['progress'] }}% complete</span>
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

<div class="page-grid two" style="margin-top: 18px;">
    <section class="panel">
        <p class="panel-label">Progress</p>
        <h2 class="panel-title">Topic Completion</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Topic</th><th>Status</th><th>Best quiz score</th></tr></thead>
                <tbody>
                    @foreach ($topicsProgress as $topic)
                        <tr>
                            <td><strong>{{ $topic['title'] }}</strong></td>
                            <td><span class="status {{ $topic['completed'] === 'Completed' ? 'success' : 'neutral' }}">{{ $topic['completed'] }}</span></td>
                            <td>{{ $topic['score'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <div class="list-stack">
        <section class="panel" style="padding: 15px;">
            <p class="panel-label">History</p>
            <h2 class="panel-title">Final Exam Attempts</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Date</th><th>Score</th><th>Result</th></tr></thead>
                    <tbody>
                        @forelse ($examLogs as $row)
                            <tr>
                                <td>{{ $row['date'] }}</td>
                                <td>{{ $row['score'] }}</td>
                                <td><span class="status {{ $row['status'] === 'Passed' ? 'success' : 'danger' }}">{{ $row['status'] }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="muted">No final exam attempts recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel" style="padding: 15px; margin-top: 10px;">
            <p class="panel-label">Finances</p>
            <h2 class="panel-title">Vouchers Purchased</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Code</th><th>Price</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($voucherLogs as $v)
                            <tr>
                                <td><code>{{ $v['code'] }}</code></td>
                                <td>{{ $v['price'] }}</td>
                                <td><span class="status {{ $v['status'] === 'Redeemed' ? 'success' : 'info' }}">{{ $v['status'] }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="muted">No vouchers purchased.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
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
