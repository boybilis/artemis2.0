@extends('admin.layouts.app')
@section('title', 'Branch')
@section('kicker', 'Content Management')

@section('content')
<link rel="stylesheet" href="{{ asset('branch-landing.css') }}?v=6">
<style>
    body .data-table.branches-directory-table thead th { background:#48515f;color:#fff; }
    .branches-directory-table tbody tr:nth-child(odd) td { background:var(--surface-solid); }
    .branches-directory-table tbody tr:nth-child(even) td { background:color-mix(in srgb,var(--surface-solid) 92%,var(--text)); }
    .branch-admin-toolbar,.branch-admin-actions { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem; }
    .branch-admin-list { margin-top:1rem; }
    .branch-admin-domain { margin-bottom:1rem; }
    .branch-admin-domain .field { flex:1;min-width:min(100%,15rem); }
    #branch-dialog { position:fixed;inset:0;margin:auto;width:min(48rem,calc(100% - 1.5rem));max-height:calc(100dvh - 1.5rem);box-sizing:border-box;overflow:auto;border:1px solid var(--border);border-radius:16px;background:var(--surface-solid);color:var(--text);padding:clamp(.9rem,3vw,1.5rem); }
    #branch-dialog::backdrop { background:rgba(12,29,46,.7); }
    #branch-dialog .field { margin-bottom:.9rem; }
    #branch-save-error { color:var(--wrong); }
    .branch-row-actions { display:flex;gap:.25rem; }
    .branch-row-actions button { display:grid;place-items:center;width:44px;height:44px;border:0;border-radius:8px;background:transparent;color:var(--text);cursor:pointer; }
    .branch-row-actions button:hover { background:var(--border); }
    .branch-row-actions .branch-delete { color:var(--wrong); }
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
        <div class="table-wrap"><table class="data-table branches-directory-table" aria-label="Branches">
            <thead><tr><th scope="col">Branch</th><th scope="col">Landing Page</th><th scope="col">Subdomain</th><th scope="col">Actions</th></tr></thead>
            <tbody>
            @forelse($branches as $branch)
                <tr><td>{{ $branch->name }}</td><td><a href="{{ route('branches.show', $branch->slug, false) }}" target="_blank" rel="noopener">/branches/{{ $branch->slug }}</a></td><td>@if($baseDomain)<a href="https://{{ $branch->slug }}.{{ $baseDomain }}/" target="_blank" rel="noopener">{{ $branch->slug }}.{{ $baseDomain }}</a>@else{{ $branch->slug }} <small>(domain not set)</small>@endif</td>
                    <td><div class="branch-row-actions">
                        <button type="button" data-edit-branch="{{ route('admin.branches.update', $branch) }}" data-name="{{ $branch->name }}" data-slug="{{ $branch->slug }}" data-description="{{ $branch->description }}" data-hero="{{ route('branches.hero', $branch->slug, false) }}" aria-label="Edit {{ $branch->name }}" title="Edit branch"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 3 5 5L8 21H3v-5zM13 6l5 5"/></svg></button>
                        @unless(Auth::user()->isAssistantAdmin())
                            <button type="button" class="branch-delete" data-delete-branch="{{ route('admin.branches.destroy', $branch) }}" data-name="{{ $branch->name }}" aria-label="Delete {{ $branch->name }}" title="Delete branch"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V3h8v3m3 0-1 15H6L5 6m5 4v7m4-7v7"/></svg></button>
                        @endunless
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="4">No branches yet.</td></tr>
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
        <input type="hidden" name="_method" id="branch-method" value="POST">
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
    const createUrl = form.action;
    const heroField = get('branch-hero');
    let imageUrl, savedHero;
    const resetPreview = () => {
        preview.hidden = true;
        form.hidden = false;
        if (imageUrl) URL.revokeObjectURL(imageUrl);
        imageUrl = null;
    };
    const openEditor = edit => {
        form.reset();
        resetPreview();
        notice.hidden = true;
        get('branch-dialog-title').textContent = edit ? 'Edit Branch' : 'Add New Branch';
        get('branch-method').value = edit ? 'PUT' : 'POST';
        form.action = edit?.editBranch || createUrl;
        heroField.required = !edit;
        savedHero = edit?.hero || '';
        get('branch-name').value = edit?.name || '';
        get('branch-slug').value = edit?.slug || '';
        get('branch-description').value = edit?.description || '';
        dialog.showModal();
    };
    get('branch-add').onclick = () => openEditor();
    document.addEventListener('click', async event => {
        const edit = event.target.closest('[data-edit-branch]');
        if (edit) { openEditor(edit.dataset); return; }
        const remove = event.target.closest('[data-delete-branch]');
        if (!remove || remove.disabled || !confirm(`Permanently delete ${remove.dataset.name}? Its landing page will no longer be available.`)) return;
        remove.disabled = true;
        try {
            const response = await fetch(remove.dataset.deleteBranch, {
                method:'DELETE', headers:{Accept:'application/json','X-CSRF-TOKEN':form.elements._token.value},
            });
            const data = await response.json().catch(() => null);
            if (!response.ok || !data?.success) throw new Error('Could not delete the branch.');
            showAdminToast(data.message, 'success');
            await loadAdminTable('branches', @json(route('admin.branches.index')), false);
        } catch (error) { showAdminToast(error.message, 'error'); }
        finally { remove.disabled = false; }
    });
    get('branch-close').onclick = () => dialog.close();
    dialog.addEventListener('close', resetPreview);
    get('branch-preview-back').onclick = () => { resetPreview(); get('branch-preview-button').focus(); };
    get('branch-preview-button').onclick = () => {
        if (!form.reportValidity()) return;
        const file = get('branch-hero').files[0];
        if (file && (!['image/jpeg','image/png','image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024)) {
            notice.textContent = 'Choose a JPG, PNG, or WebP image up to 5 MB.';
            notice.hidden = false;
            return;
        }
        const image = preview.querySelector('[data-branch-preview-image]');
        imageUrl = file ? URL.createObjectURL(file) : null;
        image.src = imageUrl || savedHero;
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
