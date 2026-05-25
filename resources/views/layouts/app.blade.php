<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ratepoint - Revenue Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sidebar-green: #22a96e;
            --sidebar-green-hover: #1d9461;
            --sidebar-green-dark: #1a8555;
            --accent-yellow: #F4B400;
            --accent-blue: #007BFF;
            --accent-red: #E74C3C;
            --accent-green: #22a96e;
            --body-bg: #eef7f2;
            --text-dark: #1a2e22;
            --text-muted: #6c7a6f;
            --card-bg: #ffffff;
            --sidebar-width: 195px;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--body-bg);
            color: var(--text-dark);
            margin: 0;
        }

        /* ── Sidebar ── */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background-color: var(--sidebar-green);
            color: white;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand {
            padding: 24px 20px 22px;
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #fff;
            border-bottom: 1px solid rgba(255,255,255,0.15);
        }

        .sidebar-nav {
            flex: 1;
            padding: 12px 0;
        }

        .sidebar-nav .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255,255,255,0.85);
            padding: 13px 20px;
            font-size: 0.88rem;
            font-weight: 500;
            border-radius: 0;
            transition: all 0.2s;
            text-decoration: none;
            border-left: 3px solid transparent;
        }

        .sidebar-nav .nav-link:hover {
            background: rgba(255,255,255,0.12);
            color: #fff;
            border-left-color: rgba(255,255,255,0.5);
        }

        .sidebar-nav .nav-link.active {
            background: rgba(0,0,0,0.18);
            color: #fff;
            border-left-color: #fff;
            font-weight: 600;
        }

        .sidebar-nav .nav-link i {
            width: 18px;
            text-align: center;
            font-size: 0.9rem;
        }

        /* ── Top Nav ── */
        .top-nav {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            height: 0;
            z-index: 999;
        }

        /* ── Main Content ── */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px 32px;
            min-height: 100vh;
        }

        /* ── Stat Cards ── */
        .stat-card {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 20px 22px;
            border: none;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
        }

        .stat-card.border-green::before  { background: var(--accent-green); }
        .stat-card.border-yellow::before { background: var(--accent-yellow); }
        .stat-card.border-blue::before   { background: var(--accent-blue); }
        .stat-card.border-red::before    { background: var(--accent-red); }

        .stat-card .stat-label {
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .stat-card .stat-value {
            font-size: 1.65rem;
            font-weight: 700;
            color: var(--text-dark);
            line-height: 1;
        }

        .stat-card .stat-sub {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 6px;
        }

        /* ── Content Cards ── */
        .content-card {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 24px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            border: none;
        }

        .content-card .card-heading {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 18px;
        }

        /* ── Buttons ── */
        .btn-primary {
            background-color: var(--sidebar-green);
            border: none;
            border-radius: 8px;
            padding: 10px 22px;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .btn-primary:hover {
            background-color: var(--sidebar-green-dark);
        }

        /* ── Tables ── */
        .table th {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            border-bottom: 1px solid #e5ede9;
        }

        .table td {
            font-size: 0.875rem;
            vertical-align: middle;
            border-bottom: 1px solid #f0f5f2;
        }

        /* ── Badges ── */
        .badge-success-soft {
            background: #d1fae5;
            color: #065f46;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 600;
        }

        .badge-warning-soft {
            background: #fef3c7;
            color: #92400e;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 600;
        }

        .badge-danger-soft {
            background: #fee2e2;
            color: #991b1b;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 600;
        }

        /* ── Alert ── */
        .alert-success {
            background: #d1fae5;
            border: none;
            color: #065f46;
            border-radius: 8px;
        }

        /* ── Mobile ── */
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; padding: 20px 16px; }
        }
    </style>
    @yield('styles')
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-th-large me-2" style="font-size:1rem; opacity:0.9;"></i>RATEPOINT
        </div>
        <nav class="sidebar-nav">
            <a class="nav-link {{ request()->is('admin/dashboard') ? 'active' : '' }}" href="/admin/dashboard">
                <i class="fas fa-th-large"></i> Dashboard
            </a>
            <a class="nav-link {{ request()->is('admin/reconciliation*') ? 'active' : '' }}" href="/admin/reconciliation">
                <i class="fas fa-balance-scale"></i> Reconciliation
            </a>
            <a class="nav-link {{ request()->is('admin/agents*') ? 'active' : '' }}" href="/admin/agents">
                <i class="fas fa-user-friends"></i> Field Agents
            </a>
            <a class="nav-link {{ request()->is('admin/collections*') ? 'active' : '' }}" href="/admin/collections">
                <i class="fas fa-coins"></i> Collections
            </a>
            <a class="nav-link {{ request()->is('admin/businesses*') ? 'active' : '' }}" href="/admin/businesses">
                <i class="fas fa-store"></i> Businesses
            </a>
            <a class="nav-link {{ request()->is('admin/reports*') ? 'active' : '' }}" href="/admin/reports">
                <i class="fas fa-chart-bar"></i> Reports
            </a>
            <a class="nav-link {{ request()->is('admin/audit-logs*') ? 'active' : '' }}" href="/admin/audit-logs">
                <i class="fas fa-history"></i> Audit Logs
            </a>
            <a class="nav-link {{ request()->is('admin/settings*') ? 'active' : '' }}" href="/admin/settings">
                <i class="fas fa-cog"></i> Settings
            </a>
        </nav>
        <div style="padding: 16px 0; border-top: 1px solid rgba(255,255,255,0.15);">
            <a class="nav-link" href="/logout" style="color:rgba(255,255,255,0.85); display:flex; align-items:center; gap:12px; padding:13px 20px; font-size:0.88rem; text-decoration:none;">
                <i class="fas fa-sign-out-alt" style="width:18px; text-align:center;"></i> Logout
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    @yield('scripts')
</body>
</html>
