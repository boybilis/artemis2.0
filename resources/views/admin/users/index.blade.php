@extends('admin.layouts.app')

@section('title', 'User Management')
@section('kicker', 'Accounts')

@section('content')
<style>
    body .users-directory-table thead th, body.light-mode .users-directory-table thead th { background: #4b5563; color: #fff; }
    .users-directory-table tbody tr:nth-child(odd) td { background: #fff; color: #073b63; }
    .users-directory-table tbody tr:nth-child(even) td { background: #f1f3f5; color: #073b63; }
    .users-directory-table .role-select { color: #073b63; }
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

<div data-ajax-table="users"><div class="stat-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 2rem;">
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
        <table class="data-table users-directory-table">
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
                            @if(!Auth::user()->isAssistantAdmin() && Auth::user()->hasAdminAccess())
                                <form action="{{ route('admin.users.role', $user['id']) }}" method="POST" class="role-form ajax-role-form">
                                    @csrf
                                    <select name="role" class="role-select" data-original="{{ strtolower($user['role']) }}">
                                        <option value="student" {{ strtolower($user['role']) === 'student' ? 'selected' : '' }}>Student</option>
                                        <option value="instructor" {{ strtolower($user['role']) === 'instructor' ? 'selected' : '' }}>Instructor</option>
                                        <option value="encoder" {{ strtolower($user['role']) === 'encoder' ? 'selected' : '' }}>Encoder</option>
                                        <option value="staff" {{ strtolower($user['role']) === 'staff' ? 'selected' : '' }}>Staff</option>
                                        <option value="admin" {{ strtolower($user['role']) === 'admin' ? 'selected' : '' }}>Admin</option>
                                        <option value="assistant_admin" {{ in_array(strtolower($user['role']), ['assistant_admin', 'assistant admin']) ? 'selected' : '' }}>Asst. Admin</option>
                                    </select>
                                </form>
                            @else
                                {{ strtolower($user['role']) === 'assistant_admin' ? 'Asst. Admin' : ucfirst($user['role']) }}
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

</div>
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
const saveToast = document.getElementById('saveToast'), toastSaveBtn = document.getElementById('toastSaveBtn');
function syncRoleToast(){saveToast.classList.toggle('visible',changedForms.size>0);}
document.addEventListener('change',event=>{
 if(!event.target.matches('[data-ajax-table="users"] .role-select'))return;
 const select=event.target,form=select.closest('form');
 [...changedForms].filter(item=>item.action===form.action).forEach(item=>changedForms.delete(item));
 if(select.value!==select.dataset.original)changedForms.add(form);
 select.classList.toggle('changed',select.value!==select.dataset.original);syncRoleToast();
});
function cancelChanges(){changedForms.forEach(form=>{const select=form.querySelector('select');select.value=select.dataset.original;select.classList.remove('changed')});changedForms.clear();document.querySelectorAll('.role-select').forEach(select=>{select.value=select.dataset.original;select.classList.remove('changed')});syncRoleToast();}
async function saveChanges(){
 if(!changedForms.size)return;
 toastSaveBtn.disabled=true;toastSaveBtn.textContent='Saving…';
 try{
  const results=await Promise.allSettled([...changedForms].map(async form=>{
   const response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
   const data=await response.json();if(!response.ok)throw new Error(data.message||'Unable to save role.');
   changedForms.delete(form);
  }));
  syncRoleToast();await loadAdminTable('users',location.href,false);
  if(results.some(result=>result.status==='rejected'))throw new Error('Some role changes could not be saved. Please try again.');
  showAdminToast('Roles updated.','success');
 }catch(error){showAdminToast(error.message,'error')}
 finally{toastSaveBtn.disabled=false;toastSaveBtn.textContent='Save Changes';}
}
document.addEventListener('admin-table:updated',event=>{
 if(event.detail.tableId!=='users')return;
 document.querySelectorAll('.role-select').forEach(select=>{
  const form=select.closest('form'),pending=[...changedForms].find(item=>item.action===form.action);
  if(pending){select.value=pending.querySelector('select').value;select.classList.add('changed');changedForms.delete(pending);changedForms.add(form);}
 });
});
let userSearchTimer;
function searchUsers(form){const url=new URL(form.action);url.search=new URLSearchParams(new FormData(form));loadAdminTable('users',url.href);}
document.addEventListener('input',event=>{
 if(!event.target.matches('[data-ajax-table="users"] input[name="search"]'))return;
 const form=event.target.form;clearTimeout(userSearchTimer);userSearchTimer=setTimeout(()=>searchUsers(form),350);
});
document.addEventListener('submit',async event=>{
 const form=event.target;if(!form.closest('[data-ajax-table="users"]'))return;
 event.preventDefault();
 if(form.method.toUpperCase()==='GET'){clearTimeout(userSearchTimer);searchUsers(form);return;}
 if(form.classList.contains('ajax-role-form')){saveChanges();return;}
 const button=form.querySelector('button');if(button)button.disabled=true;
 try{const response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
 const data=await response.json();if(!response.ok)throw new Error(data.message||'Unable to update user.');
 await loadAdminTable('users',location.href,false);showAdminToast(data.message,'success');
 }catch(error){if(button)button.disabled=false;showAdminToast(error.message,'error')}
});
document.addEventListener('click',event=>{const link=event.target.closest('[data-ajax-table="users"] .toolbar a');if(link){event.preventDefault();loadAdminTable('users',link.href);}});
</script>
@endsection
