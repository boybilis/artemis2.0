// Independent V2 design renderer. Data and assessment services are shared with V1.
function renderTestBankWorkspaceV2(workspace) {
    const workspaceArea = $('test-bank-workspace-area');
    if (!workspaceArea) return;
    const expiresAt = workspace.expiresAt
        ? new Date(workspace.expiresAt).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'})
        : 'No expiration';
    const subjectCards = (workspace.subjects || []).map((subject, index) => `
        <article class="test-bank-subject-card">
            <div class="test-bank-subject-card-head">
                <span class="test-bank-subject-icon tone-${(index % 3) + 1}"><i data-lucide="${index % 3 === 1 ? 'brain' : index % 3 === 2 ? 'notebook-tabs' : 'book-open'}"></i></span>
                <small>${Number(subject.premadeTestCount || 0)} TESTS</small>
            </div>
            <h3>${escapeHtml(subject.title)}</h3>
            <p>${Number(subject.questionCount || 0)} approved practice questions with detailed rationales</p>
            <div class="test-bank-subject-stats"><span><strong>${Number(subject.completedTests || 0)}</strong><small>Completed</small><small class="test-bank-subject-required">5 required</small></span><span><strong>${subject.averageScore === null ? '--' : `${Number(subject.averageScore)}%`}</strong><small>average</small></span></div>
            <div class="test-bank-subject-progress"><div><span>Subject progress</span><strong>${Number(subject.progress || 0)}%</strong></div><div><span style="width:${Number(subject.progress || 0)}%"></span></div></div>
            <div class="test-bank-subject-test-controls"><select class="test-bank-subject-test-type" aria-label="Select test type for ${escapeHtml(subject.title)}"><option value="warm_up">Warm-up</option><option value="mastery">Mastery Test</option></select><button type="button" class="test-bank-subject-start" data-test-bank-subject="${Number(subject.id)}" ${Number(subject.questionCount || 0) ? '' : 'disabled'}>Start Test</button></div>
        </article>`).join('');
    const renderPremadeCard = test => `
        <article class="test-bank-premade-card">
            <div><small>${test.isSimulation ? 'SIMULATION TEST' : 'PREMADE QUIZ'}</small><h3>${escapeHtml(test.title)}</h3><p>${escapeHtml(test.description || 'Admin-curated randomized practice quiz.')}</p>${test.timeLimitMinutes ? `<p>${Number(test.timeLimitMinutes)} minute time limit</p>` : ''}</div>
            <div class="test-bank-premade-actions"><span>${Number(test.itemCount || 0)} items</span><button type="button" class="test-bank-start-test" data-test-bank-id="${Number(workspace.id)}" data-test-bank-quiz-id="${Number(test.id)}" ${(Number(test.itemCount || 0) && !test.locked) ? '' : 'disabled'}>${test.locked ? 'Locked' : 'Start Test'}</button></div>
        </article>`;
    const simulationCards = (workspace.premadeTests || []).filter(test => test.isSimulation).map(renderPremadeCard).join('');
    const premadeCards = (workspace.premadeTests || []).filter(test => !test.isSimulation).map(renderPremadeCard).join('');
    const historyAttemptCounts = (workspace.history || []).reduce((counts, attempt) => {
        counts[attempt.quizId] = (counts[attempt.quizId] || 0) + 1;
        return counts;
    }, {});
    const historyRows = (workspace.history || []).map(attempt => {
        const title = attempt.quizType === 'subject'
            ? attempt.title.replace(/\s*·\s*(Warm-up|Mastery Test)$/i, '')
            : attempt.title;
        const attemptCount = historyAttemptCounts[attempt.quizId] || 1;
        const takenDate = attempt.takenAt ? new Date(attempt.takenAt) : null;
        const takenAt = takenDate && !Number.isNaN(takenDate.getTime())
            ? takenDate.toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric', timeZone:'Asia/Manila'})
            : '';
        const timing = attempt.timeLimitMinutes ? `Timed (${Number(attempt.timeLimitMinutes)} minutes)` : 'Untimed';
        const subtitle = [attempt.testTypeLabel, `${Number(attempt.totalItems || 0)} questions`, timing, `${attemptCount} ${attemptCount === 1 ? 'attempt' : 'attempts'}`].filter(Boolean).join(' · ');
        const percentage = Number(attempt.totalItems || 0) > 0 ? Math.round(Number(attempt.correctItems || 0) / Number(attempt.totalItems) * 100) : 0;
        return `<article class="test-bank-tracker-row" data-tracker-record data-cancelled="${attempt.cancelled ? 'true' : 'false'}">
            <div class="test-bank-tracker-copy"><h3>${escapeHtml(title)}${takenAt ? ` <span class="test-bank-tracker-taken-date">· (${escapeHtml(takenAt)})</span>` : ''}</h3><div class="test-bank-tracker-subtitle">${escapeHtml(subtitle)}</div><p class="test-bank-tracker-coverage"><strong>SUBJECTS:</strong> ${escapeHtml(attempt.coverage || 'Not specified')}</p></div>
            <div class="test-bank-tracker-metric"><small>SCORE</small><strong>${attempt.cancelled ? '—' : `${percentage}%`}</strong></div>
            <div class="test-bank-tracker-metric"><small>RESULT</small><strong class="${attempt.cancelled ? 'cancelled' : attempt.passed ? 'passed' : 'failed'}">${attempt.cancelled ? 'Cancelled' : attempt.passed ? 'Passed' : 'Failed'}</strong></div>
            <div class="test-bank-tracker-metric"><small>RANK</small><strong>${attempt.quizType === 'premade' ? (attempt.rank ?? '--') : '--'}</strong></div>
            <div class="test-bank-tracker-action">${attempt.cancelled ? '<small>No review available</small>' : `${attempt.passed ? '' : '<small class="test-bank-retake-badge">Retake recommended</small>'}<div class="test-bank-tracker-buttons"><button type="button" class="test-bank-history-review" data-test-bank-attempt="${Number(attempt.id)}">Review</button><button type="button" class="test-bank-history-retake" data-retake-attempt="${Number(attempt.id)}">Re-take</button></div>`}</div>
        </article>`;
    }).join('');
    const builderSubjects = (workspace.subjects || []).map(subject => `
        <label class="test-bank-builder-subject"><input type="checkbox" name="subject_ids" value="${Number(subject.id)}" ${Number(subject.questionCount || 0) ? '' : 'disabled'}><span><strong>${escapeHtml(subject.title)}</strong><small>${Number(subject.questionCount || 0)} available questions</small></span></label>`).join('');
    const learnerQuizCards = (workspace.learnerQuizzes || []).map(quiz => `
        <article class="test-bank-builder-quiz-card" data-builder-quiz-card>
            <div><small>MY PRACTICE TEST</small><h3>${escapeHtml(quiz.title)}</h3><p>${Number(quiz.itemCount || 0) === 20 ? 'Mastery Test' : 'Warm-up'} · ${Number(quiz.itemCount || 0)} questions · ${quiz.timed ? `${Number(quiz.timeLimitMinutes)} minutes` : 'Untimed'} · ${Number(quiz.attemptCount || 0)} attempt${Number(quiz.attemptCount || 0) === 1 ? '' : 's'}</p><p class="test-bank-builder-quiz-subjects"><strong>Subjects:</strong> ${escapeHtml(quiz.coverageLabel || (quiz.subjects || []).map(subject => subject.title).join(', ') || 'No subjects listed')}</p></div>
            <div class="test-bank-builder-quiz-actions"><button type="button" class="test-bank-start-test" data-test-bank-id="${Number(workspace.id)}" data-test-bank-quiz-id="${Number(quiz.id)}">Start Test</button><button type="button" class="test-bank-builder-edit test-bank-builder-icon-button" data-builder-edit="${Number(quiz.id)}" aria-label="Edit ${escapeHtml(quiz.title)}"><i data-lucide="pencil"></i><span class="test-bank-builder-action-tooltip" aria-hidden="true">Edit</span></button><button type="button" class="test-bank-builder-delete test-bank-builder-icon-button" data-builder-delete="${Number(quiz.id)}" aria-label="Delete ${escapeHtml(quiz.title)}"><i data-lucide="trash-2"></i><span class="test-bank-builder-action-tooltip" aria-hidden="true">Delete</span></button></div>
        </article>`).join('');
    const warmUpPassed = Number(workspace.simulationProgress?.warmUpPassed || 0);
    const warmUpRequired = Number(workspace.simulationProgress?.warmUpRequired || 10);
    const masteryPassed = Number(workspace.simulationProgress?.masteryPassed || 0);
    const masteryRequired = Number(workspace.simulationProgress?.masteryRequired || 5);
    const simulationUnlocked = Boolean(workspace.simulationProgress?.unlocked);
    const requiredPassed = Math.min(warmUpPassed, warmUpRequired) + Math.min(masteryPassed, masteryRequired);
    const totalRequired = warmUpRequired + masteryRequired;
    const requirementProgress = totalRequired ? Math.round((requiredPassed / totalRequired) * 100) : 100;
    const completedAttempts = (workspace.history || []).filter(attempt => !attempt.cancelled).length;
    const cancelledAttempts = (workspace.history || []).filter(attempt => attempt.cancelled).length;
    const simulationEligibilityCard = `<article class="test-bank-simulation-eligibility ${simulationUnlocked ? 'unlocked' : 'locked'}">
        <header class="test-bank-simulation-eligibility-head"><div><small>SIMULATION TEST ELIGIBILITY</small><h2>${simulationUnlocked ? 'Simulation Tests Unlocked' : 'Complete the requirements to unlock'}</h2><p>Only passed custom exams created in Test Builder count toward these requirements.</p></div><span><i data-lucide="${simulationUnlocked ? 'lock-keyhole-open' : 'lock-keyhole'}"></i>${simulationUnlocked ? 'Unlocked' : 'Locked'}</span></header>
        <div class="test-bank-simulation-criteria">
            <div><div><strong>Warm Up exams</strong><span>${warmUpPassed}/${warmUpRequired} passed</span></div><div class="test-bank-simulation-progress"><span style="width:${Math.min(100, warmUpRequired ? (warmUpPassed / warmUpRequired) * 100 : 100)}%"></span></div><small>Pass ${warmUpRequired} custom 10-item Warm Up exams.</small></div>
            <div><div><strong>Mastery Tests</strong><span>${masteryPassed}/${masteryRequired} passed</span></div><div class="test-bank-simulation-progress"><span style="width:${Math.min(100, masteryRequired ? (masteryPassed / masteryRequired) * 100 : 100)}%"></span></div><small>Pass ${masteryRequired} custom 20-item Mastery Tests.</small></div>
        </div>
    </article>`;

    workspaceArea.innerHTML = `
        <div class="test-bank-hero-grid">
        <section class="test-bank-workspace-hero">
            <span class="test-bank-workspace-mark"><i data-lucide="book-open"></i></span>
            <div><small>ACTIVE TEST BANK</small><h1>${escapeHtml(workspace.title)}</h1><p style="white-space:pre-line">${escapeHtml(workspace.description?.trim() || 'Build exam confidence with practice tests, detailed rationales, and progress tracking.')}</p></div>
        </section>
        <section class="test-bank-subscription-panel">
            <span><i data-lucide="credit-card"></i></span>
            <div><small>YOUR ${escapeHtml(workspace.title).toUpperCase()} SUBSCRIPTION</small><h3>${Number(workspace.accessDays)}-Day Test Bank Access</h3><p>Until ${expiresAt}${workspace.daysRemaining === null ? '' : ` · ${Number(workspace.daysRemaining)} days remaining`}</p></div>
            <div class="test-bank-subscription-actions"><span class="test-bank-enrolled-capsule">Enrolled</span><button type="button" class="btn-ghost" id="extend-test-bank-btn">Extend Subscription</button></div>
        </section>
        </div>
        <section class="test-bank-study-path">
            <div class="test-bank-study-path-heading"><h2>Guided Study Path</h2><p>Work through each phase at your own pace.</p></div>
            <div class="test-bank-study-path-grid">
                <article class="test-bank-study-phase active"><header class="test-bank-phase-header"><small>PHASE 01</small></header><h3>Warm Up Quizzes</h3><p>Create unlimited 10-item quizzes to start your daily review journey.</p><button type="button" data-guided-builder="10">Select Warm Up</button></article>
                <article class="test-bank-study-phase"><header class="test-bank-phase-header"><small>PHASE 02</small></header><h3>Mastery Tests</h3><p>Create unlimited 20-item tests for mastery. 60% is the passing score. Aim for 100% to gain confidence that you have mastered the concepts and are on the right track.</p><button type="button" data-guided-builder="20">Select Mastery Tests</button></article>
                <article class="test-bank-study-phase ${workspace.simulationProgress?.unlocked ? 'unlocked' : 'locked'}"><header class="test-bank-phase-header"><small>PHASE 03</small></header><h3>Simulation Test</h3><p>Practice in an exam setting with Simulation 1 and Simulation 2. Retakes are unlimited. Each timed test has 100–150 items and cannot be paused. Unlock after passing at least 10 Warm Up Quizzes and 5 Mastery Tests.</p><b>${Number(workspace.simulationProgress?.warmUpPassed || 0)}/10 Warm Up passed · ${Number(workspace.simulationProgress?.masteryPassed || 0)}/5 Mastery Tests passed</b>${workspace.simulationProgress?.unlocked ? '<button type="button" data-guided-simulation>Simulation Test Unlocked</button>' : '<strong><i data-lucide="lock-keyhole"></i> Simulation locked</strong>'}</article>
            </div>
        </section>
        <div class="test-bank-workspace-tabs" role="tablist">
            <button type="button" class="active" data-test-bank-tab="builder"><i data-lucide="wand-sparkles"></i> Test Builder</button>
            <button type="button" data-test-bank-tab="premade"><i data-lucide="book-open"></i> Pre-made Tests</button>
            <button type="button" data-test-bank-tab="progress"><i data-lucide="chart-no-axes-column-increasing"></i> Progress Tracker</button>
        </div>
        <section class="test-bank-tab-panel hidden" data-test-bank-panel="premade">
            ${simulationCards ? `<div class="test-bank-simulation-grid">${simulationCards}</div>` : ''}
            <div class="test-bank-selected-phase" aria-live="polite"><small>SELECTED PHASE</small><strong data-selected-phase-label>Warm Up Quizzes · 10 items</strong><span>Choose a phase above to change this test.</span></div>
            <div class="test-bank-panel-heading"><div><h2>${/prometric/i.test(workspace.title) ? 'Prometrics RN Pre-Made Tests by subject' : `${escapeHtml(workspace.title)} Pre-Made Tests by subject`}</h2><p>Curated from approved questions in the course question bank.</p></div><div id="test-bank-carousel-controls" class="subject-carousel-controls" aria-label="Premade test carousel controls"><span id="test-bank-carousel-range" class="subject-carousel-range" aria-live="polite"></span><button id="test-bank-carousel-prev" type="button" aria-label="Show previous premade tests"><i data-lucide="chevron-left"></i></button><button id="test-bank-carousel-next" type="button" aria-label="Show next premade tests"><i data-lucide="chevron-right"></i></button></div></div>
            ${premadeCards ? `<div class="test-bank-premade-grid">${premadeCards}</div>` : ''}
            <div class="test-bank-subject-grid">${subjectCards || '<div class="empty-course-filter"><p>No approved subject questions yet.</p></div>'}</div>
        </section>
        <section class="test-bank-tab-panel" data-test-bank-panel="builder">
            <div class="test-bank-builder-layout">
                <form id="test-bank-builder-form" class="test-bank-builder-form">
                    <header class="test-bank-history-heading test-bank-builder-card-header"><h2>Build a Practice Test</h2><p>Select one or more subjects, choose the number of questions, and optionally add a timer.</p></header>
                    <div class="test-bank-builder-settings"><label><span>Quiz type</span><select class="form-control" name="item_count" required><option value="10">Warm-up</option><option value="20">Mastery Test</option></select></label><label class="test-bank-builder-timed"><input type="checkbox" name="timed"><span>Timed exam</span></label><label id="test-bank-builder-minutes" class="hidden"><span>Time limit in minutes</span><input class="form-control" type="number" name="time_limit_minutes" min="1" max="600" value="30"></label></div>
                    <fieldset aria-labelledby="test-bank-builder-subject-title"><div class="test-bank-builder-subject-heading"><span id="test-bank-builder-subject-title">Subjects</span><label><input type="checkbox" id="test-bank-builder-select-all"> Select all</label></div><div class="test-bank-builder-subjects">${builderSubjects}</div></fieldset>
                    <div class="test-bank-builder-form-actions"><button type="button" id="test-bank-builder-cancel" class="test-bank-builder-cancel hidden">Cancel Edit</button><button type="submit" class="test-bank-builder-create"><i data-lucide="wand-sparkles"></i> Create Practice Test</button></div>
                </form>
                <div class="test-bank-builder-saved"><header class="test-bank-history-heading test-bank-builder-card-header"><h2>My Practice Tests</h2><p>Your saved tests can be taken again anytime while your subscription is active.</p></header><div class="test-bank-builder-list">${learnerQuizCards || '<div class="test-bank-empty-panel"><i data-lucide="clipboard-list"></i><h2>No saved practice tests</h2><p>Use the builder to create your first test.</p></div>'}</div>${(workspace.learnerQuizzes || []).length > 5 ? '<div class="test-bank-builder-pagination"><span id="test-bank-builder-page-range"></span><div><button type="button" id="test-bank-builder-prev"><i data-lucide="chevron-left"></i> Previous</button><button type="button" id="test-bank-builder-next">Next <i data-lucide="chevron-right"></i></button></div></div>' : ''}</div>
            </div>
        </section>
        <section class="test-bank-tab-panel hidden" data-test-bank-panel="progress"><div class="test-bank-history-heading"><h2>Progress Tracker</h2><p>Every test taken, with its result and rank. Passing score is 60%. Rank compares each subscribed learner’s highest completed score percentage in this Test Bank.</p></div>${simulationEligibilityCard}<section class="test-bank-progress-report"><div class="test-bank-progress-report-head"><div><span class="test-bank-progress-report-icon"><i data-lucide="chart-no-axes-column-increasing"></i></span><div><small>REQUIRED CUSTOM TESTS</small><h2>Progress Tracker</h2><p>Passed required tests ÷ total required tests × 100</p></div></div><div><strong>${Math.max(0, Math.min(100, Number(workspace.readiness || 0)))}%</strong><span>Exam readiness</span><div><i style="width:${Math.max(0, Math.min(100, Number(workspace.readiness || 0)))}%"></i></div></div></div><div class="test-bank-progress-stats"><div><strong>${totalRequired}</strong><span>Total required</span></div><div><strong>${completedAttempts}</strong><span>Completed attempts</span></div><div><strong>${cancelledAttempts}</strong><span>Cancelled</span></div><div><strong>${requiredPassed}</strong><span>Requirements passed</span></div></div><div class="test-bank-history-table-tools"><label><i data-lucide="search"></i><input id="test-bank-history-search" type="search" placeholder="Search progress records" aria-label="Search progress records"></label><div class="test-bank-history-filters"><button type="button" id="test-bank-show-cancelled" class="test-bank-cancelled-toggle" role="switch" aria-checked="false"><span class="test-bank-toggle-track" aria-hidden="true"></span>Show Cancelled Tests</button><span id="test-bank-history-count" aria-live="polite"></span></div></div><div class="test-bank-tracker-list">${historyRows || '<p class="test-bank-history-empty">No Test Bank attempts yet.</p>'}</div><div id="test-bank-history-pagination" class="test-bank-history-pagination"><span id="test-bank-history-range"></span><div><button type="button" id="test-bank-history-prev"><i data-lucide="chevron-left"></i> Previous</button><span id="test-bank-history-pages"></span><button type="button" id="test-bank-history-next">Next <i data-lucide="chevron-right"></i></button></div></div></section></section>`;

    $('extend-test-bank-btn')?.addEventListener('click', event => startTestBankCheckout(workspace.id, event.currentTarget));
    const startSavedTest = async (quizId, button, attempt = null) => {
        const premade = (workspace.premadeTests || []).find(test => Number(test.id) === quizId);
        const learnerQuiz = (workspace.learnerQuizzes || []).find(test => Number(test.id) === quizId);
        const subjectTitles = premade
            ? (workspace.subjects || []).filter(subject => (premade.subjectIds || []).map(Number).includes(Number(subject.id))).map(subject => subject.title)
            : learnerQuiz ? (learnerQuiz.subjects || []).map(subject => subject.title)
                : attempt?.coverage ? [attempt.coverage] : [];
        const shouldStart = await showTestBankInstructions({
            title: premade?.title || learnerQuiz?.title || attempt?.title || 'Test Bank Exam',
            itemCount: premade?.itemCount ?? learnerQuiz?.itemCount ?? attempt?.totalItems ?? 0,
            timeLimitMinutes: premade?.timeLimitMinutes ?? learnerQuiz?.timeLimitMinutes ?? attempt?.timeLimitMinutes ?? null,
            coverage: subjectTitles,
        });
        if (shouldStart) await startTestBankPremadeQuiz(Number(workspace.id), quizId, button);
    };
    workspaceArea.querySelectorAll('.test-bank-start-test').forEach(button => button.addEventListener('click', () => {
        startSavedTest(Number(button.dataset.testBankQuizId), button);
    }));
    workspaceArea.querySelectorAll('[data-retake-attempt]').forEach(button => button.addEventListener('click', () => {
        const attempt = (workspace.history || []).find(item => Number(item.id) === Number(button.dataset.retakeAttempt));
        if (!attempt || attempt.cancelled) return;
        const premade = (workspace.premadeTests || []).find(test => Number(test.id) === Number(attempt.quizId));
        if (premade?.locked) {
            showToast('Complete the requirements to unlock this simulation test.', 'info');
            return;
        }
        startSavedTest(Number(attempt.quizId), button, attempt);
    }));
    workspaceArea.querySelectorAll('.test-bank-subject-start').forEach(button => button.addEventListener('click', async () => {
        const originalText = button.textContent;
        const type = button.parentElement?.querySelector('.test-bank-subject-test-type')?.value || 'warm_up';
        const subject = (workspace.subjects || []).find(item => Number(item.id) === Number(button.dataset.testBankSubject));
        const shouldStart = await showTestBankInstructions({
            title: `${subject?.title || 'Subject'} · ${type === 'mastery' ? 'Mastery Test' : 'Warm-up'}`,
            itemCount: type === 'mastery' ? 20 : 10,
            timeLimitMinutes: null,
            coverage: subject ? [subject.title] : [],
        });
        if (!shouldStart) return;
        button.disabled = true;
        button.textContent = 'Preparing…';
        try {
            const result = await apiRequest(`/api/test-banks/${workspace.id}/subjects/${Number(button.dataset.testBankSubject)}/quizzes`, 'POST', {quiz_type:type});
            if (result.message && result.message !== 'Subject test ready.') showToast(result.message, 'info');
            button.disabled = false;
            button.textContent = originalText;
            await startTestBankPremadeQuiz(workspace.id, Number(result.quizId), button);
        } catch (error) {
            button.disabled = false;
            button.textContent = originalText;
            showToast(error.message || 'Unable to prepare this subject test.', 'error');
        }
    }));
    const builderForm = $('test-bank-builder-form');
    let builderEditingQuizId = null;
    const timedInput = builderForm?.elements.namedItem('timed');
    const minutesField = $('test-bank-builder-minutes');
    const builderCancel = $('test-bank-builder-cancel');
    const selectAllSubjects = $('test-bank-builder-select-all');
    const subjectCheckboxes = [...(builderForm?.querySelectorAll('[name="subject_ids"]:not(:disabled)') || [])];
    const syncSelectAllSubjects = () => {
        if (!selectAllSubjects) return;
        const selectedCount = subjectCheckboxes.filter(input => input.checked).length;
        selectAllSubjects.checked = subjectCheckboxes.length > 0 && selectedCount === subjectCheckboxes.length;
        selectAllSubjects.indeterminate = selectedCount > 0 && selectedCount < subjectCheckboxes.length;
    };
    selectAllSubjects?.addEventListener('change', () => {
        subjectCheckboxes.forEach(input => { input.checked = selectAllSubjects.checked; });
        syncSelectAllSubjects();
    });
    subjectCheckboxes.forEach(input => input.addEventListener('change', syncSelectAllSubjects));
    const resetBuilderForm = () => {
        builderEditingQuizId = null;
        builderForm?.reset();
        syncSelectAllSubjects();
        minutesField?.classList.add('hidden');
        if (builderForm) builderForm.elements.namedItem('time_limit_minutes').required = false;
        builderCancel?.classList.add('hidden');
        const submit = builderForm?.querySelector('[type="submit"]');
        if (submit) submit.innerHTML = '<i data-lucide="wand-sparkles"></i> Create Practice Test';
        if (window.lucide && submit) lucide.createIcons({root: submit});
    };
    timedInput?.addEventListener('change', () => {
        minutesField?.classList.toggle('hidden', !timedInput.checked);
        const minutesInput = builderForm.elements.namedItem('time_limit_minutes');
        if (minutesInput) minutesInput.required = timedInput.checked;
    });
    builderCancel?.addEventListener('click', resetBuilderForm);
    workspaceArea.querySelectorAll('[data-builder-edit]').forEach(button => button.addEventListener('click', () => {
        const quiz = (workspace.learnerQuizzes || []).find(item => Number(item.id) === Number(button.dataset.builderEdit));
        if (!quiz || !builderForm) return;
        builderEditingQuizId = Number(quiz.id);
        builderForm.querySelectorAll('[name="subject_ids"]').forEach(input => { input.checked = (quiz.subjectIds || []).map(Number).includes(Number(input.value)); });
        syncSelectAllSubjects();
        const itemCountSelect = builderForm.elements.namedItem('item_count');
        const existingCount = String(Number(quiz.itemCount || 10));
        if (![...itemCountSelect.options].some(option => option.value === existingCount)) {
            itemCountSelect.add(new Option(`Existing Test — ${existingCount} questions`, existingCount));
        }
        itemCountSelect.value = existingCount;
        timedInput.checked = Boolean(quiz.timed);
        builderForm.elements.namedItem('time_limit_minutes').value = Number(quiz.timeLimitMinutes || 30);
        timedInput.dispatchEvent(new Event('change'));
        builderCancel?.classList.remove('hidden');
        const submit = builderForm.querySelector('[type="submit"]');
        submit.innerHTML = '<i data-lucide="save"></i> Save Changes';
        if (window.lucide) lucide.createIcons({root: submit});
        builderForm.scrollIntoView({behavior:'smooth', block:'start'});
    }));
    workspaceArea.querySelectorAll('[data-builder-delete]').forEach(button => button.addEventListener('click', async () => {
        const quiz = (workspace.learnerQuizzes || []).find(item => Number(item.id) === Number(button.dataset.builderDelete));
        if (!quiz || !window.confirm(`Delete "${quiz.title}"? All attempt results and reviews for this test will also be deleted.`)) return;
        button.disabled = true;
        try {
            const result = await apiRequest(`/api/test-banks/${workspace.id}/quizzes/${quiz.id}`, 'DELETE');
            showToast(result.message || 'Practice test deleted.', 'success');
            await openTestBankWorkspace(workspace.id, 'v2');
            document.querySelector('[data-test-bank-tab="builder"]')?.click();
        } catch (error) {
            button.disabled = false;
            showToast(error.message || 'Unable to delete the practice test.', 'error');
        }
    }));
    const builderQuizCards = [...workspaceArea.querySelectorAll('[data-builder-quiz-card]')];
    if (builderQuizCards.length > 5) {
        let builderPage = 0;
        const pageSize = 5;
        const pageCount = Math.ceil(builderQuizCards.length / pageSize);
        const previous = $('test-bank-builder-prev');
        const next = $('test-bank-builder-next');
        const range = $('test-bank-builder-page-range');
        const renderBuilderPage = () => {
            const start = builderPage * pageSize;
            const end = Math.min(builderQuizCards.length, start + pageSize);
            builderQuizCards.forEach((card, index) => card.classList.toggle('hidden', index < start || index >= end));
            if (range) range.textContent = `Showing ${start + 1}–${end} of ${builderQuizCards.length}`;
            if (previous) previous.disabled = builderPage === 0;
            if (next) next.disabled = builderPage >= pageCount - 1;
        };
        previous?.addEventListener('click', () => { if (builderPage > 0) { builderPage--; renderBuilderPage(); } });
        next?.addEventListener('click', () => { if (builderPage < pageCount - 1) { builderPage++; renderBuilderPage(); } });
        renderBuilderPage();
    }
    builderForm?.addEventListener('submit', async event => {
        event.preventDefault();
        const submit = builderForm.querySelector('[type="submit"]');
        const subjectIds = [...builderForm.querySelectorAll('[name="subject_ids"]:checked')].map(input => Number(input.value));
        if (!subjectIds.length) { showToast('Select at least one subject.', 'error'); return; }
        submit.disabled = true;
        submit.textContent = 'Creating…';
        try {
            const endpoint = builderEditingQuizId
                ? `/api/test-banks/${workspace.id}/quizzes/${builderEditingQuizId}`
                : `/api/test-banks/${workspace.id}/quizzes`;
            const result = await apiRequest(endpoint, builderEditingQuizId ? 'PUT' : 'POST', {
                subject_ids: subjectIds,
                item_count: Number(builderForm.elements.namedItem('item_count').value),
                timed: Boolean(timedInput?.checked),
                time_limit_minutes: timedInput?.checked ? Number(builderForm.elements.namedItem('time_limit_minutes').value) : null,
            });
            showToast(result.message || (builderEditingQuizId ? 'Practice test updated.' : 'Practice test created.'), 'success');
            await openTestBankWorkspace(workspace.id, 'v2');
            document.querySelector('[data-test-bank-tab="builder"]')?.click();
        } catch (error) {
            submit.disabled = false;
            submit.innerHTML = '<i data-lucide="wand-sparkles"></i> Create Practice Test';
            showToast(error.message || 'Unable to create the practice test.', 'error');
            if (window.lucide) lucide.createIcons({root: submit});
        }
    });
    workspaceArea.querySelectorAll('.test-bank-history-review').forEach(button => button.addEventListener('click', () => {
        const attempt = (workspace.history || []).find(item => Number(item.id) === Number(button.dataset.testBankAttempt));
        if (!attempt) return;
        const title = $('quiz-assessment-title');
        if (title) title.textContent = attempt.title || 'Premade Test';
        showAssessmentSummary({
            score: attempt.score,
            total: attempt.total,
            passed: attempt.passed,
            questions: attempt.questions || [],
            incorrectQuestions: (attempt.questions || []).filter(question => !question.correct),
        }, () => {
            showScreen('dashboard-screen');
            openTestBankWorkspace(workspace.id, 'v2').then(() => {
                document.querySelector('[data-test-bank-tab="progress"]')?.click();
            });
        });
    }));
    workspaceArea.querySelectorAll('[data-test-bank-tab]').forEach(button => button.addEventListener('click', () => {
        workspaceArea.querySelectorAll('[data-test-bank-tab]').forEach(tab => tab.classList.toggle('active', tab === button));
        workspaceArea.querySelectorAll('[data-test-bank-panel]').forEach(panel => panel.classList.toggle('hidden', panel.dataset.testBankPanel !== button.dataset.testBankTab));
    }));
    workspaceArea.querySelectorAll('[data-guided-builder]').forEach(button => button.addEventListener('click', () => {
        workspaceArea.querySelectorAll('.test-bank-study-phase').forEach(card => {
            card.classList.toggle('active', card === button.closest('.test-bank-study-phase'));
        });
        workspaceArea.querySelector('[data-test-bank-tab="builder"]')?.click();
        const itemCount = builderForm?.elements.namedItem('item_count');
        if (itemCount) itemCount.value = Number(button.dataset.guidedBuilder);
        const subjectTestType = Number(button.dataset.guidedBuilder) === 20 ? 'mastery' : 'warm_up';
        workspaceArea.querySelector('[data-selected-phase-label]').textContent = subjectTestType === 'mastery'
            ? 'Mastery Tests · 20 items' : 'Warm Up Quizzes · 10 items';
        workspaceArea.querySelectorAll('.test-bank-subject-test-type').forEach(select => {
            select.value = subjectTestType;
        });
        builderForm?.scrollIntoView({behavior:'smooth', block:'start'});
    }));
    workspaceArea.querySelector('[data-guided-simulation]')?.addEventListener('click', () => {
        workspaceArea.querySelectorAll('.test-bank-study-phase').forEach(card => {
            card.classList.toggle('active', card.classList.contains('unlocked'));
        });
        workspaceArea.querySelector('[data-test-bank-tab="premade"]')?.click();
        const simulationSection = workspaceArea.querySelector('.test-bank-simulation-grid')
            || workspaceArea.querySelector('[data-test-bank-panel="premade"]');
        simulationSection?.scrollIntoView({behavior:'smooth', block:'start'});
    });
    setupTestBankCarousel(workspaceArea);
    setupTestBankHistoryTable(workspaceArea);
    if (window.lucide) lucide.createIcons();
}

