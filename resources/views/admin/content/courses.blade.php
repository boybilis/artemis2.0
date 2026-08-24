@extends('admin.layouts.app')

@section('title', ($classManagement ?? false) ? 'Class Management' : 'Course Management')
@section('kicker', ($classManagement ?? false) ? 'Batch Delivery' : 'Content Management')

@section('header_actions')
    @if(!($classManagement ?? false))<button class="btn btn-primary" type="button" onclick="openAddCourseModal()">Add Course</button>@endif
@endsection

@section('content')
@php($isAdmin = Auth::user()->is_admin || strtolower((string) Auth::user()->role) === 'admin')
<style>
    .course-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
    .course-info { flex: 1 1 auto; min-width: 0; padding-right: 1rem; }
    .course-info h3 { margin: 0 0 0.5rem 0; }
    .course-info p { color: var(--text-muted); margin: 0; font-size: 0.9rem; }
    .course-actions { display:flex; align-items:center; justify-content:flex-end; flex:0 0 auto; flex-wrap:nowrap; gap:.4rem; }
    .course-actions form { display:flex !important; margin:0; flex:0 0 auto; }
    .course-actions .btn-primary,
    .course-actions .btn-ghost { width:auto !important; margin:0 !important; padding:.58rem .78rem; border-radius:8px; font-size:.76rem; line-height:1.15; }
    .enrollment-modal-content{max-width:980px}.enrollment-controls{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem}.enrollment-student-table tbody tr{cursor:pointer}.enrollment-student-table tbody tr.selected{background:rgba(124,58,237,.12)}.enrollment-modal-pagination{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-top:1rem}.enrollment-reset-note{margin:0;color:var(--text-muted);font-size:.76rem}@media(max-width:700px){.enrollment-controls{grid-template-columns:1fr}.enrollment-modal-pagination{align-items:stretch;flex-direction:column}.enrollment-modal-pagination>div{display:flex;gap:.5rem}.enrollment-modal-pagination button{flex:1}}
    @media(max-width:900px){.course-info{padding-right:0}.course-actions{justify-content:flex-start;flex-wrap:wrap}.course-actions>*{flex:0 1 auto}.course-actions form{flex:0 1 auto}.course-actions .btn-primary,.course-actions .btn-ghost{width:auto !important}}
</style>

<!-- Removed old tabs -->

<div class="split-grid">
    <section class="panel">
        <div class="toolbar">
            <div>
                <p class="panel-label">{{ ($classManagement ?? false) ? 'Classes and batches' : 'Master courses' }}</p>
                <h2 class="panel-title">{{ ($classManagement ?? false) ? 'Class Management' : 'Course Content Library' }}</h2>
            </div>
        </div>

        @if(session('success'))
            <div style="margin: 0 0 1rem; padding: 0.75rem 1rem; background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.25); border-radius: 8px; color: #10b981; font-size: 0.875rem; font-weight: 500;">
                ✓ {{ session('success') }}
            </div>
        @endif

        <div id="coursesList">
            @forelse ($courses as $course)
                <div class="course-card">
                    <div class="course-info">
                        <h3>{{ $course->title }}</h3>
                        <p>{{ $course->description }}</p>
                        <p>
                            <span class="status {{ $course->approval_status === 'approved' ? 'success' : ($course->approval_status === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst($course->approval_status) }}</span>
                        </p>
                    </div>
                    <div class="course-actions">
                        @if($classManagement ?? false)
                        @if($isAdmin)<button class="btn-primary" type="button" onclick="openAddBatchModal({{ $course->id }})">Create Batch</button>@endif
                        <button class="btn-ghost" type="button" onclick="openBatchListModal({{ $course->id }})">Manage Batches</button>
                        @else
                        <a href="{{ route('admin.content.subjects', $course->id) }}" class="btn-primary" style="text-decoration:none;">Manage Content</a>
                        <button class="btn-ghost" type="button" onclick='openEditCourseModal(@json($course->id), @json($course->title), @json($course->description), @json($course->approval_status))'>Edit</button>
                        <form action="{{ route('admin.content.courses.destroy', $course->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('Delete this course?');">
                            @csrf
                            @method('DELETE')
                            <button class="btn-ghost" style="color: var(--wrong);">Delete</button>
                        </form>
                        @endif
                    </div>
                </div>
            @empty
                <p>No courses found. Create one to get started.</p>
            @endforelse
        </div>
    </section>
</div>

