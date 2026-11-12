@extends('admin.layouts.app')

@section('title', Auth::user()->hasAdminAccess() ? 'Payment and Vouchers' : 'Voucher Management')
@section('kicker', 'Payments and Access')



@section('content')
<style>
    body .data-table.payments-directory-table thead th { background:#48515f;color:#fff; }
    .payment-table-wrap { container-type:inline-size; }
    .data-table.payments-directory-table { min-width:0;table-layout:fixed; }
    .payments-directory-table :is(th,td) { padding:.5rem .65rem; }
    .payments-directory-table tbody td { background:var(--surface-solid); }
    .payments-directory-table .payment-record-alt td { background:color-mix(in srgb,var(--surface-solid) 92%,var(--text)); }
    .payments-directory-table .payment-cell { display:block;min-width:0;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
    .payments-directory-table .status.payment-cell { width:fit-content;padding:.22rem .45rem; }
    .payment-reference { display:flex;align-items:center;gap:.25rem; }
    .payment-expand { flex:none;display:grid;place-items:center;width:2rem;height:2rem;background:transparent;border:0;border-radius:6px;color:var(--text);cursor:pointer; }
    .payment-expand:hover { background:var(--border); }
    .payment-expand:focus-visible { outline:2px solid var(--brand-aqua); }
    .payment-expand[aria-expanded="true"] span { transform:rotate(90deg); }
    .payment-details[hidden] { display:none; }
    .payment-details dl { display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,12rem),1fr));gap:.7rem 1rem;margin:.15rem 0;padding:.5rem .65rem; }
    .payment-details dt { font-weight:700;color:var(--text-muted); }
    .payment-details dd { margin:0;overflow-wrap:anywhere; }
    .payment-table-toolbar { display:flex;align-items:flex-end;flex-wrap:wrap;gap:.6rem;margin-top:.7rem; }
    .payment-table-toolbar .field { margin:0; }
    .payment-table-toolbar .field:has(input[type="search"]) { flex:1 1 14rem; }
    @container (max-width:800px) { .payments-directory-table .payment-secondary { display:none; } }
    @container (max-width:460px) { .payments-directory-table .payment-learner { display:none; } }
    .payment-pages { display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin-top:.65rem; }
</style>
@if(Auth::user()->hasAdminAccess())
    <section class="panel" style="margin-bottom:1rem">
        <h2 class="panel-title">Automatic Payments</h2>
        <p class="panel-subtitle">Pause new PayMongo checkouts for maintenance. Existing payments can still be verified, and enrollment codes remain usable.</p>
        <form id="payment-mode-form" method="post" action="{{ route('admin.vouchers.payment-mode') }}" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
            @csrf @method('PUT')
            <label><input type="checkbox" id="automatic-payment-toggle" role="switch" @checked($automaticPayments)> Enable automatic PayMongo payments</label>
            <button type="submit" class="btn-primary">Save payment mode</button>
        </form>
    </section>
    <section class="panel" style="margin-bottom:1rem">
        <h2 class="panel-title">Issue Enrollment Voucher</h2>
        <p class="panel-subtitle">Generate a single-use code for the learner’s registered email and selected batch.</p>
        <form id="assigned-voucher-form" action="{{ route('admin.vouchers.assign') }}" method="post">
            @csrf
            <div class="form-grid">
                <div class="field"><label for="voucher-learner-email">Learner email</label><input id="voucher-learner-email" type="email" name="email" required maxlength="255"></div>
                <div class="field"><label for="voucher-batch">Batch class</label><select id="voucher-batch" name="batch_id" required><option value="">Select an available batch</option>@foreach($paymentBatches as $batch)<option value="{{ $batch->id }}">{{ $batch->name }} ({{ $batch->code }})</option>@endforeach</select></div>
            </div>
            <button class="btn-primary" type="submit" @disabled($paymentBatches->isEmpty())>Generate &amp; Email Voucher</button>
        </form>
    </section>
@endif
    @include('admin.partials.payment-directory')

