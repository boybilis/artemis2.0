@extends('admin.layouts.app')

@section('title', 'User Management')
@section('kicker', 'Accounts')

@section('content')
<style>
    .role-form { display: flex; align-items: center; gap: 0.5rem; margin: 0; }
    .role-select { background: rgba(255,255,255,0.05); border: 1.5px solid var(--border); color: var(--text); padding: 0.3rem 0.5rem; border-radius: 8px; font-size: 0.8rem; outline: none; cursor: pointer; transition: border-color 0.2s; }
    .role-select.changed { border-color: var(--accent); }
    body.light-mode .role-select { background: rgba(0,0,0,0.05); }
    .actions { display: flex; gap: 0.5rem; justify-content: flex-end; }
    
    .floating-save-toast { position: fixed; bottom: -100px; left: 50%; transform: translateX(-50%); background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 1rem 1.5rem; display: flex; align-items: center; gap: 1.5rem; box-shadow: 0 20px 40px rgba(0,0,0,0.4); z-index: 100; transition: bottom 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
    .floating-save-toast.visible { bottom: 2rem; }
    .toast-message { font-size: 0.9rem; font-weight: 500; color: var(--text); }
    body.light-mode .floating-save-toast { box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
</style>

<div class="stat-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 2rem;">
    <article class="metric-card">
        <p class="metric-label">All users</p>
        <p class="metric-value">{{ number_format($stats['total']) }}</p>
        <p class="metric-note">Total platform accounts</p>
    </article>
    <article class="metric-card">
        <p class="metric-label">Active users</p>
        <p class="metric-value" style="background: var(--correct); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">{{ number_format($stats['active']) }}</p>
        <p class="metric-note">Currently active</p>
    </article>
    <article class="metric-card">
        <p class="metric-label">Inactive users</p>
        <p class="metric-value" style="background: var(--text-muted); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">{{ number_format($stats['inactive']) }}</p>
        <p class="metric-note">Dormant or disabled</p>
    </article>
</div>

<section class="panel">
    <div class="toolbar" style="margin-bottom: 1.5rem; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 1rem;">
        <div>
            <p class="panel-label">Directory</p>
            <h2 class="panel-title">All Users</h2>
        </div>
        <form action="{{ route('admin.users.index') }}" method="GET" class="toolbar-group" style="gap: 0.5rem; display: flex;">
            <input type="search" name="search" placeholder="Search name, email..." value="{{ request('search') }}" style="background: rgba(255,255,255,0.02); border: 1.5px solid var(--border); color: var(--text); padding: 0.55rem 1rem; border-radius: 8px; font-family: inherit; font-size: 0.85rem; outline: none; width: 220px;">
            @if(request()->anyFilled(['search']))
                <a href="{{ route('admin.users.index') }}" class="btn-ghost" style="padding: 0.55rem 1rem; font-size: 0.85rem; display: inline-flex; align-items: center; justify-content: center;">Clear</a>
            @endif
        </form>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Affiliation</th>
                    <th>Role</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td style="font-weight: 600;">{{ $user['name'] }}</td>
                        <td style="color: var(--text-muted);">{{ $user['email'] }}</td>
                        <td>{{ $user['affiliation'] }}</td>
                        <td>
                            @if(Auth::user()->is_admin || trim(strtolower(Auth::user()->role)) === 'admin')
                                <form action="{{ route('admin.users.role', $user['id']) }}" method="POST" class="role-form ajax-role-form">
                                    @csrf
                                    <select name="role" class="role-select" data-original="{{ strtolower($user['role']) }}">
                                        <option value="student" {{ strtolower($user['role']) === 'student' ? 'selected' : '' }}>Student</option>
                                        <option value="instructor" {{ strtolower($user['role']) === 'instructor' ? 'selected' : '' }}>Instructor</option>
                                        <option value="admin" {{ strtolower($user['role']) === 'admin' ? 'selected' : '' }}>Admin</option>
                                    </select>
                                </form>
                            @else
                                {{ ucfirst($user['role']) }}
                            @endif
                        </td>
                        <td style="text-align: right;">
                            <div class="actions">
                                <form action="{{ route('admin.users.toggle', $user['id']) }}" method="POST" style="margin: 0;">
                                    @csrf
                                    <button class="btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem; {{ $user['status'] === 'Active' ? 'background: rgba(239,68,68,0.1); color: var(--wrong); border: 1px solid rgba(239,68,68,0.2);' : 'background: rgba(16,185,129,0.1); color: var(--correct); border: 1px solid rgba(16,185,129,0.2);' }}" type="submit">
                                        {{ $user['status'] === 'Inactive' ? 'Activate' : 'Disable' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            No users found matching your filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <div style="padding: 1.5rem; border-top: 1px solid var(--border);">
        {{ $users->appends(request()->query())->links('pagination::bootstrap-4') }}
    </div>
</section>

<!-- Floating Save Toast -->
<div class="floating-save-toast" id="saveToast">
    <div class="toast-message">You have unsaved role changes.</div>
    <div style="display: flex; gap: 0.5rem;">
        <button class="btn-ghost" onclick="cancelChanges()" style="padding: 0.5rem 1rem;">Discard</button>
        <button class="btn-primary" onclick="saveChanges()" id="toastSaveBtn" style="padding: 0.5rem 1rem; display: flex; align-items: center; gap: 0.5rem;">
            Save Changes
        </button>
    </div>
</div>

<script>
    let changedForms = new Set();
    const saveToast = document.getElementById('saveToast');
    const toastSaveBtn = document.getElementById('toastSaveBtn');
    const roleSelects = document.querySelectorAll('.role-select');

    roleSelects.forEach(select => {
        select.addEventListener('change', function() {
            const form = this.closest('form');
            if (this.value !== this.dataset.original) {
                this.classList.add('changed');
                changedForms.add(form);
            } else {
                this.classList.remove('changed');
                changedForms.delete(form);
            }
            
            if (changedForms.size > 0) {
                saveToast.classList.add('visible');
            } else {
                saveToast.classList.remove('visible');
            }
        });
    });

    function cancelChanges() {
        roleSelects.forEach(select => {
            select.value = select.dataset.original;
            select.classList.remove('changed');
        });
        changedForms.clear();
        saveToast.classList.remove('visible');
    }

    async function saveChanges() {
        if (changedForms.size === 0) return;
        
        toastSaveBtn.innerHTML = 'Saving...';
        toastSaveBtn.disabled = true;

        const promises = [];
        changedForms.forEach(form => {
            promises.push(
                fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
            );
        });

        try {
            await Promise.all(promises);
            window.location.reload();
        } catch (error) {
            alert('Error saving changes. Please try again.');
            toastSaveBtn.innerHTML = 'Save Changes';
            toastSaveBtn.disabled = false;
        }
    }
</script>
@endsection
