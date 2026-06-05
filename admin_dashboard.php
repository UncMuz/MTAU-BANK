<?php
include 'db.php'; session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: login.php"); exit(); }
$msg = "";

// 1. Loan Request Override Handlers
if (isset($_GET['approve_loan'])) {
    $lid = intval($_GET['approve_loan']);
    $loan_file = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM loans WHERE id=$lid"));
    if($loan_file) {
        $luser = mysqli_fetch_assoc(mysqli_query($conn, "SELECT account_no FROM users WHERE id=".$loan_file['user_id']));
        if($luser) {
            $u_acc = $luser['account_no'];
            mysqli_query($conn, "UPDATE loans SET status='approved' WHERE id=$lid");
            mysqli_query($conn, "UPDATE users SET balance = balance + ".$loan_file['amount']." WHERE id=".$loan_file['user_id']);
            $tx_desc = "Credit Disbursal: " . $loan_file['loan_type'] . " Principal Release";
            mysqli_query($conn, "INSERT INTO transactions (account_no, type, amount, description) VALUES ('$u_acc', 'deposit', ".$loan_file['amount'].", '$tx_desc')");
            mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES (".$loan_file['user_id'].", 'Credit Disbursed', 'Administrative board authorized credit line release.')");
        }
    }
    header("Location: admin_dashboard.php"); exit();
}

if (isset($_GET['reject_loan'])) {
    $lid = intval($_GET['reject_loan']);
    mysqli_query($conn, "UPDATE loans SET status='rejected' WHERE id=$lid");
    header("Location: admin_dashboard.php"); exit();
}

// 2. Resolve Support Ticket Handler
if (isset($_GET['resolve_ticket'])) {
    $tid = intval($_GET['resolve_ticket']);
    mysqli_query($conn, "UPDATE support_tickets SET status='resolved' WHERE id=$tid");
    header("Location: admin_dashboard.php"); exit();
}

// 3. User Accounts Control Actions
if (isset($_GET['freeze_user'])) {
    $uid = intval($_GET['freeze_user']);
    mysqli_query($conn, "UPDATE users SET status='frozen' WHERE id=$uid");
    mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES ($uid, 'Account Frozen', 'User profile locked by administrative board.')");
    header("Location: admin_dashboard.php"); exit();
}

if (isset($_GET['unfreeze_user'])) {
    $uid = intval($_GET['unfreeze_user']);
    mysqli_query($conn, "UPDATE users SET status='active' WHERE id=$uid");
    mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES ($uid, 'Account Unfrozen', 'User profile active state restored by administrative board.')");
    header("Location: admin_dashboard.php"); exit();
}

if (isset($_GET['reset_password'])) {
    $uid = intval($_GET['reset_password']);
    $default_hash = password_hash('mtau123', PASSWORD_BCRYPT);
    mysqli_query($conn, "UPDATE users SET password='$default_hash' WHERE id=$uid");
    mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES ($uid, 'Password Reset', 'Access credentials reset to default string (mtau123) by admin.')");
    header("Location: admin_dashboard.php"); exit();
}

if (isset($_GET['change_class']) && isset($_GET['class_val'])) {
    $uid = intval($_GET['change_class']);
    $cval = mysqli_real_escape_string($conn, $_GET['class_val']);
    mysqli_query($conn, "UPDATE users SET account_class='$cval' WHERE id=$uid");
    mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES ($uid, 'Class Switch', 'User tier classification altered to $cval by admin.')");
    header("Location: admin_dashboard.php"); exit();
}

if (isset($_GET['delete_user'])) {
    $uid = intval($_GET['delete_user']);
    mysqli_query($conn, "DELETE FROM users WHERE id=$uid");
    header("Location: admin_dashboard.php"); exit();
}

// 4. Update System Settings Handler
if (isset($_POST['update_settings'])) {
    $loan_rate = mysqli_real_escape_string($conn, $_POST['loan_markup_rate']);
    $maintenance = mysqli_real_escape_string($conn, $_POST['maintenance_mode']);
    
    mysqli_query($conn, "UPDATE system_settings SET value='$loan_rate' WHERE name='loan_markup_rate'");
    mysqli_query($conn, "UPDATE system_settings SET value='$maintenance' WHERE name='maintenance_mode'");
    $msg = "<div class='alert alert-success text-center glass-card text-white'>Configuration settings updated successfully!</div>";
}

