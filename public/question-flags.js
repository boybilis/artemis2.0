(() => {
    const footer = document.querySelector('#assessment-summary-screen .exam-environment-footer');
    if (!footer) return;
    const style = document.createElement('style');
    style.textContent = `
        .question-flag-dialog{position:fixed;inset:0;margin:auto;width:min(600px,calc(100vw - 32px));max-height:88dvh;overflow:auto;box-sizing:border-box;padding:0;border:1px solid var(--border);border-radius:20px;background:var(--surface-solid,#fff);color:var(--text);box-shadow:0 24px 70px #073b6333}
        .question-flag-dialog::backdrop{background:rgba(3,24,44,.65)}
        .question-flag-dialog .modal-header{position:sticky;top:0;z-index:1;display:flex;justify-content:space-between;align-items:center;gap:16px;padding:20px 24px;border-bottom:1px solid var(--border);background:var(--surface-solid,#fff)}
        .question-flag-dialog h2{margin:0;font-size:1.3rem;line-height:1.4}
        .question-flag-dialog .question-flag-close{display:grid;place-items:center;flex:0 0 32px;width:32px;height:32px;padding:0;border:0;border-radius:50%;background:#073b63;color:#fff;cursor:pointer}
        .question-flag-dialog .question-flag-close:hover{background:#078b9b}
        .question-flag-dialog .modal-body{padding:24px}
        .question-flag-dialog .form-label{display:block;margin:20px 0 8px;font-weight:600;font-size:.9rem}
        .question-flag-dialog .form-control{display:block;width:100%;box-sizing:border-box;padding:12px 14px;min-height:150px;border:1px solid var(--border);border-radius:10px;font:inherit;line-height:1.6;resize:vertical;background:var(--surface-solid,#fff);color:var(--text);transition:border-color .15s,box-shadow .15s}
        .question-flag-dialog .form-control:focus{outline:0;border-color:#11a7c0;box-shadow:0 0 0 3px #11a7c026}
        .question-flag-dialog .form-text{display:block;margin-top:8px;font-size:.8rem;color:var(--text-muted)}
        .question-flag-dialog .question-flag-question{padding:16px;margin:0;background:var(--surface,#eef8fb);border:1px solid var(--border);border-radius:10px;line-height:1.6;white-space:pre-wrap;overflow-wrap:anywhere}
        .question-flag-actions{display:flex;align-items:center;gap:12px;justify-content:flex-end;flex-wrap:wrap}
        .question-flag-dialog .modal-footer{padding:16px 24px;border-top:1px solid var(--border)}
        .question-flag-dialog .question-flag-actions button{min-height:40px;border-radius:8px}
        .question-flag-dialog button:focus-visible{outline:3px solid #11a7c0;outline-offset:3px}
        .question-flag-card{padding:20px 0;border-bottom:1px solid var(--border)}
        .question-flag-card:first-child{padding-top:0}.question-flag-card:last-child{border-bottom:0}
        .question-flag-card p{white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.6;margin:12px 0}
        .question-flag-status{display:inline-flex;padding:4px 10px;border-radius:999px;font-size:.75rem;font-weight:600;background:#fff1dc;color:#875200}
        .question-flag-status.answered{background:#dff4ed;color:#086c59}
        .question-flag-reply{padding:14px 16px;border-left:3px solid #11a7c0;border-radius:6px;background:var(--surface,#eef8fb)}
        @media(max-width:600px){.question-flag-dialog .modal-header,.question-flag-dialog .modal-body,.question-flag-dialog .modal-footer{padding:16px}.question-flag-dialog h2{font-size:1.1rem}}
    `;
    document.head.append(style);
    const button = document.createElement('button');
    button.type = 'button'; button.className = 'btn-ghost'; button.textContent = 'Flag this question';
    footer.style.gap = '12px'; footer.style.flexWrap = 'wrap';
    footer.insertBefore(button, document.getElementById('assessment-summary-continue'));
    function modal(title) {
        const dialog = document.createElement('dialog'); dialog.className = 'question-flag-dialog';
        dialog.setAttribute('aria-label', title);
        dialog.innerHTML = `<header class="modal-header"><h2 class="modal-title">${escapeHtml(title)}</h2><button type="button" class="question-flag-close" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m4 4 8 8M12 4l-8 8"/></svg></button></header>`;
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
        form.innerHTML = `<div class="modal-body"><p class="question-flag-question">${escapeHtml(question.question)}</p><label class="form-label" for="question-flag-reason">Reason for flagging</label><textarea class="form-control" id="question-flag-reason" name="reason" minlength="3" maxlength="5000" aria-describedby="question-flag-help" required placeholder="What would you like to ask about this question?"></textarea><small class="form-text" id="question-flag-help">Your instructor or administrator can reply in Flagged Questions.</small><p role="status"></p></div><div class="modal-footer question-flag-actions"><button type="button" class="btn-ghost">Cancel</button><button type="submit" class="btn-primary">Submit</button></div>`;
        form.querySelector('[type="button"]').onclick = () => dialog.close();
        form.onsubmit = async event => {
            event.preventDefault(); const submit = form.querySelector('[type="submit"]'); submit.disabled = true;
            try {
                if (!question.flagReference) throw new Error('Please reopen this completed test review before flagging the question.');
                await apiRequest('/api/question-flags', 'POST', {...question.flagReference,reason:form.elements.reason.value});
                dialog.close(); showToast('Question flagged. View replies in Flagged Questions.', 'success');
            } catch (error) { form.querySelector('[role="status"]').textContent = error.message; submit.disabled = false; }
        };
        dialog.append(form); dialog.showModal(); form.elements.reason.focus();
    };
    const progress = document.getElementById('sidebar-my-progress-btn');
    const menu = document.createElement('button'); menu.type = 'button'; menu.className = 'learner-sidebar-item';
    menu.innerHTML = '<i data-lucide="flag"></i><span>Flagged Questions</span>'; progress?.after(menu);
    menu.style.position = 'relative';
    const badge=document.createElement('span');
    badge.hidden=true; badge.setAttribute('aria-live','polite');
    badge.style.cssText='position:absolute;right:6px;top:4px;min-width:19px;padding:2px 5px;border-radius:999px;background:#dc2626;color:#fff;font-size:11px;font-weight:700;line-height:16px;text-align:center';
    menu.append(badge);
    async function refreshUnread() {
        if (!state.user) { badge.hidden=true; return; }
        try { const data=await apiRequest('/api/question-flags/unread'); badge.textContent=data.count; badge.hidden=!data.count; badge.setAttribute('aria-label',`${data.count} unread replies`); } catch (_) {}
    }
    setTimeout(refreshUnread,1500);
    setInterval(()=>{if(!document.hidden) refreshUnread();},30000);
    window.addEventListener('focus',refreshUnread);
    menu.onclick = async () => {
        const dialog = modal('Flagged Questions');
        dialog.style.width = 'min(850px,calc(100vw - 32px))';
        const content = document.createElement('div'); content.className = 'modal-body'; dialog.append(content); dialog.showModal();
        async function load(page = 1) {
            content.textContent = 'Loading flagged questions…';
            try {
                const result = await apiRequest(`/api/question-flags?page=${page}`), records = result.flags;
                content.innerHTML = records.data.map(flag => `<article class="question-flag-card"><span class="badge question-flag-status ${flag.reply ? 'answered' : ''}">${flag.reply ? 'Answered' : 'Awaiting reply'}</span><p><strong>Question:</strong> ${escapeHtml(flag.question_snapshot.question)}</p><p><strong>Your reason:</strong> ${escapeHtml(flag.reason)}</p>${flag.reply ? `<p class="question-flag-reply"><strong>Reply from ${escapeHtml(flag.responder?.name || 'Management')}:</strong> ${escapeHtml(flag.reply)}</p>` : '<p>Your instructor or administrator will reply here.</p>'}</article>`).join('') || '<p>You have not flagged any questions yet.</p>';
                content.querySelectorAll('.question-flag-card').forEach((card,index)=>{
                    const flag=records.data[index];
                    if(!flag.reply) return;
                    const reply=card.querySelector('.question-flag-reply'); reply.hidden=true;
                    const view=document.createElement('button'); view.className='btn-ghost'; view.type='button';
                    view.textContent=flag.learner_read_at ? 'View reply' : 'View new reply';
                    view.onclick=async()=>{
                        reply.hidden=false;
                        try { await apiRequest(`/api/question-flags/${flag.id}/read`,'POST',{replied_at:flag.replied_at}); view.textContent='View reply'; await refreshUnread(); } catch(error){showToast(error.message,'error');}
                    };
                    reply.before(view);
                });
                const pages = document.createElement('div'); pages.className = 'question-flag-actions';
                pages.innerHTML = `<button class="btn-ghost" ${page <= 1 ? 'disabled' : ''}>Previous</button><span>Page ${page} of ${records.last_page}</span><button class="btn-ghost" ${page >= records.last_page ? 'disabled' : ''}>Next</button>`;
                pages.firstElementChild.onclick = () => load(page - 1); pages.lastElementChild.onclick = () => load(page + 1); content.append(pages);
            } catch (error) { content.textContent = error.message; }
        }
        await load();
    };
    if (window.lucide) lucide.createIcons();
})();
