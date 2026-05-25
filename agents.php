<?php
require 'db.php';
checkLogin();

// Fetch agents and their latest location/stats
$agents = $pdo->query("
    SELECT u.id, u.name, u.email, u.phone_number, u.is_active,
           z.name as zone_name,
           al.is_online, al.last_seen_at,
           (SELECT COUNT(*) FROM collections WHERE agent_id = u.id) as total_collections,
           (SELECT COALESCE(SUM(amount), 0) FROM collections WHERE agent_id = u.id) as total_amount
    FROM users u
    LEFT JOIN zones z ON z.id = u.zone_id
    LEFT JOIN agent_locations al ON al.agent_id = u.id
    WHERE u.role IN ('field_agent', 'agent')
    ORDER BY u.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Field Agents - Ratepoint</title>
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

        /* ── Cards ── */
        .agent-card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            height: 100%;
            display: flex;
            flex-direction: column;
            transition: transform 0.2s;
        }
        .agent-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .agent-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        .agent-avatar {
            width: 45px; height: 45px;
            border-radius: 50%;
            background: #eef7f2;
            color: var(--green);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .agent-name {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text);
            margin: 0;
        }
        .agent-zone {
            font-size: 0.75rem;
            color: var(--muted);
        }
        .status-badge {
            font-size: 0.7rem;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 600;
        }
        .status-online { background: #d1fae5; color: #065f46; }
        .status-offline { background: #fee2e2; color: #991b1b; }
        
        .agent-details {
            font-size: 0.85rem;
            color: var(--text);
            margin-bottom: 15px;
            flex: 1;
        }
        .detail-row {
            display: flex;
            margin-bottom: 8px;
        }
        .detail-row i {
            width: 20px;
            color: var(--green);
            margin-top: 3px;
        }
        .stat-box {
            background: #f8faf9;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            margin-top: auto;
            border: 1px solid #eef7f2;
        }
        .stat-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text);
        }
        .stat-label {
            font-size: 0.7rem;
            color: var(--muted);
            text-transform: uppercase;
        }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; padding: 16px; }
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
        <a href="dashboard.php" class="nav-link">
            <i class="fas fa-th-large"></i> Dashboard
        </a>
        <a href="reconciliation.php" class="nav-link">
            <i class="fas fa-balance-scale"></i> Reconciliation
        </a>
        <a href="agents.php" class="nav-link active">
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
    <div class="page-header">
        <div>
            <h4>Field Agents</h4>
            <div style="font-size:0.85rem; color:var(--muted); margin-top:2px;">
                Manage and monitor revenue collectors
            </div>
        </div>
        <button class="btn btn-sm text-white" style="background: var(--green);">
            <i class="fas fa-plus me-1"></i> Add Agent
        </button>
    </div>

    <div class="row g-4">
        <?php if (empty($agents)): ?>
            <div class="col-12 text-center text-muted py-5">
                <i class="fas fa-users mb-3" style="font-size: 3rem; opacity: 0.3;"></i>
                <p>No field agents found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($agents as $agent): ?>
                <?php 
                    $isOnline = false;
                    if ($agent['last_seen_at']) {
                        $isOnline = (time() - strtotime($agent['last_seen_at'])) < 300;
                    }
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="agent-card">
                        <div class="agent-header">
                            <div class="agent-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div style="flex:1;">
                                <h5 class="agent-name"><?= htmlspecialchars($agent['name']) ?></h5>
                                <div class="agent-zone"><?= htmlspecialchars($agent['zone_name'] ?? 'Unassigned Zone') ?></div>
                            </div>
                            <div>
                                <?php if ($isOnline): ?>
                                    <span class="status-badge status-online">Online</span>
                                <?php else: ?>
                                    <span class="status-badge status-offline">Offline</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="agent-details">
                            <div class="detail-row">
                                <i class="fas fa-envelope"></i>
                                <span><?= htmlspecialchars($agent['email']) ?></span>
                            </div>
                            <div class="detail-row">
                                <i class="fas fa-phone"></i>
                                <span><?= htmlspecialchars($agent['phone_number'] ?? 'N/A') ?></span>
                            </div>
                            <?php if ($agent['last_seen_at']): ?>
                                <div class="detail-row">
                                    <i class="fas fa-clock"></i>
                                    <span style="font-size: 0.8rem; color: var(--muted);">Last seen: <?= date('M d, Y h:i A', strtotime($agent['last_seen_at'])) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="row g-2 mt-auto">
                            <div class="col-6">
                                <div class="stat-box">
                                    <div class="stat-value"><?= number_format($agent['total_collections']) ?></div>
                                    <div class="stat-label">Collections</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-box">
                                    <div class="stat-value">GH₵ <?= number_format($agent['total_amount'], 2) ?></div>
                                    <div class="stat-label">Revenue</div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