// Fetch current configurations
$loan_rate_query = mysqli_fetch_assoc(mysqli_query($conn, "SELECT value FROM system_settings WHERE name='loan_markup_rate'"));
$loan_rate_val = $loan_rate_query ? $loan_rate_query['value'] : '0.12';

$maint_query = mysqli_fetch_assoc(mysqli_query($conn, "SELECT value FROM system_settings WHERE name='maintenance_mode'"));
$maint_val = $maint_query ? $maint_query['value'] : '0';

// Analytics Summaries
$users_count = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM users WHERE role='user'"));
$tx_count = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM transactions"));
$total_liquidity_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(balance) as total FROM users WHERE role='user'"));
$total_liquidity = $total_liquidity_row['total'] ? $total_liquidity_row['total'] : 0;

// Data Lists
$pending_loans = mysqli_query($conn, "SELECT loans.*, users.full_name FROM loans JOIN users ON loans.user_id=users.id WHERE loans.status='pending' ORDER BY loans.id DESC");
$user_registry = mysqli_query($conn, "SELECT * FROM users WHERE role='user' ORDER BY id DESC");
$recent_payments = mysqli_query($conn, "SELECT bill_payments.*, users.full_name FROM bill_payments JOIN users ON bill_payments.user_id=users.id ORDER BY bill_payments.id DESC LIMIT 5");
$pending_tickets = mysqli_query($conn, "SELECT support_tickets.*, users.full_name FROM support_tickets JOIN users ON support_tickets.user_id=users.id WHERE support_tickets.status='open' ORDER BY support_tickets.id DESC");

