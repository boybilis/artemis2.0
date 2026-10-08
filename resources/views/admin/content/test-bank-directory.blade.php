@extends('admin.layouts.app')

@section('title', 'Test Banks')
@section('kicker', 'Test Bank Management')
@section('toast_notifications', 'true')

@section('header_actions')
<button type="button" class="btn-primary" onclick="openDirectoryBankForm()" {{ $courses->isEmpty() ? 'disabled' : '' }}>Create Test Bank</button>
@endsection

@section('content')
<section class="panel">
    <p class="panel-label">TEST BANKS</p>
    <h2 class="panel-title">Test Bank Catalogs</h2>
    <p class="panel-subtitle">Create independently named Test Banks linked to a master course's subjects and shared question bank.</p>
    <div class="test-bank-directory">
        @forelse($testBanks as $testBank)
            <article class="test-bank-directory-card">
                <div><h3>{{ $testBank->title }}</h3><p class="muted">{{ $testBank->code }} · {{ $testBank->course?->title }}</p><p class="muted">{{ $testBank->status === 'active' ? 'Active' : 'Inactive' }} · {{ $testBank->access_days }} days · ₱{{ number_format($testBank->price, 2) }}</p></div>
                <div class="directory-actions"><a class="btn-primary" href="{{ route('admin.content.test-banks.manage', [$testBank->course_id, $testBank]) }}">Manage Test Bank</a><button type="button" class="btn-ghost" onclick='openDirectoryBankForm(@json($testBank))'>Edit</button></div>
            </article>
        @empty
            <p class="muted">No Test Banks created yet. {{ $courses->isEmpty() ? 'Create a master course first in Course Management.' : 'Select Create Test Bank to get started.' }}</p>
        @endforelse
    </div>
    {{ $testBanks->links() }}
</section>
<div id="directoryBankModal" class="admin-modal">
    <form id="directoryBankForm" method="POST" class="admin-modal-content" style="max-width:760px">
        @csrf
        <input id="directoryBankMethod" type="hidden" name="_method" value="POST">
        <div class="admin-modal-header"><h3 id="directoryBankHeading" class="admin-modal-title">Create Test Bank</h3><button type="button" class="admin-modal-close" onclick="closeDirectoryBankForm()">&times;</button></div>
        <div class="admin-modal-body"><div class="form-grid">
            <div class="field full"><label for="directoryBankCourse">Master course</label><select id="directoryBankCourse" class="form-control" required><option value="">Select master course</option>@foreach($courses as $course)<option value="{{ $course->id }}" data-store-url="{{ route('admin.content.test-banks.store', $course) }}">{{ $course->title }} (#{{ $course->id }})</option>@endforeach</select><small class="muted">Supplies subjects and shared questions. The linked course is preserved when editing.</small></div>
            <div class="field"><label>Test Bank display name</label><input class="form-control" name="title" required maxlength="255"></div>
            <div class="field"><label>Catalog code</label><input class="form-control" name="code" required maxlength="80"></div>
            <div class="field full"><label>Description</label><textarea class="form-control" name="description" rows="3"></textarea></div>
            <div class="field"><label>Price (PHP)</label><input class="form-control" name="price" type="number" min="0" step=".01" required></div>
            <div class="field"><label>Price (USD, optional)</label><input class="form-control" name="usd_price" type="number" min="0" step=".01"></div>
            <div class="field"><label>Access duration (days)</label><input class="form-control" name="access_days" type="number" min="1" max="3650" required value="30"></div>
        </div></div>
        <div class="admin-modal-footer"><button type="button" class="btn-ghost" onclick="closeDirectoryBankForm()">Cancel</button><button type="submit" class="btn-primary">Save Test Bank</button></div>
    </form>
</div>
<script>
const directoryForm = document.getElementById('directoryBankForm');
const directoryCourse = document.getElementById('directoryBankCourse');
function updateDirectoryStoreUrl() { directoryForm.action = directoryCourse.selectedOptions[0]?.dataset.storeUrl || ''; }
directoryCourse.addEventListener('change', updateDirectoryStoreUrl);
function openDirectoryBankForm(item = null) {
    directoryForm.reset();
    directoryCourse.disabled = Boolean(item);
    directoryCourse.value = item ? String(item.course_id) : '';
    document.getElementById('directoryBankMethod').value = item ? 'PUT' : 'POST';
    document.getElementById('directoryBankHeading').textContent = item ? 'Edit Test Bank' : 'Create Test Bank';
    updateDirectoryStoreUrl();
    if (item) {
        directoryForm.action += '/' + Number(item.id);
        ['title', 'code', 'description', 'price', 'usd_price', 'access_days'].forEach(key => directoryForm.elements.namedItem(key).value = item[key] ?? '');
    }
    document.getElementById('directoryBankModal').classList.add('open');
}
function closeDirectoryBankForm() { document.getElementById('directoryBankModal').classList.remove('open'); }
@if(session('success')) document.addEventListener('DOMContentLoaded', () => showAdminToast(@json(session('success')), 'success')); @endif
@if($errors->any()) document.addEventListener('DOMContentLoaded', () => showAdminToast(@json(implode(' ', $errors->all())), 'error', 6500)); @endif
</script>
<style>
    .directory-actions{display:flex;gap:.65rem;align-items:center}@media(max-width:600px){.directory-actions{flex-direction:column;align-items:stretch}}
    .test-bank-directory{display:grid;gap:1rem;margin:1.25rem 0}.test-bank-directory-card{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1.25rem;border:1px solid var(--border);border-radius:14px;background:var(--surface)}.test-bank-directory-card h3{margin:0;font-size:1.05rem}.test-bank-directory-card p{margin:.4rem 0 0;font-size:.8rem}.test-bank-directory-card a{text-decoration:none;white-space:nowrap}@media(max-width:600px){.test-bank-directory-card{align-items:stretch;flex-direction:column}.test-bank-directory-card a{text-align:center}}
</style>
@endsection