<!-- RESET ASSESSMENT CONFIRMATION / RESULT MODAL -->
<div id="resetAssessmentConfirmModal" class="admin-modal" style="z-index:10001">
    <div class="admin-modal-content" style="max-width:460px;text-align:center">
        <div class="admin-modal-header" style="justify-content:center;border-bottom:0;padding-bottom:0">
            <h3 id="reset_confirmation_title" class="admin-modal-title">Confirm Assessment Reset</h3>
        </div>
        <div class="admin-modal-body" style="padding-top:.75rem">
            <p id="reset_confirmation_message" style="margin:0;color:var(--text-muted);line-height:1.65"></p>
        </div>
        <div class="admin-modal-footer" style="border-top:0;justify-content:center">
            <button type="button" id="reset_confirmation_cancel" class="btn-ghost" onclick="closeResetConfirmationModal()">Cancel</button>
            <button type="button" id="reset_confirmation_confirm" class="btn-primary">Reset Exam Take</button>
        </div>
    </div>
</div>

<div id="unenrollStudentModal" class="admin-modal" style="z-index:10001">
    <div class="admin-modal-content" style="max-width:460px;text-align:center">
        <div class="admin-modal-header" style="justify-content:center;border-bottom:0;padding-bottom:0"><h3 id="unenroll_modal_title" class="admin-modal-title">Confirm Unenrollment</h3></div>
        <div class="admin-modal-body" style="padding-top:.75rem"><p id="unenroll_modal_message" style="margin:0;color:var(--text-muted);line-height:1.65"></p></div>
        <div class="admin-modal-footer" style="border-top:0;justify-content:center"><button type="button" id="unenroll_modal_cancel" class="btn-ghost" onclick="closeUnenrollModal()">Cancel</button><button type="button" id="unenroll_modal_confirm" class="btn-primary" style="background:var(--wrong)">Unenroll</button></div>
    </div>
</div>

<div id="courseBatchesModal" class="admin-modal">
    <div class="admin-modal-content" style="max-width:900px">
        <div class="admin-modal-header"><div><h3 id="batch_modal_title" class="admin-modal-title">Batch List</h3><p id="batch_course_title" class="muted" style="margin:.25rem 0 0"></p></div><button type="button" class="admin-modal-close" onclick="closeModal('courseBatchesModal')">&times;</button></div>
        <div class="admin-modal-body">
            <form id="courseBatchForm" class="panel" style="padding:1rem;margin-bottom:1rem;display:none">
                <input type="hidden" id="batch_id">
                <input type="hidden" id="batch_mock_attempts"><input type="hidden" id="batch_mock_passing" value="80"><input type="hidden" id="batch_mock_time_limit">
                <div class="form-grid" style="margin:0">
                    <div class="field"><label>Assigned master course</label><select id="batch_assigned_course" required>@foreach($courses as $masterCourse)<option value="{{ $masterCourse->id }}">{{ $masterCourse->title }}</option>@endforeach</select></div>
                    <div class="field"><label>Batch name</label><input id="batch_name" required placeholder="DOH–HAAD Batch 1"></div>
                    <div class="field"><label>Batch code</label><input id="batch_code" required placeholder="HAAD-B1"></div>
                    <div class="field"><label>Starts</label><input id="batch_starts_at" type="datetime-local"></div>
                    <div class="field"><label>Ends</label><input id="batch_ends_at" type="datetime-local"></div>
                    <div class="field"><label>Day</label><select id="batch_schedule_day"><option value="">Select day</option>@foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day)<option value="{{ $day }}">{{ $day }}</option>@endforeach</select></div>
                    <div class="field"><label>Start time</label><input id="batch_start_time" type="time"></div>
                    <div class="field"><label>End time</label><input id="batch_end_time" type="time"></div>
                    <div class="field"><label>Modality</label><select id="batch_modality"><option value="">Select modality</option><option>Online</option><option>Blended</option><option>Live via Zoom</option></select></div>
                    <div class="field"><label>Price (PHP)</label><input id="batch_price" type="number" min="0" step="0.01" required placeholder="0.00"></div>
                    <div class="field"><label>Display price (USD)</label><input id="batch_usd_price" type="number" min="0" step="0.01" placeholder="Optional"></div>
                    <div class="field"><label>Capacity</label><input id="batch_capacity" type="number" min="1" placeholder="Unlimited"></div>
                    <div class="field"><label>Status</label><select id="batch_status"><option value="open">Open for enrollment</option><option value="draft">Draft</option><option value="closed">Closed</option><option value="completed">Completed</option></select></div>
                    <div class="field full"><label>Description</label><textarea id="batch_description" rows="2"></textarea></div>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:.5rem;margin-top:1rem"><button type="button" class="btn-ghost" onclick="resetBatchForm()">Clear</button><button type="submit" class="btn-primary">Save Batch</button></div>
            </form>
            <div id="courseBatchListPanel" class="table-wrap"><table class="data-table"><thead><tr><th>Batch</th><th>Schedule</th><th>Students</th><th>Status</th><th>Action</th></tr></thead><tbody id="course_batches_body"></tbody></table></div>
        </div>
    </div>
