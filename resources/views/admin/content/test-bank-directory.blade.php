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
                <div><h3>{{ $testBank->title }}</h3><p class="muted">{{ $testBank->code }} · {{ $testBank->course?->title }}</p><p class="muted">{{ $testBank->status === 'active' ? 'Approved' : 'Pending' }} · {{ $testBank->access_days }} days · ₱{{ number_format($testBank->price, 2) }}</p></div>
                <div class="directory-actions"><a class="btn-primary" href="{{ route('admin.content.test-banks.manage', [$testBank->course_id, $testBank]) }}">Manage Test Bank</a><button type="button" class="btn-ghost" onclick='openDirectoryBankForm(@json($testBank))'>Edit</button>
                @if(auth()->user()->is_admin || strtolower(trim(auth()->user()->role)) === 'admin')
                <a class="btn-ghost" href="{{ route('admin.content.test-banks.enrollments', [$testBank->course_id,$testBank]) }}">Enrollments</a>
                <button type="button" class="btn-ghost" style="color:#dc2626;border-color:#dc2626" onclick="document.getElementById('deleteBank{{ $testBank->id }}').classList.add('open')">Delete</button>
                @endif</div>
            </article>
            @if(auth()->user()->is_admin || strtolower(trim(auth()->user()->role)) === 'admin')
            <div id="deleteBank{{ $testBank->id }}" class="admin-modal" role="dialog" aria-modal="true" aria-labelledby="deleteBankTitle{{ $testBank->id }}">
                <form method="POST" action="{{ route('admin.content.test-banks.destroy', [$testBank->course_id,$testBank]) }}" class="admin-modal-content" style="max-width:560px">@csrf @method('DELETE')
                    <div class="admin-modal-header"><h3 id="deleteBankTitle{{ $testBank->id }}" class="admin-modal-title">Delete Test Bank?</h3><button type="button" class="admin-modal-close" aria-label="Close" onclick="document.getElementById('deleteBank{{ $testBank->id }}').classList.remove('open')">&times;</button></div>
                    <div class="admin-modal-body"><p><strong>{{ $testBank->title }}</strong></p><p>This permanently deletes this catalog, its quizzes, learner subscriptions, and exam attempts/results.</p><p>Questions shared with another catalog in the same course will be retained. If this is the last catalog, questions belonging to it will also be deleted.</p><p style="color:#dc2626;font-weight:600">This action cannot be undone.</p></div>
                    <div class="admin-modal-footer"><button type="button" class="btn-ghost" onclick="document.getElementById('deleteBank{{ $testBank->id }}').classList.remove('open')">Cancel</button><button type="submit" class="btn-primary" style="background:#dc2626;color:white">Delete Test Bank</button></div>
                </form>
            </div>
            @endif
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
            <div class="field full"><label>Enrollment options (optional)</label><p class="muted">Add exam/location choices under this single Test Bank. Leave empty for standard enrollment. Deactivate an option to stop new enrollments.</p><input type="hidden" name="enrollment_options_json" id="bankOptionsJson" value="[]"><div id="bankOptionRows"></div><button type="button" class="btn-ghost" id="addBankOption">Add Exam / Location</button></div>
            <div class="field"><label>Price (PHP)</label><input class="form-control" name="price" type="number" min="0" step=".01" required></div>
            <div class="field"><label>Price (USD, optional)</label><input class="form-control" name="usd_price" type="number" min="0" step=".01"></div>
            <div class="field"><label>Access duration (days)</label><input class="form-control" name="access_days" type="number" min="1" max="3650" required value="30"></div>
            <div class="field"><label for="directoryBankStatus">Approval status</label><select id="directoryBankStatus" class="form-control" name="status" required><option value="draft">Pending</option><option value="active">Approved</option></select><small class="muted">Only approved Test Banks are displayed to learners.</small></div>
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
    document.getElementById('bankOptionRows').replaceChildren();
    (item?.enrollment_options || []).forEach(addBankOptionRow);
    directoryCourse.disabled = Boolean(item);
    directoryCourse.value = item ? String(item.course_id) : '';
    document.getElementById('directoryBankMethod').value = item ? 'PUT' : 'POST';
    document.getElementById('directoryBankHeading').textContent = item ? 'Edit Test Bank' : 'Create Test Bank';
    updateDirectoryStoreUrl();
    if (item) {
        directoryForm.action += '/' + Number(item.id);
        ['title', 'code', 'description', 'price', 'usd_price', 'access_days'].forEach(key => directoryForm.elements.namedItem(key).value = item[key] ?? '');
        directoryForm.elements.namedItem('status').value = item.status === 'active' ? 'active' : 'draft';
    }
    document.getElementById('directoryBankModal').classList.add('open');
}
function closeDirectoryBankForm() { document.getElementById('directoryBankModal').classList.remove('open'); }
function addBankOptionRow(option = {}) {
    const row = document.createElement('div');
    row.className = 'bank-option-row';
    row.dataset.optionId = option.id || crypto.randomUUID();
    row.innerHTML = '<input class="form-control" placeholder="Exam name" aria-label="Exam name" maxlength="255" required><input class="form-control" placeholder="Country/location" aria-label="Country or location" maxlength="100" required><label class="bank-option-active"><input type="checkbox"> Active</label><button type="button" class="btn-ghost bank-option-remove" aria-label="Remove enrollment option"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m4 4 8 8M12 4l-8 8"/></svg></button>';
    const inputs = row.querySelectorAll('input');
    inputs[0].value = option.name || ''; inputs[1].value = option.country || ''; inputs[2].checked = option.active ?? true;
    row.querySelector('button').onclick = () => row.remove();
    document.getElementById('bankOptionRows').append(row);
}
document.getElementById('addBankOption').onclick = () => addBankOptionRow();
directoryForm.addEventListener('submit', () => {
    document.getElementById('bankOptionsJson').value = JSON.stringify(Array.from(document.getElementById('bankOptionRows').children).map(row => {
        const inputs = row.querySelectorAll('input');
        return {id:row.dataset.optionId,name:inputs[0].value.trim(),country:inputs[1].value.trim(),active:inputs[2].checked};
    }));
});
@if(session('success')) document.addEventListener('DOMContentLoaded', () => showAdminToast(@json(session('success')), 'success')); @endif
@if($errors->any()) document.addEventListener('DOMContentLoaded', () => showAdminToast(@json(implode(' ', $errors->all())), 'error', 6500)); @endif
</script>
<style>
    #directoryBankForm .bank-option-row{display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1fr) auto 32px;gap:10px;margin:12px 0;align-items:center;font-size:.9rem}
    #directoryBankForm .bank-option-active{display:inline-flex;align-items:center;gap:6px;margin:0;white-space:nowrap;font-size:inherit;font-weight:400;line-height:1.4}
    #directoryBankForm .bank-option-active input[type="checkbox"]{appearance:auto;width:16px;height:16px;min-width:16px;min-height:0;padding:0;margin:0;accent-color:#08a991;box-shadow:none}
    #directoryBankForm .bank-option-remove{display:grid;place-items:center;width:32px;height:32px;min-height:32px;padding:0;border-radius:8px;color:#dc2626;border-color:#e8b5b5;background:transparent}
    #directoryBankForm .bank-option-remove:hover{background:#fef2f2}
    @media(max-width:550px){#directoryBankForm .bank-option-row{grid-template-columns:minmax(0,1fr) auto 32px}#directoryBankForm .bank-option-row>.form-control:first-child{grid-column:1/-1}}
    .directory-actions{display:flex;gap:.65rem;align-items:center}@media(max-width:600px){.directory-actions{flex-direction:column;align-items:stretch}}
    .test-bank-directory{display:grid;gap:1rem;margin:1.25rem 0}.test-bank-directory-card{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1.25rem;border:1px solid var(--border);border-radius:14px;background:var(--surface)}.test-bank-directory-card h3{margin:0;font-size:1.05rem}.test-bank-directory-card p{margin:.4rem 0 0;font-size:.8rem}.test-bank-directory-card a{text-decoration:none;white-space:nowrap}@media(max-width:600px){.test-bank-directory-card{align-items:stretch;flex-direction:column}.test-bank-directory-card a{text-align:center}}
</style>
@endsection
