(() => {
    const exam = document.getElementById('quiz-screen');
    const controls = exam?.querySelector('.exam-footer-controls');
    if (!controls) return;
    const style = document.createElement('style');
    style.textContent = `
        .prometric-calculator-toggle{display:none}
        #quiz-screen.prometric-simulation-exam .prometric-calculator-toggle{display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;padding:9px;border:1px solid #8ac43f;border-radius:8px;background:#8ac43f;color:#17220b;cursor:pointer}
        .prometric-calculator{position:fixed;inset:auto;top:var(--calculator-top,104px);right:16px;margin:0;width:min(300px,calc(100vw - 32px));max-height:var(--calculator-height,70vh);overflow:auto;box-sizing:border-box;padding:18px;border:2px solid #8ac43f;border-radius:14px;background:#fff;color:#222;box-shadow:0 12px 48px #0006;font:16px Arial,sans-serif}
        .prometric-calculator::backdrop{background:#0004}
        .prometric-calculator header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
        .prometric-calculator button{font:inherit;cursor:pointer;border:1px solid #ccc;border-radius:6px;background:#f2f2f2;color:#222;min-height:44px}
        .prometric-calculator button:hover{background:#d9e9c1}
        .prometric-calculator button:focus-visible,.prometric-calculator-toggle:focus-visible{outline:3px solid #628c2d;outline-offset:3px}
        .prometric-calculator header button{width:32px;min-height:32px;border:0;border-radius:50%;background:#444;color:white;display:grid;place-items:center}
        .prometric-calculator output{display:block;text-align:right;padding:14px 8px;background:#eff3e8;border:1px solid #ccc;border-radius:6px;margin-bottom:12px;font-size:26px;overflow-wrap:anywhere}
        .prometric-calculator-keys{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
        .prometric-calculator button[data-key="="]{background:#8ac43f}
    `;
    document.head.append(style);
    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'prometric-calculator-toggle';
    toggle.title = 'Calculator';
    toggle.setAttribute('aria-label', 'Open calculator');
    toggle.innerHTML = '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M7 6h10M7 10h2m2 0h2m2 0h2M7 14h2m2 0h2m2 0h2M7 18h2m2 0h2m2 0h2"/></svg>';
    controls.append(toggle);
    const summaryToggle = document.createElement('button');
    summaryToggle.type = 'button';
    summaryToggle.className = 'prometric-calculator-toggle';
    summaryToggle.title = 'Test summary';
    summaryToggle.setAttribute('aria-label', 'Open test summary');
    summaryToggle.innerHTML = '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="6" height="6"/><rect x="15" y="3" width="6" height="6"/><rect x="3" y="15" width="6" height="6"/><rect x="15" y="15" width="6" height="6"/></svg>';
    controls.append(summaryToggle);
    const summary = document.createElement('dialog');
    summary.className = 'prometric-calculator prometric-test-summary';
    summary.setAttribute('aria-label', 'Test summary');
    exam.append(summary);
    style.textContent += `
        .prometric-test-summary{inset:0;margin:auto;width:min(1100px,calc(100vw - 32px));max-height:calc(100dvh - 32px)}
        .prometric-summary-counts{line-height:1.7;margin:12px 0}
        .prometric-summary-legend{display:flex;flex-wrap:wrap;gap:12px;font-size:13px;margin-bottom:16px}
        .prometric-summary-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(44px,1fr));gap:8px}
        .prometric-summary-grid button{font-size:13px;font-weight:700;min-height:44px;padding:4px;border:1px solid currentColor}
        .prometric-summary-grid .summary-answered{background:#8ac43f;color:#17220b}
        .prometric-summary-grid .summary-unanswered{background:#e0e0e0;color:#252525}
        .prometric-summary-grid .summary-marked{background:#facc15;color:#302500}
        .prometric-summary-grid button[aria-current="true"]{outline:2px solid #222;outline-offset:2px}
    `;
    summaryToggle.addEventListener('click', () => {
        if (!exam.classList.contains('prometric-simulation-exam') || summary.open) return;
        const preview = typeof quizData === 'undefined';
        if (!preview && !assessmentReviewActive && quizData[qIndex]) {
            answersList[qIndex] = Array.isArray(selected) ? [...selected] : selected && typeof selected === 'object' ? {...selected} : selected;
        }
        const total = preview ? questions.length : quizData.length;
        const current = preview ? index : qIndex;
        const entries = Array.from({length:total}, (_, position) => ({
            position,
            answered: preview ? answers[position] !== undefined : assessmentAnswerIsComplete(answersList[position]),
            marked: preview ? Boolean(marked[position]) : reviewQuestions.has(position)
        }));
        const done = entries.filter(entry => entry.answered).length;
        const flags = entries.filter(entry => entry.marked).length;
        summary.innerHTML = '<header><strong>Test summary</strong><button type="button" aria-label="Close test summary">×</button></header>';
        const counts = document.createElement('p');
        counts.className = 'prometric-summary-counts';
        counts.textContent = `${done} answered · ${total - done} remaining · ${flags} marked · ${total} total`;
        summary.append(counts);
        const legend = document.createElement('div');
        legend.className = 'prometric-summary-legend';
        legend.textContent = '✓ Green: answered   ○ Gray: unanswered   ⚑ Yellow: marked for review';
        summary.append(legend);
        const grid = document.createElement('div');
        grid.className = 'prometric-summary-grid';
        entries.forEach(entry => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = entry.marked ? 'summary-marked' : entry.answered ? 'summary-answered' : 'summary-unanswered';
            button.textContent = `${entry.position + 1} ${entry.marked ? '⚑' : entry.answered ? '✓' : '○'}`;
            button.setAttribute('aria-label', `Question ${entry.position + 1}, ${entry.answered ? 'answered' : 'unanswered'}${entry.marked ? ', marked for review' : ''}`);
            if (entry.position === current) button.setAttribute('aria-current', 'true');
            button.addEventListener('click', () => {
                summary.close();
                if (preview) { index = entry.position; render(); }
                else {
                    qIndex = entry.position;
                    assessmentReviewActive = false;
                    editingQuestionFromReview = false;
                    document.getElementById('assessment-review-panel').style.display = 'none';
                    document.getElementById('quiz-question-panel').style.display = '';
                    renderQuestion();
                }
                exam.querySelector('.exam-environment-body').scrollTop = 0;
            });
            grid.append(button);
        });
        summary.append(grid);
        summary.querySelector('header button').onclick = () => summary.close();
        summary.showModal();
    });
    const dialog = document.createElement('dialog');
    dialog.className = 'prometric-calculator';
    dialog.setAttribute('aria-label', 'Calculator');
    dialog.innerHTML = '<header><strong>Calculator</strong><button type="button" aria-label="Close calculator"><svg width="16" height="16" viewBox="0 0 16 16" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m3 3 10 10M13 3 3 13"/></svg></button></header><output aria-live="polite">0</output><div class="prometric-calculator-keys"></div>';
    exam.append(dialog);
    let value = '0', stored = null, operator = null, fresh = true;
    const output = dialog.querySelector('output');
    function press(key) {
        if (key === 'C') { value = '0'; stored = operator = null; fresh = true; }
        else if (/^[0-9.]$/.test(key)) {
            if (fresh || value === 'Error') { value = key === '.' ? '0.' : key; fresh = false; }
            else if (key !== '.' || !value.includes('.')) { if (value.length < 15) value = value === '0' && key !== '.' ? key : value + key; }
        } else if (key === '⌫') { value = value.slice(0, -1) || '0'; }
        else if (key === '±') { if (Number(value)) value = String(-Number(value)); }
        else {
            const number = Number(value);
            if (!Number.isFinite(number)) return;
            if (operator && stored !== null && !fresh) {
                const result = operator === '+' ? stored + number : operator === '−' ? stored - number : operator === '×' ? stored * number : number === 0 ? NaN : stored / number;
                value = Number.isFinite(result) ? String(Number(result.toPrecision(12))) : 'Error';
            }
            stored = value === 'Error' || key === '=' ? null : Number(value);
            operator = key === '=' || value === 'Error' ? null : key;
            fresh = true;
        }
        output.textContent = value;
    }
    for (const key of ['C','⌫','±','÷','7','8','9','×','4','5','6','−','1','2','3','+','0','.','=']) {
        const button = document.createElement('button');
        button.type = 'button'; button.textContent = key; button.dataset.key = key;
        if (key === '⌫') button.setAttribute('aria-label', 'Backspace');
        if (key === '±') button.setAttribute('aria-label', 'Change sign');
        button.addEventListener('click', () => press(key));
        dialog.querySelector('.prometric-calculator-keys').append(button);
    }
    function positionCalculator() {
        const body = exam.querySelector('.exam-environment-body').getBoundingClientRect();
        const top = Math.max(16, body.top + 16);
        dialog.style.setProperty('--calculator-top', `${top}px`);
        dialog.style.setProperty('--calculator-height', `${Math.max(44, Math.min(body.bottom, window.innerHeight) - top - 16)}px`);
    }
    toggle.addEventListener('click', () => {
        if (exam.classList.contains('prometric-simulation-exam') && !dialog.open) {
            positionCalculator();
            dialog.showModal();
        }
    });
    window.addEventListener('resize', () => { if (dialog.open) positionCalculator(); });
    dialog.querySelector('header button').addEventListener('click', () => dialog.close());
    dialog.addEventListener('keydown', event => {
        const key = ({'*':'×','/':'÷','-':'−','Enter':'=','Backspace':'⌫','Delete':'C'})[event.key] || event.key;
        if (/^[0-9.+]$/.test(key) || ['×','÷','−','=','⌫','C'].includes(key)) { event.preventDefault(); event.stopPropagation(); press(key); }
    });
    new MutationObserver(() => {
        if (summary.open && (!exam.classList.contains('prometric-simulation-exam') || !exam.classList.contains('active'))) summary.close();
        if (dialog.open && (!exam.classList.contains('prometric-simulation-exam') || !exam.classList.contains('active'))) dialog.close();
    }).observe(exam, {attributes:true, attributeFilter:['class']});
})();
