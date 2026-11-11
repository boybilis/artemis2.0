@extends('admin.layouts.app', ['pageTitle' => 'Class Management'])
@section('kicker', 'Batch Delivery')
@php($isAdmin = Auth::user()->hasAdminAccess() || strtolower((string) Auth::user()->role) === 'staff')

@section('content')
<style>.class-batches-heading{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem}.class-batches-heading #showClassBatchForm{white-space:nowrap}</style>
@if($isAdmin)
<section id="classBatchFormPanel" class="panel {{ $errors->any() ? '' : 'hidden' }}" style="margin-bottom:1.25rem">
    <div class="panel-label">Batch setup</div><h2 id="class-batch-form-title" class="panel-title">Create Batch</h2>
    <p class="panel-subtitle">Create the class first, then assign one or more reusable master courses.</p>
    <form id="classBatchForm" method="POST" action="{{ route('admin.classes.batches.store') }}">@csrf<input id="class_batch_method" type="hidden" name="_method" value="POST">
        <div class="form-grid">
            <div class="field"><label>Batch name</label><input id="class_batch_name" name="name" required placeholder="OCTOBER 2026 (MWF) FULL"></div>
            <div class="field"><label>Batch code</label><input id="class_batch_code" name="code" readonly placeholder="Generated from the batch name"><small class="muted">Automatically generated and checked for uniqueness when saved.</small></div>
            <div class="field"><label for="class_batch_starts_at">Start date</label><input id="class_batch_starts_at" name="starts_at" type="date"></div>
            <div class="field"><label for="class_batch_ends_at">End date</label><input id="class_batch_ends_at" name="ends_at" type="date"></div>
            <fieldset class="field full batch-session-fieldset">
                <legend>Weekly class sessions</legend>
                <small class="muted">Give each session its own hours. Leave the row blank for a flexible schedule.</small>
                <div id="batch-session-rows"></div>
                <button type="button" id="batch-add-session" class="btn-ghost">+ Add Session</button>
                <template id="batch-session-template">
                    <div class="batch-session-row">
                        <label>Day<select data-session-field="day"><option value="">Select day</option>@foreach(\App\Models\CourseBatch::CLASS_DAYS as $day)<option>{{ $day }}</option>@endforeach</select></label>
                        <label>Start time<input type="time" data-session-field="start_time"></label>
                        <label>End time<input type="time" data-session-field="end_time"></label>
                        <button type="button" class="btn-ghost batch-remove-session">Remove</button>
                    </div>
                </template>
            </fieldset>
            <div class="field"><label>Modality</label><select id="class_batch_modality" name="modality"><option value="">Select modality</option><option>Online</option><option>Blended</option><option>Live via Zoom</option></select></div>
            <div class="field"><label>Batch price (PHP)</label><input id="class_batch_price" name="price" type="number" min="0" step=".01" required></div>
            <div class="field"><label>Display price (USD)</label><input id="class_batch_usd_price" name="usd_price" type="number" min="0" step=".01"></div>
            <div class="field"><label>Capacity</label><input id="class_batch_capacity" name="capacity" type="number" min="1"></div>
            <div class="field"><label>Status</label><select id="class_batch_status" name="status"><option value="open">Open</option><option value="draft">Draft</option><option value="closed">Closed</option><option value="completed">Completed</option></select></div>
            <div class="field full"><label for="class_batch_description">Description</label><textarea id="class_batch_description" name="description" rows="3" class="form-control" placeholder="Describe this batch offering, delivery setup, or important learner information."></textarea></div>
            <div class="field full"><label for="class_batch_course_type">Course type</label><select id="class_batch_course_type" name="course_type" required>@foreach(\App\Models\CourseBatch::COURSE_TYPES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select><small class="muted">Determines which subjects enrolled learners can access.</small></div>
            <div class="field full"><label>Included master courses</label><div class="class-course-checklist">@forelse($courses as $course)<label><input type="checkbox" name="course_ids[]" value="{{ $course->id }}"><span>{{ $course->title }}</span></label>@empty<p class="muted">No approved master courses are available.</p>@endforelse</div></div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:.6rem;margin-top:1rem"><button id="class_batch_cancel" type="button" class="btn-ghost" onclick="closeClassBatchForm()">Cancel</button><button class="btn-primary" type="submit">Save Batch</button></div>
    </form>
</section>
@endif

<section class="panel">
    <div class="class-batches-heading">
        <div><div class="panel-label">Classes</div><h2 class="panel-title">Manage Batches</h2></div>
        @if($isAdmin)
            <button id="showClassBatchForm" type="button" class="btn-primary" onclick="openClassBatchForm()">+ Add Batch Class</button>
        @endif
    </div>
    <p class="panel-subtitle">Each batch unlocks every master course assigned to it.</p>
    <div data-ajax-table="class-batches">
    <div class="class-batch-grid">
    @forelse($batches as $batch)
        @php($editor = ['id'=>$batch->id,'name'=>$batch->name,'code'=>$batch->code,'description'=>$batch->description,'course_type'=>$batch->course_type,'starts_at'=>$batch->starts_at?->format('Y-m-d'),'ends_at'=>$batch->ends_at?->format('Y-m-d'),'class_sessions'=>$batch->weeklySessions(),'legacy_schedule'=>$batch->class_sessions === null,'schedule_day'=>$batch->schedule_day,'start_time'=>substr((string)$batch->start_time,0,5),'end_time'=>substr((string)$batch->end_time,0,5),'modality'=>$batch->modality,'price'=>$batch->price,'usd_price'=>$batch->usd_price,'capacity'=>$batch->capacity,'status'=>$batch->status,'course_ids'=>$batch->courses->pluck('id')->values()])
        <article class="class-batch-card">
            <div class="class-batch-card-head"><div><small>{{ $batch->code }}</small><h3>{{ $batch->name }}</h3></div><button type="button" class="btn-ghost class-batch-details-button" aria-haspopup="dialog" aria-controls="batch-details-{{ $batch->id }}" onclick="document.getElementById(this.getAttribute('aria-controls')).showModal()">View Details</button></div>
            <dialog id="batch-details-{{ $batch->id }}" class="class-batch-details" aria-labelledby="batch-details-title-{{ $batch->id }}">
            <header><div><small>{{ $batch->code }}</small><h2 id="batch-details-title-{{ $batch->id }}">{{ $batch->name }}</h2></div><form method="dialog"><button class="btn-ghost" type="submit" aria-label="Close batch details" autofocus>Close</button></form></header>
            <div class="class-batch-details-body">
            <span class="status {{ $batch->status==='open'?'success':'warning' }}">{{ ucfirst($batch->status) }}</span>
            <p>{{ $batch->description ?: 'No batch description provided.' }}</p>
            @if($batch->includes_intensive_final_coaching)<div class="class-batch-phase"><i data-lucide="badge-check"></i><span>With Intensive Final Coaching Phase</span></div>@endif
            <div class="class-batch-meta"><span><i data-lucide="calendar-days"></i>{{ $batch->starts_at?->format('M d, Y') ?? 'Open schedule' }} – {{ $batch->ends_at?->format('M d, Y') ?? 'No end date' }}</span><span><i data-lucide="clock-3"></i>{{ $batch->schedule_day ?: 'Flexible schedule' }} · {{ $batch->modality ?: 'Online' }}</span><span><i data-lucide="users"></i>{{ $batch->active_enrollments_count }} enrolled</span></div>
            <div class="class-batch-courses"><strong>Included courses</strong>@foreach($batch->courses as $course)<span class="class-course-capsule">{{ $course->title }}@if($batch->includes_intensive_final_coaching && $course->subjects->contains('is_intensive_final_coaching', true))<em class="class-course-phase"><i data-lucide="badge-check"></i>Intensive Final Coaching Phase</em>@endif</span>@endforeach</div>
            <dl class="class-batch-detail-grid">
                @php($formatTime = fn ($time) => $time ? \Carbon\Carbon::createFromFormat('!H:i', $time)->format('g:i A') : 'Not set')
                <div><dt>Class sessions</dt><dd>@forelse($editor['class_sessions'] as $session)<div>{{ $session['day'] }} · {{ $formatTime($session['start_time']) }}{{ $session['end_time'] ? ' – '.$formatTime($session['end_time']) : '' }}</div>@empty{{ $formatTime($editor['start_time']) }}{{ $editor['end_time'] ? ' – '.$formatTime($editor['end_time']) : '' }}@endforelse</dd></div>
                <div><dt>Capacity</dt><dd>{{ $batch->capacity ?: 'Unlimited' }}</dd></div>
                <div><dt>Price (PHP)</dt><dd>{{ $batch->price === null ? 'Not set' : '₱'.number_format($batch->price, 2) }}</dd></div>
                <div><dt>Price (USD)</dt><dd>{{ $batch->usd_price === null ? 'Not set' : '$'.number_format($batch->usd_price, 2) }}</dd></div>
            </dl>
            </div>
            </dialog>
            <div class="class-batch-actions"><button class="btn-primary class-batch-zoom-button" type="button" onclick="openClassZoom({{ $batch->id }}, @js($batch->name))">Zoom Calendar ({{ $batch->zoom_sessions_count }})</button>@if($isAdmin)<div class="class-batch-icon-group"><button class="class-batch-icon" type="button" aria-label="Edit batch" title="Edit batch" data-batch="{{ json_encode($editor) }}" onclick="editClassBatch(JSON.parse(this.dataset.batch))"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 3 5 5L8 21H3v-5zM13 6l5 5"/></svg></button><form method="POST" action="{{ route('admin.classes.batches.destroy',$batch) }}" onsubmit="return confirmDelete(event,'Delete this batch? Only batches without enrollment history can be deleted.')">@csrf @method('DELETE')<button type="submit" class="class-batch-icon class-batch-delete" aria-label="Delete batch" title="Delete batch"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V3h8v3m3 0-1 15H6L5 6m5 4v7m4-7v7"/></svg></button></form></div>@endif</div>
        </article>
    @empty <p class="muted class-batch-empty">No batches have been created.</p> @endforelse
    </div>
    @if($batches->hasPages())
        <nav class="pagination class-batch-carousel-controls" aria-label="Batch carousel pages">
            @if($batches->onFirstPage())<button class="btn-ghost" type="button" disabled>‹ Previous</button>
            @else<a class="btn-ghost" href="{{ $batches->previousPageUrl() }}" rel="prev">‹ Previous</a>@endif
            <span aria-live="polite">Page {{ $batches->currentPage() }} of {{ $batches->lastPage() }}</span>
            @if($batches->hasMorePages())<a class="btn-ghost" href="{{ $batches->nextPageUrl() }}" rel="next">Next ›</a>
            @else<button class="btn-ghost" type="button" disabled>Next ›</button>@endif
        </nav>
    @endif
    </div>
</section>
<style>
    [data-ajax-table="class-batches"]>.class-batch-grid { grid-template-columns:repeat(3,minmax(0,1fr)); }
    .class-batch-grid .class-batch-card { display:flex;flex-direction:column;gap:.85rem;min-width:0;padding:.9rem; }
    .class-batch-grid .class-batch-card-head { align-items:flex-start;gap:.5rem;flex-wrap:wrap; }
    .class-batch-card-head>div { flex:1;min-width:min(100%,8rem);overflow-wrap:anywhere; }
    .class-batch-card-head .class-batch-details-button { border-radius:999px;padding:.45rem .7rem;font-size:.72rem;white-space:nowrap; }
    .class-batch-grid .class-batch-actions { margin-top:auto; }
    .class-batch-grid .class-batch-actions { align-items:center; }
    .class-batch-icon-group { display:flex;align-items:center;gap:.25rem;margin-inline-start:auto; }
    .class-batch-actions .class-batch-zoom-button { min-height:44px;padding:.55rem .75rem;font-size:.75rem; }
    .class-batch-actions .class-batch-icon { display:inline-grid;place-items:center;width:44px;height:44px;padding:0;border:0;border-radius:8px;background:transparent;color:var(--text);cursor:pointer; }
    .class-batch-actions .class-batch-icon:hover { background:var(--border); }
    .class-batch-actions .class-batch-icon:focus-visible { outline:2px solid var(--brand-aqua);outline-offset:2px; }
    .class-batch-actions .class-batch-delete { color:var(--wrong); }
    .class-batch-empty { grid-column:1/-1; }
    .class-batch-carousel-controls { display:flex;justify-content:center;align-items:center;gap:.75rem;flex-wrap:wrap;margin-top:1rem; }
    .class-batch-carousel-controls a { text-decoration:none; }
    .class-batch-details { position:fixed;inset:0;margin:auto;box-sizing:border-box;width:min(36rem,calc(100% - 2rem));max-height:calc(100dvh - 2rem);overflow:auto;padding:0;border:1px solid var(--border);border-radius:16px;background:var(--surface-solid);color:var(--text); }
    .class-batch-details::backdrop { background:rgba(13,29,43,.65); }
    .class-batch-details header { display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem;padding:1rem;border-bottom:1px solid var(--border); }
    .class-batch-details header h2 { margin:.25rem 0 0;overflow-wrap:anywhere; }
    .class-batch-details header small { color:var(--text-muted);overflow-wrap:anywhere; }
    .class-batch-details header form { margin:0; }
    .class-batch-details-body { padding:1rem; }
    .class-batch-details-body>p { white-space:pre-wrap;overflow-wrap:anywhere; }
    .class-batch-details .class-course-capsule { color:var(--text); }
    .class-batch-detail-grid { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem;margin:0; }
    .class-batch-detail-grid>div { border:1px solid var(--border);border-radius:10px;padding:.65rem; }
    .class-batch-detail-grid dt { color:var(--text-muted);font-size:.75rem; }
    .class-batch-detail-grid dd { margin:.25rem 0 0;overflow-wrap:anywhere; }
    @media(max-width:1100px){[data-ajax-table="class-batches"]>.class-batch-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:680px){[data-ajax-table="class-batches"]>.class-batch-grid,.class-batch-detail-grid{grid-template-columns:1fr}}
</style>

<div id="classZoomModal" class="admin-modal"><div class="admin-modal-content" style="max-width:900px"><div class="admin-modal-header"><div><h3 class="admin-modal-title">Batch Zoom Calendar</h3><p id="class_zoom_batch" class="muted"></p></div><button class="admin-modal-close" onclick="closeClassZoom()">&times;</button></div><div class="admin-modal-body"><form id="classZoomForm" class="panel" style="padding:1rem"><input id="class_zoom_id" type="hidden"><div class="form-grid"><div class="field full"><label for="class_zoom_course">Master course</label><select id="class_zoom_course" required></select></div><div class="field"><label for="class_zoom_subject">Subject</label><select id="class_zoom_subject" required></select></div><div class="field"><label for="class_zoom_topic">Post under topic</label><select id="class_zoom_topic"></select><small class="muted">Zoom Links is added for this batch only if needed. The master course stays unchanged.</small></div><div class="field"><label>Session title</label><input id="class_zoom_title" required></div><div class="field"><label>Live Zoom link</label><input id="class_zoom_url" type="url" required></div><div class="field full"><label>Zoom recording link</label><input id="class_zoom_recording_url" type="url" placeholder="Add the recording link after the live class"><small class="muted">Only learners enrolled in this class batch will see this recording.</small></div><div class="field"><label>Starts</label><input id="class_zoom_start" type="datetime-local" required></div><div class="field"><label>Ends</label><input id="class_zoom_end" type="datetime-local"></div><div class="field"><label>Status</label><select id="class_zoom_status"><option value="scheduled">Scheduled</option><option value="cancelled">Cancelled</option></select></div><div class="field full"><label>Description</label><textarea id="class_zoom_description" rows="2"></textarea></div></div><div style="display:flex;justify-content:flex-end;gap:.5rem"><button type="button" class="btn-ghost" onclick="resetClassZoomForm()">Clear</button><button class="btn-primary">Save Session</button></div></form><div id="class_zoom_list" class="list-stack" style="margin-top:1rem"></div></div></div></div>

<style>.class-course-checklist{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.55rem;padding:.8rem;border:1px solid var(--border);border-radius:12px}.class-course-checklist label{display:flex;align-items:center;justify-content:flex-start;gap:.65rem;min-height:42px;padding:.55rem .7rem;border-radius:8px;background:rgba(47,103,143,.06);cursor:pointer}.class-course-checklist input[type="checkbox"]{appearance:auto;-webkit-appearance:checkbox;flex:0 0 auto;width:16px!important;height:16px!important;min-width:16px;margin:0;padding:0;accent-color:#0bb89d}.class-course-checklist label span{line-height:1.3;text-align:left}.form-control{display:block;width:100%;padding:.75rem .85rem;border:1px solid var(--border);border-radius:10px;background:var(--surface);color:var(--text);font:inherit;line-height:1.5;box-sizing:border-box}.form-control:focus{border-color:#19344f;outline:0;box-shadow:0 0 0 3px rgba(47,103,143,.14)}.class-batch-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.class-batch-card{padding:1.15rem;border:1px solid var(--border);border-top:4px solid #19344f;border-radius:15px;background:var(--surface)}.class-batch-card-head{display:flex;justify-content:space-between;gap:1rem}.class-batch-card-head small{color:#f47721;font-weight:800}.class-batch-card h3{margin:.25rem 0}.class-batch-card>p{color:var(--text-muted);font-size:.8rem;line-height:1.55;white-space:pre-wrap;overflow-wrap:anywhere}.class-batch-meta{display:flex;flex-direction:column;gap:.45rem}.class-batch-meta span{display:flex;align-items:center;gap:.45rem;color:var(--text-muted);font-size:.75rem}.class-batch-meta svg{width:15px;color:#f47721}.class-batch-courses{display:flex;flex-wrap:wrap;gap:.4rem;margin:1rem 0}.class-batch-courses strong{width:100%;font-size:.72rem}.class-batch-courses .class-course-capsule{display:inline-flex;align-items:center;gap:.45rem;padding:.3rem .55rem;border-radius:999px;color:#19344f;background:rgba(47,103,143,.1);font-size:.68rem;font-weight:700}.class-course-phase{display:inline-flex;align-items:center;gap:.25rem;padding:.18rem .42rem;border:1px solid #f47721;border-radius:999px;color:#f47721;background:var(--surface);font-style:normal;font-size:.62rem}.class-course-phase svg{width:12px;height:12px}.class-batch-actions{display:flex;gap:.45rem;flex-wrap:wrap}.class-batch-actions form{margin:0}@media(max-width:850px){.class-batch-grid{grid-template-columns:1fr}}@media(max-width:640px){.class-course-checklist{grid-template-columns:1fr}.class-batch-courses .class-course-capsule{align-items:flex-start;flex-direction:column;border-radius:12px}}</style>
<style>.class-phase-check{display:flex;align-items:flex-start;gap:.7rem;padding:.8rem;border:1px solid var(--border);border-radius:10px;background:rgba(245,102,0,.06);cursor:pointer}.class-phase-check input{appearance:auto;-webkit-appearance:checkbox;width:17px!important;height:17px!important;min-width:17px;margin:.12rem 0 0;accent-color:#f47721}.class-phase-check strong,.class-phase-check small{display:block}.class-phase-check small{margin-top:.2rem;color:var(--text-muted);font-size:.7rem}.class-batch-phase{display:inline-flex;align-items:center;gap:.4rem;margin:0 0 .8rem;padding:.38rem .62rem;border:1px solid #f47721;border-radius:999px;color:#f47721;font-size:.7rem;font-weight:800}.class-batch-phase svg{width:15px;height:15px}</style>
<style>
.class-days-fieldset{min-width:0;margin:0;padding:0;border:0}.class-days-fieldset legend{margin-bottom:.4rem;font-size:.8rem;color:var(--text-muted)}
.class-days-checklist{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.45rem;padding:.7rem;border:1px solid var(--border);border-radius:10px;background:var(--surface)}
.class-days-checklist label{display:flex;align-items:center;gap:.5rem;margin:0;padding:.25rem;font-size:.78rem;cursor:pointer}
.class-days-checklist input[type="checkbox"]{appearance:auto;-webkit-appearance:checkbox;width:16px!important;height:16px!important;min-width:16px;margin:0;padding:0;accent-color:#11a7c0}
.class-days-checklist input:focus-visible{outline:2px solid #11a7c0;outline-offset:3px}
</style>
<style>
    #classBatchForm .field select { appearance:auto;background-image:none;padding-inline-end:1rem; }
    .batch-session-fieldset { min-width:0;border:1px solid var(--border);border-radius:12px;padding:1rem; }
    .batch-session-row { display:grid;grid-template-columns:minmax(0,1.2fr) repeat(2,minmax(0,1fr)) auto;gap:.65rem;align-items:end;margin:.75rem 0; }
    .batch-session-row label { display:grid;gap:.35rem;min-width:0; }
    .batch-session-row :is(input,select) { width:100%;min-width:0; }
    @media(max-width:650px){.batch-session-row{grid-template-columns:repeat(2,minmax(0,1fr))}.batch-session-row label:first-child{grid-column:1/-1}}
</style>
<script src="{{ asset('class-zoom-placement.js') }}?v=1"></script>
<script>
const classCsrf=@json(csrf_token());let activeClassBatch=null;let classZoomSessions=[];
let batchSessionIndex = 0, legacyBatchSessions = null;
const batchSessionRows = document.getElementById('batch-session-rows');
function readBatchSessions() {
    return Array.from(batchSessionRows.children, row => Object.fromEntries(Array.from(row.querySelectorAll('[data-session-field]'), input => [input.dataset.sessionField, input.value])));
}
function appendBatchSession(values = {}) {
    if (!batchSessionRows || batchSessionRows.children.length >= 28) return;
    const row = document.getElementById('batch-session-template').content.firstElementChild.cloneNode(true);
    row.querySelectorAll('[data-session-field]').forEach(input => {
        const key = input.dataset.sessionField;
        input.name = `class_sessions[${batchSessionIndex}][${key}]`;
        input.value = values[key] ?? '';
    });
    batchSessionIndex++;
    row.querySelector('.batch-remove-session').onclick = () => {
        row.remove();
        if (!batchSessionRows.children.length) appendBatchSession();
        document.getElementById('batch-add-session').disabled = false;
    };
    batchSessionRows.append(row);
    document.getElementById('batch-add-session').disabled = batchSessionRows.children.length >= 28;
    return row;
}
function renderBatchSessions(sessions = []) {
    if (!batchSessionRows) return;
    batchSessionIndex = 0;
    batchSessionRows.replaceChildren();
    for (const session of sessions.length ? sessions : [{}]) appendBatchSession(session);
}
if (batchSessionRows) {
    renderBatchSessions();
    document.getElementById('batch-add-session').onclick = () => appendBatchSession()?.querySelector('select').focus();
    document.getElementById('classBatchForm').addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget, button = form.querySelector('[type=submit]');
        if (button.disabled) return;
        const payload = new FormData(form);
        if (legacyBatchSessions === JSON.stringify(readBatchSessions())) {
            for (const key of [...payload.keys()]) if (key.startsWith('class_sessions[')) payload.delete(key);
        }
        button.disabled = true;
        try {
            const response = await fetch(form.action, {method:'POST', body:payload, headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
            const data = await response.json().catch(() => null);
            if (!response.ok || !data?.success) {
                const fallback = response.redirected ? 'Batch save returned an unexpected redirect. Reload the page and try again.'
                    : response.status === 419 ? 'Your session expired. Reload the page and try again.'
                    : `Could not save the batch (HTTP ${response.status}).`;
                throw new Error(Object.values(data?.errors || {}).flat()[0] || fallback);
            }
            closeClassBatchForm();
            showAdminToast(data.message, 'success');
            await loadAdminTable('class-batches', @json(route('admin.classes.index')), false);
        } catch (error) { showAdminToast(error.message, 'error'); }
        finally { button.disabled = false; }
    });
}
function openClassBatchForm(){resetClassBatchForm();document.getElementById('classBatchFormPanel').classList.remove('hidden');document.getElementById('showClassBatchForm').classList.add('hidden');document.getElementById('classBatchFormPanel').scrollIntoView({behavior:'smooth',block:'start'});}
function closeClassBatchForm(){resetClassBatchForm();document.getElementById('classBatchFormPanel').classList.add('hidden');document.getElementById('showClassBatchForm').classList.remove('hidden');}
function editClassBatch(item){const form=document.getElementById('classBatchForm');document.getElementById('classBatchFormPanel').classList.remove('hidden');document.getElementById('showClassBatchForm').classList.add('hidden');form.action=`/admin/classes/batches/${item.id}`;document.getElementById('class_batch_method').value='PUT';document.getElementById('class-batch-form-title').textContent='Edit Batch';['name','code','description','starts_at','ends_at','modality','price','usd_price','capacity','status'].forEach(key=>document.getElementById(`class_batch_${key}`).value=item[key]??'');renderBatchSessions(item.class_sessions); legacyBatchSessions = item.legacy_schedule ? JSON.stringify(readBatchSessions()) : null;document.getElementById('class_batch_course_type').value=item.course_type||'comprehensive';document.querySelectorAll('input[name="course_ids[]"]').forEach(input=>input.checked=item.course_ids.includes(Number(input.value)));document.getElementById('classBatchFormPanel').scrollIntoView({behavior:'smooth',block:'start'});}
function resetClassBatchForm(){const form=document.getElementById('classBatchForm');form.reset();legacyBatchSessions=null;renderBatchSessions();form.action='{{ route('admin.classes.batches.store') }}';document.getElementById('class_batch_method').value='POST';document.getElementById('class-batch-form-title').textContent='Create Batch';}
@if($errors->any()) document.getElementById('showClassBatchForm').classList.add('hidden'); @endif
document.getElementById('class_batch_name')?.addEventListener('input',event=>{if(document.getElementById('class_batch_method').value!=='POST')return;document.getElementById('class_batch_code').value=(event.target.value||'').trim().toUpperCase().replace(/[^A-Z0-9]+/g,'-').replace(/^-+|-+$/g,'').slice(0,70);});
function resetClassZoomForm(){['class_zoom_id','class_zoom_title','class_zoom_url','class_zoom_recording_url','class_zoom_start','class_zoom_end','class_zoom_description'].forEach(id=>document.getElementById(id).value='');document.getElementById('class_zoom_status').value='scheduled';selectClassZoomPlacement({});}
async function openClassZoom(id,name){activeClassBatch=id;document.getElementById('class_zoom_batch').textContent=name;document.getElementById('classZoomModal').classList.add('open');resetClassZoomForm();await loadClassZoom();}function closeClassZoom(){document.getElementById('classZoomModal').classList.remove('open');}
function formatClassZoomSchedule(item){const start=new Date(item.startsAt);const end=item.endsAt?new Date(item.endsAt):null;const dayDate=start.toLocaleDateString('en-US',{weekday:'long',month:'short',day:'numeric',year:'numeric'});const startTime=start.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'});const endTime=end?end.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'}):'No end time';return `${dayDate} · ${startTime} – ${endTime}`;}
async function loadClassZoom(){const response=await fetch(`/admin/classes/batches/${activeClassBatch}/zoom-sessions`,{headers:{Accept:'application/json'}});const data=await response.json();setClassZoomPlacementCourses(data.courses||[]);classZoomSessions=data.sessions||[];document.getElementById('class_zoom_list').innerHTML=classZoomSessions.length?classZoomSessions.map(item=>`<div class="list-item"><strong>${escapeClass(item.title)}</strong><small class="muted" style="display:block">${escapeClass(formatClassZoomSchedule(item))} · ${escapeClass(item.status)}${item.recordingUrl?' · Recording available':''}</small><div style="display:flex;gap:.4rem;margin-top:.6rem"><button class="btn-ghost" onclick="editClassZoom(${item.id})">Edit</button><button class="btn-ghost" style="color:var(--wrong)" onclick="deleteClassZoom(${item.id})">Delete</button></div></div>`).join(''):'<p class="muted">No Zoom sessions scheduled.</p>';}
function editClassZoom(id){const item=classZoomSessions.find(row=>row.id===id);if(!item)return;selectClassZoomPlacement(item);document.getElementById('class_zoom_id').value=item.id;document.getElementById('class_zoom_title').value=item.title||'';document.getElementById('class_zoom_url').value=item.zoomUrl||'';document.getElementById('class_zoom_recording_url').value=item.recordingUrl||'';document.getElementById('class_zoom_start').value=(item.startsAt||'').slice(0,16);document.getElementById('class_zoom_end').value=(item.endsAt||'').slice(0,16);document.getElementById('class_zoom_description').value=item.description||'';document.getElementById('class_zoom_status').value=item.status;}
document.getElementById('classZoomForm').addEventListener('submit',async event=>{event.preventDefault();const id=document.getElementById('class_zoom_id').value;const payload={course_id:document.getElementById('class_zoom_course').value||null,subject_id:document.getElementById('class_zoom_subject').value||null,topic_id:document.getElementById('class_zoom_topic').value||null,title:document.getElementById('class_zoom_title').value,zoom_url:document.getElementById('class_zoom_url').value,recording_url:document.getElementById('class_zoom_recording_url').value||null,starts_at:document.getElementById('class_zoom_start').value,ends_at:document.getElementById('class_zoom_end').value||null,description:document.getElementById('class_zoom_description').value||null,status:document.getElementById('class_zoom_status').value};const response=await fetch(`/admin/classes/batches/${activeClassBatch}/zoom-sessions${id?`/${id}`:''}`,{method:id?'PUT':'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':classCsrf},body:JSON.stringify(payload)});const data=await response.json();if(!response.ok)return showAdminAlert(Object.values(data.errors||{}).flat().join(' ')||data.message);resetClassZoomForm();await loadClassZoom();});
async function deleteClassZoom(id){const response=await fetch(`/admin/classes/batches/${activeClassBatch}/zoom-sessions/${id}`,{method:'DELETE',headers:{Accept:'application/json','X-CSRF-TOKEN':classCsrf}});if(!response.ok)return showAdminAlert('Unable to delete Zoom session.');await loadClassZoom();}function escapeClass(value){const div=document.createElement('div');div.textContent=value??'';return div.innerHTML;}
</script>
@endsection
