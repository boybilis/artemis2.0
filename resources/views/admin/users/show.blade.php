@extends('admin.layouts.app')

@section('title', 'User Profile')
@section('kicker', 'User Details')

@section('header_actions')
    <a class="btn btn-muted" href="{{ route('admin.users.index') }}">Back to users</a>
    <button class="btn btn-primary" type="button">Save changes</button>
@endsection

@section('content')
<div class="split-grid">
    <section class="panel">
        <p class="panel-label">Profile</p>
        <h2 class="panel-title">{{ $userStats['name'] }}</h2>
        <p class="panel-subtitle">Learner account details and institutional affiliation.</p>

        <div class="form-grid">
            <div class="field">
                <label>First name</label>
                <input type="text" readonly value="{{ $userStats['first_name'] }}">
            </div>
            <div class="field">
                <label>Last name</label>
                <input type="text" readonly value="{{ $userStats['last_name'] }}">
            </div>
            <div class="field">
                <label>Email address</label>
                <input type="email" readonly value="{{ $userStats['email'] }}">
            </div>
            <div class="field">
                <label>Phone number</label>
                <input type="text" readonly value="{{ $userStats['phone'] }}">
            </div>
            <div class="field">
                <label>Birthdate</label>
                <input type="text" readonly value="{{ $userStats['birthdate'] }}">
            </div>
            <div class="field">
                <label>Affiliation Type</label>
                <input type="text" readonly value="{{ ucfirst($userStats['affiliation_type']) }}">
            </div>
            <div class="field full">
                <label>School / Institution / Company Name</label>
                <input type="text" readonly value="{{ $userStats['affiliation'] }}">
            </div>
        </div>
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
@endsection
