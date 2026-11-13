@extends('admin.layouts.app')

@section('title', Auth::user()->hasAdminAccess() ? 'Payment and Vouchers' : 'Voucher Management')
@section('kicker', 'Payments and Access')



@section('content')
<style>
    .paymongo-setup-header { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.8rem; }
    #paymongo-setup-dialog { width:min(52rem,calc(100% - 2rem));max-height:calc(100dvh - 2rem);margin:auto;padding:clamp(1rem,3vw,1.5rem);border:1px solid var(--border);border-radius:16px;background:var(--surface-solid);color:var(--text); }
    #paymongo-setup-dialog::backdrop { background:rgba(9,26,43,.65); }
    .paymongo-setup-actions { display:flex;justify-content:flex-end;gap:.65rem;flex-wrap:wrap; }
    .paymongo-setup-guide { margin:0 0 1.5rem;max-width:75ch;line-height:1.55; }
    #payment-setup-form .form-grid { grid-template-columns:repeat(2,minmax(0,1fr));gap:1.25rem 1.5rem;margin-bottom:1.5rem; }
    #payment-setup-form .field { min-width:0;margin:0; }
    #payment-setup-form label { margin-bottom:.4rem; }
    #payment-setup-form input { min-width:0;max-width:100%; }
    #payment-setup-form small { display:block;margin-top:.45rem;line-height:1.45;overflow-wrap:anywhere;color:var(--text-muted); }
    @media(max-width:900px) {
        #payment-setup-form .form-grid { grid-template-columns:minmax(0,1fr);gap:1.05rem; }
    }
    @media(max-width:520px) {
        .paymongo-setup-actions button { width:100%; }
    }
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
    @unless(Auth::user()->isAssistantAdmin())
        <dialog id="paymongo-setup-dialog" aria-labelledby="paymongo-setup-title">
            <h2 id="paymongo-setup-title" class="panel-title">PayMongo Setup</h2>
            <p class="muted paymongo-setup-guide">Pause automatic payments and resolve any open checkouts before switching accounts. Configure the webhook in the same PayMongo account and mode as the API key.</p>
            <form id="payment-setup-form" method="post" action="{{ route('admin.vouchers.payment-setup') }}" autocomplete="off">
                @csrf @method('PUT')
                <div class="form-grid">
                    <div class="field"><label for="paymongo-api-key">PayMongo secret API key</label><input id="paymongo-api-key" name="paymongo_secret_key" type="password" autocomplete="off" placeholder="sk_live_… or sk_test_…" maxlength="255" required></div>
                    <div class="field"><label for="paymongo-webhook-secret">Webhook signing secret</label><input id="paymongo-webhook-secret" name="paymongo_webhook_secret" type="password" autocomplete="off" placeholder="whsk_…" maxlength="255" required></div>
                    <div class="field"><label for="paymongo-setup-password">Your current LMS password</label><input id="paymongo-setup-password" name="current_password" type="password" autocomplete="current-password" required></div>
                    <div class="field"><label for="paymongo-webhook-url">Webhook endpoint URL</label><input id="paymongo-webhook-url" readonly value="{{ route('payments.paymongo.webhook') }}"><small>Subscribe to checkout_session.payment.paid in PayMongo.</small></div>
                </div>
                <p id="paymongo-setup-error" role="alert" style="color:var(--wrong)" hidden></p>
                <div class="paymongo-setup-actions">
                    <button type="button" class="btn-ghost" id="paymongo-setup-cancel" autofocus>Cancel</button>
                    <button type="submit" class="btn-primary">Save PayMongo Setup</button>
                </div>
            </form>
        </dialog>
    @endunless
    <section class="panel" style="margin-bottom:1rem">
        <div class="paymongo-setup-header">
            <h2 class="panel-title">Automatic Payments</h2>
            @unless(Auth::user()->isAssistantAdmin())
                <button type="button" class="btn-ghost" id="paymongo-setup-open" aria-haspopup="dialog" aria-controls="paymongo-setup-dialog">PayMongo Setup</button>
            @endunless
        </div>
        @unless(Auth::user()->isAssistantAdmin())
            <p id="paymongo-setup-status" class="muted" role="status">{{ app(\App\Services\PaymongoCredentials::class)->status() }}</p>
        @endunless
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
    const setup = document.getElementById('payment-setup-form');
    const dialog = document.getElementById('paymongo-setup-dialog');
    const setupError = document.getElementById('paymongo-setup-error');
    if (dialog) {
        const cancel = document.getElementById('paymongo-setup-cancel');
        document.getElementById('paymongo-setup-open').addEventListener('click', () => dialog.showModal());
        cancel.addEventListener('click', () => dialog.close());
        dialog.addEventListener('cancel', event => { if (cancel.disabled) event.preventDefault(); });
        dialog.addEventListener('close', () => { setup.reset(); setupError.hidden = true; });
    }
    for (const form of [mode, issue, setup].filter(Boolean)) form.addEventListener('submit', async event => {
        event.preventDefault();
        const button = form.querySelector('[type=submit]');
        if (button.disabled) return;
        button.disabled = true;
        const setupCancel = form === setup ? document.getElementById('paymongo-setup-cancel') : null;
        if (setupCancel) { setupCancel.disabled = true; setupError.hidden = true; }
        const payload = new FormData(form);
        if (form === mode) payload.set('enabled', document.getElementById('automatic-payment-toggle').checked ? '1' : '0');
        try {
            const response = await fetch(form.action, {method:'POST', body:payload, headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
            const data = await response.json().catch(() => null);
            if (!response.ok || !data?.success) throw new Error(Object.values(data?.errors || {}).flat()[0] || data?.message || 'Unable to save. Please try again.');
            if (form === issue) {
                form.reset();
                document.dispatchEvent(new Event('payment-directory:refresh'));
            }
            if (form === setup) {
                document.getElementById('paymongo-setup-status').textContent = data.status;
                dialog.close();
            }
            showAdminToast(data.message, data.emailed === false ? 'info' : 'success');
        } catch (error) {
            if (form === setup) { setupError.textContent = error.message; setupError.hidden = false; }
            else showAdminToast(error.message, 'error');
        }
        finally { button.disabled = false; if (setupCancel) setupCancel.disabled = false; }
    });
});
</script>
@endif
@endsection
