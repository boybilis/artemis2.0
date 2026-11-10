<section class="jumbotron branch-hero">
    <img class="branch-hero-image" data-branch-preview-image @if(isset($branch)) src="{{ route('branches.hero', $branch->slug, false) }}" @else hidden @endif alt="">
    <div class="branch-hero-copy">
        <img class="branch-hero-logo" src="{{ asset('images/gra-test-prep-white.svg') }}" width="220" height="115" alt="GRA Test Prep">
        @if(isset($branch))<h1>{{ $branch->name }}</h1>@else<h2 data-branch-preview-name>Branch name</h2>@endif
        <p class="branch-hero-description" data-branch-preview-description>{{ $branch->description ?? '' }}</p>
        @if(isset($branch))<button class="branch-hero-login" id="hero-login-btn" type="button">Login</button>@else<span class="branch-hero-login">Login</span>@endif
    </div>
</section>