<script>
(() => {
    let pending;
    let columnObserver;
    const fitPaymentColumns = panel => {
        const table = panel.querySelector('.payments-directory-table');
        const wrap = table.closest('.payment-table-wrap');
        const sync = () => {
            const count = [...table.tHead.rows[0].cells]
                .filter(cell => getComputedStyle(cell).display !== 'none').length;
            if (count) table.querySelectorAll('tbody td[colspan]').forEach(cell => {
                if (cell.colSpan !== count) cell.colSpan = count;
            });
        };
        columnObserver?.disconnect();
        sync();
        if ('ResizeObserver' in window) {
            columnObserver = new ResizeObserver(sync);
            columnObserver.observe(wrap);
        }
    };
    const load = async url => {
        const panel = document.querySelector('[data-ajax-table="vouchers-table"]');
        const message = panel.querySelector('.payment-list-message');
        pending?.abort();
        const request = new AbortController();
        pending = request;
        panel.setAttribute('aria-busy', 'true');
        message.textContent = 'Loading payments…';
        message.hidden = false;
        try {
            const response = await fetch(url, {
                headers:{Accept:'text/html','X-Requested-With':'XMLHttpRequest','X-Admin-Table':'vouchers-table'},
                credentials:'same-origin',signal:request.signal,
            });
            if(!response.ok) throw new Error();
            const html = new DOMParser().parseFromString(await response.text(), 'text/html');
            const next = html.querySelector('[data-ajax-table="vouchers-table"]');
            if(!next) throw new Error();
            if(!request.signal.aborted) {
                const focused = document.activeElement;
                const focusId = panel.contains(focused) ? focused.id : '';
                const wasPaging = focused?.hasAttribute('data-payment-page');
                panel.replaceWith(next);
                fitPaymentColumns(next);
                if(focusId) document.getElementById(focusId)?.focus({preventScroll:true});
                else if(wasPaging) next.querySelector('[data-payment-page]:not(:disabled)')?.focus({preventScroll:true});
            }
        } catch {
            if(!request.signal.aborted) message.textContent = 'Unable to load payments. Select Search to try again.';
        } finally {
            if(!request.signal.aborted) panel.removeAttribute('aria-busy');
        }
    };
    const reload = form => {
        const url = new URL(form.action);
        url.search = new URLSearchParams(new FormData(form));
        load(url.href);
    };
    document.addEventListener('submit', event => {
        if (event.target.id !== 'voucher-table-filters') return;
        event.preventDefault();
        reload(event.target);
    });
    document.addEventListener('change', event => {
        if (event.target.id === 'voucher-size') reload(event.target.form);
    });
    document.addEventListener('click', event => {
        const expand = event.target.closest('.payment-expand');
        if (expand) {
            const details = document.getElementById(expand.getAttribute('aria-controls'));
            if (details) {
                details.hidden = !details.hidden;
                expand.setAttribute('aria-expanded', String(!details.hidden));
            }
            return;
        }
        const button = event.target.closest('[data-payment-page]');
        if (button && !button.disabled) load(button.dataset.paymentPage);
    });
    document.addEventListener('payment-directory:refresh', () => reload(document.getElementById('voucher-table-filters')));
    const directory = document.querySelector('[data-ajax-table="vouchers-table"]');
    fitPaymentColumns(directory);
    const initial = directory.hasAttribute('data-payment-directory-lazy') ? directory : null;
    if (initial && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
            if (entries.some(entry => entry.isIntersecting)) {
                observer.disconnect();
                if (initial.isConnected && !initial.hasAttribute('aria-busy')) reload(initial.querySelector('form'));
            }
        });
        observer.observe(initial);
    } else if (initial) reload(initial.querySelector('form'));
})();
</script>
@if(Auth::user()->hasAdminAccess())
<script>
document.addEventListener('DOMContentLoaded', () => {
    const mode = document.getElementById('payment-mode-form');
    const issue = document.getElementById('assigned-voucher-form');
    for (const form of [mode, issue]) form.addEventListener('submit', async event => {
        event.preventDefault();
        const button = form.querySelector('[type=submit]');
        if (button.disabled) return;
        button.disabled = true;
        const payload = new FormData(form);
        if (form === mode) payload.set('enabled', document.getElementById('automatic-payment-toggle').checked ? '1' : '0');
        try {
            const response = await fetch(form.action, {method:'POST', body:payload, headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
            const data = await response.json().catch(() => null);
            if (!response.ok || !data?.success) throw new Error(Object.values(data?.errors || {}).flat()[0] || 'Unable to save. Please try again.');
            showAdminToast(data.message, data.emailed === false ? 'info' : 'success');
            if (form === issue) {
                form.reset();
                document.dispatchEvent(new Event('payment-directory:refresh'));
            }
        } catch (error) { showAdminToast(error.message, 'error'); }
        finally { button.disabled = false; }
    });
});
</script>
@endif
@endsection
