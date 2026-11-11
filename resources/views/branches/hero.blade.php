<section class="jumbotron branch-hero">
    <img class="branch-hero-image" data-branch-preview-image @if(isset($branch)) src="{{ route('branches.hero', $branch->slug, false) }}" @else hidden @endif alt="">
    <div class="branch-hero-copy">
        @if(isset($branch))<h1>{{ $branch->name }}</h1>@else<h2 data-branch-preview-name>Branch name</h2>@endif
        <p class="branch-hero-description" data-branch-preview-description>{{ $branch->description ?? '' }}</p>
    </div>
</section>
