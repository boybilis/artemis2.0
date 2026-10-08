@extends('admin.layouts.app')

@section('title', 'Test Banks')
@section('kicker', 'Test Bank Management')

@section('content')
<section class="panel">
    <p class="panel-label">MASTER COURSES</p>
    <h2 class="panel-title">Test Bank Catalogs</h2>
    <p class="panel-subtitle">Choose a master course to create and manage its catalogs, questions, and premade tests.</p>
    <div class="test-bank-directory">
        @forelse($courses as $course)
            <article class="test-bank-directory-card">
                <div><h3>{{ $course->title }}</h3><p class="muted">{{ $course->test_banks_count }} {{ $course->test_banks_count === 1 ? 'catalog' : 'catalogs' }}</p></div>
                <a class="btn-primary" href="{{ route('admin.content.test-banks.index', $course) }}">Manage Test Banks</a>
            </article>
        @empty
            <p class="muted">No master courses available. Create a course in Course Management first.</p>
        @endforelse
    </div>
    {{ $courses->links() }}
</section>
<style>
    .test-bank-directory{display:grid;gap:1rem;margin:1.25rem 0}.test-bank-directory-card{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1.25rem;border:1px solid var(--border);border-radius:14px;background:var(--surface)}.test-bank-directory-card h3{margin:0;font-size:1.05rem}.test-bank-directory-card p{margin:.4rem 0 0;font-size:.8rem}.test-bank-directory-card a{text-decoration:none;white-space:nowrap}@media(max-width:600px){.test-bank-directory-card{align-items:stretch;flex-direction:column}.test-bank-directory-card a{text-align:center}}
</style>
@endsection
