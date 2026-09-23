@extends('admin.layouts.app')

@section('title', 'Test Bank Catalogs')
@section('kicker', 'Paid Test Products')

@section('header_actions')
    <button type="button" class="btn-primary" onclick="openTestBankForm()">Add Test Bank</button>
@endsection

@section('content')
<style>
    .test-bank-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem}.test-bank-admin-card{display:flex;flex-direction:column;gap:.8rem;padding:1.25rem;border:1px solid var(--border);border-top:4px solid #f56600;border-radius:14px;background:var(--surface)}.test-bank-admin-card h3{margin:0}.test-bank-admin-card p{margin:0;color:var(--text-muted);font-size:.82rem;line-height:1.55}.test-bank-admin-meta{display:grid;grid-template-columns:1fr 1fr;gap:.65rem}.test-bank-admin-meta span{display:flex;flex-direction:column;gap:.15rem;padding:.65rem;border-radius:9px;background:rgba(47,103,143,.07);font-size:.72rem}.test-bank-admin-meta small{color:var(--text-muted);font-size:.6rem;text-transform:uppercase;letter-spacing:.08em}.test-bank-admin-actions{display:flex;gap:.5rem;margin-top:auto}.test-bank-admin-actions>*{flex:1}.test-bank-admin-actions form button{width:100%}@media(max-width:600px){.test-bank-admin-meta{grid-template-columns:1fr}}
</style>

<div class="toolbar">
    <div>
        <a href="{{ route('admin.content.index') }}" class="btn-ghost" style="display:inline-flex;margin-bottom:1rem;text-decoration:none">← Back to Courses</a>
        <p class="panel-label">{{ $course->title }}</p>
        <h2 class="panel-title">Course Test Bank Catalogs</h2>
        <p class="panel-subtitle">Create separately paid question-bank products for this master course.</p>
    </div>
</div>

@if(session('success'))<div class="notice" style="color:var(--correct)">{{ session('success') }}</div>@endif
@if($errors->any())<div class="notice" style="color:var(--wrong)">{{ $errors->first() }}</div>@endif

<section class="panel">
    <div class="test-bank-grid">
        @forelse($testBanks as $testBank)
            <article class="test-bank-admin-card">
                <div><span class="status {{ $testBank->status === 'active' ? 'success' : 'danger' }}">{{ $testBank->status === 'active' ? 'Active' : 'Inactive' }}</span></div>
                <div><small style="color:#f56600;font-weight:800;letter-spacing:.08em">{{ $testBank->code }}</small><h3>{{ $testBank->title }}</h3></div>
                <p>{{ $testBank->description ?: 'No description provided.' }}</p>
                <div class="test-bank-admin-meta">
                    <span><small>Price</small><strong>₱{{ number_format($testBank->price, 2) }}</strong></span>
                    <span><small>Access</small><strong>{{ $testBank->access_days }} days</strong></span>
                    <span><small>Master course</small><strong>{{ $course->title }}</strong></span>
                    <span><small>Visibility</small><strong>{{ $testBank->status === 'active' ? 'Learners can view' : 'Hidden' }}</strong></span>
                </div>
                <div class="test-bank-admin-actions">
                    <button type="button" class="btn-ghost" onclick='editTestBank(@json($testBank))'>Edit</button>
                    <form method="POST" action="{{ route('admin.content.test-banks.status', [$course, $testBank]) }}">@csrf<button type="submit" class="btn-ghost">{{ $testBank->status === 'active' ? 'Deactivate' : 'Activate' }}</button></form>
                    <button type="button" class="btn-ghost" style="color:var(--wrong)" onclick="requestDeleteTestBank({{ $testBank->id }}, @js($testBank->title))">Delete</button>
                </div>
            </article>
        @empty
            <p class="muted">No Test Bank catalogs have been created for this course.</p>
        @endforelse
    </div>
</section>

<div id="testBankFormModal" class="admin-modal">
    <form id="testBankForm" method="POST" action="{{ route('admin.content.test-banks.store', $course) }}" class="admin-modal-content" style="max-width:760px">
        @csrf
        <input id="test_bank_method" type="hidden" name="_method" value="POST">
        <div class="admin-modal-header"><div><h3 id="test_bank_form_title" class="admin-modal-title">Add Test Bank</h3><p class="muted" style="margin:.25rem 0 0">{{ $course->title }}</p></div><button type="button" class="admin-modal-close" onclick="closeModal('testBankFormModal')">&times;</button></div>
        <div class="admin-modal-body">
            <div class="form-grid">
                <div class="field"><label>Test Bank title</label><input id="test_bank_title" class="form-control" name="title" required maxlength="255"></div>
                <div class="field"><label>Catalog code</label><input id="test_bank_code" class="form-control" name="code" required maxlength="80" placeholder="DOH-TB-001"></div>
                <div class="field full"><label>Description</label><textarea id="test_bank_description" class="form-control" name="description" rows="4"></textarea></div>
                <div class="field"><label>Price (PHP)</label><input id="test_bank_price" class="form-control" name="price" type="number" min="0" step="0.01" required></div>
                <div class="field"><label>Price (USD, optional display)</label><input id="test_bank_usd_price" class="form-control" name="usd_price" type="number" min="0" step="0.01"></div>
                <div class="field"><label>Learner access duration (days)</label><input id="test_bank_access_days" class="form-control" name="access_days" type="number" min="1" max="3650" required value="30"></div>
            </div>
            <p class="muted" style="font-size:.75rem">New Test Banks are activated immediately. The learner access duration starts separately when each successful subscription is activated.</p>
        </div>
        <div class="admin-modal-footer"><button type="button" class="btn-ghost" onclick="closeModal('testBankFormModal')">Cancel</button><button type="submit" class="btn-primary">Save Test Bank</button></div>
    </form>
</div>

<div id="deleteTestBankModal" class="admin-modal">
    <form id="deleteTestBankForm" method="POST" class="admin-modal-content" style="max-width:460px;text-align:center">
        @csrf @method('DELETE')
        <div class="admin-modal-header" style="justify-content:center"><h3 class="admin-modal-title">Delete Test Bank?</h3></div>
        <div class="admin-modal-body"><p id="delete_test_bank_message" class="muted"></p></div>
        <div class="admin-modal-footer" style="justify-content:center"><button type="button" class="btn-ghost" onclick="closeModal('deleteTestBankModal')">Cancel</button><button type="submit" class="btn-danger">Delete</button></div>
    </form>
</div>

<script>
const testBankStoreUrl=@json(route('admin.content.test-banks.store',$course));
const testBankBaseUrl=@json(url('/admin/content/courses/'.$course->id.'/test-banks'));
function openModal(id){document.getElementById(id)?.classList.add('open')}
function closeModal(id){document.getElementById(id)?.classList.remove('open')}
function openTestBankForm(){const form=document.getElementById('testBankForm');form.reset();form.action=testBankStoreUrl;document.getElementById('test_bank_method').value='POST';document.getElementById('test_bank_access_days').value=30;document.getElementById('test_bank_form_title').textContent='Add Test Bank';openModal('testBankFormModal')}
function editTestBank(item){openTestBankForm();document.getElementById('test_bank_form_title').textContent='Edit Test Bank';document.getElementById('testBankForm').action=`${testBankBaseUrl}/${item.id}`;document.getElementById('test_bank_method').value='PUT';['title','code','description','price','usd_price','access_days'].forEach(key=>document.getElementById(`test_bank_${key}`).value=item[key]??'')}
function requestDeleteTestBank(id,title){document.getElementById('deleteTestBankForm').action=`${testBankBaseUrl}/${id}`;document.getElementById('delete_test_bank_message').textContent=`Delete “${title}”? Existing learner access records for this Test Bank will also be removed.`;openModal('deleteTestBankModal')}
</script>
@endsection