// Audit Logs Filtering Handler
$filter_query = "";
if (isset($_GET['filter_type']) && !empty($_GET['filter_type'])) {
    $ftype = mysqli_real_escape_string($conn, $_GET['filter_type']);
    $filter_query = " WHERE activity_type='$ftype' ";
}
$audit_logs = mysqli_query($conn, "SELECT system_logs.*, users.full_name FROM system_logs JOIN users ON system_logs.user_id=users.id " . $filter_query . " ORDER BY system_logs.id DESC LIMIT 15");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MTAU Bank - Administration Override</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <div class="bank-watermark"></div>

    <nav class="navbar navbar-dark sticky-top px-4 py-3 shadow">
        <span class="navbar-brand fw-bold text-glow fs-4 tracking-wider">
            <i data-lucide="landmark" class="lucide-icon text-glow" style="width:24px; height:24px;"></i> MTAU BANK Admin Console
        </span>
        <span class="navbar-text">
            <a href="admin_manual.php" target="_blank" class="btn btn-sm btn-outline-light fw-bold px-3 me-3" style="border-radius:8px;">
                <i data-lucide="book-open" class="lucide-icon" style="width:14px; height:14px;"></i> Admin Manual
            </a>
            <a href="logout.php" class="btn btn-sm btn-danger fw-bold px-3" style="border-radius:8px;">
                <i data-lucide="log-out" class="lucide-icon" style="width:14px; height:14px;"></i> Exit Session
            </a>
        </span>
    </nav>
    
    <div class="container my-5">
        <?php echo $msg; ?>

        <!-- System Summary Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="glass-card text-center mb-0">
                    <h6 class="text-white-50 text-uppercase tracking-wider small">Active Clients</h6>
                    <h2 class="font-monospace text-glow fw-bold"><?php echo $users_count; ?> Profiles</h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="glass-card text-center mb-0">
                    <h6 class="text-white-50 text-uppercase tracking-wider small">Total Transactions</h6>
                    <h2 class="font-monospace text-glow fw-bold"><?php echo $tx_count; ?> Postings</h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="glass-card text-center mb-0">
                    <h6 class="text-white-50 text-uppercase tracking-wider small">Total Network Deposits</h6>
                    <h2 class="font-monospace text-glow fw-bold">Rs. <?php echo number_format($total_liquidity, 2); ?></h2>
                </div>
            </div>
        </div>

        <!-- Global Parameter Settings Panel -->
        <div class="glass-card mb-4">
            <h5 class="text-glow mb-3 fw-bold border-bottom border-secondary pb-2">Global Settings Manager</h5>
            <form action="" method="POST" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label text-white-50 small">Loan Markup Rate (Decimal)</label>
                    <input type="number" step="0.01" min="0" max="1" name="loan_markup_rate" class="form-control font-monospace" value="<?php echo htmlspecialchars($loan_rate_val); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-white-50 small">System Maintenance Status</label>
                    <select name="maintenance_mode" class="form-select">
                        <option value="0" <?php echo ($maint_val === '0') ? 'selected' : ''; ?>>Operational Mode (Live)</option>
                        <option value="1" <?php echo ($maint_val === '1') ? 'selected' : ''; ?>>Maintenance Mode (Locked)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" name="update_settings" class="btn btn-gradient w-100 py-2.5">Save Configuration</button>
                </div>
            </form>
        </div>

        <div class="row g-4 mb-4">
            <!-- Left Side: Loan Requests -->
            <div class="col-md-6">
                <div class="glass-card h-100 mb-0">
                    <h5 class="text-glow mb-3 fw-bold border-bottom border-secondary pb-2">Pending Loans</h5>
                    <div class="table-responsive">
                        <table class="table table-dark table-striped align-middle small mb-0 table-custom">
                            <thead>
                                <tr>
                                    <th>Client Name</th>
                                    <th>Loan Type</th>
                                    <th>Principal</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(mysqli_num_rows($pending_loans) == 0): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-white-50 py-3">No pending loan requests found.</td>
                                    </tr>
                                <?php endif; while($pl = mysqli_fetch_assoc($pending_loans)): ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo htmlspecialchars($pl['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($pl['loan_type']); ?></td>
                                        <td class="font-monospace text-glow">Rs. <?php echo number_format($pl['amount']); ?></td>
                                        <td class="text-center">
                                            <a href="admin_dashboard.php?approve_loan=<?php echo $pl['id']; ?>" class="btn btn-sm btn-success fw-bold me-1">Approve</a>
                                            <a href="admin_dashboard.php?reject_loan=<?php echo $pl['id']; ?>" class="btn btn-sm btn-danger fw-bold">Reject</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Side: Support Tickets -->
            <div class="col-md-6">
                <div class="glass-card h-100 mb-0">
                    <h5 class="text-glow mb-3 fw-bold border-bottom border-secondary pb-2">Pending Support Tickets</h5>
                    <div class="table-responsive">
                        <table class="table table-dark table-striped align-middle small mb-0 table-custom">
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Subject</th>
                                    <th>Description</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(mysqli_num_rows($pending_tickets) == 0): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-white-50 py-3">No pending support tickets.</td>
                                    </tr>
                                <?php endif; while($t = mysqli_fetch_assoc($pending_tickets)): ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo htmlspecialchars($t['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($t['subject']); ?></td>
                                        <td><?php echo htmlspecialchars($t['message']); ?></td>
                                        <td class="text-center">
                                            <a href="admin_dashboard.php?resolve_ticket=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-glow fw-bold">Resolve</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Accounts Management Grid -->
        <div class="glass-card mb-4">
            <h5 class="text-glow mb-3 fw-bold border-bottom border-secondary pb-2">User Directory & Controls</h5>
            <div class="table-responsive">
                <table class="table table-dark table-striped table-bordered align-middle mb-0 table-custom">
                    <thead>
                        <tr>
                            <th>Roll Number</th>
                            <th>Full Name</th>
                            <th>Contact / Info</th>
                            <th>Tier Class</th>
                            <th>Status</th>
                            <th>Balance</th>
                            <th class="text-center" style="width: 280px;">Account Controls</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($user_registry) == 0): ?>
                            <tr>
                                <td colspan="7" class="text-center text-white-50 py-3">No client records found.</td>
                            </tr>
                        <?php endif; while($u = mysqli_fetch_assoc($user_registry)): ?>
                            <tr>
                                <td class="font-monospace text-glow small"><?php echo $u['roll_no']; ?></td>
                                <td class="fw-bold text-white"><?php echo htmlspecialchars($u['full_name']); ?></td>
                                <td style="font-size:11px;">
                                    <?php echo htmlspecialchars($u['email']); ?><br>
                                    <?php echo htmlspecialchars($u['phone']); ?><br>
                                    CNIC: <?php echo htmlspecialchars($u['cnic']); ?>
                                </td>
                                <td>
                                    <!-- Dynamic Tier Select -->
                                    <select onchange="window.location='admin_dashboard.php?change_class=<?php echo $u['id']; ?>&class_val='+this.value" class="form-select form-select-sm bg-dark text-white font-monospace border-secondary p-1">
                                        <option value="Student" <?php echo ($u['account_class'] === 'Student') ? 'selected' : ''; ?>>Student</option>
                                        <option value="Standard" <?php echo ($u['account_class'] === 'Standard') ? 'selected' : ''; ?>>Standard</option>
                                        <option value="Business" <?php echo ($u['account_class'] === 'Business') ? 'selected' : ''; ?>>Business</option>
                                    </select>
                                </td>
                                <td>
                                    <?php if($u['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Frozen</span>
                                    <?php endif; ?>
                                </td>
                                <td class="font-monospace text-glow">Rs. <?php echo number_format($u['balance'], 2); ?></td>
                                <td class="text-center">
                                    <?php if($u['status'] === 'active'): ?>
                                        <a href="admin_dashboard.php?freeze_user=<?php echo $u['id']; ?>" class="btn btn-xs btn-warning btn-sm fw-bold me-1 text-dark" style="font-size:10px;">Lock</a>
                                    <?php else: ?>
                                        <a href="admin_dashboard.php?unfreeze_user=<?php echo $u['id']; ?>" class="btn btn-xs btn-success btn-sm fw-bold me-1 text-white" style="font-size:10px;">Unlock</a>
                                    <?php endif; ?>
                                    <a href="admin_dashboard.php?reset_password=<?php echo $u['id']; ?>" onclick="return confirm('Reset this password to default (mtau123)?')" class="btn btn-xs btn-outline-glow btn-sm me-1" style="font-size:10px; padding: 4px 8px;">Reset PW</a>
                                    <a href="admin_dashboard.php?delete_user=<?php echo $u['id']; ?>" onclick="return confirm('Purge this profile?')" class="btn btn-xs btn-danger btn-sm fw-bold" style="font-size:10px;">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Filterable Audit Logs Console -->
        <div class="glass-card mb-0">
            <h5 class="text-glow mb-3 fw-bold border-bottom border-secondary pb-2">Audit Logs</h5>
            
            <form action="" method="GET" class="row g-3 align-items-center mb-3">
                <div class="col-auto">
                    <label class="col-form-label small text-white-50">Filter by Event Class:</label>
                </div>
                <div class="col-auto">
                    <select name="filter_type" class="form-select form-select-sm bg-dark text-white border-secondary" onchange="this.form.submit()">
                        <option value="">-- View All Logs --</option>
                        <option value="Login" <?php echo (isset($_GET['filter_type']) && $_GET['filter_type'] === 'Login') ? 'selected' : ''; ?>>Login</option>
                        <option value="Transfer" <?php echo (isset($_GET['filter_type']) && $_GET['filter_type'] === 'Transfer') ? 'selected' : ''; ?>>Transfers</option>
                        <option value="Payment" <?php echo (isset($_GET['filter_type']) && $_GET['filter_type'] === 'Payment') ? 'selected' : ''; ?>>Payments</option>
                        <option value="Card Config" <?php echo (isset($_GET['filter_type']) && $_GET['filter_type'] === 'Card Config') ? 'selected' : ''; ?>>Card Operations</option>
                        <option value="Credit Request" <?php echo (isset($_GET['filter_type']) && $_GET['filter_type'] === 'Credit Request') ? 'selected' : ''; ?>>Credit requests</option>
                        <option value="Support Ticket" <?php echo (isset($_GET['filter_type']) && $_GET['filter_type'] === 'Support Ticket') ? 'selected' : ''; ?>>Support Tickets</option>
                        <option value="Asset Investment" <?php echo (isset($_GET['filter_type']) && $_GET['filter_type'] === 'Asset Investment') ? 'selected' : ''; ?>>Investments</option>
                    </select>
                </div>
                <?php if(isset($_GET['filter_type']) && !empty($_GET['filter_type'])): ?>
                    <div class="col-auto">
                        <a href="admin_dashboard.php" class="btn btn-sm btn-secondary" style="border-radius: 8px;">Clear Filters</a>
                    </div>
                <?php endif; ?>
            </form>

            <div class="table-responsive">
                <table class="table table-dark table-striped align-middle small mb-0 table-custom">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Audited Client</th>
                            <th>Activity Event Class</th>
                            <th>Description Logs</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($audit_logs) == 0): ?>
                            <tr>
                                <td colspan="4" class="text-center text-white-50 py-3">No system logs matching search filters.</td>
                            </tr>
                        <?php endif; while($log = mysqli_fetch_assoc($audit_logs)): ?>
                            <tr>
                                <td class="font-monospace text-white-50"><?php echo $log['logged_at']; ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($log['full_name']); ?></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($log['activity_type']); ?></span></td>
                                <td class="text-white"><?php echo htmlspecialchars($log['description']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    <script>
        lucide.createIcons();
    </script>
    <script src="cooleffectslite.js"></script>
</body>
</html>