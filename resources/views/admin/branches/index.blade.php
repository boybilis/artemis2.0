@extends('admin.layouts.app')
@section('title', 'Branch')
@section('kicker', 'Content Management')

@section('content')
<link rel="stylesheet" href="{{ asset('branch-landing.css') }}?v=4">
<style>
    .branch-admin-toolbar,.branch-admin-actions { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem; }
    .branch-admin-list { margin-top:1rem; }
    .branch-admin-domain { margin-bottom:1rem; }
    .branch-admin-domain .field { flex:1;min-width:min(100%,15rem); }
    #branch-dialog { position:fixed;inset:0;margin:auto;width:min(48rem,calc(100% - 1.5rem));max-height:calc(100dvh - 1.5rem);box-sizing:border-box;overflow:auto;border:1px solid var(--border);border-radius:16px;background:var(--surface-solid);color:var(--text);padding:clamp(.9rem,3vw,1.5rem); }
    #branch-dialog::backdrop { background:rgba(12,29,46,.7); }
    #branch-dialog .field { margin-bottom:.9rem; }
    #branch-dialog .branch-hero { border-radius:12px;min-height:24rem; }
    #branch-save-error { color:var(--wrong); }
</style>
<section class="panel branch-admin-domain">
    <h2 class="panel-title">Branch Base Domain</h2>
    <p class="panel-subtitle">Leave blank to use path links on the current LMS domain. Enter your registered domain only after wildcard DNS, hosting, and HTTPS are ready. Saving here does not configure those services.</p>
    <form id="branch-domain-form" class="branch-admin-actions" action="{{ route('admin.branches.domain') }}" method="post">
        @csrf @method('PUT')
        <div class="field"><label for="branch-base-domain">Registered domain</label><input id="branch-base-domain" name="base_domain" value="{{ $baseDomain }}" placeholder="example.com" maxlength="189"></div>
        <button class="btn-primary" type="submit">Save Domain</button>
    </form>
</section>
<section class="panel">
    <div class="branch-admin-toolbar"><h2 class="panel-title">Branches</h2><button class="btn-primary" id="branch-add" type="button">+ Add New Branch</button></div>
    <div class="branch-admin-list" data-ajax-table="branches">
        <div class="table-wrap"><table class="data-table" aria-label="Branches">
            <thead><tr><th scope="col">Branch</th><th scope="col">Landing Page</th><th scope="col">Subdomain</th></tr></thead>
            <tbody>
            @forelse($branches as $branch)
                <tr><td>{{ $branch->name }}</td><td><a href="{{ route('branches.show', $branch->slug, false) }}" target="_blank" rel="noopener">/branches/{{ $branch->slug }}</a></td><td>@if($baseDomain)<a href="https://{{ $branch->slug }}.{{ $baseDomain }}/" target="_blank" rel="noopener">{{ $branch->slug }}.{{ $baseDomain }}</a>@else{{ $branch->slug }} <small>(domain not set)</small>@endif</td></tr>
            @empty
                <tr><td colspan="3">No branches yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $branches->links('pagination::bootstrap-4') }}
    </div>
</section>
<dialog id="branch-dialog" aria-labelledby="branch-dialog-title">
    <div class="branch-admin-toolbar"><h2 id="branch-dialog-title">Add New Branch</h2><button class="btn-ghost" id="branch-close" type="button">Close</button></div>
    <form id="branch-form" action="{{ route('admin.branches.store') }}" method="post" enctype="multipart/form-data">
        @csrf
        <div class="field"><label for="branch-name">Branch name</label><input id="branch-name" name="name" required maxlength="120"></div>
        <div class="field"><label for="branch-hero">Background image (hero)</label><input id="branch-hero" name="hero" type="file" accept="image/jpeg,image/png,image/webp" required><small>JPG, PNG, or WebP, up to 5 MB.</small></div>
        <div class="field"><label for="branch-description">Description</label><textarea id="branch-description" name="description" rows="5" required maxlength="3000"></textarea></div>
        <div class="field"><label for="branch-slug">Subdomain name</label><input id="branch-slug" name="slug" required maxlength="63" pattern="[A-Za-z0-9](?:[A-Za-z0-9\-]*[A-Za-z0-9])?" placeholder="cebu"><small>Letters, digits, and hyphens. Publishes at /branches/your-name.</small></div>
        <p id="branch-save-error" role="alert" hidden></p>
        <div class="branch-admin-actions"><button class="btn-ghost" id="branch-preview-button" type="button">Preview</button><button class="btn-primary" type="submit">Save Branch</button></div>
    </form>
    <div id="branch-preview" hidden>
        @include('branches.hero', ['branch' => null])
        <button class="btn-ghost" id="branch-preview-back" type="button">Back to form</button>
    </div>
</dialog>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const get = id => document.getElementById(id);
    const dialog = get('branch-dialog'), form = get('branch-form'), preview = get('branch-preview'), notice = get('branch-save-error');
    const domain = get('branch-domain-form');
    let imageUrl;
    const resetPreview = () => {
        preview.hidden = true;
        form.hidden = false;
        if (imageUrl) URL.revokeObjectURL(imageUrl);
        imageUrl = null;
    };
    get('branch-add').onclick = () => dialog.showModal();
    get('branch-close').onclick = () => dialog.close();
    dialog.addEventListener('close', resetPreview);
    get('branch-preview-back').onclick = () => { resetPreview(); get('branch-preview-button').focus(); };
    get('branch-preview-button').onclick = () => {
        if (!form.reportValidity()) return;
        const file = get('branch-hero').files[0];
        if (!['image/jpeg','image/png','image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
            notice.textContent = 'Choose a JPG, PNG, or WebP image up to 5 MB.';
            notice.hidden = false;
            return;
        }
        const image = preview.querySelector('[data-branch-preview-image]');
        imageUrl = URL.createObjectURL(file);
        image.src = imageUrl;
        image.hidden = false;
        preview.querySelector('[data-branch-preview-name]').textContent = get('branch-name').value;
        preview.querySelector('[data-branch-preview-description]').textContent = get('branch-description').value;
        form.hidden = true;
        preview.hidden = false;
        get('branch-preview-back').focus();
    };
    for (const target of [form, domain]) target.addEventListener('submit', async event => {
        event.preventDefault();
        const button = target.querySelector('[type=submit]');
        if (button.disabled) return;
        button.disabled = true;
        notice.hidden = true;
        try {
            const response = await fetch(target.action, {method:'POST', body:new FormData(target), headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
            const data = await response.json().catch(() => null);
            if (!response.ok || !data?.success) throw new Error(Object.values(data?.errors || {}).flat()[0] || 'Could not save. Please try again.');
            if (target === form) { dialog.close(); form.reset(); }
            showAdminToast(data.message, 'success');
            await loadAdminTable('branches', @json(route('admin.branches.index')), false);
        } catch (error) {
            if (target === form && dialog.open) { notice.textContent = error.message; notice.hidden = false; }
            else showAdminToast(error.message, 'error');
        } finally { button.disabled = false; }
    });
});
</script>
@endsection
