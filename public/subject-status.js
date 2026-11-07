(() => {
    document.querySelectorAll('form[data-subject-status]').forEach(form => {
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (form.dataset.saving === 'true') return;
            const button = form.querySelector('button[type="submit"]');
            const status = form.querySelector('input[name="status"]');
            form.dataset.saving = 'true';
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            try {
                const response = await fetch(form.action, {
                    method: 'POST', credentials: 'same-origin',
                    headers: {'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest'},
                    body: new FormData(form)
                });
                const data = await response.json().catch(() => null);
                if (!response.ok || !data?.success || !['approved', 'pending'].includes(data.status)) {
                    throw new Error(data?.message || 'Could not update the subject status. Please try again.');
                }
                const approved = data.status === 'approved';
                button.textContent = approved ? 'Approved' : 'Pending';
                button.classList.toggle('success', approved);
                button.classList.toggle('warning', !approved);
                button.title = approved ? 'Set to Pending — hide from learners' : 'Approve — show to eligible learners';
                button.setAttribute('aria-label', `${form.dataset.subjectTitle}: ${approved ? 'set to pending and hide from learners' : 'approve and show to eligible learners'}`);
                status.value = approved ? 'pending' : 'approved';
                showAdminToast(data.message, 'success');
            } catch (error) {
                showAdminToast(error.message || 'Could not update the subject status. Please try again.', 'error', 6500);
            } finally {
                form.dataset.saving = 'false';
                button.disabled = false;
                button.removeAttribute('aria-busy');
            }
        });
    });
})();
