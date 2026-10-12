// Primary learner Test Bank interface, using the existing data and quiz services.
function renderTestBankWorkspaceV2(workspace) {
    const workspaceArea = $('test-bank-workspace-area');
    if (!workspaceArea) return;
    const expiresAt = workspace.expiresAt ? new Date(workspace.expiresAt).toLocaleDateString('en-US', {month:'short',day:'numeric',year:'numeric'}) : 'No expiration';
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
                <article class="test-bank-study-phase"><header class="test-bank-phase-header"><small>PHASE 03</small></header><h3>Simulation Test</h3><p>Practice in an exam setting with Simulation 1 and Simulation 2. Retakes are unlimited. Each timed test has 150 items, a 300-minute time limit, and cannot be paused. Unlock after passing at least 10 Warm Up Quizzes and 5 Mastery Tests.</p><b>${Number(workspace.simulationProgress?.warmUpPassed || 0)}/10 Warm Up passed · ${Number(workspace.simulationProgress?.masteryPassed || 0)}/5 Mastery Tests passed</b><button type="button" data-guided-simulation>Select Simulation</button></article>
            </div>
        </section>
<div class="v2-practice-area" aria-live="polite"></div>`;
    $('extend-test-bank-btn')?.addEventListener('click', event => startTestBankCheckout(workspace.id,event.currentTarget));
    workspaceArea.querySelectorAll('[data-guided-builder]').forEach(button => button.addEventListener('click', () => {
        workspaceArea.querySelectorAll('.test-bank-study-phase').forEach(card => card.classList.toggle('active',card === button.closest('.test-bank-study-phase')));
        renderV2PracticeCard(workspace,Number(button.dataset.guidedBuilder));
    }));
    workspaceArea.querySelector('[data-guided-simulation]')?.addEventListener('click', event => {
        workspaceArea.querySelectorAll('.test-bank-study-phase').forEach(card => card.classList.toggle('active', card === event.currentTarget.closest('.test-bank-study-phase')));
        renderV2SimulationCard(workspace);
    });
    renderV2PracticeCard(workspace, 10, false);
    if (window.lucide) lucide.createIcons();
}

function renderV2SimulationCard(workspace) {
    const host = $('test-bank-workspace-area').querySelector('.v2-practice-area');
    const progress = workspace.simulationProgress || {};
    const warm = Math.max(0, Number(progress.warmUpPassed || 0));
    const mastery = Math.max(0, Number(progress.masteryPassed || 0));
    const unlocked = Boolean(progress.unlocked) && warm >= 10 && mastery >= 5;
    const tests = (workspace.premadeTests || []).filter(test => test.isSimulation);
    const remaining = [];
    if (warm < 10) remaining.push(`${10 - warm} more distinct Warm Up Quizzes`);
    if (mastery < 5) remaining.push(`${5 - mastery} more distinct Mastery Tests`);
    host.innerHTML = `<section class="v2-practice-card v2-simulation-card">
        <small class="v2-practice-eyebrow">EXAM CONDITIONS</small><h2>Exam Simulations</h2>
        <p class="v2-practice-description">Practice a full-length test with a timer, no pause, and no explanations until submission.</p>
        <div class="v2-practice-facts"><div><strong>150</strong><span>Items per simulation</span></div><div><strong>300 minutes</strong><span>Timed · No pause</span></div><div><strong>2</strong><span>Simulations</span></div></div>
        <section class="v2-simulation-eligibility"><h3>Simulation test eligibility</h3>
            <div class="v2-eligibility-label"><span>Warm Up Quizzes</span><strong>${warm}/10 passed</strong></div><div class="v2-eligibility-track" role="progressbar" aria-label="Warm Up quizzes passed" aria-valuemin="0" aria-valuemax="10" aria-valuenow="${Math.min(10, warm)}"><i style="width:${Math.min(100, warm * 10)}%"></i></div>
            <div class="v2-eligibility-label"><span>Mastery Tests</span><strong>${mastery}/5 passed</strong></div><div class="v2-eligibility-track" role="progressbar" aria-label="Mastery tests passed" aria-valuemin="0" aria-valuemax="5" aria-valuenow="${Math.min(5, mastery)}"><i style="width:${Math.min(100, mastery * 20)}%"></i></div>
            <p>${unlocked ? 'Requirements complete. Your simulation tests are unlocked.' : `Pass ${remaining.join(' and ')} to unlock.`}</p>
        </section>
        ${[0,1].map(index => {
            const test = tests[index];
            const locked = !unlocked || Boolean(test?.locked);
            return `<div class="v2-simulation-row"><div><h3>Simulation ${index + 1}</h3><p>${index ? 'A second question set' : 'Full-length practice'} · Unlimited retakes</p></div><button type="button" data-v2-simulation="${test ? Number(test.id) : ''}" ${locked || !test ? 'disabled' : ''}>${locked ? 'Locked' : test ? 'Start Simulation' : 'Not available'}</button></div>`;
        }).join('')}
    </section>`;
    host.querySelectorAll('[data-v2-simulation]').forEach(button => button.addEventListener('click', async () => {
        const test = tests.find(item => Number(item.id) === Number(button.dataset.v2Simulation));
        if (!unlocked || !test || test.locked) return;
        if (await showTestBankInstructions({title:test.title,itemCount:test.itemCount,timeLimitMinutes:test.timeLimitMinutes,coverage:[]})) await startTestBankPremadeQuiz(workspace.id,test.id,button);
    }));
    host.scrollIntoView({behavior:'smooth',block:'start'});
}

function renderV2PracticeCard(workspace,itemCount = 10, scrollToCard = true) {
    const host = $('test-bank-workspace-area').querySelector('.v2-practice-area');
    const mastery = itemCount === 20;
    const title = mastery ? 'Mastery Tests' : 'Warm Up Quizzes';
    const startLabel = mastery ? 'Start Mastery Test' : 'Start Warm Up Quiz';
    const subjects = (workspace.subjects || []).filter(subject => Number(subject.questionCount) > 0);
    const latest = (workspace.history || []).filter(attempt => !attempt.cancelled && Number(attempt.totalItems) === itemCount).sort((a,b) => new Date(b.takenAt) - new Date(a.takenAt))[0];
    const percent = latest && Number(latest.total) > 0 ? Math.round(Number(latest.score) / Number(latest.total) * 100) : 0;
    const progress = workspace.simulationProgress || {};
    host.innerHTML = `<div class="v2-practice-layout"><div class="v2-practice-main"><section class="v2-practice-card">
        <small class="v2-practice-eyebrow">${mastery ? 'BUILD YOUR UNDERSTANDING' : 'SHORT & FLEXIBLE PRACTICE'}</small>
        <h2>${title}</h2><p class="v2-practice-description">${mastery ? 'Strengthen your understanding with 20-item tests by subject. Score at least 60% to pass, then keep practicing toward 100%.' : 'Build your knowledge with short, 10-item quizzes—ideal for daily practice or a quick study break.'}</p>
        <div class="v2-practice-facts"><div><strong>${itemCount}</strong><span>${mastery ? 'Questions per test' : 'Questions per quiz'}</span></div><div><strong>${mastery ? '60%' : 'Self-paced'}</strong><span>${mastery ? 'Platform passing score' : 'Quick study sessions'}</span></div><div><strong>Unlimited</strong><span>Practice & retakes</span></div></div>
        <h3>Choose how you want to practice</h3><div class="v2-practice-modes"><button type="button" data-v2-mode="custom" aria-pressed="true">Create Your Own</button><button type="button" data-v2-mode="premade" aria-pressed="false">Pre-Made Tests</button></div>
        <div class="v2-custom-controls"><label class="v2-subject-label" for="v2-practice-subject">Subject area</label><select id="v2-practice-subject"><option value="mixed">Mixed</option><option value="weak">Weak Topics</option></select><p class="v2-topic-hint" id="v2-selection-hint"></p>
        <label for="v2-practice-timing">Test timing</label><select id="v2-practice-timing"><option value="untimed" selected>Un-timed</option><option value="timed">Timed (${itemCount} minutes)</option></select>
        <div class="v2-premade-choice hidden"><label for="v2-practice-premade">Pre-Made Test</label><select id="v2-practice-premade"></select></div>
        <button type="button" class="v2-start" id="v2-practice-start">${startLabel}</button><p class="v2-practice-note">${mastery ? 'Review explanations before retaking a failed test. Each distinct passed test counts once toward simulation eligibility.' : 'Review your answers after each quiz. If you do not pass, review the explanations and try again.'}</p></div><div class="v2-subject-carousel hidden"></div>
    </section><section class="v2-generated-quizzes v2-practice-card" aria-label="Generated quizzes"></section></div><aside class="v2-practice-rail" aria-label="Practice results and next steps">
        <section class="v2-rail-card"><h2>Latest result</h2>${latest ? `<p>${escapeHtml(latest.coverage || latest.title)}</p><strong class="v2-result-score ${latest.passed ? 'passed' : ''}">${percent}%</strong><span class="v2-result-status ${latest.passed ? 'passed' : ''}">${latest.passed ? 'Passed · Well done' : 'Failed · Try again'}</span><p>${latest.passed ? 'Great work! Keep practicing to strengthen your knowledge.' : 'Keep practicing. Review your answers, then try again.'}</p><div class="v2-rail-actions"><button type="button" data-v2-review>Review Answers</button><button type="button" class="v2-retake" data-v2-retake>Retake</button></div>` : '<p>No completed results yet. Start a test to see your latest result here.</p>'}</section>
        <section class="v2-rail-card"><h2>Your next step</h2><p>${latest && !latest.passed ? `Spend a few minutes reviewing <strong>${escapeHtml(latest.coverage || latest.title)}</strong>, then check your understanding with fresh questions.` : 'Build confidence with fresh questions and review explanations after each test.'}</p><button type="button" data-v2-weak>Practice ${latest && !latest.passed ? 'Weak Topics' : 'More Questions'}</button></section>
        <section class="v2-rail-card"><h2>Simulation readiness</h2>${[['Warm Up Quizzes',Number(progress.warmUpPassed || 0),10],['Mastery Tests',Number(progress.masteryPassed || 0),5]].map(([label,count,total]) => `<div class="v2-eligibility-label"><span>${label}</span><strong>${count}/${total} passed</strong></div><div class="v2-eligibility-track" role="progressbar" aria-label="${label} passed" aria-valuemin="0" aria-valuemax="${total}" aria-valuenow="${Math.max(0,Math.min(total,count))}"><i style="width:${Math.max(0,Math.min(100,count / total * 100))}%"></i></div>`).join('')}<button type="button" data-v2-view-simulation>View exam simulations →</button></section>
        <section class="v2-rail-card"><h2>Exam Readiness %</h2><strong class="v2-readiness-value">${getV2ProgressStats(workspace).readiness}%</strong><p>Passed required tests ÷ total required tests × 100</p><div class="v2-rail-actions"><button type="button" data-v2-progress-snapshot>Progress Snapshot</button><button type="button" data-v2-progress-tracker>Progress Tracker</button></div></section>
    </aside></div>`;
    let mode = 'custom';
    const subjectSelect = host.querySelector('#v2-practice-subject'), testSelect = host.querySelector('#v2-practice-premade'), start = host.querySelector('#v2-practice-start');
    let customSelection = 'mixed', premadeSelection = subjects[0] ? String(subjects[0].id) : '';
    const weakSubjects = (workspace.weakTopicSubjectIds || []).map(id => subjects.find(subject => Number(subject.id) === Number(id))).filter(Boolean);
    const selectedTopics = () => subjectSelect.value === 'weak' ? weakSubjects : subjectSelect.value === 'mixed' ? subjects : subjects.filter(subject => String(subject.id) === subjectSelect.value);
    const selectionLabel = selected => subjectSelect.value === 'weak' ? 'Weak Topics' : subjectSelect.value === 'mixed' ? 'Mixed' : selected[0]?.title || 'Choose subject';
    const renderSelection = () => {
        subjectSelect.innerHTML = (mode === 'custom' ? '<option value="mixed">Mixed</option><option value="weak">Weak Topics</option>' : '') + subjects.map(subject => `<option value="${Number(subject.id)}">${escapeHtml(subject.title)}</option>`).join('');
        subjectSelect.value = mode === 'custom' ? customSelection : premadeSelection;
    };
    const update = () => {
        const selected = selectedTopics();
        const hint = host.querySelector('#v2-selection-hint');
        if (hint) hint.textContent = mode === 'premade' ? 'Choose a subject for your pre-made test.' : subjectSelect.value === 'weak' ? weakSubjects.length ? `Practice your ${weakSubjects.length} lowest-scoring subjects: ${weakSubjects.map(subject => subject.title).join(', ')}. Based on correct answers from completed tests.` : 'No weak topics identified yet. Complete a test first.' : subjectSelect.value === 'mixed' ? 'Practice questions across all available subjects.' : 'Practice questions from this subject.';
        const tests = (workspace.premadeTests || []).filter(test => !test.isSimulation && !test.locked && Number(test.itemCount) === itemCount && selected.length && selected.every(subject => (test.subjectIds || []).map(Number).includes(Number(subject.id))));
        testSelect.innerHTML = tests.map(test => `<option value="${Number(test.id)}">${escapeHtml(test.title)}</option>`).join('') || '<option value="">No matching pre-made tests</option>';
        start.disabled = !selected.length || (mode === 'premade' && !tests.length && selected.length !== 1);
        host.querySelector('.v2-premade-choice').classList.toggle('hidden',mode !== 'premade' || !tests.length);
    };
    host.querySelectorAll('[data-v2-mode]').forEach(button => button.addEventListener('click',() => {
        mode = button.dataset.v2Mode;
        host.querySelector('.v2-generated-quizzes')?.classList.toggle('hidden',mode !== 'custom');
        host.querySelector('.v2-custom-controls')?.classList.toggle('hidden',mode === 'premade');
        host.querySelector('.v2-subject-carousel')?.classList.toggle('hidden',mode !== 'premade');
        if (mode === 'premade') renderV2SubjectCarousel(host.querySelector('.v2-subject-carousel'),workspace,itemCount);
        host.querySelectorAll('[data-v2-mode]').forEach(item => item.setAttribute('aria-pressed',String(item === button)));
        renderSelection(); update();
    }));
    subjectSelect.addEventListener('change', () => {
        if (mode === 'custom') customSelection = subjectSelect.value; else premadeSelection = subjectSelect.value;
        update();
    });
    start.addEventListener('click',async () => {
        const selected = selectedTopics();
        if (start.disabled || !selected.length) return;
        const test = mode === 'premade' ? (workspace.premadeTests || []).find(item => Number(item.id) === Number(testSelect.value)) : null;
        const timed = mode === 'custom' && host.querySelector('#v2-practice-timing')?.value === 'timed';
        if (!await showTestBankInstructions({title:test?.title || `${title} · ${selectionLabel(selected)}`,itemCount,timeLimitMinutes:test?.timeLimitMinutes || (timed ? itemCount : null),coverage:selected.map(subject => subject.title)})) return;
        start.disabled = true; start.textContent = 'Preparing…';
        try {
            let quizId = test?.id;
            if (!quizId) {
                const endpoint = mode === 'custom' ? `/api/test-banks/${workspace.id}/quizzes` : `/api/test-banks/${workspace.id}/subjects/${Number(selected[0].id)}/quizzes`;
                const payload = mode === 'custom' ? {subject_ids:selected.map(subject => Number(subject.id)),item_count:itemCount,timed,time_limit_minutes:timed ? itemCount : null,...(subjectSelect.value === 'weak' ? {coverage_mode:'weak'} : {})} : {quiz_type:mastery ? 'mastery' : 'warm_up'};
                const created = await apiRequest(endpoint,'POST',payload);
                quizId = created.quizId;
                if (mode === 'custom') {
                    workspace.learnerQuizzes ||= [];
                    workspace.learnerQuizzes.unshift(created.quiz || {id:Number(quizId),title:`${title} · ${selectionLabel(selected)}`,itemCount,subjectIds:selected.map(subject => Number(subject.id)),coverageLabel:selected.map(subject => subject.title).join(', '),timed,timeLimitMinutes:timed ? itemCount : null,attemptCount:0,createdAt:new Date().toISOString()});
                    renderV2GeneratedQuizzes(host.querySelector('.v2-generated-quizzes'),workspace,itemCount);
                }
            }
            await startTestBankPremadeQuiz(workspace.id,Number(quizId),start);
        } catch(error) { showToast(error.message || 'Unable to prepare this quiz.','error'); }
        finally { start.textContent = startLabel; update(); }
    });
    renderSelection(); update();
    renderV2GeneratedQuizzes(host.querySelector('.v2-generated-quizzes'),workspace,itemCount);
    host.querySelector('[data-v2-review]')?.addEventListener('click', () => {
        if (!latest) return;
        const assessmentTitle = $('quiz-assessment-title');
        if (assessmentTitle) assessmentTitle.textContent = latest.title;
        showAssessmentSummary({score:latest.score,total:latest.total,passed:latest.passed,questions:latest.questions || [],incorrectQuestions:(latest.questions || []).filter(question => !question.correct)}, async () => {
            showScreen('dashboard-screen');
            await openTestBankWorkspace(workspace.id);
            $('test-bank-workspace-area').querySelector(`[data-guided-builder="${itemCount}"]`)?.click();
        });
    });
    host.querySelector('[data-v2-retake]')?.addEventListener('click', async event => {
        if (!latest?.quizId) return;
        if (await showTestBankInstructions({title:latest.title,itemCount,timeLimitMinutes:latest.timeLimitMinutes,coverage:[latest.coverage || 'Mixed']})) await startTestBankPremadeQuiz(workspace.id,Number(latest.quizId),event.currentTarget);
    });
    host.querySelector('[data-v2-weak]')?.addEventListener('click', () => {
        mode = 'custom';
        host.querySelector('.v2-generated-quizzes')?.classList.remove('hidden');
        host.querySelector('.v2-custom-controls')?.classList.remove('hidden');
        host.querySelector('.v2-subject-carousel')?.classList.add('hidden');
        customSelection = 'weak';
        host.querySelectorAll('[data-v2-mode]').forEach(button => button.setAttribute('aria-pressed',String(button.dataset.v2Mode === 'custom')));
        renderSelection(); update();
        subjectSelect.focus();
    });
    host.querySelector('[data-v2-view-simulation]')?.addEventListener('click', () => $('test-bank-workspace-area').querySelector('[data-guided-simulation]')?.click());
    host.querySelector('[data-v2-progress-snapshot]')?.addEventListener('click', () => openV2ProgressModal(workspace,'snapshot'));
    host.querySelector('[data-v2-progress-tracker]')?.addEventListener('click', () => openV2ProgressModal(workspace,'tracker'));
    if (scrollToCard) host.scrollIntoView({behavior:'smooth',block:'start'});
}

function renderV2GeneratedQuizzes(container,workspace,itemCount,page = 0) {
    if (!container) return;
    const quizzes = (workspace.learnerQuizzes || []).filter(quiz => /Mastery Test/i.test(quiz.title) ? itemCount === 20 : /Warm Up Quiz/i.test(quiz.title) ? itemCount === 10 : Number(quiz.itemCount) === itemCount);
    const pages = Math.max(1,Math.ceil(quizzes.length / 5));
    page = Math.max(0,Math.min(page,pages - 1));
    container.querySelectorAll('.accordion-collapse').forEach(panel => window.bootstrap?.Collapse?.getInstance(panel)?.dispose());
    container.id = `v2-generated-accordion-${itemCount}`;
    container.innerHTML = `<header class="v2-generated-heading"><h2>My ${itemCount === 20 ? 'Mastery Tests' : 'Warm Up Quizzes'}</h2><span>${quizzes.length} created</span></header>${quizzes.length ? quizzes.slice(page * 5,page * 5 + 5).map(quiz => {
        const attempts = (workspace.history || []).filter(attempt => Number(attempt.quizId) === Number(quiz.id)).sort((a,b) => new Date(a.takenAt) - new Date(b.takenAt) || Number(a.id) - Number(b.id));
        const completed = attempts.filter(attempt => !attempt.cancelled && Number(attempt.totalItems) > 0);
        const copy = `<div><h3>${escapeHtml(quiz.title)}</h3><p>${Number(quiz.itemCount)} items · ${quiz.timeLimitMinutes ? `Timed (${Number(quiz.timeLimitMinutes)} minutes)` : 'Un-timed'} · ${completed.length} completed attempts</p><p>Subjects: ${escapeHtml(quiz.coverageLabel || 'Mixed')}</p></div>`;
        if (!completed.length) return `<article class="v2-generated-row">${copy}<button type="button" data-v2-generated-start="${Number(quiz.id)}">Start Quiz</button></article>`;
        return `<article class="v2-generated-accordion accordion-item"><header class="accordion-header"><button type="button" class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#v2-quiz-attempts-${Number(quiz.id)}" aria-expanded="false" aria-controls="v2-quiz-attempts-${Number(quiz.id)}">${copy}<span class="v2-attempt-chevron" aria-hidden="true">⌄</span></button></header><div id="v2-quiz-attempts-${Number(quiz.id)}" class="accordion-collapse collapse" data-bs-parent="#${container.id}"><div class="v2-generated-attempts accordion-body">${attempts.map((attempt,index) => {
            const number = Number(attempt.attemptNumber) || index + 1;
            const suffix = number % 100 >= 11 && number % 100 <= 13 ? 'th' : ({1:'st',2:'nd',3:'rd'}[number % 10] || 'th');
            const date = new Date(attempt.takenAt);
            const takenAt = Number.isNaN(date.getTime()) ? '' : date.toLocaleString('en-US',{timeZone:'Asia/Manila',month:'short',day:'numeric',year:'numeric',hour:'numeric',minute:'2-digit'});
            const percent = Number(attempt.total) > 0 ? Math.round(Number(attempt.score) / Number(attempt.total) * 100) : 0;
            return `<article class="v2-generated-attempt"><div><strong>${number}${suffix} attempt</strong><p>${escapeHtml(takenAt)}</p></div><div><strong>${attempt.cancelled ? '—' : `${percent}%`}</strong><span class="${attempt.cancelled ? '' : attempt.passed ? 'passed' : 'failed'}">${attempt.cancelled ? 'Cancelled' : attempt.passed ? 'Passed' : 'Failed'}</span></div>${attempt.cancelled ? '' : `<button type="button" data-v2-generated-review="${Number(attempt.id)}">Review</button>`}</article>`;
        }).join('')}<button type="button" data-v2-generated-start="${Number(quiz.id)}">Retake Quiz</button></div></div></article>`;
    }).join('') : '<p>No quizzes created in this phase yet. Create your first quiz above.</p>'}<nav class="v2-generated-pagination" aria-label="Created quiz pages"><button type="button" data-v2-generated-prev ${page === 0 ? 'disabled' : ''}>Previous</button><span aria-live="polite">Page ${page + 1} of ${pages}</span><button type="button" data-v2-generated-next ${page >= pages - 1 ? 'disabled' : ''}>Next</button></nav>`;
    container.querySelector?.('[data-v2-generated-prev]')?.addEventListener('click',() => { if (page > 0) renderV2GeneratedQuizzes(container,workspace,itemCount,page - 1); });
    container.querySelector?.('[data-v2-generated-next]')?.addEventListener('click',() => { if (page < pages - 1) renderV2GeneratedQuizzes(container,workspace,itemCount,page + 1); });
    container.querySelectorAll('[data-v2-generated-start]').forEach(button => button.addEventListener('click',async () => {
        const quiz = quizzes.find(quiz => Number(quiz.id) === Number(button.dataset.v2GeneratedStart));
        if (!quiz || button.disabled) return;
        if (await showTestBankInstructions({title:quiz.title,itemCount:quiz.itemCount,timeLimitMinutes:quiz.timeLimitMinutes,coverage:[quiz.coverageLabel || 'Mixed']})) await startTestBankPremadeQuiz(workspace.id,Number(quiz.id),button);
    }));
    // Keep the accordion usable if the Bootstrap CDN is unavailable.
    if (!window.bootstrap?.Collapse) container.querySelectorAll('.accordion-button').forEach(button => button.addEventListener('click',() => {
        const opening = button.getAttribute('aria-expanded') !== 'true';
        container.querySelectorAll('.accordion-button').forEach(other => {
            const expanded = other === button && opening;
            other.setAttribute('aria-expanded',String(expanded));
            other.classList.toggle('collapsed',!expanded);
            container.querySelector(other.dataset.bsTarget)?.classList.toggle('show',expanded);
        });
    }));
    container.querySelectorAll('[data-v2-generated-review]').forEach(button => button.addEventListener('click',() => {
        const attempt = (workspace.history || []).find(attempt => Number(attempt.id) === Number(button.dataset.v2GeneratedReview));
        if (!attempt || attempt.cancelled) return;
        const title = $('quiz-assessment-title'); if (title) title.textContent = attempt.title;
        showAssessmentSummary({score:attempt.score,total:attempt.total,passed:attempt.passed,questions:attempt.questions || [],incorrectQuestions:(attempt.questions || []).filter(question => !question.correct)},async () => {
            showScreen('dashboard-screen');
            await openTestBankWorkspace(workspace.id);
            $('test-bank-workspace-area').querySelector(`[data-guided-builder="${itemCount}"]`)?.click();
        });
    }));
}

function getV2ProgressStats(workspace) {
    const history = workspace.history || [], completed = history.filter(attempt => !attempt.cancelled && Number(attempt.totalItems) > 0);
    const progress = workspace.simulationProgress || {};
    const warm = Number(progress.warmUpPassed || 0), mastery = Number(progress.masteryPassed || 0);
    const warmRequired = Number(progress.warmUpRequired || 10), masteryRequired = Number(progress.masteryRequired || 5);
    const required = warmRequired + masteryRequired, passed = Math.min(warm,warmRequired) + Math.min(mastery,masteryRequired);
    const average = completed.length ? Math.round(completed.reduce((sum,attempt) => sum + (Number(attempt.total) > 0 ? Number(attempt.score) / Number(attempt.total) * 100 : 0),0) / completed.length) : null;
    const dayKey = date => new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Manila',year:'numeric',month:'2-digit',day:'2-digit'}).format(date);
    const days = new Set(completed.map(attempt => new Date(attempt.takenAt)).filter(date => !Number.isNaN(date.getTime())).map(dayKey));
    let cursor = new Date(), streak = 0;
    if (!days.has(dayKey(cursor))) cursor = new Date(cursor.getTime() - 86400000);
    while (days.has(dayKey(cursor))) { streak++; cursor = new Date(cursor.getTime() - 86400000); }
    return {completed:completed.length,cancelled:history.filter(attempt => attempt.cancelled).length,questions:completed.reduce((sum,attempt) => sum + Number(attempt.totalItems || 0),0),average,streak,warm,mastery,warmRequired,masteryRequired,required,passed,readiness:required ? Math.round(passed / required * 100) : 0};
}

function openV2ProgressModal(workspace,view) {
    const area = $('test-bank-workspace-area');
    area.querySelector('.v2-progress-modal')?.remove();
    const stats = getV2ProgressStats(workspace);
    const dialog = document.createElement('dialog');
    dialog.className = `v2-progress-modal ${view === 'tracker' ? 'v2-progress-wide' : ''}`;
    dialog.setAttribute('aria-labelledby','v2-progress-modal-title');
    const bars = [['Warm Up Quizzes',stats.warm,stats.warmRequired],['Mastery Tests',stats.mastery,stats.masteryRequired]].map(([label,count,total]) => `<div class="v2-eligibility-label"><span>${label} passed</span><strong>${count}/${total}</strong></div><div class="v2-eligibility-track" role="progressbar" aria-label="${label} passed" aria-valuemin="0" aria-valuemax="${total}" aria-valuenow="${Math.min(total,count)}"><i style="width:${Math.min(100,count / total * 100)}%"></i></div>`).join('');
dialog.innerHTML = `<header class="v2-progress-modal-heading"><h2 id="v2-progress-modal-title">${view === 'snapshot' ? 'My Progress Snapshot' : 'Progress Tracker'}</h2><button type="button" data-v2-modal-close aria-label="Close progress modal">×</button></header>${view === 'snapshot' ? `<p>Your learning progress in ${escapeHtml(workspace.title)}.</p><div class="v2-snapshot-stats">${[[stats.completed,'Tests taken'],[stats.average === null ? '—' : `${stats.average}%`,'Average score'],[stats.questions,'Questions answered'],[stats.streak,'Day streak']].map(([value,label]) => `<div><strong>${value}</strong><span>${label}</span></div>`).join('')}</div><h3>SIMULATION UNLOCK PROGRESS</h3>${bars}<p>Pass both required totals to unlock Simulation. Results update after completed tests.</p>` : `<section class="test-bank-progress-report"><div class="test-bank-progress-report-head"><div><span class="test-bank-progress-report-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 20v-6M10 20V9M17 20V3"/></svg></span><div><small>REQUIRED CUSTOM TESTS</small><h2>Progress Tracker</h2><p>Passed required tests ÷ total required tests × 100</p></div></div><div><strong>${stats.readiness}%</strong><span>Exam readiness</span><div role="progressbar" aria-label="Exam readiness" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${stats.readiness}"><i style="width:${stats.readiness}%"></i></div></div></div><div class="test-bank-progress-stats">${[[stats.required,'Total required'],[stats.completed,'Completed attempts'],[stats.cancelled,'Cancelled'],[stats.passed,'Requirements passed']].map(([value,label]) => `<div><strong>${value}</strong><span>${label}</span></div>`).join('')}</div><div class="v2-tracker-tools"><label class="v2-tracker-search"><span aria-hidden="true">⌕</span><input type="search" data-v2-record-search placeholder="Search progress records" aria-label="Search progress records"></label><div class="v2-tracker-filters"><label class="v2-cancelled-switch"><input type="checkbox" role="switch" data-v2-record-cancelled><span class="v2-switch-track" aria-hidden="true"></span><span>Show Cancelled Tests</span></label><span aria-hidden="true">·</span><span data-v2-record-count aria-live="polite"></span></div></div><div class="test-bank-tracker-list" data-v2-record-list></div><div class="v2-tracker-pagination"><button type="button" data-v2-record-prev>Previous</button><span data-v2-record-page></span><button type="button" data-v2-record-next>Next</button></div></section>`}`;
    dialog.querySelector('[data-v2-modal-close]').innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>';
    area.appendChild(dialog);
    dialog.querySelector('[data-v2-modal-close]').addEventListener('click',() => dialog.close());
    dialog.addEventListener('close',() => dialog.remove(),{once:true});
    dialog.addEventListener('click',event => { if (event.target === dialog) { const rect = dialog.getBoundingClientRect(); if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close(); } });
    if (view === 'tracker') {
        let page = 0;
        const search = dialog.querySelector('[data-v2-record-search]'), cancelled = dialog.querySelector('[data-v2-record-cancelled]');
        const history = workspace.history || [];
        const render = () => {
            const records = history.filter(attempt => (cancelled.checked || !attempt.cancelled) && `${attempt.title} ${attempt.coverage} ${attempt.testTypeLabel}`.toLowerCase().includes(search.value.trim().toLowerCase()));
            page = Math.min(page,Math.max(0,Math.ceil(records.length / 5) - 1));
            dialog.querySelector('[data-v2-record-count]').textContent = `${records.length} records`;
            dialog.querySelector('[data-v2-record-page]').textContent = `Page ${page + 1} of ${Math.max(1,Math.ceil(records.length / 5))}`;
            dialog.querySelector('[data-v2-record-prev]').disabled = page === 0;
            dialog.querySelector('[data-v2-record-next]').disabled = (page + 1) * 5 >= records.length;
            dialog.querySelector('[data-v2-record-list]').innerHTML = records.slice(page * 5,page * 5 + 5).map(attempt => {
                const date = new Date(attempt.takenAt);
                const takenAt = Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString('en-US',{timeZone:'Asia/Manila',month:'short',day:'numeric',year:'numeric'});
                const percent = Number(attempt.totalItems) > 0 ? Math.round(Number(attempt.correctItems || 0) / Number(attempt.totalItems) * 100) : 0;
                const ordered = history.filter(item => Number(item.quizId) === Number(attempt.quizId)).sort((a,b) => new Date(a.takenAt) - new Date(b.takenAt) || Number(a.id) - Number(b.id));
                const count = Number(attempt.attemptNumber) || ordered.findIndex(item => item.id === attempt.id) + 1;
                const suffix = count % 100 >= 11 && count % 100 <= 13 ? 'th' : ({1:'st',2:'nd',3:'rd'}[count % 10] || 'th');
return `<article class="test-bank-tracker-row"><div class="test-bank-tracker-copy"><h3>${escapeHtml(attempt.quizType === 'subject' ? attempt.title.replace(/\s*·\s*(Warm-up|Mastery Test)$/i, '') : attempt.title)} <span class="test-bank-tracker-taken-date">${takenAt ? `· (${takenAt})` : ''}</span></h3><div class="test-bank-tracker-subtitle">${escapeHtml(attempt.testTypeLabel || 'Test')} · ${Number(attempt.totalItems || 0)} questions · ${attempt.timeLimitMinutes ? `Timed (${Number(attempt.timeLimitMinutes)} minutes)` : 'Untimed'} · ${count}${suffix} attempt</div><p class="test-bank-tracker-coverage"><strong>SUBJECTS:</strong> ${escapeHtml(attempt.coverage || 'Not specified')}</p></div><div class="test-bank-tracker-metric"><small>SCORE</small><strong>${attempt.cancelled ? '—' : `${percent}%`}</strong></div><div class="test-bank-tracker-metric"><small>RESULT</small><strong class="${attempt.cancelled ? 'cancelled' : attempt.passed ? 'passed' : 'failed'}">${attempt.cancelled ? 'Cancelled' : attempt.passed ? 'Passed' : 'Failed'}</strong></div><div class="test-bank-tracker-metric"><small>RANK</small><strong>${attempt.quizType === 'premade' ? escapeHtml(attempt.rank ?? '--') : '--'}</strong></div><div class="test-bank-tracker-action">${attempt.cancelled ? '<small>No review available</small>' : `${!attempt.passed && attempt.quizType !== 'subject' ? '<small class="test-bank-retake-badge">Retake recommended</small>' : ''}<div class="test-bank-tracker-buttons"><button type="button" class="test-bank-history-review" data-v2-record-review="${Number(attempt.id)}">Review</button>${attempt.quizType === 'subject' ? '' : `<button type="button" class="test-bank-history-retake" data-v2-record-retake="${Number(attempt.id)}">Re-take</button>`}</div>`}</div></article>`;
            }).join('') || '<p>No matching Test Bank attempts.</p>';
        };
        search.addEventListener('input',() => { page = 0; render(); });
        cancelled.addEventListener('change',() => { page = 0; render(); });
        dialog.querySelector('[data-v2-record-prev]').addEventListener('click',() => { page--; render(); });
        dialog.querySelector('[data-v2-record-next]').addEventListener('click',() => { page++; render(); });
        dialog.querySelector('[data-v2-record-list]').addEventListener('click',async event => {
            const button = event.target.closest('[data-v2-record-review], [data-v2-record-retake]');
            if (!button) return;
            const attempt = history.find(item => Number(item.id) === Number(button.dataset.v2RecordReview || button.dataset.v2RecordRetake));
            if (!attempt || attempt.cancelled) return;
            if (button.dataset.v2RecordReview) {
                dialog.close();
                const title = $('quiz-assessment-title'); if (title) title.textContent = attempt.title;
                showAssessmentSummary({score:attempt.score,total:attempt.total,passed:attempt.passed,questions:attempt.questions || [],incorrectQuestions:(attempt.questions || []).filter(question => !question.correct)},async () => { showScreen('dashboard-screen'); await openTestBankWorkspace(workspace.id,'v2'); openV2ProgressModal(workspace,'tracker'); });
            } else if (attempt.quizType !== 'subject') {
                dialog.close();
                if (await showTestBankInstructions({title:attempt.title,itemCount:attempt.totalItems,timeLimitMinutes:attempt.timeLimitMinutes,coverage:[attempt.coverage || 'Mixed']})) await startTestBankPremadeQuiz(workspace.id,Number(attempt.quizId),button);
            }
        });
        render();
    }
    dialog.showModal();
}

function renderV2SubjectCarousel(container,workspace,itemCount) {
    if (!container) return;
    container.v2TitleObserver?.disconnect();
    const fitTitles = () => {
        container.querySelectorAll('.v2-subject-card h3').forEach(heading => {
            if (!heading.clientWidth) return;
            heading.title = heading.textContent;
            heading.style.webkitLineClamp = 'unset';
            heading.style.height = 'auto';
            const base = parseFloat(getComputedStyle(document.documentElement).fontSize);
            let size = base * 1.05;
            heading.style.fontSize = `${size}px`;
            while (size > base * .75 && heading.scrollHeight > size * 1.4 * 2 + 1) {
                size = Math.max(base * .75,size - .5);
                heading.style.fontSize = `${size}px`;
            }
            heading.style.webkitLineClamp = '2';
            heading.style.height = '';
        });
    };
    const subjects = (workspace.subjects || []).filter(subject => Number(subject.questionCount) > 0);
    let page = 0;
    let transitioning = false;
    const changePage = async direction => {
        const nextPage = page + direction;
        if (transitioning || nextPage < 0 || nextPage * 3 >= subjects.length) return;
        transitioning = true;
        const grid = container.querySelector('.v2-subject-grid');
        const reduceMotion = typeof matchMedia !== 'undefined' && matchMedia('(prefers-reduced-motion: reduce)').matches;
        const animate = async (element,frames,duration) => {
            if (reduceMotion || !element?.animate) return;
            try { await element.animate(frames,{duration,easing:'cubic-bezier(.22,.61,.36,1)'}).finished; } catch (_) { /* Navigation can detach a running animation. */ }
        };
        container.querySelectorAll('[data-v2-prev], [data-v2-next]').forEach(button => { button.disabled = true; });
        try {
            await animate(grid,[{transform:'translateX(0)',opacity:1},{transform:`translateX(${-direction * 100}%)`,opacity:0}],180);
            if (container.isConnected === false) return;
            page = nextPage;
            render();
            await animate(container.querySelector('.v2-subject-grid'),[{transform:`translateX(${direction * 100}%)`,opacity:0},{transform:'translateX(0)',opacity:1}],240);
            container.querySelector(direction > 0 ? '[data-v2-next]' : '[data-v2-prev]')?.focus?.({preventScroll:true});
        } finally { transitioning = false; }
    };
    const render = () => {
        const visible = subjects.slice(page * 3,page * 3 + 3);
container.innerHTML = `<div class="v2-carousel-heading"><div><h3>${escapeHtml(workspace.title)} Pre-Made Tests by subject</h3><p>Each subject’s pre-made test can be taken only once. Practice thoroughly with subject-based quizzes before attempting it.</p></div><div><span>Subjects ${subjects.length ? page * 3 + 1 : 0}–${Math.min(subjects.length,page * 3 + 3)} of ${subjects.length}</span><button type="button" data-v2-prev ${page === 0 ? 'disabled' : ''} aria-label="Previous subjects">‹</button><button type="button" data-v2-next ${(page + 1) * 3 >= subjects.length ? 'disabled' : ''} aria-label="Next subjects">›</button></div></div><div class="v2-subject-grid">${visible.map(subject => { const result = (workspace.v2SubjectResults || []).find(result => Number(result.subjectId) === Number(subject.id) && result.phase === (itemCount === 20 ? 'mastery' : 'warm_up')); return `<article class="v2-subject-card"><header class="v2-subject-header"><h3>${escapeHtml(subject.title)}</h3></header><div class="v2-subject-stats"><span><strong>${result ? 1 : 0}</strong>Completed</span><span><strong>${result ? `${Number(result.score)}%` : '—'}</strong>Score</span></div><button type="button" class="v2-start" data-v2-subject-start="${Number(subject.id)}" ${result ? 'disabled' : ''}>${result ? 'Completed' : itemCount === 20 ? 'Start Mastery Test' : 'Start Warm Up Quiz'}</button></article>`; }).join('')}</div>`;
        container.querySelector('[data-v2-prev]')?.addEventListener('click',() => changePage(-1));
        container.querySelector('[data-v2-next]')?.addEventListener('click',() => changePage(1));
        container.querySelectorAll('[data-v2-subject-start]').forEach(button => button.addEventListener('click',async () => {
            const subject = subjects.find(item => Number(item.id) === Number(button.dataset.v2SubjectStart));
            if (!subject || button.disabled) return;
            if (!await showTestBankInstructions({title:subject.title,itemCount,timeLimitMinutes:null,coverage:[subject.title]})) return;
            button.disabled = true;
            try {
                const result = await apiRequest(`/api/test-banks/${workspace.id}/subjects/${subject.id}/quizzes`,'POST',{quiz_type:itemCount === 20 ? 'mastery' : 'warm_up',reuse_set:true});
                await startTestBankPremadeQuiz(workspace.id,Number(result.quizId),button);
            } catch(error) { showToast(error.message || 'Unable to prepare this test.','error'); }
            finally { button.disabled = false; }
        }));
        fitTitles();
    };
    render();
    if (typeof ResizeObserver !== 'undefined') {
        let lastWidth = 0;
        container.v2TitleObserver = new ResizeObserver(entries => {
            const width = entries[0].contentRect.width;
            if (width !== lastWidth) { lastWidth = width; fitTitles(); }
            if (!container.isConnected) container.v2TitleObserver.disconnect();
        });
        container.v2TitleObserver.observe(container);
    }
    if (typeof document !== 'undefined') document.fonts?.ready.then(() => { if (container.isConnected) fitTitles(); });
}