</div>

<!-- COURSE MOCK EXAM RANKING MODAL -->
<div id="courseRankingModal" class="admin-modal">
    <div class="admin-modal-content" style="max-width:760px">
        <div class="admin-modal-header">
            <div><h3 class="admin-modal-title">Mock Exam Ranking</h3><p id="ranking_course_title" class="muted" style="margin:.25rem 0 0">Top 20 learners by best score</p></div>
            <button type="button" class="admin-modal-close" onclick="closeModal('courseRankingModal')">&times;</button>
        </div>
        <div class="admin-modal-body">
            <div class="table-wrap" style="margin-top:0">
                <table class="data-table" style="min-width:560px">
                    <thead><tr><th style="width:70px">Rank</th><th>Learner</th><th>Top Score</th><th>Result</th></tr></thead>
                    <tbody id="course_ranking_body"><tr><td colspan="4" class="muted">Loading rankings…</td></tr></tbody>
                </table>
            </div>
        </div>
        <div class="admin-modal-footer"><button type="button" class="btn-ghost" onclick="closeModal('courseRankingModal')">Close</button></div>
    </div>
</div>

<!-- ENROLLED STUDENTS / ASSESSMENT RESET MODAL -->
<div id="enrolledStudentsModal" class="admin-modal">
    <div class="admin-modal-content enrollment-modal-content">
        <div class="admin-modal-header">
            <div><h3 class="admin-modal-title">Enrolled Students</h3><p id="enrollment_course_title" class="muted" style="margin:.25rem 0 0"></p></div>
            <button type="button" class="admin-modal-close" onclick="closeModal('enrolledStudentsModal')">&times;</button>
        </div>
        <div class="admin-modal-body">
            <div class="enrollment-controls">
                <div class="field"><label>Subject</label><select id="enrollment_subject_filter" onchange="filterEnrollmentAssessments()"><option value="">All Subjects / Course-wide</option></select></div>
                <div class="field"><label>Assessment to reset</label><select id="enrollment_assessment"><option value="">Select an assessment</option></select></div>
            </div>
            <div class="table-wrap">
                <table class="data-table enrollment-student-table">
                    <thead><tr><th style="width:45px">Select</th><th>Student</th><th>Email</th><th>Batch</th><th>Date Enrolled</th><th>Status</th></tr></thead>
                    <tbody id="enrollment_students_body"><tr><td colspan="6" class="muted">Loading enrolled students…</td></tr></tbody>
                </table>
            </div>
            <div class="enrollment-modal-pagination"><span id="enrollment_page_info" class="muted"></span><div><button type="button" id="enrollment_prev" class="btn-ghost">Previous</button><button type="button" id="enrollment_next" class="btn-ghost">Next</button></div></div>
        </div>
        <div class="admin-modal-footer">
            <p class="enrollment-reset-note">Resetting removes only the selected learner's saved attempts for this assessment.</p>
            <button type="button" id="reset_assessment_take" class="btn-primary" disabled>Reset Exam Take</button>
        </div>
    </div>
</div>

<!-- ADD COURSE MODAL -->
<div id="addCourseModal" class="admin-modal">
    <form action="{{ route('admin.content.courses.store') }}" method="POST" class="admin-modal-content">
        @csrf
        <div class="admin-modal-header">
            <h3 class="admin-modal-title">Add New Course</h3>
            <button type="button" class="admin-modal-close" onclick="closeModal('addCourseModal')">&times;</button>
        </div>
        <div class="admin-modal-body">
            <div class="field">
                <label>Course Title</label>
                <input type="text" name="title" required style="width:100%; padding: 0.8rem; border-radius: 8px; background: rgba(255,255,255,0.02); border: 1.5px solid var(--border); color: var(--text);">
            </div>
            <div class="field" style="margin-top: 1rem;">
                <label>Description</label>
                <textarea name="description" style="width:100%; padding: 0.8rem; border-radius: 8px; background: rgba(255,255,255,0.02); border: 1.5px solid var(--border); color: var(--text);"></textarea>
            </div>
            @if($isAdmin)
            @else
                <p class="muted" style="margin-top:1rem">An administrator will approve this master course. Pricing, schedules, and enrollment are configured in batches.</p>
            @endif
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="btn-ghost" onclick="closeModal('addCourseModal')">Cancel</button>
            <button type="submit" class="btn-primary">Create Course</button>
        </div>
    </form>
