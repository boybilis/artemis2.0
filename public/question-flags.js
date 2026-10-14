(() => {
    const footer = document.querySelector('#assessment-summary-screen .exam-environment-footer');
    if (!footer) return;
    const style = document.createElement('style');
    style.textContent = '.question-flag-dialog{position:fixed;inset:0;margin:auto;width:min(600px,calc(100vw - 32px));max-height:85vh;overflow:auto;box-sizing:border-box;padding:24px;border:1px solid var(--border);border-radius:16px;background:var(--surface-solid,#fff);color:var(--text)}.question-flag-dialog::backdrop{background:#0008}.question-flag-dialog textarea{width:100%;box-sizing:border-box;padding:12px;min-height:130px;margin:12px 0;border:1px solid var(--border);border-radius:8px;font:inherit}.question-flag-actions{display:flex;gap:12px;justify-content:flex-end;flex-wrap:wrap}.question-flag-card{padding:20px 0;border-bottom:1px solid var(--border)}.question-flag-card p{white-space:pre-wrap;overflow-wrap:anywhere}.question-flag-dialog header{display:flex;justify-content:space-between;align-items:center;gap:16px}';
    document.head.append(style);
    const button = document.createElement('button');
    button.type = 'button'; button.className = 'btn-ghost'; button.textContent = 'Flag this question';
    footer.style.gap = '12px'; footer.style.flexWrap = 'wrap';
    footer.insertBefore(button, document.getElementById('assessment-summary-continue'));
    function modal(title) {
        const dialog = document.createElement('dialog'); dialog.className = 'question-flag-dialog';
        dialog.setAttribute('aria-label', title);
        dialog.innerHTML = `<header><h2>${escapeHtml(title)}</h2><button type="button" class="btn-ghost" aria-label="Close">×</button></header>`;
        dialog.querySelector('header button').onclick = () => dialog.close();
        dialog.addEventListener('close', () => dialog.remove(), {once:true});
        document.body.append(dialog); return dialog;
    }
    button.onclick = () => {
        const selectedButton = document.querySelector('[data-summary-question].active');
        const question = selectedButton ? assessmentSummaryQuestions[Number(selectedButton.dataset.summaryQuestion)] : null;
        if (!question) { showToast('Select a question to flag.', 'info'); return; }
        const dialog = modal('Flag this question');
        const form = document.createElement('form');
        form.innerHTML = `<p>${escapeHtml(question.question)}</p><label for="question-flag-reason">Reason for flagging</label><textarea id="question-flag-reason" name="reason" minlength="3" maxlength="5000" required placeholder="What would you like to ask about this question?"></textarea><p role="status"></p><div class="question-flag-actions"><button type="button" class="btn-ghost">Cancel</button><button type="submit" class="btn-primary">Submit</button></div>`;
        form.querySelector('[type="button"]').onclick = () => dialog.close();
        form.onsubmit = async event => {
            event.preventDefault(); const submit = form.querySelector('[type="submit"]'); submit.disabled = true;
            try {
                await apiRequest('/api/question-flags', 'POST', {question:question.question,reason:form.elements.reason.value});
                dialog.close(); showToast('Question flagged. View replies in Flagged Questions.', 'success');
            } catch (error) { form.querySelector('[role="status"]').textContent = error.message; submit.disabled = false; }
        };
        dialog.append(form); dialog.showModal(); form.elements.reason.focus();
    };
    const progress = document.getElementById('sidebar-my-progress-btn');
    const menu = document.createElement('button'); menu.type = 'button'; menu.className = 'learner-sidebar-item';
    menu.innerHTML = '<i data-lucide="flag"></i><span>Flagged Questions</span>'; progress?.after(menu);
    menu.onclick = async () => {
        const dialog = modal('Flagged Questions');
        dialog.style.width = 'min(850px,calc(100vw - 32px))';
        const content = document.createElement('div'); dialog.append(content); dialog.showModal();
        async function load(page = 1) {
            content.textContent = 'Loading flagged questions…';
            try {
                const result = await apiRequest(`/api/question-flags?page=${page}`), records = result.flags;
                content.innerHTML = records.data.map(flag => `<article class="question-flag-card"><strong>${flag.reply ? 'Answered' : 'Awaiting reply'}</strong><p><strong>Question:</strong> ${escapeHtml(flag.question_snapshot.question)}</p><p><strong>Your reason:</strong> ${escapeHtml(flag.reason)}</p>${flag.reply ? `<p><strong>Reply from ${escapeHtml(flag.responder?.name || 'Management')}:</strong> ${escapeHtml(flag.reply)}</p>` : '<p>Your instructor or administrator will reply here.</p>'}</article>`).join('') || '<p>You have not flagged any questions yet.</p>';
                const pages = document.createElement('div'); pages.className = 'question-flag-actions';
                pages.innerHTML = `<button class="btn-ghost" ${page <= 1 ? 'disabled' : ''}>Previous</button><span>Page ${page} of ${records.last_page}</span><button class="btn-ghost" ${page >= records.last_page ? 'disabled' : ''}>Next</button>`;
                pages.firstElementChild.onclick = () => load(page - 1); pages.lastElementChild.onclick = () => load(page + 1); content.append(pages);
            } catch (error) { content.textContent = error.message; }
        }
        await load();
    };
    if (window.lucide) lucide.createIcons();
})();
