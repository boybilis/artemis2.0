@extends('admin.layouts.app')

@section('title', 'Subjects')
@section('kicker', 'Course Content')
@section('toast_notifications', 'true')

@section('content')
@php($isAdmin = Auth::user()->hasAdminAccess())
<style>
    .subject-row-actions { display:inline-flex;align-items:stretch;gap:.25rem;vertical-align:middle; }
    .subject-row-actions form { display:flex;margin:0; }
    .subject-row-actions .btn-primary { display:inline-flex;align-items:center;padding:.45rem .75rem;text-decoration:none; }
.subjects-catalog-table{--text:#19344f;--text-muted:#526b7a;--border:#cbd5e1}
body .subjects-catalog-table thead th,body.light-mode .subjects-catalog-table thead th{background:#19344f;color:#fff}
.subjects-catalog-table tbody tr:nth-child(odd) td{background:#fff;color:#19344f}
.subjects-catalog-table tbody tr:nth-child(even) td{background:#f1f3f5;color:#19344f}
.subject-title-row{display:flex;align-items:center;gap:.6rem}.subject-drag-handle{display:inline-flex;flex-shrink:0;align-items:center;justify-content:center;width:30px;height:30px;padding:4px;border:0;border-radius:6px;background:transparent;color:#526b7a;cursor:grab;touch-action:none}.subject-drag-handle:hover{background:#dce8ec;color:#19344f}.subject-drag-handle:active{cursor:grabbing}.subject-drag-handle:focus-visible{outline:2px solid #19344f}.subject-drag-ghost{opacity:.4}.subject-drag-handle:disabled{cursor:wait;opacity:.5}
.subjects-catalog-table .subject-status-toggle{border:0;font-family:inherit;font-size:.75rem;font-weight:600;cursor:pointer;transition:background-color .15s ease,color .15s ease}.subjects-catalog-table .subject-status-toggle:hover,.subjects-catalog-table .subject-status-toggle:focus-visible{background:var(--brand-aqua-strong,#087f93);color:#fff}.subject-status-toggle:focus-visible{outline:3px solid #19344f;outline-offset:3px}
.subject-phase-check{display:flex;align-items:flex-start;gap:.7rem;padding:.85rem;border:1px solid var(--border);border-radius:10px;background:rgba(245,102,0,.06);cursor:pointer}.subject-phase-check input{appearance:auto;-webkit-appearance:checkbox;width:17px!important;height:17px!important;min-width:17px;margin:.12rem 0 0;accent-color:#f47721}.subject-phase-check strong,.subject-phase-check small{display:block}.subject-phase-check small{margin-top:.2rem;color:var(--text-muted);font-size:.7rem;line-height:1.4}
.subject-modal-content{max-width:780px}.subject-form-section{padding:1rem;border:1px solid var(--border);border-radius:12px;background:rgba(255,255,255,.02)}body.light-mode .subject-form-section{background:rgba(0,0,0,.02)}.subject-form-section+.subject-form-section{margin-top:1rem}.subject-form-heading{display:flex;align-items:center;gap:.55rem;margin:0 0 1rem;color:var(--text);font-family:'Outfit',sans-serif;font-size:.95rem;font-weight:700}.subject-form-heading span{display:inline-flex;width:26px;height:26px;align-items:center;justify-content:center;border-radius:8px;background:rgba(17,167,192,.12);color:var(--accent);font-size:.75rem}.subject-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.subject-form-grid .field{min-width:0}.subject-form-grid .field.full{grid-column:1/-1}.subject-modal-content .field{display:flex;flex-direction:column;gap:.45rem}.subject-modal-content .field label{font-size:.78rem;font-weight:700;color:var(--text-muted)}.subject-modal-content .field label .required{color:var(--wrong)}.subject-modal-content .field textarea{width:100%;min-height:96px;box-sizing:border-box;padding:.75rem 1rem;border:1.5px solid var(--border);border-radius:10px;outline:none;background:rgba(255,255,255,.04);color:var(--text);font:inherit;font-size:.875rem;line-height:1.5;resize:vertical;transition:.2s}.subject-modal-content .field textarea:focus{border-color:var(--accent);background:rgba(17,167,192,.05);box-shadow:0 0 0 3px rgba(17,167,192,.1)}body.light-mode .subject-modal-content .field textarea{background:rgba(0,0,0,.04)}.subject-field-help{margin:0;color:var(--text-muted);font-size:.72rem;line-height:1.4}.subject-modal-content .admin-modal-body{padding-top:1.25rem}@media(max-width:700px){.subject-form-grid{grid-template-columns:1fr}.subject-form-grid .field.full{grid-column:auto}.subject-modal-content{max-height:calc(100dvh - 1.5rem)}}
</style>
<div style="margin-bottom:1.5rem;display:flex;align-items:center;gap:1rem;">
    <a href="{{ route('admin.content.index') }}" class="btn-ghost" style="padding:.5rem;color:var(--text-muted);text-decoration:none;">&larr; Back to Courses</a>
    <h2 style="margin:0;font-family:'Outfit',sans-serif;">{{ $course->title }}</h2>
</div>

<section class="panel">
    <div class="toolbar">
        <div><p class="panel-label">Course subjects</p><h2 class="panel-title">Subject Catalog</h2></div>
        <button type="button" class="btn-primary" onclick="openSubjectModal()">Add Subject</button>
    </div>
    <p class="panel-subtitle">Manage the reusable subjects contained in this master course. Delivery schedules and modality are configured per batch.</p>
    <div class="table-wrap">
        <table class="data-table subjects-catalog-table">
            <thead><tr><th>Code</th><th>Subject</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody @if($isAdmin) id="subjects-sortable" data-reorder-url="{{ route('admin.content.subjects.reorder', $course->id) }}" data-csrf="{{ csrf_token() }}" @endif>
            @forelse($subjects as $subject)
                <tr data-subject-id="{{ $subject->id }}">
                    <td><div class="subject-title-row">@if($isAdmin)<button type="button" class="subject-drag-handle" title="Drag up or down to reorder. Use arrow keys to move." aria-label="Reorder {{ $subject->title }}"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3v18M8 7l4-4 4 4M8 17l4 4 4-4"/></svg></button>@endif<strong>{{ $subject->subject_code ?: '—' }}</strong></div></td>
                    <td><strong>{{ $subject->title }}</strong>@if($subject->is_intensive_final_coaching)<span class="status warning" style="margin-left:.45rem">Intensive Final Coaching</span>@endif<small style="display:block;color:var(--text-muted);margin-top:.3rem">{{ $subject->description ?: 'No description' }}</small></td>
                    <td>
                        @if($isAdmin)
                        <form data-subject-status data-subject-title="{{ $subject->title }}" method="POST" action="{{ route('admin.content.subjects.status', ['course'=>$course->id,'subject'=>$subject->id]) }}" style="margin:0">
                            @csrf
                            <input type="hidden" name="status" value="{{ $subject->status === 'approved' ? 'pending' : 'approved' }}">
                            <button type="submit" class="status subject-status-toggle {{ $subject->status === 'approved' ? 'success' : 'warning' }}" title="{{ $subject->status === 'approved' ? 'Set to Pending — hide from learners' : 'Approve — show to eligible learners' }}" aria-label="{{ $subject->title }}: {{ $subject->status === 'approved' ? 'set to pending and hide from learners' : 'approve and show to eligible learners' }}">{{ ucfirst($subject->status) }}</button>
                        </form>
                        @else
                        <span class="status {{ $subject->status === 'approved' ? 'success' : 'warning' }}">{{ ucfirst($subject->status) }}</span>
                        @endif
                    </td>
                    <td style="white-space:nowrap">
                        <div class="subject-row-actions">
                        <a href="{{ route('admin.content.topics', ['course' => $course->id, 'subject_id' => $subject->id]) }}" class="btn-primary">Manage</a>
                        <button type="button" class="btn-ghost edit-subject-btn" aria-label="Edit subject" title="Edit subject"
                            data-id="{{ $subject->id }}" data-code="{{ $subject->subject_code }}" data-title="{{ $subject->title }}"
                            data-description="{{ $subject->description }}" data-order="{{ $subject->sort_order }}" data-intensive="{{ $subject->is_intensive_final_coaching ? '1' : '0' }}" data-mentoring="{{ $subject->is_mentoring ? '1' : '0' }}"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block"><path d="m16 3 5 5L8 21H3v-5zM13 6l5 5"/></svg></button>
                        <form method="POST" action="{{ route('admin.content.subjects.destroy', ['course' => $course->id, 'subject' => $subject->id]) }}" onsubmit="return confirm('Delete this subject?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-ghost" style="color:var(--wrong)" aria-label="Delete subject" title="Delete subject"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block"><path d="M3 6h18M8 6V3h8v3m3 0-1 15H6L5 6m5 4v7m4-7v7"/></svg></button>
                        </form>
                        </div>
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
                <div class="field full">
                    <label class="subject-phase-check" for="subject_intensive_final_coaching">
                        <input id="subject_intensive_final_coaching" type="checkbox" name="is_intensive_final_coaching" value="1">
                        <span><strong>Intensive Final Coaching phase</strong><small>Only learners whose enrolled batch includes Intensive Final Coaching can see this subject.</small></span>
                    </label>
                </div>
            </div>
                <div class="field full"><label class="subject-phase-check" for="subject_mentoring"><input id="subject_mentoring" type="checkbox" name="is_mentoring" value="1"><span><strong>Mentoring subject</strong><small>Included in Mentoring batches and Complete Review courses.</small></span></label></div>
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
@if(session('success')) document.addEventListener('DOMContentLoaded', () => showAdminToast(@json(session('success')), 'success')); @endif
@if(session('error')) document.addEventListener('DOMContentLoaded', () => showAdminToast(@json(session('error')), 'error', 6500)); @endif
@if($errors->any()) document.addEventListener('DOMContentLoaded', () => showAdminToast(@json(implode(' ', $errors->all())), 'error', 6500)); @endif
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
    document.getElementById('subject_intensive_final_coaching').checked = button.dataset.intensive === '1';
    document.getElementById('subject_mentoring').checked = button.dataset.mentoring === '1';
    document.getElementById('subjectModalTitle').textContent = 'Edit Subject';
    document.getElementById('subjectSaveButton').textContent = 'Save Changes';
    subjectModal.classList.add('open');
}));
subjectModal.addEventListener('click', event => { if (event.target === subjectModal) closeSubjectModal(); });
</script>
<script src="{{ asset('subject-status.js') }}?v=1"></script>
@if($isAdmin)
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script src="{{ asset('subject-order.js') }}?v=1"></script>
@endif
@endsection
