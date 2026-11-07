(() => {
    const list = document.getElementById('subjects-sortable');
    if (!list) return;
    const rows = () => Array.from(list.querySelectorAll('tr[data-subject-id]'));
    let previous = [], saving = false, sortable;
    async function saveOrder() {
        const ordered = rows();
        if (ordered.every((row, index) => row === previous[index])) return;
        saving = true;
        sortable?.option('disabled', true);
        list.setAttribute('aria-busy', 'true');
        list.querySelectorAll('.subject-drag-handle').forEach(button => button.disabled = true);
        try {
            const response = await fetch(list.dataset.reorderUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: {'Accept':'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN':list.dataset.csrf},
                body: JSON.stringify({ordered_ids: ordered.map(row => Number(row.dataset.subjectId))})
            });
            const data = await response.json().catch(() => null);
            if (!response.ok || !data?.success) throw new Error(data?.message || 'Could not save subject order. Please try again.');
            ordered.forEach((row, index) => {
                const edit = row.querySelector('.edit-subject-btn');
                if (edit) edit.dataset.order = String(index + 1);
            });
            showAdminToast(data.message, 'success');
        } catch (error) {
            previous.forEach(row => list.appendChild(row));
            showAdminToast(error.message || 'Could not save subject order. Please try again.', 'error', 6500);
        } finally {
            saving = false;
            sortable?.option('disabled', false);
            list.removeAttribute('aria-busy');
            list.querySelectorAll('.subject-drag-handle').forEach(button => button.disabled = false);
        }
    }
    if (typeof Sortable !== 'undefined') {
        sortable = new Sortable(list, {
            handle: '.subject-drag-handle', draggable: 'tr[data-subject-id]', direction: 'vertical',
            animation: 150, ghostClass: 'subject-drag-ghost',
            onStart() { previous = rows(); }, onEnd: saveOrder
        });
    }
    list.addEventListener('keydown', event => {
        const handle = event.target.closest('.subject-drag-handle');
        if (!handle || saving || !['ArrowUp', 'ArrowDown'].includes(event.key)) return;
        event.preventDefault();
        previous = rows();
        const row = handle.closest('tr'), index = previous.indexOf(row);
        const neighbor = previous[index + (event.key === 'ArrowUp' ? -1 : 1)];
        if (!neighbor) return;
        if (event.key === 'ArrowUp') list.insertBefore(row, neighbor);
        else list.insertBefore(neighbor, row);
        saveOrder().then(() => handle.focus());
    });
})();
