@extends('admin.layouts.app')

@section('title', (Auth::user()->hasAdminAccess()) ? 'Admin Dashboard' : ucfirst((string) Auth::user()->role).' Dashboard')
@section('kicker', 'Overview')


@section('content')
<style>
    .dashboard-registration-stack { display:grid;gap:1rem;min-width:0; }
    .dashboard-active-batches.panel { padding:clamp(1.25rem,2.5vw,1.9rem); }
    .dashboard-active-batches .panel-subtitle { line-height:1.55;margin-bottom:1.45rem; }
    .active-batch-chart { display:grid;gap:1.15rem;margin:0;padding:0;list-style:none; }
    .active-batch-chart li { --batch-bar:#184b7a;min-width:0; }
    .active-batch-chart li + li { padding-top:1.15rem;border-top:1px solid var(--border); }
    .active-batch-chart [data-family="nclex"] { --batch-bar:var(--brand-aqua); }
    .active-batch-chart [data-family="pnle"] { --batch-bar:#e6b628; }
    .active-batch-chart [data-family="prometric"] { --batch-bar:#28945f; }
    .active-batch-chart [data-family="cse"] { --batch-bar:var(--brand-slate); }
    .active-batch-label { display:flex;justify-content:space-between;align-items:baseline;gap:1rem;line-height:1.4;overflow-wrap:anywhere; }
    .active-batch-label span { font-variant-numeric:tabular-nums;flex:none; }
    .active-batch-chart small { display:block;margin-top:.3rem;font-size:.8rem;line-height:1.5;color:var(--text-muted);overflow-wrap:anywhere; }
    .active-batch-track { margin-top:.65rem;height:.55rem;border-radius:99px;background:var(--border);overflow:hidden; }
    .active-batch-track span { display:block;height:100%;border-radius:inherit;background:var(--batch-bar); }
    body:not(.light-mode) .active-batch-track span { background:color-mix(in srgb,var(--batch-bar) 65%,white); }
    .admin-dashboard-metrics { display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem; }
    body .admin-dashboard-metrics .metric-card {
        --metric-ink:var(--brand-ice);
        min-width:0;min-height:8.5rem;display:flex;flex-direction:column;justify-content:center;gap:.45rem;
        border:1px solid rgba(255,255,255,.32);border-radius:16px;padding:1.3rem;
        background:var(--brand-slate);
        box-shadow:0 8px 20px rgba(25,52,79,.13);
    }
    body.light-mode .admin-dashboard-metrics .metric-card {
        --metric-ink:var(--brand-slate);
        background:var(--brand-white);
        border-color:var(--border);
    }
    .admin-dashboard-metrics .metric-card::before,.admin-dashboard-metrics .metric-card::after { content:none; }
    .admin-dashboard-metrics .metric-label { margin:0;color:var(--metric-ink);font-size:.78rem;letter-spacing:normal;text-transform:none; }
    .admin-dashboard-metrics .metric-value {
        margin:0;background:none;color:var(--metric-ink);-webkit-text-fill-color:currentColor;
        font-size:clamp(1.35rem,2vw,1.95rem);line-height:1.15;overflow-wrap:anywhere;font-variant-numeric:tabular-nums;
    }
    .admin-dashboard-metrics .metric-note { margin:0;color:var(--metric-ink);font-size:.78rem; }
    .admin-dashboard-insights { display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,1fr);gap:1rem;margin-top:1rem;align-items:start; }
    body .admin-dashboard-insights .panel { min-width:0;background:var(--surface-solid);border-radius:16px;box-shadow:0 6px 18px rgba(25,52,79,.08); }
    .admin-dashboard-insights .panel::before,.admin-dashboard-insights .panel::after { content:none; }
    .admin-dashboard-recent .data-table { min-width:0; }
    .admin-dashboard-recent .admin-compact-table :is(th,td) { padding:.45rem .65rem; }
    .admin-dashboard-recent .admin-compact-table .status { padding:.22rem .55rem; }
    body .admin-dashboard-recent .data-table thead th { background:#48515f;color:#fff; }
    .admin-dashboard-recent .data-table tbody tr:nth-child(odd) td { background:var(--surface-solid); }
    .admin-dashboard-recent .data-table tbody tr:nth-child(even) td { background:color-mix(in srgb,var(--surface-solid) 92%,var(--text)); }
    .recent-users-pagination { display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:.6rem;margin-top:1rem; }
    .registration-chart-scroll { overflow-x:auto; }
    .registration-chart { display:grid;grid-auto-flow:column;grid-auto-columns:minmax(2rem,1fr);gap:.35rem;min-width:max-content;margin:0;padding:0;list-style:none; }
    .registration-month { min-width:0;display:grid;grid-template-rows:11rem auto;gap:.5rem;text-align:center;font-size:.72rem;color:var(--text); }
    .registration-track { display:flex;align-items:flex-end;justify-content:center;padding-top:1.4rem;border-bottom:1px solid var(--border); }
    .registration-bar { position:relative;width:75%;max-width:2.5rem;border-radius:5px 5px 0 0;background:var(--brand-aqua); }
    .registration-count { position:absolute;bottom:100%;left:50%;transform:translateX(-50%);padding-bottom:.2rem;font-weight:700;font-variant-numeric:tabular-nums; }
    @media(max-width:1120px) {
        .admin-dashboard-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .admin-dashboard-insights { grid-template-columns:minmax(0,1fr); }
    }
    @media(max-width:520px) { .admin-dashboard-metrics { grid-template-columns:minmax(0,1fr); } }
</style>
<div class="admin-dashboard-metrics">
    @foreach ($stats as $stat)
        <article class="metric-card">
            <p class="metric-label">{{ $stat['label'] }}</p>
            <p class="metric-value">{{ $stat['value'] }}</p>
            <p class="metric-note">{{ $stat['note'] }}</p>
        </article>
    @endforeach
</div>

<div class="admin-dashboard-insights">
    @include('admin.partials.dashboard-recent-users')
    <div class="dashboard-registration-stack">
    <section class="panel" aria-labelledby="monthly-registrations-title">
        <p class="panel-label">{{ $registrationYear }} overview</p>
        <h2 class="panel-title" id="monthly-registrations-title">Monthly Registrations</h2>
        <p class="panel-subtitle">New accounts from January through {{ last($monthlyRegistrations)['label'] }}.</p>
        @php($chartMax = max(1, max(array_column($monthlyRegistrations, 'count'))))
        <div class="registration-chart-scroll" tabindex="0" aria-label="Monthly registrations bar chart">
            <ol class="registration-chart" role="list">
                @foreach($monthlyRegistrations as $registration)
                    <li class="registration-month">
                        <div class="registration-track">
                            <div class="registration-bar" style="height:{{ $registration['count'] / $chartMax * 100 }}%">
                                <span class="registration-count">{{ number_format($registration['count']) }}</span>
                            </div>
                        </div>
                        <abbr title="{{ $registration['label'] }}">{{ $registration['month'] }}</abbr>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>
    @if($activeBatches !== null)
        @include('admin.partials.dashboard-active-batches')
    @endif
    </div>
</div>
<script>
(() => {
    let chart = document.querySelector('[data-active-batches]');
    if (!chart) return;
    let pending = false;
    const refresh = async (url, paging = false) => {
        if (pending) return;
        pending = true;
        if (paging) chart.setAttribute('aria-busy', 'true');
        const error = chart.querySelector('[data-active-batches-error]');
        try {
            const response = await fetch(url, {
                headers:{Accept:'text/html','X-Requested-With':'XMLHttpRequest','X-Admin-Table':'active-batches'},
                credentials:'same-origin', cache:'no-store',
            });
            if (!response.ok) throw new Error();
            const next = new DOMParser().parseFromString(await response.text(), 'text/html').querySelector('[data-active-batches]');
            if (!next) throw new Error();
            const focused = chart.contains(document.activeElement);
            chart.replaceWith(next);
            chart = next;
            if (focused) (chart.querySelector('[data-active-batches-page]:not(:disabled)') || chart.querySelector('h2')).focus({preventScroll:true});
        } catch {
            error.textContent = 'Unable to refresh active batches. Showing the last loaded counts.';
            error.hidden = false;
        } finally {
            pending = false;
            chart.removeAttribute('aria-busy');
        }
    };
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-active-batches-page]');
        if (button?.dataset.activeBatchesPage && !button.disabled) refresh(button.dataset.activeBatchesPage, true);
    });
    setInterval(() => { if (!document.hidden) refresh(chart.dataset.pageUrl); }, 5 * 60 * 1000);
})();

document.addEventListener('click', async event => {
    const button = event.target.closest('[data-recent-users-page]');
    if (!button || button.disabled || !button.dataset.recentUsersPage) return;
    const panel = button.closest('[data-ajax-table="recent-users"]');
    if (panel.getAttribute('aria-busy') === 'true') return;
    const errorMessage = panel.querySelector('.recent-users-error');
    errorMessage.hidden = true;
    panel.setAttribute('aria-busy', 'true');
    try {
        const response = await fetch(button.dataset.recentUsersPage, {
            headers: {Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest'},
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error();
        const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
        const next = parsed.querySelector('[data-ajax-table="recent-users"]');
        if (!next) throw new Error();
        panel.replaceWith(next);
        next.querySelector('[data-recent-users-page]:not(:disabled)')?.focus({preventScroll:true});
    } catch {
        errorMessage.textContent = 'Unable to load recent users. Please try again.';
        errorMessage.hidden = false;
    } finally {
        panel.removeAttribute('aria-busy');
    }
});
</script>
@endsection
