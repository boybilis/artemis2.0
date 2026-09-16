@extends('admin.layouts.app', ['pageTitle' => 'Review Packages'])

@section('kicker', 'Package Management')

@section('content')
<div class="page-grid two">
    <section class="panel">
        <div class="panel-label">Package promo</div>
        <h2 class="panel-title" id="package-form-heading">Create Package</h2>
        <p class="panel-subtitle">Combine multiple active batch offerings under one promotional price.</p>
        <form id="package-form" method="POST" action="{{ route('admin.packages.store') }}">
            @csrf
            <input type="hidden" id="package-method" name="_method" value="POST">
            <div class="field"><label for="package-name">Package name</label><input id="package-name" name="name" required></div>
            <div class="field"><label for="package-description">Description</label><textarea id="package-description" name="description" rows="4"></textarea></div>
            <div class="form-grid-2">
                <div class="field"><label for="package-price">Package price (PHP)</label><input id="package-price" name="price" type="number" min="0" step="0.01" required></div>
                <div class="field"><label for="package-start">Start date</label><input id="package-start" name="starts_at" type="date" required></div>
                <div class="field"><label for="package-class-type">Type of class</label><select id="package-class-type" name="class_type" required><option>Live Online</option><option>Face to face</option><option>Full Online</option></select></div>
                <div class="field"><label for="package-status">Status</label><select id="package-status" name="status" required><option value="active">Active</option><option value="draft">Draft</option></select></div>
            </div>
            <div class="field">
                <label>Included active batch offerings</label>
                <div class="package-batch-checklist">
                    @forelse($batches as $batch)
                        <label class="package-batch-option"><input type="checkbox" name="batch_ids[]" value="{{ $batch->id }}"><span><strong>{{ $batch->course?->title }} — {{ $batch->name }}</strong><small>{{ $batch->code }} · {{ $batch->starts_at?->format('M d, Y') ?? 'Open schedule' }}</small></span></label>
                    @empty
                        <p class="muted">No active batch offerings are currently available.</p>
                    @endforelse
                </div>
            </div>
            <div style="display:flex;gap:.65rem;margin-top:1rem"><button class="btn-primary" type="submit">Save Package</button><button class="btn-ghost hidden" id="package-cancel-edit" type="button" onclick="resetPackageForm()">Cancel Edit</button></div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-label">Current promotions</div>
        <h2 class="panel-title">Package Offerings</h2>
        <div class="list-stack" style="margin-top:1.25rem">
            @forelse($packages as $package)
                @php
                    $packageEditorData = [
                        'id' => $package->id, 'name' => $package->name, 'description' => $package->description,
                        'price' => $package->price, 'starts_at' => $package->starts_at?->toDateString(),
                        'class_type' => $package->class_type, 'status' => $package->status,
                        'batch_ids' => $package->batches->pluck('id')->values(),
                    ];
                @endphp
                <article class="list-item">
                    <div style="display:flex;justify-content:space-between;gap:1rem"><div><strong>{{ $package->name }}</strong><span class="muted">₱{{ number_format($package->price, 2) }} · {{ $package->class_type }} · starts {{ $package->starts_at?->format('M d, Y') }}</span></div><span class="status {{ $package->status === 'active' ? 'success' : 'warning' }}">{{ ucfirst($package->status) }}</span></div>
                    <p class="muted" style="margin:.75rem 0">{{ $package->description ?: 'No description provided.' }}</p>
                    <ul style="margin:.5rem 0 1rem;padding-left:1.25rem">@foreach($package->batches as $batch)<li>{{ $batch->course?->title }} — {{ $batch->name }} ({{ $batch->code }})</li>@endforeach</ul>
                    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                        <button type="button" class="btn-ghost" data-package="{{ json_encode($packageEditorData) }}" onclick="editPackage(JSON.parse(this.dataset.package))">Edit</button>
                        <form method="POST" action="{{ route('admin.packages.destroy', $package) }}" onsubmit="return confirm('Delete this package promotion?')">@csrf @method('DELETE')<button class="btn-ghost" style="color:var(--wrong)" type="submit">Delete</button></form>
                    </div>
                </article>
            @empty
                <p class="muted">No package promotions have been created.</p>
            @endforelse
        </div>
    </section>
</div>

<style>
.form-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.package-batch-checklist{max-height:310px;overflow:auto;border:1px solid var(--border);border-radius:12px;padding:.6rem}.package-batch-option{display:flex;align-items:flex-start;gap:.7rem;padding:.75rem;border-radius:9px;cursor:pointer}.package-batch-option:hover{background:rgba(255,255,255,.05)}.package-batch-option input{margin-top:.2rem}.package-batch-option span{display:flex;flex-direction:column;gap:.2rem}.package-batch-option small{color:var(--text-muted)}@media(max-width:720px){.form-grid-2{grid-template-columns:1fr}}
</style>
<script>
const packageForm=document.getElementById('package-form');
function editPackage(item){packageForm.action=`/admin/packages/${item.id}`;document.getElementById('package-method').value='PUT';document.getElementById('package-form-heading').textContent='Edit Package';document.getElementById('package-name').value=item.name||'';document.getElementById('package-description').value=item.description||'';document.getElementById('package-price').value=item.price||0;document.getElementById('package-start').value=item.starts_at||'';document.getElementById('package-class-type').value=item.class_type;document.getElementById('package-status').value=item.status;document.querySelectorAll('input[name="batch_ids[]"]').forEach(input=>input.checked=item.batch_ids.includes(Number(input.value)));document.getElementById('package-cancel-edit').classList.remove('hidden');window.scrollTo({top:0,behavior:'smooth'});}
function resetPackageForm(){packageForm.reset();packageForm.action='{{ route('admin.packages.store') }}';document.getElementById('package-method').value='POST';document.getElementById('package-form-heading').textContent='Create Package';document.getElementById('package-cancel-edit').classList.add('hidden');}
</script>
@endsection
