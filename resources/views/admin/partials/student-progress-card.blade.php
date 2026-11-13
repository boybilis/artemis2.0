<style>
    #user-progress-retry[hidden] { display:none !important; }
    .user-subject-progress .data-table { min-width:0; }
    body .user-subject-progress .data-table thead th { background:#48515f;color:#fff; }
    .user-subject-progress .data-table :is(th,td) { padding:.55rem .65rem;overflow-wrap:anywhere; }
    .user-subject-progress .data-table tbody tr:nth-child(odd) td { background:var(--surface-solid); }
    .user-subject-progress .data-table tbody tr:nth-child(even) td { background:color-mix(in srgb,var(--surface-solid) 92%,var(--text)); }
    .user-progress-table { margin-top:1rem; }
    .user-progress-table nav { margin-top:.65rem; }
    .user-subject-progress .subject-progress-table tbody td { background:var(--surface-solid); }
    .user-subject-progress .subject-progress-table .subject-progress-alt td { background:color-mix(in srgb,var(--surface-solid) 92%,var(--text)); }
    .subject-progress-name,.subject-progress-meter { display:flex;align-items:center;gap:.55rem; }
    .subject-progress-meter progress { width:clamp(2.5rem,12vw,9rem);height:.55rem;accent-color:var(--brand-aqua-strong); }
    .subject-progress-expand { flex:none;width:2rem;height:2rem;border:0;border-radius:6px;background:transparent;color:var(--text);cursor:pointer; }
    .subject-progress-expand:hover { background:var(--border); }
    .subject-progress-expand:focus-visible { outline:2px solid var(--brand-aqua); }
    .subject-progress-expand span { display:inline-block; }
    .subject-progress-expand[aria-expanded="true"] span { transform:rotate(90deg); }
    .subject-progress-details[hidden] { display:none; }
    .subject-progress-toolbar { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.65rem; }
    .subject-progress-toolbar h3 { margin:0; }
    .subject-progress-switch { position:relative;display:inline-flex;align-items:center;gap:.55rem;margin-inline-start:auto;min-height:44px;cursor:pointer;color:var(--text); }
    .subject-progress-switch input { position:absolute;width:1px;height:1px;opacity:0; }
    .subject-progress-switch-track { display:inline-flex;align-items:center;width:2.3rem;height:1.25rem;padding:2px;border-radius:999px;background:#64748b; }
    .subject-progress-switch-track::after { content:"";width:1rem;height:1rem;border-radius:50%;background:#fff; }
    .subject-progress-switch input:checked + .subject-progress-switch-track { justify-content:flex-end;background:var(--brand-aqua-strong); }
    .subject-progress-switch input:focus-visible + .subject-progress-switch-track { outline:2px solid var(--brand-aqua);outline-offset:3px; }
    .subject-progress-details dl { display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,11rem),1fr));gap:.75rem;margin:.25rem 0 .25rem clamp(1.25rem,4vw,2.5rem);padding:.25rem 0 .25rem .8rem;border-inline-start:2px solid var(--border); }
    .subject-progress-details dt { font-weight:700; }
    .subject-progress-details dd { margin:0; }
    .subject-assessment-result + .subject-assessment-result { margin-top:.55rem; }
    .subject-assessment-attempts { display:grid;gap:.25rem;list-style:none;margin:.25rem 0 0;padding-inline-start:.55rem; }
</style>
<section class="panel user-subject-progress" style="margin-top:1rem" aria-labelledby="user-subject-progress-title">
    <p class="panel-label">Progress</p>
    <h2 class="panel-title" id="user-subject-progress-title">Student Progress</h2>
    @if(isset($studentSearchUrl))
        <div class="field">
            <label for="progress-student-search">Search student name</label>
            <input id="progress-student-search" type="search" maxlength="120" autocomplete="off" data-search-url="{{ $studentSearchUrl }}">
        </div>
        <div class="field">
            <label for="progress-student">Student</label>
            <select id="progress-student" disabled><option value="">Select a student</option></select>
        </div>
        <p id="progress-student-message" class="muted" role="status"></p>
    @endif
    <div class="field">
        <label for="user-progress-course">Enrolled course</label>
        <select id="user-progress-course" data-progress-url="{{ $progressUrl }}" @disabled($enrolledCourses->isEmpty())>
            <option value="">Select a course</option>
            @foreach($enrolledCourses as $enrollment)
                <option value="{{ $enrollment['batch_id'] }}:{{ $enrollment['course_id'] }}">{{ $enrollment['label'] }}</option>
            @endforeach
        </select>
    </div>
    <p id="user-progress-message" class="muted" role="status">{{ $enrolledCourses->isEmpty() ? (isset($studentSearchUrl) ? 'Search for and select a student first.' : 'This learner has no active course enrollments.') : 'Select an enrolled course to load progress.' }}</p>
    <button type="button" class="btn-ghost" id="user-progress-retry" hidden>Retry</button>
    <div id="user-progress-results"></div>
