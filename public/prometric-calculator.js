(() => {
    const exam = document.getElementById('quiz-screen');
    const controls = exam?.querySelector('.exam-footer-controls');
    if (!controls) return;
    const style = document.createElement('style');
    style.textContent = `
        .prometric-calculator-toggle{display:none}
        #quiz-screen.prometric-simulation-exam .prometric-calculator-toggle{display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;padding:9px;border:1px solid #8ac43f;border-radius:8px;background:#8ac43f;color:#17220b;cursor:pointer}
        .prometric-calculator{position:fixed;inset:0;margin:auto;width:min(300px,calc(100vw - 32px));max-height:90vh;overflow:auto;box-sizing:border-box;padding:18px;border:2px solid #8ac43f;border-radius:14px;background:#fff;color:#222;box-shadow:0 12px 48px #0006;font:16px Arial,sans-serif}
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
    toggle.addEventListener('click', () => {
        if (exam.classList.contains('prometric-simulation-exam') && !dialog.open) dialog.showModal();
    });
    dialog.querySelector('header button').addEventListener('click', () => dialog.close());
    dialog.addEventListener('keydown', event => {
        const key = ({'*':'×','/':'÷','-':'−','Enter':'=','Backspace':'⌫','Delete':'C'})[event.key] || event.key;
        if (/^[0-9.+]$/.test(key) || ['×','÷','−','=','⌫','C'].includes(key)) { event.preventDefault(); event.stopPropagation(); press(key); }
    });
    new MutationObserver(() => {
        if (dialog.open && (!exam.classList.contains('prometric-simulation-exam') || !exam.classList.contains('active'))) dialog.close();
    }).observe(exam, {attributes:true, attributeFilter:['class']});
})();
