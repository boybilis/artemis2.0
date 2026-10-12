// V2-only phase-based interface. Data and quiz services remain shared with V1.
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
                <article class="test-bank-study-phase"><header class="test-bank-phase-header"><small>PHASE 03</small></header><h3>Simulation Test</h3><p>Practice in an exam setting with Simulation 1 and Simulation 2. Retakes are unlimited. Each timed test has 100–150 items and cannot be paused. Unlock after passing at least 10 Warm Up Quizzes and 5 Mastery Tests.</p><b>${Number(workspace.simulationProgress?.warmUpPassed || 0)}/10 Warm Up passed · ${Number(workspace.simulationProgress?.masteryPassed || 0)}/5 Mastery Tests passed</b><button type="button" data-guided-simulation>Select Simulation</button></article>
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
        <div class="v2-practice-facts"><div><strong>100–150</strong><span>Questions · sample setting</span></div><div><strong>Timed</strong><span>No pause</span></div><div><strong>2</strong><span>Simulations</span></div></div>
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

function renderV2PracticeCard(workspace,itemCount = 10) {
    const host = $('test-bank-workspace-area').querySelector('.v2-practice-area');
    const mastery = itemCount === 20;
    const title = mastery ? 'Mastery Tests' : 'Warm Up Quizzes';
    const startLabel = mastery ? 'Start Mastery Test' : 'Start Warm Up Quiz';
    const subjects = (workspace.subjects || []).filter(subject => Number(subject.questionCount) > 0);
    host.innerHTML = `<section class="v2-practice-card">
        <small class="v2-practice-eyebrow">${mastery ? 'BUILD YOUR UNDERSTANDING' : 'SHORT & FLEXIBLE PRACTICE'}</small>
        <h2>${title}</h2><p class="v2-practice-description">${mastery ? 'Strengthen your understanding with 20-item tests by subject. Score at least 60% to pass, then keep practicing toward 100%.' : 'Build your knowledge with short, 10-item quizzes—ideal for daily practice or a quick study break.'}</p>
        <div class="v2-practice-facts"><div><strong>${itemCount}</strong><span>${mastery ? 'Questions per test' : 'Questions per quiz'}</span></div><div><strong>${mastery ? '60%' : 'Self-paced'}</strong><span>${mastery ? 'Platform passing score' : 'Quick study sessions'}</span></div><div><strong>Unlimited</strong><span>Practice & retakes</span></div></div>
        <h3>Choose how you want to practice</h3><div class="v2-practice-modes"><button type="button" data-v2-mode="custom" aria-pressed="true">Create Your Own</button><button type="button" data-v2-mode="premade" aria-pressed="false">Pre-Made Tests</button></div>
        <label class="v2-subject-label" for="v2-practice-subject">Subject area</label><select id="v2-practice-subject"><option value="mixed">Mixed</option>${subjects.map(subject => `<option value="${Number(subject.id)}">${escapeHtml(subject.title)}</option>`).join('')}</select>
        <div class="v2-premade-choice hidden"><label for="v2-practice-premade">Pre-Made Test</label><select id="v2-practice-premade"></select></div>
        <button type="button" class="v2-start" id="v2-practice-start">${startLabel}</button><p class="v2-practice-note">${mastery ? 'Review explanations before retaking a failed test. Each distinct passed test counts once toward simulation eligibility.' : 'Review your answers after each quiz. If you do not pass, review the explanations and try again.'}</p>
    </section>`;
    let mode = 'custom';
    const subjectSelect = host.querySelector('#v2-practice-subject'), testSelect = host.querySelector('#v2-practice-premade'), start = host.querySelector('#v2-practice-start');
    const update = () => {
        const tests = (workspace.premadeTests || []).filter(test => !test.isSimulation && !test.locked && Number(test.itemCount) === itemCount && (subjectSelect.value === 'mixed' || (test.subjectIds || []).map(Number).includes(Number(subjectSelect.value))));
        testSelect.innerHTML = tests.map(test => `<option value="${Number(test.id)}">${escapeHtml(test.title)}</option>`).join('') || '<option value="">No matching pre-made tests</option>';
        start.disabled = !subjects.length || (mode === 'premade' && !tests.length && subjectSelect.value === 'mixed');
        host.querySelector('.v2-premade-choice').classList.toggle('hidden',mode !== 'premade' || !tests.length);
    };
    host.querySelectorAll('[data-v2-mode]').forEach(button => button.addEventListener('click',() => {
        mode = button.dataset.v2Mode;
        host.querySelectorAll('[data-v2-mode]').forEach(item => item.setAttribute('aria-pressed',String(item === button)));
        update();
    }));
    subjectSelect.addEventListener('change',update);
    start.addEventListener('click',async () => {
        const selected = subjectSelect.value === 'mixed' ? subjects : subjects.filter(subject => Number(subject.id) === Number(subjectSelect.value));
        const test = mode === 'premade' ? (workspace.premadeTests || []).find(item => Number(item.id) === Number(testSelect.value)) : null;
        if (!await showTestBankInstructions({title:test?.title || title,itemCount,timeLimitMinutes:test?.timeLimitMinutes || null,coverage:selected.map(subject => subject.title)})) return;
        start.disabled = true; start.textContent = 'Preparing…';
        try {
            let quizId = test?.id;
            if (!quizId) {
                const endpoint = mode === 'custom' ? `/api/test-banks/${workspace.id}/quizzes` : `/api/test-banks/${workspace.id}/subjects/${Number(subjectSelect.value)}/quizzes`;
                const payload = mode === 'custom' ? {subject_ids:selected.map(subject => Number(subject.id)),item_count:itemCount,timed:false,time_limit_minutes:null} : {quiz_type:mastery ? 'mastery' : 'warm_up'};
                quizId = (await apiRequest(endpoint,'POST',payload)).quizId;
            }
            await startTestBankPremadeQuiz(workspace.id,Number(quizId),start);
        } catch(error) { showToast(error.message || 'Unable to prepare this quiz.','error'); }
        finally { start.textContent = startLabel; update(); }
    });
    update();
    host.scrollIntoView({behavior:'smooth',block:'start'});
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
