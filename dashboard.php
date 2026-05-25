<?php
require 'db.php';
checkLogin();

$stats = [
    'revenue' => $pdo->query("SELECT COALESCE(SUM(amount),0) FROM collections")->fetchColumn(),
    'today'   => $pdo->query("SELECT COALESCE(SUM(amount),0) FROM collections WHERE DATE(collected_at) = CURDATE()")->fetchColumn(),
    'agents'  => $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('field_agent','agent')")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM collections WHERE id NOT IN (SELECT COALESCE(collection_id,0) FROM reconciliations)")->fetchColumn(),
];

$top_agents = $pdo->query("SELECT u.name, SUM(c.amount) as total FROM collections c JOIN users u ON c.agent_id = u.id GROUP BY u.name ORDER BY total DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

$zone_revenue = $pdo->query("
    SELECT z.name, COALESCE(SUM(c.amount),0) as total
    FROM zones z
    LEFT JOIN businesses b ON b.zone_id = z.id
    LEFT JOIN collections c ON c.business_id = b.id
    GROUP BY z.name
    ORDER BY total DESC
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($zone_revenue)) {
    $zone_revenue = [
        ['name' => 'Zone A', 'total' => 12000],
        ['name' => 'Zone B', 'total' => 15000],
        ['name' => 'Zone C', 'total' => 8000],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Ratepoint</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --green: #22a96e;
            --green-dark: #1a8555;
            --body-bg: #eef7f2;
            --sidebar-w: 195px;
            --text: #1a2e22;
            --muted: #6c7a6f;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--body-bg);
            color: var(--text);
            margin: 0;
        }

        /* ── Sidebar ── */
        .sidebar {
            width: var(--sidebar-w);
            height: 100vh;
            background: var(--green);
            position: fixed;
            left: 0; top: 0;
            z-index: 100;
            display: flex;
            flex-direction: column;
        }
        .sidebar-brand {
            padding: 22px 20px;
            font-size: 1.05rem;
            font-weight: 800;
            letter-spacing: 2px;
            color: #fff;
            border-bottom: 1px solid rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sidebar-nav { flex: 1; padding: 10px 0; }
        .nav-link {
            display: flex;
            align-items: center;
            gap: 11px;
            color: rgba(255,255,255,0.85);
            padding: 13px 20px;
            font-size: 0.87rem;
            font-weight: 500;
            text-decoration: none;
            border-left: 3px solid transparent;
            transition: all 0.2s;
        }
        .nav-link:hover {
            background: rgba(255,255,255,0.12);
            color: #fff;
            border-left-color: rgba(255,255,255,0.5);
        }
        .nav-link.active {
            background: rgba(0,0,0,0.18);
            color: #fff;
            border-left-color: #fff;
            font-weight: 600;
        }
        .nav-link i { width: 17px; text-align: center; font-size: 0.88rem; }
        .sidebar-footer {
            border-top: 1px solid rgba(255,255,255,0.15);
            padding: 6px 0;
        }

        /* ── Main ── */
        .main {
            margin-left: var(--sidebar-w);
            padding: 28px 30px;
            min-height: 100vh;
        }

        /* ── Page header ── */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }
        .page-header h4 {
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0;
        }
        .page-header .date-badge {
            font-size: 0.78rem;
            color: var(--muted);
        }

        /* ── Stat Cards ── */
        .stat-row { display: flex; gap: 16px; margin-bottom: 20px; }
        .stat-card {
            flex: 1;
            background: #fff;
            border-radius: 10px;
            padding: 18px 20px 16px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            border-radius: 10px 10px 0 0;
        }
        .stat-card.c-green::before  { background: #22a96e; }
        .stat-card.c-yellow::before { background: #F4B400; }
        .stat-card.c-blue::before   { background: #007BFF; }
        .stat-card.c-red::before    { background: #E74C3C; }

        .stat-label {
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 8px;
        }
        .stat-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text);
            line-height: 1;
        }
        .stat-sub {
            font-size: 0.72rem;
            color: var(--muted);
            margin-top: 6px;
        }

        /* ── Content Cards ── */
        .card-box {
            background: #fff;
            border-radius: 10px;
            padding: 22px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        }
        .card-heading {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 16px;
        }

        /* ── Top Agents ── */
        .agent-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f0f5f2;
        }
        .agent-row:last-child { border-bottom: none; }
        .agent-avatar {
            width: 34px; height: 34px;
            border-radius: 50%;
            background: #d1fae5;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .agent-avatar i { font-size: 0.75rem; color: #22a96e; }
        .agent-name { font-size: 0.82rem; font-weight: 600; }
        .agent-role { font-size: 0.7rem; color: var(--muted); }
        .agent-amount { font-size: 0.82rem; font-weight: 700; color: #22a96e; }

        /* ── Chart Row ── */
        .chart-row {
            display: flex;
            gap: 16px;
            margin-bottom: 20px;
        }
        .chart-wrap { flex: 1 1 60%; }
        .agents-wrap { flex: 1 1 38%; }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; padding: 16px; }
            .stat-row, .chart-row { flex-direction: column; }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-th-large" style="font-size:1rem; opacity:0.9;"></i>
        RATEPOINT
    </div>
    <div class="sidebar-nav">
        <a href="dashboard.php" class="nav-link active">
            <i class="fas fa-th-large"></i> Dashboard
        </a>
        <a href="reconciliation.php" class="nav-link">
            <i class="fas fa-balance-scale"></i> Reconciliation
        </a>
        <a href="agents.php" class="nav-link">
            <i class="fas fa-user-friends"></i> Field Agents
        </a>
    </div>
    <div class="sidebar-footer">
        <a href="logout.php" class="nav-link" style="color:rgba(255,255,255,0.85);">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>

<!-- Main Content -->
<div class="main">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h4>Dashboard</h4>
            <div style="font-size:0.78rem; color:var(--muted); margin-top:2px;">
                Welcome back, <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></strong>
            </div>
        </div>
        <div class="date-badge">
            <i class="fas fa-calendar-alt me-1"></i><?= date('D, d M Y') ?>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="stat-row">
        <div class="stat-card c-green">
            <div class="stat-label">Total Revenue</div>
            <div class="stat-value">GH₵ <?= number_format($stats['revenue'], 2) ?></div>
            <div class="stat-sub">All time collections</div>
        </div>
        <div class="stat-card c-yellow">
            <div class="stat-label">Today</div>
            <div class="stat-value">GH₵ <?= number_format($stats['today'], 2) ?></div>
            <div class="stat-sub">Real-time updates</div>
        </div>
        <div class="stat-card c-blue">
            <div class="stat-label">Agents</div>
            <div class="stat-value"><?= $stats['agents'] ?></div>
            <div class="stat-sub">Active field agents</div>
        </div>
        <div class="stat-card c-red">
            <div class="stat-label">Pending Recon</div>
            <div class="stat-value"><?= $stats['pending'] ?></div>
            <div class="stat-sub">Unverified bank slips</div>
        </div>
    </div>

    <!-- Charts + Top Agents -->
    <div class="chart-row">
        <div class="card-box chart-wrap">
            <div class="card-heading">Revenue Analytics</div>
            <div style="position:relative; height:240px; width:100%;">
                <canvas id="revChart"></canvas>
            </div>
        </div>
        <div class="card-box agents-wrap">
            <div class="card-heading">Top Agents</div>
            <?php if (empty($top_agents)): ?>
                <div style="text-align:center; padding:30px 0; color:var(--muted); font-size:0.85rem;">
                    <i class="fas fa-users" style="font-size:2rem; opacity:0.2; display:block; margin-bottom:8px;"></i>
                    No agent data yet
                </div>
            <?php else: ?>
                <?php foreach ($top_agents as $a): ?>
                <div class="agent-row">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div class="agent-avatar"><i class="fas fa-user"></i></div>
                        <div>
                            <div class="agent-name"><?= htmlspecialchars($a['name']) ?></div>
                            <div class="agent-role">Field Agent</div>
                        </div>
                    </div>
                    <div class="agent-amount">GH₵ <?= number_format($a['total'], 2) ?></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const labels = <?= json_encode(array_column($zone_revenue, 'name')) ?>;
    const data   = <?= json_encode(array_column($zone_revenue, 'total')) ?>;

    new Chart(document.getElementById('revChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Revenue',
                data: data,
                backgroundColor: '#22a96e',
                borderRadius: 5,
                borderSkipped: false,
                barPercentage: 0.55,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    align: 'end',
                    labels: { boxWidth: 14, font: { size: 11, family: 'Inter' }, color: '#6c7a6f' }
                },
                tooltip: {
                    callbacks: { label: ctx => ' GH₵ ' + ctx.parsed.y.toLocaleString() }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11, family: 'Inter' }, color: '#6c7a6f' }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: '#f0f5f2', drawBorder: false },
                    ticks: {
                        font: { size: 11, family: 'Inter' },
                        color: '#6c7a6f',
                        callback: v => v.toLocaleString()
                    }
                }
            }
        }
    });
</script>
</body>
</html>