</section>
<script>
(() => {
    const course = document.getElementById('user-progress-course');
    const results = document.getElementById('user-progress-results');
    const message = document.getElementById('user-progress-message');
    const retry = document.getElementById('user-progress-retry');
    let pending, lastUrl;
    const load = async url => {
        pending?.abort();
        const request = new AbortController();
        pending = request;
        lastUrl = url;
        message.textContent = 'Loading progress…';
        retry.hidden = true;
        results.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, {
                headers: {Accept:'text/html','X-Requested-With':'XMLHttpRequest','X-Admin-Table':'user-course-progress'},
                credentials: 'same-origin', signal: request.signal,
            });
            if (!response.ok) throw new Error();
            const html = new DOMParser().parseFromString(await response.text(), 'text/html');
            const fragment = html.querySelector('[data-user-progress-tables]');
            if (!fragment) throw new Error();
            if (request.signal.aborted) return;
            results.replaceChildren(fragment);
            message.textContent = '';
        } catch {
            if (!request.signal.aborted) {
                message.textContent = 'Unable to load progress. Please try again.';
                retry.hidden = false;
            }
        } finally {
            if (!request.signal.aborted) results.removeAttribute('aria-busy');
        }
    };
    course.addEventListener('change', () => {
        pending?.abort();
        results.replaceChildren();
        results.removeAttribute('aria-busy');
        retry.hidden = true;
        if (!course.value) {
            message.textContent = 'Select an enrolled course to load progress.';
            return;
        }
        const [batchId, courseId] = course.value.split(':');
        const url = new URL(course.dataset.progressUrl);
        const student = document.getElementById('progress-student');
        if (student) url.searchParams.set('learner_id', student.value);
        url.searchParams.set('batch_id', batchId);
        url.searchParams.set('course_id', courseId);
        load(url.href);
    });
    const setSubjectExpanded = (button, expanded) => {
        const details = document.getElementById(button.getAttribute('aria-controls'));
        if (!details) return;
        details.hidden = !expanded;
        button.setAttribute('aria-expanded', String(expanded));
    };
    const subjectControls = () => [...results.querySelectorAll('.subject-progress-expand')];
    const isExpanded = button => button.getAttribute('aria-expanded') === 'true';
    results.addEventListener('change', event => {
        if (event.target.matches('[data-subject-progress-toggle-all]')) {
            subjectControls().forEach(button => setSubjectExpanded(button, event.target.checked));
        }
    });
    results.addEventListener('click', event => {
        const expand = event.target.closest('.subject-progress-expand');
        if (expand) {
            setSubjectExpanded(expand, !isExpanded(expand));
            const toggleAll = results.querySelector('[data-subject-progress-toggle-all]');
            if (toggleAll) toggleAll.checked = subjectControls().every(isExpanded);
            return;
        }
        const link = event.target.closest('.pagination a');
        if (!link) return;
        event.preventDefault();
        load(link.href);
    });
    const search = document.getElementById('progress-student-search');
    if (search) {
        const student = document.getElementById('progress-student');
        const status = document.getElementById('progress-student-message');
        let timer, searchRequest, courseRequest;
        const resetCourses = () => {
            courseRequest?.abort();
            course.replaceChildren(new Option('Select a course', ''));
            course.disabled = true;
            course.dispatchEvent(new Event('change'));
        };
        const getJson = async (url, signal) => {
            const response = await fetch(url, {headers:{Accept:'application/json'},signal});
            if (!response.ok) throw new Error();
            return response.json();
        };
        search.addEventListener('input', () => {
            clearTimeout(timer);
            searchRequest?.abort();
            student.replaceChildren(new Option('Select a student', ''));
            student.disabled = true;
            resetCourses();
            const name = search.value.trim();
            message.textContent = 'Select a student to load enrolled courses.';
            status.textContent = name.length < 2 ? 'Enter at least two characters.' : 'Searching…';
            if (name.length < 2) return;
            timer = setTimeout(async () => {
                const request = searchRequest = new AbortController();
                const url = new URL(search.dataset.searchUrl);
                url.searchParams.set('name', name);
                try {
                    const {students, more} = await getJson(url, request.signal);
                    if (request.signal.aborted) return;
                    students.forEach(row => student.add(new Option(row.label, row.id)));
                    student.disabled = !students.length;
                    status.textContent = more ? 'Showing the first 20 matches. Refine your search for more.' : (students.length ? 'Choose a student from the results.' : 'No students found.');
                } catch {
                    if (!request.signal.aborted) status.textContent = 'Unable to search. Try typing the name again.';
                }
            }, 250);
        });
        student.addEventListener('change', async () => {
            resetCourses();
            if (!student.value) {
                message.textContent = 'Select a student to load enrolled courses.';
                return;
            }
            const request = courseRequest = new AbortController();
            const url = new URL(course.dataset.progressUrl);
            url.searchParams.set('learner_id', student.value);
            message.textContent = 'Loading enrolled courses…';
            try {
                const {courses} = await getJson(url, request.signal);
                if (request.signal.aborted) return;
                courses.forEach(row => course.add(new Option(row.label, row.batch_id + ':' + row.course_id)));
                course.disabled = !courses.length;
                message.textContent = courses.length ? 'Select an enrolled course to load progress.' : 'This learner has no active course enrollments.';
            } catch {
                if (!request.signal.aborted) message.textContent = 'Unable to load enrolled courses. Select the student again.';
            }
        });
    }
    retry.addEventListener('click', () => load(lastUrl));
})();
</script>
