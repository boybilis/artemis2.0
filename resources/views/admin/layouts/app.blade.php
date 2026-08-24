@php
    $pageTitle = trim($__env->yieldContent('title')) ?: 'Admin';
    $isAdmin = Auth::user()->is_admin || trim(strtolower(Auth::user()->role)) === 'admin';
    $workspaceRole = $isAdmin ? 'Admin' : 'Instructor';
    
    $pendingContentCount = 0;
    if ($isAdmin) {
        $pendingContentCount = \App\Models\Topic::where('status', 'pending')->count() 
                             + \App\Models\QuizQuestion::where('status', 'pending')->count();
    }

    $navItems = [
        ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => 'layout-dashboard'],
        ['label' => 'Course Management', 'route' => 'admin.content.index', 'active' => 'admin.content.*', 'icon' => 'book-open', 'badge' => $pendingContentCount > 0 ? $pendingContentCount : null],
        ['label' => 'Class Management', 'route' => 'admin.classes.index', 'active' => 'admin.classes.*', 'icon' => 'calendar-days'],
    ];
    if ($isAdmin) {
        array_splice($navItems, 1, 0, [[
            'label' => 'Users', 'route' => 'admin.users.index', 'active' => 'admin.users.*', 'icon' => 'users'
        ]]);
    }
    
    $newVouchersCount = 0;
    $newCertificatesCount = 0;
    
    if ($isAdmin || trim(strtolower(Auth::user()->role)) === 'instructor') {
        $lastVouchersViewed = Auth::user()->last_vouchers_viewed_at ?? '1970-01-01 00:00:00';
        $lastCertificatesViewed = Auth::user()->last_certificates_viewed_at ?? '1970-01-01 00:00:00';
        
        $newVouchersCount = \App\Models\Voucher::where('created_at', '>', $lastVouchersViewed)->count();
        $newCertificatesCount = \App\Models\Certificate::where('created_at', '>', $lastCertificatesViewed)->count();
        
        $navItems[] = ['label' => 'Vouchers', 'route' => 'admin.vouchers.index', 'active' => 'admin.vouchers.*', 'icon' => 'ticket', 'badge' => $newVouchersCount > 0 ? $newVouchersCount : null];
        $navItems[] = ['label' => 'Certificates', 'route' => 'admin.certificates.index', 'active' => 'admin.certificates.*', 'icon' => 'award', 'badge' => $newCertificatesCount > 0 ? $newCertificatesCount : null];
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle }} | Artemis 2.0 {{ $workspaceRole }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="{{ asset('style.css') }}?v={{ time() }}">
    <style>
        body { margin: 0; overflow: hidden; }
        .admin-shell { display: grid; grid-template-columns: 270px 1fr; height: calc(100vh - 75px); background: var(--bg); overflow: hidden; }
        .sidebar { background: rgba(10,10,15,0.8); backdrop-filter: blur(20px); border-right: 1px solid var(--border); padding: 1.5rem; display: flex; flex-direction: column; overflow-y: auto; gap: 1rem; }
        body.light-mode .sidebar { background: rgba(232,232,232,0.85); }
        .nav-list { display: flex; flex-direction: column; gap: 0.5rem; }
        .nav-link { padding: 0.68rem 0.85rem; border-radius: 8px; font-size: 0.83rem; color: var(--text); display: flex; align-items: center; gap: 0.75rem; transition: all 0.2s; text-decoration: none; }
        .nav-link:not(.active):hover { background: rgba(255,255,255,0.05); }
        body.light-mode .nav-link:not(.active):hover { background: rgba(0,0,0,0.05); }
        .nav-link.active { background: var(--gradient); color: #fff; font-weight: 600; box-shadow: 0 4px 15px var(--glow); }

        .admin-main { flex: 1; background: var(--bg); display: flex; flex-direction: column; min-width: 0; overflow-y: auto; }
        
        .page-header { margin-bottom: 2rem; }
        .page-header h1 { font-family: 'Outfit', sans-serif; font-size: 1.8rem; font-weight: 800; margin: 0; }
        .kicker { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase; color: var(--accent); margin-bottom: 0.3rem; }

        .content { padding: 2.5rem; max-width: 1200px; margin: 0 auto; width: 100%; }
        
        .admin-user-chip { display: flex; align-items: center; gap: 1rem; padding: 0.35rem 0.85rem; border: 1.5px solid var(--border); border-radius: 12px; background: rgba(255,255,255,0.04); }
        body.light-mode .admin-user-chip { background: rgba(0,0,0,0.04); }
        .avatar { width: 26px; height: 26px; border-radius: 8px; background: var(--gradient); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.7rem; color: #fff; }
        
        /* Dashboard styles */
        .page-grid, .stat-grid, .split-grid { display: grid; gap: 1.25rem; }
        .stat-grid { grid-template-columns: repeat(4, 1fr); }
        .page-grid.two { grid-template-columns: 1.45fr 0.95fr; }
        
        .panel, .metric-card { background: rgba(255,255,255,0.04); border: 1.5px solid var(--border); border-radius: 12px; padding: 1.5rem; }
        body.light-mode .panel, body.light-mode .metric-card { background: rgba(0,0,0,0.03); }
        .panel-label, .metric-label { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.75rem; }
        .metric-value { font-family: 'Outfit', sans-serif; font-size: 1.8rem; font-weight: 800; margin-bottom: 0.25rem; background: var(--gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .metric-note { font-size: 0.82rem; color: var(--text-muted); }
        
        .panel-title { font-family: 'Outfit', sans-serif; font-size: 1.2rem; font-weight: 700; margin-bottom: 0.4rem; }
        .panel-subtitle { font-size: 0.88rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 1.5rem; }
        
        .list-stack { display: flex; flex-direction: column; gap: 0.85rem; }
        .list-item { background: rgba(255,255,255,0.02); border: 1.5px solid var(--border); border-radius: 10px; padding: 1rem; }
        body.light-mode .list-item { background: rgba(0,0,0,0.02); }
        .list-item strong { display: block; font-size: 0.95rem; margin-bottom: 0.4rem; }
        
        .progress-track { height: 6px; background: rgba(255,255,255,0.1); border-radius: 99px; margin-bottom: 0.5rem; overflow: hidden; }
        body.light-mode .progress-track { background: rgba(0,0,0,0.1); }
        .progress-fill { height: 100%; background: var(--gradient); border-radius: 99px; }

        /* Forms */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; }
        .form-grid .field.full { grid-column: 1 / -1; }
        
        /* Modals overrides */
        .modal-content { background: var(--bg2); border: 1.5px solid var(--border); border-radius: 16px; width: 100%; max-width: 600px; }
        body.light-mode .modal-content { background: #e8e8e8; }
        .modal-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-title { margin: 0; font-family: 'Outfit', sans-serif; font-size: 1.4rem; font-weight: 700; }
        .modal-body { padding: 1.5rem; }
        .modal-footer { padding: 1.25rem 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 0.75rem; }

        /* Tables */
        .table-wrap { overflow-x: auto; border: 1.5px solid var(--border); border-radius: 12px; background: rgba(255,255,255,0.02); margin-top: 1rem; }
        body.light-mode .table-wrap { background: rgba(0,0,0,0.02); }
        .data-table { width: 100%; border-collapse: collapse; min-width: 700px; }
        .data-table th, .data-table td { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); text-align: left; font-size: 0.875rem; }
        .data-table th { font-size: 0.75rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: var(--text-muted); background: rgba(255,255,255,0.02); }
        body.light-mode .data-table th { background: rgba(0,0,0,0.02); }
        .data-table tr:last-child td { border-bottom: none; }
        
        .status { display: inline-flex; align-items: center; padding: 0.35rem 0.75rem; border-radius: 99px; font-size: 0.75rem; font-weight: 700; background: rgba(255,255,255,0.05); }
        .status.success { color: var(--correct); background: rgba(16,185,129,0.12); }
        .status.warning { color: #f59e0b; background: rgba(245,158,11,0.12); }
        .status.danger { color: var(--wrong); background: rgba(239,68,68,0.12); }
        .status.info { color: #3b82f6; background: rgba(59,130,246,0.12); }
        
        .toolbar { display: flex; gap: 1rem; align-items: center; justify-content: space-between; flex-wrap: wrap; margin-bottom: 1.25rem; }
        .toolbar-group { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; }

        .notice { padding: 1rem 1.5rem; border: 1.5px solid var(--border); border-radius: 12px; margin-bottom: 1.5rem; font-size: 0.875rem; background: rgba(255,255,255,0.03); }

        .btn-danger { background: var(--wrong); color: #fff; border: none; padding: .75rem 1.5rem; border-radius: 10px; font-family: inherit; font-weight: 600; font-size: .9rem; cursor: pointer; transition: all .3s; white-space: nowrap; }
        .btn-warning { background: #f59e0b; color: #fff; border: none; padding: .75rem 1.5rem; border-radius: 10px; font-family: inherit; font-weight: 600; font-size: .9rem; cursor: pointer; transition: all .3s; white-space: nowrap; }

        /* Dropdown overrides */
        .dropdown-menu { display: none; position: absolute; right: 0; top: 100%; background: var(--bg2); border: 1.5px solid var(--border); border-radius: 12px; padding: 0.5rem; min-width: 160px; z-index: 100; margin-top: 0.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        body.light-mode .dropdown-menu { background: #e8e8e8; }
        .dropdown-menu.open { display: block; }
        .dropdown-item { display: block; width: 100%; text-align: left; padding: 0.6rem 1rem; border: none; background: none; color: var(--text); font-size: 0.85rem; border-radius: 8px; cursor: pointer; }
        .dropdown-item:hover { background: rgba(255,255,255,0.05); }
        body.light-mode .dropdown-item:hover { background: rgba(0,0,0,0.05); }
        .dropdown-item.danger { color: var(--wrong); }
        .dropdown-item.danger:hover { background: rgba(239,68,68,0.1); }

        .admin-mobile-menu { display: none; width: 42px; height: 42px; align-items: center; justify-content: center; border: 1px solid var(--border); border-radius: 10px; background: var(--surface); color: var(--text); cursor: pointer; flex-shrink: 0; }
        .admin-sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 190; }
        .table-scroll-hint { display: none; color: var(--text-muted); font-size: 0.72rem; font-weight: 600; margin: 0.65rem 0 -0.35rem; align-items: center; gap: 0.35rem; }

        @media (max-width: 900px) {
            .admin-shell { grid-template-columns: 1fr; }
            .admin-mobile-menu { display: inline-flex; }
            .sidebar { display: flex; position: fixed; top: 0; bottom: 0; left: 0; width: min(82vw, 300px); z-index: 200; transform: translateX(-105%); transition: transform 0.25s ease; box-shadow: 18px 0 45px rgba(0,0,0,0.3); padding-top: 5.5rem; }
            body.admin-nav-open .sidebar { transform: translateX(0); }
            body.admin-nav-open .admin-sidebar-overlay { display: block; }
            body.admin-nav-open { overflow: hidden; }
            .stat-grid, .page-grid.two, .split-grid { grid-template-columns: 1fr !important; }
            .content { padding: 1.5rem; }
            .landing-nav { padding: 0.8rem 1.25rem !important; }
            .page-header { margin-bottom: 1.35rem; }
            .panel, .metric-card { padding: 1.15rem; }
            .form-grid { grid-template-columns: 1fr; }
            .form-grid .field { grid-column: 1 / -1; }
            .toolbar, .toolbar-group { align-items: stretch; }
            .toolbar > *, .toolbar-group, .toolbar-group input, .toolbar-group select { width: 100% !important; min-width: 0 !important; }
            .table-wrap { -webkit-overflow-scrolling: touch; overscroll-behavior-x: contain; scrollbar-width: thin; }
            .table-scroll-hint { display: flex; }
            .data-table th, .data-table td { padding: 0.8rem 0.9rem; }
            .pagination { flex-wrap: wrap; gap: 0.35rem; }
            .page-item .page-link { min-width: 38px; min-height: 38px; padding: 0.45rem 0.65rem; }
            .admin-modal { padding: 0.75rem; align-items: flex-start; }
            .admin-modal-content { max-height: calc(100dvh - 1.5rem); border-radius: 12px; }
            .admin-modal-header, .admin-modal-body, .admin-modal-footer { padding: 1rem; }
            .admin-modal-footer { flex-wrap: wrap; }
            .admin-modal-footer > button { flex: 1 1 120px; min-height: 44px; }
            .tabs { overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; }
            .course-card { align-items: flex-start !important; flex-direction: column; gap: 1rem; }
            .course-actions { width: 100%; display: flex; flex-wrap: wrap; gap: 0.5rem; }
            .course-actions > * { flex: 1 1 120px; }
            .floating-save-toast { width: calc(100% - 2rem); max-width: 520px; flex-wrap: wrap; gap: 0.75rem !important; }
        }

        @media (max-width: 600px) {
            .content { padding: 1rem; }
            .landing-nav .nav-logo { font-size: 0.9rem; }
            .landing-nav .nav-actions { gap: 0.4rem; }
            .landing-nav .nav-actions .btn-ghost { padding: 0.55rem 0.7rem; font-size: 0.78rem; }
            .page-header h1 { font-size: 1.45rem; }
            .panel, .metric-card { padding: 1rem; }
            .metric-value { font-size: 1.5rem; }
            .notice { padding: 0.85rem 1rem; }
            .data-table { min-width: 640px; }
            .question-option-row { grid-template-columns: auto minmax(0,1fr) !important; }
            .question-option-row .btn-ghost { grid-column: 2; justify-self: start; }
        }
    </style>
</head>
<body class="light-mode">
    <script>
        (function () {
            const saved = localStorage.getItem('cssm_theme');
            if (saved === 'dark') {
                document.body.classList.remove('light-mode');
            }
        })();
    </script>
        <nav class="landing-nav" style="padding: 1rem 2.5rem; z-index: 100;">
        <div class="nav-logo" style="display: flex; align-items: center; gap: 0.5rem;">
            <button type="button" id="admin-mobile-menu" class="admin-mobile-menu" aria-label="Open admin navigation" aria-controls="admin-sidebar" aria-expanded="false">
                <i data-lucide="menu"></i>
            </button>
            Artemis 2.0 <span aria-hidden="true">|</span> {{ $workspaceRole }}
        </div>
        <div class="nav-actions">
            @yield('header_actions')
            <button type="button" class="btn-ghost" onclick="openAdminProfileSettings()" title="Account settings">
                <i data-lucide="settings" style="width:16px;height:16px"></i>
                <span>Settings</span>
            </button>
            <form action="{{ route('admin.logout') }}" method="POST" style="margin: 0;">
                @csrf
                <button type="submit" class="btn-ghost">Log out</button>
            </form>
            <button id="theme-toggle-btn" class="btn-icon-round" title="Toggle light/dark mode" aria-label="Toggle theme">
                <i id="theme-icon" data-lucide="moon"></i>
            </button>
        </div>
    </nav>

    <button type="button" id="admin-sidebar-overlay" class="admin-sidebar-overlay" aria-label="Close admin navigation"></button>

    <div class="admin-shell">
        <aside class="sidebar" id="admin-sidebar" aria-label="{{ $workspaceRole }} menu">
            <nav class="nav-list" aria-label="{{ $workspaceRole }} navigation">
                @foreach ($navItems as $item)
                    <a class="nav-link {{ request()->routeIs($item['active']) ? 'active' : '' }}" href="{{ route($item['route']) }}">
                        <i data-lucide="{{ $item['icon'] }}" style="width: 18px; height: 18px; opacity: 0.9;"></i>
                        <span style="flex-grow: 1;">{{ $item['label'] }}</span>
                        @if(isset($item['badge']) && $item['badge'])
                            <span style="background: var(--wrong); color: #fff; font-size: 0.65rem; padding: 0.15rem 0.45rem; border-radius: 99px; font-weight: 700; box-shadow: 0 0 10px rgba(239, 68, 68, 0.4);">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>
        </aside>

        <main class="admin-main">
            <section class="content">
                <div class="page-header">
                    <p class="kicker">@yield('kicker', $workspaceRole . ' Panel')</p>
                    <h1>{{ $pageTitle }}</h1>
                </div>
                @if (session('success'))
                    <div class="notice" style="border-color: #bbf7d0; background: #f0fdf4; color: #166534;">
                        {{ session('success') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="notice" style="border-color: #fecaca; background: #fef2f2; color: #991b1b;">
                        {{ session('error') }}
                    </div>
                @endif
                @if ($errors->any())
                    <div class="notice" style="border-color: #fecaca; background: #fef2f2; color: #991b1b;">
                        <ul style="margin: 0; padding-left: 20px;">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @yield('content')


            </section>
        </main>
    </div>

    <div id="adminProfileSettingsModal" class="admin-modal" role="dialog" aria-modal="true" aria-labelledby="adminProfileSettingsTitle" style="z-index:10020">
        <form id="adminProfileSettingsForm" class="admin-modal-content" style="max-width:560px">
            <div class="admin-modal-header">
                <div><p class="panel-label" style="margin:0 0 .25rem">{{ $workspaceRole }} account</p><h3 id="adminProfileSettingsTitle" class="admin-modal-title">Account Settings</h3></div>
                <button type="button" class="admin-modal-close" onclick="closeAdminProfileSettings()">&times;</button>
            </div>
            <div class="admin-modal-body">
                <div id="adminProfileSettingsErrors" class="notice" style="display:none;margin-bottom:1rem;border-color:#fecaca;background:#fef2f2;color:#991b1b"></div>
                <div class="form-grid" style="margin-bottom:0">
                    <div class="field full"><label for="admin_settings_email">Email address</label><input id="admin_settings_email" name="email" type="email" value="{{ Auth::user()->email }}" autocomplete="email" required></div>
                    <div class="field full"><label for="admin_settings_phone">Phone number</label><input id="admin_settings_phone" name="phone" type="tel" value="{{ Auth::user()->phone }}" autocomplete="tel" maxlength="30" required></div>
                    <div class="field full"><label for="admin_settings_current_password">Current password</label><input id="admin_settings_current_password" name="current_password" type="password" autocomplete="current-password"><small style="display:block;margin-top:.4rem;color:var(--text-muted)">Required when changing your email address or password.</small></div>
                    <div class="field"><label for="admin_settings_password">New password</label><input id="admin_settings_password" name="password" type="password" minlength="8" autocomplete="new-password" placeholder="Leave blank to keep current"></div>
                    <div class="field"><label for="admin_settings_password_confirmation">Confirm new password</label><input id="admin_settings_password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password"></div>
                </div>
            </div>
            <div class="admin-modal-footer"><button type="button" class="btn-ghost" onclick="closeAdminProfileSettings()">Cancel</button><button id="adminProfileSettingsSave" type="submit" class="btn-primary">Save Changes</button></div>
        </form>
    </div>
    
    <!-- GLOBAL DELETE CONFIRMATION MODAL -->
    <div id="deleteConfirmModal" class="admin-modal" style="z-index: 9999;">
        <div class="admin-modal-content" style="max-width: 400px; text-align: center;">
            <div class="admin-modal-header" style="justify-content: center; border-bottom: none; padding-bottom: 0;">
                <h3 class="admin-modal-title" style="font-size: 1.25rem; text-align: center; width: 100%;">Confirm Deletion</h3>
            </div>
            <div class="admin-modal-body" style="padding-top: 10px;">
                <p id="deleteConfirmMessage" style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 0;">Are you sure you want to delete this item?</p>
            </div>
            <div class="admin-modal-footer" style="border-top: none; padding-top: 15px; display: flex; gap: 10px; width: 100%;">
                <button type="button" class="btn-ghost" onclick="closeDeleteModal()" style="flex: 1; padding: 0.75rem;">Cancel</button>
                <button type="button" id="confirmDeleteBtn" class="btn-primary" style="flex: 1; padding: 0.75rem; background: var(--wrong); border-color: var(--wrong);">Delete</button>
            </div>
        </div>
    </div>

    <div id="adminAlertModal" class="admin-modal" role="dialog" aria-modal="true" aria-labelledby="adminAlertTitle" style="z-index:10050">
        <div class="admin-modal-content" style="max-width:440px">
            <div class="admin-modal-header"><h3 id="adminAlertTitle" class="admin-modal-title">Notice</h3><button type="button" class="admin-modal-close" onclick="closeAdminAlert()">&times;</button></div>
            <div class="admin-modal-body"><p id="adminAlertMessage" style="margin:0;color:var(--text-muted);line-height:1.65"></p></div>
            <div class="admin-modal-footer"><button type="button" class="btn-primary" onclick="closeAdminAlert()">OK</button></div>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        function showAdminAlert(message, title = 'Notice') {
            document.getElementById('adminAlertTitle').textContent = title;
            document.getElementById('adminAlertMessage').textContent = String(message ?? '');
            document.getElementById('adminAlertModal').classList.add('open');
        }
        function closeAdminAlert() { document.getElementById('adminAlertModal').classList.remove('open'); }
        window.alert = function (message) { showAdminAlert(message); };

        function openAdminProfileSettings() {
            const modal = document.getElementById('adminProfileSettingsModal');
            document.getElementById('adminProfileSettingsErrors').style.display = 'none';
            document.getElementById('adminProfileSettingsErrors').textContent = '';
            document.getElementById('admin_settings_current_password').value = '';
            document.getElementById('admin_settings_password').value = '';
            document.getElementById('admin_settings_password_confirmation').value = '';
            modal.classList.add('open');
            setTimeout(() => document.getElementById('admin_settings_email').focus(), 50);
        }
        function closeAdminProfileSettings() { document.getElementById('adminProfileSettingsModal').classList.remove('open'); }

        document.getElementById('adminProfileSettingsForm').addEventListener('submit', async function (event) {
            event.preventDefault();
            const form = event.currentTarget;
            const saveButton = document.getElementById('adminProfileSettingsSave');
            const errorBox = document.getElementById('adminProfileSettingsErrors');
            saveButton.disabled = true;
            saveButton.textContent = 'Saving...';
            errorBox.style.display = 'none';
            try {
                const response = await fetch('/api/profile/settings', {
                    method: 'POST', credentials: 'same-origin',
                    headers: {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':@json(csrf_token()),'X-Requested-With':'XMLHttpRequest'},
                    body: JSON.stringify(Object.fromEntries(new FormData(form)))
                });
                const data = await response.json();
                if (!response.ok) {
                    const messages = data.errors ? Object.values(data.errors).flat() : [data.message || 'Unable to update account settings.'];
                    errorBox.textContent = messages.join(' ');
                    errorBox.style.display = 'block';
                    return;
                }
                document.getElementById('admin_settings_email').value = data.user.email;
                document.getElementById('admin_settings_phone').value = data.user.phone;
                closeAdminProfileSettings();
                showAdminAlert(data.message || 'Your account settings have been updated.', 'Settings Updated');
            } catch (error) {
                errorBox.textContent = 'Unable to update account settings. Check your connection and try again.';
                errorBox.style.display = 'block';
            } finally {
                saveButton.disabled = false;
                saveButton.textContent = 'Save Changes';
            }
        });

        // Global Delete Modal Logic
        let formToSubmit = null;
        
        function confirmDelete(event, message) {
            event.preventDefault();
            formToSubmit = event.target.closest('form');
            document.getElementById('deleteConfirmMessage').textContent = message || 'Are you sure you want to delete this item?';
            document.getElementById('deleteConfirmModal').classList.add('open');
            return false;
        }

        function closeDeleteModal() {
            document.getElementById('deleteConfirmModal').classList.remove('open');
            formToSubmit = null;
        }

        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            if (formToSubmit) {
                formToSubmit.submit();
            }
        });

        // Global Dropdown Toggling Actions
        function toggleDropdown(btn, event) {
            event.stopPropagation();
            const menu = btn.nextElementSibling;
            const isOpen = menu.classList.contains('open');
            document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('open'));
            if (!isOpen) {
                menu.classList.add('open');
            }
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('open'));
            }
        });

        // Mobile admin navigation drawer.
        const adminMenuButton = document.getElementById('admin-mobile-menu');
        const adminSidebarOverlay = document.getElementById('admin-sidebar-overlay');

        function setAdminNavigation(open) {
            document.body.classList.toggle('admin-nav-open', open);
            if (adminMenuButton) {
                adminMenuButton.setAttribute('aria-expanded', open ? 'true' : 'false');
                adminMenuButton.setAttribute('aria-label', open ? 'Close admin navigation' : 'Open admin navigation');
                const icon = adminMenuButton.querySelector('i');
                if (icon) icon.setAttribute('data-lucide', open ? 'x' : 'menu');
                if (window.lucide) lucide.createIcons({ root: adminMenuButton });
            }
        }

        if (adminMenuButton) {
            adminMenuButton.addEventListener('click', () => {
                setAdminNavigation(!document.body.classList.contains('admin-nav-open'));
            });
        }
        if (adminSidebarOverlay) {
            adminSidebarOverlay.addEventListener('click', () => setAdminNavigation(false));
        }
        document.querySelectorAll('#admin-sidebar .nav-link').forEach(link => {
            link.addEventListener('click', () => setAdminNavigation(false));
        });
        window.addEventListener('resize', () => {
            if (window.innerWidth > 900) setAdminNavigation(false);
        });

        function setupTableScrollHints(root = document) {
            root.querySelectorAll('.table-wrap').forEach(tableWrap => {
                if (tableWrap.previousElementSibling?.classList.contains('table-scroll-hint')) return;
                const hint = document.createElement('div');
                hint.className = 'table-scroll-hint';
                hint.setAttribute('aria-hidden', 'true');
                hint.innerHTML = '<i data-lucide="move-horizontal" style="width:14px;height:14px;"></i> Swipe horizontally to see more columns';
                tableWrap.before(hint);
            });
            if (window.lucide) lucide.createIcons({ root });
        }

        setupTableScrollHints();

        // Theme Toggling Logic
        const themeBtn = document.getElementById('theme-toggle-btn');
        const themeIcon = document.getElementById('theme-icon');

        function updateIcon() {
            if (themeIcon) {
                const isLight = document.body.classList.contains('light-mode');
                themeIcon.setAttribute('data-lucide', isLight ? 'moon' : 'sun');
                if (window.lucide) {
                    lucide.createIcons();
                }
            }
        }

        // Initialize icon state
        updateIcon();

        if (themeBtn) {
            themeBtn.addEventListener('click', function () {
                const isLight = document.body.classList.toggle('light-mode');
                localStorage.setItem('cssm_theme', isLight ? 'light' : 'dark');
                updateIcon();
            });
        }

        if (window.lucide) {
            lucide.createIcons();
        }

        // Shared AJAX pagination for admin data tables.
        async function loadAdminTable(tableId, url, pushHistory = true) {
            const current = [...document.querySelectorAll('[data-ajax-table]')]
                .find(element => element.dataset.ajaxTable === tableId);
            if (!current) return;

            current.setAttribute('aria-busy', 'true');
            current.style.opacity = '0.55';
            current.style.pointerEvents = 'none';

            try {
                const response = await fetch(url, {
                    headers: {
                        'Accept': 'text/html',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });
                if (!response.ok) throw new Error(`Request failed with status ${response.status}`);

                const documentResult = new DOMParser().parseFromString(await response.text(), 'text/html');
                const replacement = [...documentResult.querySelectorAll('[data-ajax-table]')]
                    .find(element => element.dataset.ajaxTable === tableId);
                if (!replacement) throw new Error('The requested table was not found in the response.');

                current.replaceWith(replacement);
                setupTableScrollHints(replacement);
                if (pushHistory) {
                    history.pushState({ ajaxTable: tableId }, '', url);
                }
                replacement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                if (window.lucide) lucide.createIcons({ root: replacement });
            } catch (error) {
                current.removeAttribute('aria-busy');
                current.style.opacity = '';
                current.style.pointerEvents = '';
                console.error('AJAX pagination failed:', error);
                window.location.href = url;
            }
        }

        document.addEventListener('click', event => {
            const link = event.target.closest('[data-ajax-table] .pagination a');
            if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            const table = link.closest('[data-ajax-table]');
            if (!table || !link.href) return;
            event.preventDefault();
            loadAdminTable(table.dataset.ajaxTable, link.href);
        });

        window.addEventListener('popstate', event => {
            if (event.state && event.state.ajaxTable) {
                loadAdminTable(event.state.ajaxTable, window.location.href, false);
            } else {
                window.location.reload();
            }
        });

        // Global ESC handler for admin modals
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                setAdminNavigation(false);
                document.querySelectorAll('.admin-modal.open').forEach(modal => {
                    modal.classList.remove('open');
                });
            }
        });
    </script>
</body>
</html>
