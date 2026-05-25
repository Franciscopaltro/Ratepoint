<?php
require 'db.php';
checkLogin();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $stmt = $pdo->prepare("INSERT INTO reconciliations (collection_id, finance_officer_id, status, confirmed_amount, bank_slip_number) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$_POST['collection_id'], $_SESSION['user_id'], $_POST['status'], $_POST['amount'], $_POST['slip']]);
    header("Location: reconciliation.php?success=1");
    exit;
}

$pending = $pdo->query("
    SELECT c.*, b.name as b_name, u.name as a_name
    FROM collections c
    JOIN businesses b ON c.business_id = b.id
    JOIN users u ON c.agent_id = u.id
    WHERE c.id NOT IN (SELECT collection_id FROM reconciliations)
    ORDER BY c.collected_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$total_pending = count($pending);
$total_value   = array_sum(array_column($pending, 'amount'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reconciliation - Ratepoint</title>
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

        /* ── Page Header ── */
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

        /* ── Summary Cards ── */
        .summary-row {
            display: flex;
            gap: 16px;
            margin-bottom: 20px;
        }
        .sum-card {
            flex: 1;
            background: #fff;
            border-radius: 10px;
            padding: 18px 20px 16px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            position: relative;
            overflow: hidden;
        }
        .sum-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            border-radius: 10px 10px 0 0;
        }
        .sum-card.c-yellow::before { background: #F4B400; }
        .sum-card.c-red::before    { background: #E74C3C; }
        .sum-card.c-green::before  { background: #22a96e; }
        .sum-label {
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 8px;
        }
        .sum-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text);
            line-height: 1;
        }
        .sum-sub {
            font-size: 0.72rem;
            color: var(--muted);
            margin-top: 6px;
        }

        /* ── Content Card ── */
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
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* ── Table ── */
        .table th {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--muted);
            border-bottom: 1px solid #e5ede9;
            padding: 10px 12px;
        }
        .table td {
            font-size: 0.85rem;
            vertical-align: middle;
            border-bottom: 1px solid #f0f5f2;
            padding: 12px 12px;
            color: var(--text);
        }
        .table tbody tr:last-child td { border-bottom: none; }
        .table tbody tr:hover td { background: #f8fdf9; }

        /* ── Badges ── */
        .badge-pending {
            background: #fef3c7;
            color: #92400e;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .receipt-code {
            font-family: monospace;
            font-size: 0.8rem;
            background: #f0f5f2;
            padding: 2px 8px;
            border-radius: 4px;
            color: #1a2e22;
        }

        /* ── Verify Button ── */
        .btn-verify {
            background: var(--green);
            color: #fff;
            border: none;
            border-radius: 7px;
            padding: 5px 14px;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-verify:hover { background: var(--green-dark); }

        /* ── Alert ── */
        .success-alert {
            background: #d1fae5;
            color: #065f46;
            border-radius: 8px;
            padding: 10px 16px;
            font-size: 0.83rem;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ── Modal ── */
        .modal-backdrop.show {
            opacity: 0.85 !important;
            background-color: #0f2418 !important;
        }
        .modal-content { border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 20px 40px rgba(0,0,0,0.4); border-radius: 12px; }
        .modal-header {
            border-bottom: 1px solid #f0f5f2;
            padding: 18px 20px 14px;
        }
        .modal-header .modal-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text);
        }
        .modal-body { padding: 18px 20px; }
        .modal-footer {
            border-top: 1px solid #f0f5f2;
            padding: 12px 20px;
        }
        .modal .form-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text);
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .modal .form-control,
        .modal .form-select {
            border: 1.5px solid #cce0d6;
            border-radius: 8px;
            font-size: 0.875rem;
            font-family: 'Inter', sans-serif;
            padding: 10px 12px;
            color: var(--text);
        }
        .modal .form-control:focus,
        .modal .form-select:focus {
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(34,169,110,0.12);
        }
        .btn-save {
            background: var(--green);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px 22px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-save:hover { background: var(--green-dark); }
        .btn-cancel {
            background: #f0f5f2;
            color: var(--muted);
            border: none;
            border-radius: 8px;
            padding: 10px 18px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
        }

        /* ── Empty State ── */
        .empty-state {
            text-align: center;
            padding: 48px 20px;
            color: var(--muted);
        }
        .empty-state i {
            font-size: 2.5rem;
            opacity: 0.2;
            display: block;
            margin-bottom: 12px;
        }
        .empty-state p { font-size: 0.88rem; margin: 0; }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; padding: 16px; }
            .summary-row { flex-direction: column; }
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
        <a href="reconciliation.php" class="nav-link active">
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
            <h4>Reconciliation</h4>
            <div style="font-size:0.78rem; color:var(--muted); margin-top:2px;">
                Verify and reconcile field agent collections
            </div>
        </div>
        <div style="font-size:0.78rem; color:var(--muted);">
            <i class="fas fa-calendar-alt me-1"></i><?= date('D, d M Y') ?>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="success-alert">
        <i class="fas fa-check-circle"></i> Collection verified and reconciled successfully.
    </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="summary-row">
        <div class="sum-card c-yellow">
            <div class="sum-label">Pending Verification</div>
            <div class="sum-value"><?= $total_pending ?></div>
            <div class="sum-sub">Collections awaiting review</div>
        </div>
        <div class="sum-card c-red">
            <div class="sum-label">Total Pending Value</div>
            <div class="sum-value">GH₵ <?= number_format($total_value, 2) ?></div>
            <div class="sum-sub">Unverified amount</div>
        </div>
        <div class="sum-card c-green">
            <div class="sum-label">Status</div>
            <div class="sum-value" style="font-size:1.1rem; padding-top:4px;">
                <?= $total_pending === 0 ? '✓ All Clear' : 'Action Required' ?>
            </div>
            <div class="sum-sub"><?= $total_pending === 0 ? 'No pending items' : "$total_pending item(s) need attention" ?></div>
        </div>
    </div>

    <!-- Collections Table -->
    <div class="card-box">
        <div class="card-heading">
            <span>Pending Financial Reconciliations</span>
            <?php if ($total_pending > 0): ?>
            <span style="background:#fef3c7; color:#92400e; padding:3px 10px; border-radius:20px; font-size:0.7rem; font-weight:700;">
                <?= $total_pending ?> Pending
            </span>
            <?php endif; ?>
        </div>

        <?php if (empty($pending)): ?>
        <div class="empty-state">
            <i class="fas fa-check-circle" style="color:#22a96e; opacity:0.4;"></i>
            <p>All collections have been reconciled. No pending items.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Receipt #</th>
                        <th>Business</th>
                        <th>Agent</th>
                        <th>Amount</th>
                        <th>Collected</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $p): ?>
                    <tr>
                        <td><span class="receipt-code"><?= htmlspecialchars($p['receipt_number'] ?? 'N/A') ?></span></td>
                        <td style="font-weight:600;"><?= htmlspecialchars($p['b_name']) ?></td>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <div style="width:28px; height:28px; border-radius:50%; background:#d1fae5; display:flex; align-items:center; justify-content:center;">
                                    <i class="fas fa-user" style="font-size:0.65rem; color:#22a96e;"></i>
                                </div>
                                <?= htmlspecialchars($p['a_name']) ?>
                            </div>
                        </td>
                        <td style="font-weight:700; color:#22a96e;">GH₵ <?= number_format($p['amount'], 2) ?></td>
                        <td style="color:var(--muted); font-size:0.8rem;">
                            <?= isset($p['collected_at']) ? date('d M Y, H:i', strtotime($p['collected_at'])) : '—' ?>
                        </td>
                        <td><span class="badge-pending">Pending</span></td>
                        <td>
                            <button class="btn-verify"
                                data-bs-toggle="modal"
                                data-bs-target="#reconModal<?= $p['id'] ?>">
                                <i class="fas fa-check me-1"></i>Verify
                            </button>
                        </td>
                    </tr>



                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- Modals -->
<?php if (!empty($pending)): ?>
    <?php foreach ($pending as $p): ?>
    <div class="modal fade" id="reconModal<?= $p['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form method="POST" class="modal-content shadow-lg border-0" style="background-color: #fff; border-radius: 16px; overflow: hidden;">
                <div class="modal-body p-5">
                    
                    <!-- Receipt Header -->
                    <div class="text-center mb-5">
                        <div style="width: 60px; height: 60px; background: #eef7f2; color: #22a96e; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 1.8rem;">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <h3 class="fw-bold text-dark mb-1" style="letter-spacing: 0.5px;">Verify Collection</h3>
                        <p class="text-muted small text-uppercase fw-bold mb-0">Ratepoint Revenue System</p>
                    </div>

                    <!-- Receipt Details -->
                    <div class="px-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span style="font-size: 0.85rem; font-weight: 700; color: var(--muted); text-transform: uppercase;">Receipt Number</span>
                            <span class="fs-5 fw-bold text-dark font-monospace"><?= htmlspecialchars($p['receipt_number'] ?? '') ?></span>
                        </div>
                        <hr style="border-top: 2px dashed #e5ede9; opacity: 1; margin: 20px 0;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span style="font-size: 0.85rem; font-weight: 700; color: var(--muted); text-transform: uppercase;">Business</span>
                            <span class="fs-5 fw-bold text-dark text-end"><?= htmlspecialchars($p['b_name']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <span style="font-size: 0.85rem; font-weight: 700; color: var(--muted); text-transform: uppercase;">Agent</span>
                            <span class="fs-5 fw-bold text-dark text-end"><?= htmlspecialchars($p['a_name']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-4 p-4 rounded-3" style="background: #f8faf9; border: 1px solid #e5ede9;">
                            <span style="font-size: 0.85rem; font-weight: 800; color: var(--green-dark); text-transform: uppercase;">Reported Amount</span>
                            <span class="fs-2 fw-bold" style="color: #22a96e;">GH₵ <?= number_format($p['amount'], 2) ?></span>
                        </div>
                    </div>

                    <hr style="border-top: 2px dashed #e5ede9; opacity: 1; margin: 30px 0;">

                    <!-- Verification Form -->
                    <div class="px-4">
                        <input type="hidden" name="collection_id" value="<?= $p['id'] ?>">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label" style="font-size: 0.75rem; font-weight: 700; color: var(--muted); text-transform: uppercase;">Confirmed Amount (GH₵)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 fw-bold text-muted">GH₵</span>
                                    <input type="number" name="amount" step="0.01" class="form-control border-start-0 ps-0 fw-bold" value="<?= $p['amount'] ?>" required style="box-shadow: none; background: #fdfdfd;">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" style="font-size: 0.75rem; font-weight: 700; color: var(--muted); text-transform: uppercase;">Bank Slip Number</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-file-invoice"></i></span>
                                    <input type="text" name="slip" class="form-control border-start-0 ps-0 fw-bold" placeholder="SLP-..." required style="box-shadow: none; background: #fdfdfd;">
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" style="font-size: 0.75rem; font-weight: 700; color: var(--muted); text-transform: uppercase;">Verification Status</label>
                                <select name="status" class="form-select form-select-lg fw-bold" style="box-shadow: none; background: #fdfdfd; font-size: 0.95rem;">
                                    <option value="verified" style="color: #22a96e;">✓ Verified & Matched</option>
                                    <option value="suspicious" style="color: #dc3545;">⚠ Suspicious / Mismatch</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-5 px-4 d-flex gap-3">
                        <button type="button" class="btn btn-light fw-bold flex-fill py-3" data-bs-dismiss="modal" style="background: #f0f5f2; color: var(--muted); border: none; font-size: 1.05rem;">Cancel</button>
                        <button type="submit" class="btn text-white fw-bold flex-fill py-3" style="background: var(--green); border: none; font-size: 1.05rem;">
                            <i class="fas fa-check-circle me-2"></i> Verify Collection
                        </button>
                    </div>
                    
                </div>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