</div>

<!-- EDIT COURSE MODAL -->
<div id="editCourseModal" class="admin-modal">
    <form id="editCourseForm" method="POST" class="admin-modal-content">
        @csrf
        <div class="admin-modal-header">
            <h3 class="admin-modal-title">Edit Course</h3>
            <button type="button" class="admin-modal-close" onclick="closeModal('editCourseModal')">&times;</button>
        </div>
        <div class="admin-modal-body">
            <div class="field">
                <label>Course Title</label>
                <input type="text" id="edit_course_title" name="title" required style="width:100%; padding: 0.8rem; border-radius: 8px; background: rgba(255,255,255,0.02); border: 1.5px solid var(--border); color: var(--text);">
            </div>
            <div class="field" style="margin-top: 1rem;">
                <label>Description</label>
                <textarea id="edit_course_description" name="description" style="width:100%; padding: 0.8rem; border-radius: 8px; background: rgba(255,255,255,0.02); border: 1.5px solid var(--border); color: var(--text);"></textarea>
            </div>
            @if($isAdmin)
                <div class="field" style="margin-top: 1rem;"><label>Approval</label><select id="edit_course_approval_status" name="approval_status" required><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select></div>
            @else
                <p class="muted" style="margin-top:1rem">Course approval is controlled by administrators. Pricing and availability belong to batches.</p>
            @endif
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="btn-ghost" onclick="closeModal('editCourseModal')">Cancel</button>
            <button type="submit" class="btn-primary">Save</button>
        </div>
    </form>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.add('open'); }
    function closeModal(id) { document.getElementById(id).classList.remove('open'); }
    
    function openAddCourseModal() { openModal('addCourseModal'); }

    let enrollmentCourseId = null;
    let enrollmentPage = 1;
    let enrollmentSelectedUserId = null;
    let enrollmentAssessments = [];
    let pendingAssessmentReset = null;
    let resetConfirmationCompleted = false;
    let pendingUnenrollment = null;
    let unenrollmentCompleted = false;
    let batchCourseId = null;
    let loadedCourseBatches = [];

    function showBatchModalSection(mode) {
        const showingForm = mode === 'add' || mode === 'edit';
        document.getElementById('courseBatchForm').style.display = showingForm ? 'block' : 'none';
        document.getElementById('courseBatchListPanel').style.display = showingForm ? 'none' : 'block';
        document.getElementById('batch_modal_title').textContent = mode === 'edit' ? 'Edit Batch' : (mode === 'add' ? 'Add Batch' : 'Batch List');
    }

    function resetBatchForm() {
        ['batch_id','batch_name','batch_code','batch_starts_at','batch_ends_at','batch_schedule_day','batch_start_time','batch_end_time','batch_modality','batch_price','batch_usd_price','batch_capacity','batch_mock_time_limit','batch_description'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('batch_mock_attempts').value='2'; document.getElementById('batch_mock_passing').value='80';
        document.getElementById('batch_status').value = 'open';
    }
    function openAddBatchModal(courseId) { batchCourseId=courseId;resetBatchForm();document.getElementById('batch_assigned_course').value=String(courseId);document.getElementById('batch_course_title').textContent=document.querySelector(`button[onclick="openAddBatchModal(${courseId})"]`)?.closest('.course-card')?.querySelector('h3')?.textContent||'';showBatchModalSection('add');openModal('courseBatchesModal'); }
    async function openBatchListModal(courseId) { batchCourseId=courseId;resetBatchForm();showBatchModalSection('list');openModal('courseBatchesModal');await loadCourseBatches(); }
    function editCourseBatch(batchId) { const batch=loadedCourseBatches.find(item=>item.id===Number(batchId));if(!batch)return;document.getElementById('batch_assigned_course').value=String(batch.courseId||batchCourseId);document.getElementById('batch_id').value=batch.id;document.getElementById('batch_name').value=batch.name;document.getElementById('batch_code').value=batch.code;document.getElementById('batch_starts_at').value=batch.startsAt||'';document.getElementById('batch_ends_at').value=batch.endsAt||'';document.getElementById('batch_schedule_day').value=batch.scheduleDay||'';document.getElementById('batch_start_time').value=(batch.startTime||'').slice(0,5);document.getElementById('batch_end_time').value=(batch.endTime||'').slice(0,5);document.getElementById('batch_modality').value=batch.modality||'';document.getElementById('batch_price').value=batch.price||0;document.getElementById('batch_usd_price').value=batch.usdPrice||'';document.getElementById('batch_capacity').value=batch.capacity||'';document.getElementById('batch_mock_attempts').value=batch.mockMaximumAttempts||'';document.getElementById('batch_mock_passing').value=batch.mockPassingPercentage||80;document.getElementById('batch_mock_time_limit').value=batch.mockTimeLimitMinutes||'';document.getElementById('batch_status').value=batch.status;document.getElementById('batch_description').value=batch.description||'';showBatchModalSection('edit'); }
    async function loadCourseBatches() {
        showBatchModalSection('list');
        const body=document.getElementById('course_batches_body'); body.innerHTML='<tr><td colspan="5" class="muted">Loading batches…</td></tr>';
        const response=await fetch(`/admin/content/courses/${batchCourseId}/batches`,{headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'}); const data=await response.json();
        if(!response.ok||!data.success){body.innerHTML=`<tr><td colspan="5">${escapeEnrollmentText(data.message||'Unable to load batches.')}</td></tr>`;return;}
        loadedCourseBatches=data.batches||[];document.getElementById('batch_course_title').textContent=data.course.title;
        body.innerHTML=loadedCourseBatches.length?loadedCourseBatches.map(batch=>`<tr><td><strong>${escapeEnrollmentText(batch.name)}</strong><br><small>${escapeEnrollmentText(batch.code)} · ₱${Number(batch.price||0).toLocaleString(undefined,{minimumFractionDigits:2})}</small></td><td>${batch.startsAt?new Date(batch.startsAt).toLocaleString():'Open schedule'}${batch.endsAt?`<br><small>until ${new Date(batch.endsAt).toLocaleString()}</small>`:''}${batch.scheduleDay?`<br><small>${escapeEnrollmentText(batch.scheduleDay)}, ${escapeEnrollmentText(batch.startTime||'')}–${escapeEnrollmentText(batch.endTime||'')} · ${escapeEnrollmentText(batch.modality||'')}</small>`:''}</td><td>${batch.enrolledCount}${batch.capacity?` / ${batch.capacity}`:''}</td><td><span class="status ${batch.status==='open'?'success':'warning'}">${escapeEnrollmentText(batch.status)}</span></td><td><div style="display:flex;gap:.35rem;flex-wrap:wrap"><button type="button" class="btn-ghost" onclick="openEnrolledStudentsModal(${batchCourseId},${batch.id})">Students</button><button type="button" class="btn-ghost" onclick="openCourseRankingModal(${batchCourseId},${batch.id})">Ranking</button><button type="button" class="btn-ghost edit-batch-btn" data-id="${batch.id}">Edit</button></div></td></tr>`).join(''):'<tr><td colspan="5" class="muted">No batches yet. Add the first batch above.</td></tr>';
        body.querySelectorAll('.edit-batch-btn').forEach(button=>button.onclick=()=>editCourseBatch(button.dataset.id));
    }
    document.getElementById('courseBatchForm').addEventListener('submit',async event=>{event.preventDefault();const id=document.getElementById('batch_id').value;const payload={course_id:Number(document.getElementById('batch_assigned_course').value),name:document.getElementById('batch_name').value,code:document.getElementById('batch_code').value,starts_at:document.getElementById('batch_starts_at').value||null,ends_at:document.getElementById('batch_ends_at').value||null,schedule_day:document.getElementById('batch_schedule_day').value||null,start_time:document.getElementById('batch_start_time').value||null,end_time:document.getElementById('batch_end_time').value||null,modality:document.getElementById('batch_modality').value||null,price:document.getElementById('batch_price').value,usd_price:document.getElementById('batch_usd_price').value||null,capacity:document.getElementById('batch_capacity').value||null,mock_exam_maximum_attempts:document.getElementById('batch_mock_attempts').value||null,mock_exam_passing_percentage:document.getElementById('batch_mock_passing').value,mock_exam_time_limit_minutes:document.getElementById('batch_mock_time_limit').value||null,status:document.getElementById('batch_status').value,description:document.getElementById('batch_description').value};const response=await fetch(`/admin/content/courses/${batchCourseId}/batches${id?`/${id}`:''}`,{method:'POST',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':@json(csrf_token()),'X-Requested-With':'XMLHttpRequest'},credentials:'same-origin',body:JSON.stringify(payload)});const data=await response.json();if(!response.ok||!data.success)return showAdminAlert(Object.values(data.errors||{}).flat().join(' ')||data.message||'Unable to save batch.');resetBatchForm();await loadCourseBatches();});

    async function openCourseRankingModal(courseId, batchId) {
        const body = document.getElementById('course_ranking_body');
        const title = document.getElementById('ranking_course_title');
        body.innerHTML = '<tr><td colspan="4" class="muted">Loading rankings…</td></tr>';
        title.textContent = 'Top 20 learners by best mock exam score';
        closeModal('courseBatchesModal'); openModal('courseRankingModal');
        try {
            const response = await fetch(`/admin/content/courses/${courseId}/rankings?batch_id=${batchId}`, {headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Unable to load rankings.');
            title.textContent = `${data.batch.name} · ${data.course.title} · Top 20 by best mock exam score`;
            body.innerHTML = data.rankings.length ? data.rankings.map(item => `<tr>
                <td><strong>#${item.rank}</strong></td>
                <td><strong>${escapeEnrollmentText(item.name)}</strong></td>
                <td><strong>${Number(item.percentage).toFixed(2)}%</strong><div class="muted" style="font-size:.72rem">${Number(item.score).toLocaleString()} / ${Number(item.total).toLocaleString()}</div></td>
                <td><span class="status ${item.passed ? 'success' : 'danger'}">${item.passed ? 'Passed' : 'Not Passed'}</span></td>
            </tr>`).join('') : '<tr><td colspan="4" class="muted">No enrolled learner has completed the mock exam yet.</td></tr>';
        } catch (error) {
            body.innerHTML = `<tr><td colspan="4" style="color:var(--wrong)">${escapeEnrollmentText(error.message)}</td></tr>`;
        }
    }

    let enrollmentBatchId = null;
    async function openEnrolledStudentsModal(courseId, batchId) {
        enrollmentCourseId = courseId;
        enrollmentBatchId = batchId;
        enrollmentPage = 1;
        enrollmentSelectedUserId = null;
        document.getElementById('reset_assessment_take').disabled = true;
        closeModal('courseBatchesModal'); openModal('enrolledStudentsModal');
        await loadCourseEnrollments();
    }

    async function loadCourseEnrollments() {
        const body = document.getElementById('enrollment_students_body');
        body.innerHTML = '<tr><td colspan="6" class="muted">Loading enrolled students…</td></tr>';
        const response = await fetch(`/admin/content/courses/${enrollmentCourseId}/enrollments?page=${enrollmentPage}&batch_id=${enrollmentBatchId}`, {headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});
        const data = await response.json();
        if (!response.ok || !data.success) { body.innerHTML = `<tr><td colspan="6" class="muted">${data.message || 'Unable to load enrolled students.'}</td></tr>`; return; }
        document.getElementById('enrollment_course_title').textContent = `${data.batch.name} · ${data.course.title}`;
        enrollmentAssessments = data.assessments || [];
        const subjectSelect = document.getElementById('enrollment_subject_filter');
        subjectSelect.innerHTML = '<option value="">All Subjects / Course-wide</option>' + (data.subjects || []).map(subject => `<option value="${subject.id}">${escapeEnrollmentText(subject.subject_code)} — ${escapeEnrollmentText(subject.title)}</option>`).join('');
        filterEnrollmentAssessments();
        const batches = data.batches || [];
        body.innerHTML = data.students.length ? data.students.map(student => `<tr data-user-id="${student.id}"><td><input type="radio" name="enrollment_student" value="${student.id}"></td><td><strong>${escapeEnrollmentText(student.name)}</strong></td><td>${escapeEnrollmentText(student.email)}</td><td><select class="student-batch-select" data-user-id="${student.id}" aria-label="Batch for ${escapeEnrollmentText(student.name)}"><option value="">Unassigned</option>${batches.map(batch => `<option value="${batch.id}" ${Number(student.batchId) === Number(batch.id) ? 'selected' : ''}>${escapeEnrollmentText(batch.code)} — ${escapeEnrollmentText(batch.name)}</option>`).join('')}</select></td><td>${escapeEnrollmentText(student.enrolledAt || '—')}</td><td><span class="status ${student.status === 'active' ? 'success' : 'warning'}">${escapeEnrollmentText(student.status)}</span>${data.canUnenroll ? `<button type="button" class="status danger unenroll-student-btn" data-user-id="${student.id}" data-student-name="${escapeEnrollmentText(student.name)}" style="display:block;margin-top:.4rem;border:1px solid rgba(239,68,68,.35);cursor:pointer">Unenroll</button>` : ''}</td></tr>`).join('') : '<tr><td colspan="6" class="muted">No students are enrolled in this batch.</td></tr>';
        body.querySelectorAll('tr[data-user-id]').forEach(row => row.addEventListener('click', event => {
            enrollmentSelectedUserId = Number(row.dataset.userId);
            body.querySelectorAll('tr').forEach(item => item.classList.remove('selected'));
            row.classList.add('selected');
            row.querySelector('input').checked = true;
            syncResetAssessmentButton();
        }));
        body.querySelectorAll('.unenroll-student-btn').forEach(button => button.addEventListener('click', event => {
            event.stopPropagation();
            pendingUnenrollment = {userId:Number(button.dataset.userId),studentName:button.dataset.studentName};
            unenrollmentCompleted = false;
            document.getElementById('unenroll_modal_title').textContent = 'Confirm Unenrollment';
            document.getElementById('unenroll_modal_message').textContent = `Remove ${button.dataset.studentName} from ${data.course.title}? The learner will lose access to this course, but historical learning and assessment records will be preserved.`;
            document.getElementById('unenroll_modal_cancel').style.display = '';
            const confirmButton = document.getElementById('unenroll_modal_confirm');
            confirmButton.textContent = 'Unenroll'; confirmButton.disabled = false;
            openModal('unenrollStudentModal');
        }));
        body.querySelectorAll('.student-batch-select').forEach(select => {
            select.addEventListener('click', event => event.stopPropagation());
            select.addEventListener('change', async event => {
                event.stopPropagation();
                if (!select.value) { await loadCourseEnrollments(); return showAdminAlert('Please select a batch.'); }
                const response = await fetch(`/admin/content/courses/${enrollmentCourseId}/enrollments/${select.dataset.userId}/batch`, {
                    method:'POST', headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':@json(csrf_token()),'X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin', body:JSON.stringify({batch_id:Number(select.value)})
                });
                const result = await response.json();
                if (!response.ok || !result.success) { await loadCourseEnrollments(); return showAdminAlert(result.message || Object.values(result.errors || {}).flat().join(' ') || 'Unable to reassign this learner.'); }
            });
        });
        document.getElementById('enrollment_page_info').textContent = `${data.pagination.total} enrolled · Page ${data.pagination.currentPage} of ${data.pagination.lastPage}`;
        document.getElementById('enrollment_prev').disabled = data.pagination.currentPage <= 1;
        document.getElementById('enrollment_next').disabled = data.pagination.currentPage >= data.pagination.lastPage;
    }

    function escapeEnrollmentText(value) { const node=document.createElement('div'); node.textContent=value ?? ''; return node.innerHTML; }
    function filterEnrollmentAssessments() {
        const subjectId = document.getElementById('enrollment_subject_filter').value;
        const select = document.getElementById('enrollment_assessment');
        const filtered = enrollmentAssessments.filter(item => subjectId ? String(item.subjectId) === subjectId : true);
        select.innerHTML = '<option value="">Select an assessment</option>' + filtered.map(item => `<option value="${item.value}">${escapeEnrollmentText(item.label)}</option>`).join('');
        syncResetAssessmentButton();
    }
    function syncResetAssessmentButton() { document.getElementById('reset_assessment_take').disabled = !enrollmentSelectedUserId || !document.getElementById('enrollment_assessment').value; }
    document.getElementById('enrollment_assessment').addEventListener('change', syncResetAssessmentButton);
    document.getElementById('enrollment_prev').addEventListener('click', async () => { if(enrollmentPage>1){enrollmentPage--;enrollmentSelectedUserId=null;await loadCourseEnrollments();} });
    document.getElementById('enrollment_next').addEventListener('click', async () => { enrollmentPage++;enrollmentSelectedUserId=null;await loadCourseEnrollments(); });
    document.getElementById('reset_assessment_take').addEventListener('click', function () {
        const assessment = document.getElementById('enrollment_assessment').value;
        const selectedRow = document.querySelector(`#enrollment_students_body tr[data-user-id="${enrollmentSelectedUserId}"]`);
        const studentName = selectedRow?.querySelector('strong')?.textContent || 'this learner';
        const assessmentName = document.getElementById('enrollment_assessment').selectedOptions[0]?.textContent || 'this assessment';
        pendingAssessmentReset = {assessment, studentName, assessmentName, userId:enrollmentSelectedUserId};
        resetConfirmationCompleted = false;
        document.getElementById('reset_confirmation_title').textContent = 'Confirm Assessment Reset';
        document.getElementById('reset_confirmation_message').textContent = `Reset ${assessmentName} for ${studentName}? This removes the learner's saved attempts for the selected assessment and enables another take.`;
        document.getElementById('reset_confirmation_cancel').style.display = '';
        const confirmButton = document.getElementById('reset_confirmation_confirm');
        confirmButton.textContent = 'Reset Exam Take';
        confirmButton.disabled = false;
        openModal('resetAssessmentConfirmModal');
    });

    function closeResetConfirmationModal() {
        closeModal('resetAssessmentConfirmModal');
        pendingAssessmentReset = null;
        resetConfirmationCompleted = false;
        syncResetAssessmentButton();
    }

    function closeUnenrollModal() {
        closeModal('unenrollStudentModal');
        pendingUnenrollment = null;
        unenrollmentCompleted = false;
    }

    document.getElementById('unenroll_modal_confirm').addEventListener('click', async function () {
        if (unenrollmentCompleted) { closeUnenrollModal(); await loadCourseEnrollments(); return; }
        if (!pendingUnenrollment) return;
        this.disabled = true; this.textContent = 'Removing…';
        let response; let data;
        try {
            response = await fetch(`/admin/content/courses/${enrollmentCourseId}/enrollments/${pendingUnenrollment.userId}/unenroll`, {method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':@json(csrf_token()),'X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});
            data = await response.json();
        } catch (error) {
            response = {ok:false}; data = {success:false,message:'The learner could not be unenrolled. Please try again.'};
        }
        unenrollmentCompleted = true;
        document.getElementById('unenroll_modal_title').textContent = response.ok && data.success ? 'Student Unenrolled' : 'Unenrollment Unsuccessful';
        document.getElementById('unenroll_modal_message').textContent = data.message || 'The request could not be completed.';
        document.getElementById('unenroll_modal_cancel').style.display = 'none';
        this.textContent = 'Close'; this.disabled = false;
    });

    document.getElementById('reset_confirmation_confirm').addEventListener('click', async function () {
        if (resetConfirmationCompleted) { closeResetConfirmationModal(); return; }
        if (!pendingAssessmentReset) return;
        this.disabled = true;
        this.textContent = 'Resetting…';
        const {assessment, userId} = pendingAssessmentReset;
        let response;
        let data;
        try {
            response = await fetch(`/admin/content/courses/${enrollmentCourseId}/assessment-attempts/reset`, {method:'POST',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':@json(csrf_token()),'X-Requested-With':'XMLHttpRequest'},credentials:'same-origin',body:JSON.stringify({user_id:userId,assessment,batch_id:enrollmentBatchId})});
            data = await response.json();
        } catch (error) {
            response = {ok:false};
            data = {success:false,message:'The reset could not be completed. Please check the connection and try again.'};
        }
        resetConfirmationCompleted = true;
        document.getElementById('reset_confirmation_title').textContent = response.ok && data.success ? 'Assessment Reset Complete' : 'Reset Unsuccessful';
        document.getElementById('reset_confirmation_message').textContent = data.message || (response.ok ? 'The learner may retake the selected assessment.' : 'Unable to reset the assessment attempt.');
        document.getElementById('reset_confirmation_cancel').style.display = 'none';
        this.textContent = 'Close';
        this.disabled = false;
    });
    function openEditCourseModal(id, title, desc, approvalStatus) {
        document.getElementById('edit_course_title').value = title;
        document.getElementById('edit_course_description').value = desc;
        if (document.getElementById('edit_course_approval_status')) document.getElementById('edit_course_approval_status').value = approvalStatus;
        document.getElementById('editCourseForm').action = '/admin/content/courses/' + id;
        openModal('editCourseModal');
    }
</script>
@endsection