function renderTestBankV2Sidebar() {
    const list = $('sidebar-test-bank-v2-list');
    if (!list) return;
    list.innerHTML = enrolledTestBanks.map(bank => `<button type="button" class="learner-sidebar-subitem" data-test-bank-v2-id="${Number(bank.id)}"><i data-lucide="notebook-tabs"></i><span>${escapeHtml(bank.title)}</span></button>`).join('')
        || '<span class="learner-sidebar-subitem">No enrolled Test Banks yet.</span>';
    list.querySelectorAll('button').forEach(button => button.addEventListener('click', () => {
        openTestBankWorkspace(Number(button.dataset.testBankV2Id), 'v2');
        setLearnerSidebarOpen(false);
    }));
    if (window.lucide) lucide.createIcons({root:list});
}

$('sidebar-test-bank-v2-btn')?.addEventListener('click', () => {
    renderTestBankV2Sidebar();
    const button = $('sidebar-test-bank-v2-btn');
    const expanded = button.getAttribute('aria-expanded') === 'true';
    button.setAttribute('aria-expanded', String(!expanded));
    $('sidebar-test-bank-v2-list')?.classList.toggle('hidden', expanded);
    $('sidebar-enrolled-courses-btn')?.setAttribute('aria-expanded', 'false');
    $('sidebar-enrolled-courses-list')?.classList.add('hidden');
    $('sidebar-enrolled-test-banks-btn')?.setAttribute('aria-expanded', 'false');
    $('sidebar-enrolled-test-banks-list')?.classList.add('hidden');
    if (!expanded && enrolledTestBanks.length === 1) openTestBankWorkspace(enrolledTestBanks[0].id, 'v2');
});
