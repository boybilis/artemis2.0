@extends('admin.layouts.app')

@section('title', 'Subjects')
@section('kicker', 'Course Content')

@section('header_actions')
    <button type="button" class="btn btn-primary" onclick="openSubjectModal()">Add Subject</button>
@endsection

@section('content')
@php($isAdmin = Auth::user()->is_admin || strtolower((string) Auth::user()->role) === 'admin')
<style>
.subject-modal-content{max-width:780px}.subject-form-section{padding:1rem;border:1px solid var(--border);border-radius:12px;background:rgba(255,255,255,.02)}body.light-mode .subject-form-section{background:rgba(0,0,0,.02)}.subject-form-section+.subject-form-section{margin-top:1rem}.subject-form-heading{display:flex;align-items:center;gap:.55rem;margin:0 0 1rem;color:var(--text);font-family:'Outfit',sans-serif;font-size:.95rem;font-weight:700}.subject-form-heading span{display:inline-flex;width:26px;height:26px;align-items:center;justify-content:center;border-radius:8px;background:rgba(124,58,237,.12);color:var(--accent);font-size:.75rem}.subject-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.subject-form-grid .field{min-width:0}.subject-form-grid .field.full{grid-column:1/-1}.subject-modal-content .field{display:flex;flex-direction:column;gap:.45rem}.subject-modal-content .field label{font-size:.78rem;font-weight:700;color:var(--text-muted)}.subject-modal-content .field label .required{color:var(--wrong)}.subject-modal-content .field textarea{width:100%;min-height:96px;box-sizing:border-box;padding:.75rem 1rem;border:1.5px solid var(--border);border-radius:10px;outline:none;background:rgba(255,255,255,.04);color:var(--text);font:inherit;font-size:.875rem;line-height:1.5;resize:vertical;transition:.2s}.subject-modal-content .field textarea:focus{border-color:var(--accent);background:rgba(124,58,237,.05);box-shadow:0 0 0 3px rgba(124,58,237,.1)}body.light-mode .subject-modal-content .field textarea{background:rgba(0,0,0,.04)}.subject-field-help{margin:0;color:var(--text-muted);font-size:.72rem;line-height:1.4}.subject-modal-content .admin-modal-body{padding-top:1.25rem}@media(max-width:700px){.subject-form-grid{grid-template-columns:1fr}.subject-form-grid .field.full{grid-column:auto}.subject-modal-content{max-height:calc(100dvh - 1.5rem)}}
</style>
<div style="margin-bottom:1.5rem;display:flex;align-items:center;gap:1rem;">
    <a href="{{ route('admin.content.index') }}" class="btn-ghost" style="padding:.5rem;color:var(--text-muted);text-decoration:none;">&larr; Back to Courses</a>
    <h2 style="margin:0;font-family:'Outfit',sans-serif;">{{ $course->title }}</h2>
</div>

<div class="tabs">
    <a class="tab active" href="{{ route('admin.content.subjects', $course->id) }}">Subjects</a>
</div>

@if(session('success'))
    <div style="margin:0 0 1rem;padding:.75rem 1rem;border:1px solid rgba(16,185,129,.25);border-radius:8px;background:rgba(16,185,129,.1);color:#10b981;">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div style="margin:0 0 1rem;padding:.75rem 1rem;border:1px solid rgba(239,68,68,.25);border-radius:8px;background:rgba(239,68,68,.1);color:#ef4444;">{{ $errors->first() }}</div>
@endif

