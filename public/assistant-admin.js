// UI convenience only. RestrictAssistantAdmin independently enforces these limits.
(() => {
    function restrictControls() {
        document.querySelectorAll('form').forEach(form => {
            const method = form.querySelector('input[name="_method"]')?.value?.toUpperCase();
            const action = form.getAttribute('action') || '';
            if (method === 'DELETE' || /\/users\/[^/]+\/role(?:\?|$)|\/assessment-attempts\/reset|\/unenroll(?:\?|$)/.test(action)) {
                form.hidden = true;
                form.style.setProperty('display', 'none', 'important');
            }
        });
        document.querySelectorAll('button,a').forEach(control => {
            const label = control.textContent.trim();
            if (/^(?:Delete(?:\s|$)|Reset Attempts?$|Unenroll$)/i.test(label) || /\/audit-logs(?:\?|$)/.test(control.getAttribute('href') || '')) {
                control.hidden = true;
                control.style.setProperty('display', 'none', 'important');
            }
        });
    }
    restrictControls();
    new MutationObserver(restrictControls).observe(document.body, {childList:true,subtree:true});
})();