<section class="panel">
    <div class="toolbar">
        <div><p class="panel-label">Course subjects</p><h2 class="panel-title">Subject Catalog</h2></div>
        <button type="button" class="btn-primary" onclick="openSubjectModal()">Add Subject</button>
    </div>
    <p class="panel-subtitle">Manage the reusable subjects contained in this master course. Delivery schedules and modality are configured per batch.</p>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Code</th><th>Subject</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($subjects as $subject)
                <tr>
                    <td><strong>{{ $subject->subject_code ?: '—' }}</strong></td>
                    <td><strong>{{ $subject->title }}</strong><small style="display:block;color:var(--text-muted);margin-top:.3rem">{{ $subject->description ?: 'No description' }}</small></td>
                    <td><span class="status {{ $subject->status === 'approved' ? 'success' : 'warning' }}">{{ ucfirst($subject->status) }}</span></td>
                    <td style="white-space:nowrap">
                        <a href="{{ route('admin.content.topics', ['course' => $course->id, 'subject_id' => $subject->id]) }}" class="btn-primary" style="display:inline-flex;padding:.45rem .75rem;text-decoration:none">Manage</a>
                        @if($subject->status === 'pending' && $isAdmin)
                        <form method="POST" action="{{ route('admin.content.subjects.approve', ['course'=>$course->id,'subject'=>$subject->id]) }}" style="display:inline">@csrf<button class="btn-ghost" type="submit" style="color:var(--correct)">Approve</button></form>
                        @endif
                        <button type="button" class="btn-ghost edit-subject-btn"
                            data-id="{{ $subject->id }}" data-code="{{ $subject->subject_code }}" data-title="{{ $subject->title }}"
                            data-description="{{ $subject->description }}" data-order="{{ $subject->sort_order }}">Edit</button>
                        <form method="POST" action="{{ route('admin.content.subjects.destroy', ['course' => $course->id, 'subject' => $subject->id]) }}" style="display:inline" onsubmit="return confirm('Delete this subject?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-ghost" style="color:var(--wrong)">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:2rem">No subjects have been added yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<div id="subjectModal" class="admin-modal">
    <form id="subjectForm" method="POST" action="{{ route('admin.content.subjects.store', $course->id) }}" class="admin-modal-content subject-modal-content">
        @csrf
        <div class="admin-modal-header">
            <h3 id="subjectModalTitle" class="admin-modal-title">Add Subject</h3>
            <button type="button" class="admin-modal-close" onclick="closeSubjectModal()">&times;</button>
        </div>
        <div class="admin-modal-body">
          <section class="subject-form-section">
            <h4 class="subject-form-heading"><span>1</span> Subject Information</h4>
            <div class="subject-form-grid">
                <div class="field">
                    <label for="subject_code">Subject code <span class="required">*</span></label>
                    <input id="subject_code" type="text" name="subject_code" maxlength="50" required placeholder="e.g. NURS-101">
                    <p class="subject-field-help">Use a short, unique code within this course.</p>
                </div>
                <div class="field">
                    <label for="subject_title">Subject title <span class="required">*</span></label>
                    <input id="subject_title" type="text" name="title" maxlength="255" required placeholder="e.g. Medical-Surgical Nursing">
                </div>
                <div class="field full">
                    <label for="subject_description">Description</label>
                    <textarea id="subject_description" name="description" rows="4" maxlength="2000" placeholder="Provide a brief overview of the subject coverage"></textarea>
                </div>
                <div class="field full">
                    <label for="subject_sort_order">Display order</label>
                    <input id="subject_sort_order" type="number" name="sort_order" min="0" placeholder="Automatically placed last when empty">
                    <p class="subject-field-help">Controls the order in which this subject appears in the course.</p>
                </div>
            </div>
          </section>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="btn-ghost" onclick="closeSubjectModal()">Cancel</button>
            <button id="subjectSaveButton" type="submit" class="btn-primary">Add Subject</button>
        </div>
    </form>
</div>

<script>
const subjectModal = document.getElementById('subjectModal');
const subjectForm = document.getElementById('subjectForm');
const subjectStoreUrl = @json(route('admin.content.subjects.store', $course->id));
const subjectUpdateBase = @json(url('/admin/content/courses/' . $course->id . '/subjects'));
function openSubjectModal() {
    subjectForm.reset(); subjectForm.action = subjectStoreUrl;
    document.getElementById('subjectModalTitle').textContent = 'Add Subject';
    document.getElementById('subjectSaveButton').textContent = 'Add Subject';
    subjectModal.classList.add('open');
}
function closeSubjectModal() { subjectModal.classList.remove('open'); }
document.querySelectorAll('.edit-subject-btn').forEach(button => button.addEventListener('click', () => {
    subjectForm.action = `${subjectUpdateBase}/${button.dataset.id}`;
    document.getElementById('subject_code').value = button.dataset.code || '';
    document.getElementById('subject_title').value = button.dataset.title || '';
    document.getElementById('subject_description').value = button.dataset.description || '';
    document.getElementById('subject_sort_order').value = button.dataset.order || 0;
    document.getElementById('subjectModalTitle').textContent = 'Edit Subject';
    document.getElementById('subjectSaveButton').textContent = 'Save Changes';
    subjectModal.classList.add('open');
}));
subjectModal.addEventListener('click', event => { if (event.target === subjectModal) closeSubjectModal(); });
</script>
@endsection
