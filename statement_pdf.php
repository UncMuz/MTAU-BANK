<?php
include 'db.php'; session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_id = $_SESSION['user_id'];

$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id")); $account_no = $user['account_no'];
$tx_query = mysqli_query($conn, "SELECT * FROM transactions WHERE account_no='$account_no' ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Official Account Ledger Statement</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #ffffff; color: #1e112a; padding: 40px; font-family: Arial, sans-serif; }
        .statement-header { border-bottom: 3px solid #3b224c; padding-bottom: 15px; margin-bottom: 30px; }
        .meta-label { color: #3b224c; font-weight: bold; }
        .table-ledger th { background: #3b224c !important; color: white !important; }
    </style>
</head>
<body onload="window.print()">
    <div class="container" style="max-width: 900px;">
        <div class="statement-header row align-items-end">
            <div class="col-7">
                <h1 class="fw-bold tracking-tight" style="color:#3b224c;">MTAU DIGITAL BANKING CORP</h1>
                <small class="text-secondary fw-bold">Computer Engineering Dept Audit Log Records</small>
            </div>
            <div class="col-5 text-end font-monospace">
                <h4 class="fw-bold text-uppercase mb-0">Official Account Ledger</h4>
                <small class="text-muted">Scope Range: Active History Posting Timeline</small>
            </div>
        </div>

        <div class="row mb-4 p-3 rounded" style="background: #f8f9fa; border: 1px solid #dee2e6;">
            <div class="col-6">
                <div><span class="meta-label">Account Holder Name:</span> <?php echo htmlspecialchars($user['full_name']); ?></div>
                <div><span class="meta-label">National Identity (CNIC):</span> <?php echo htmlspecialchars($user['cnic']); ?></div>
                <div><span class="meta-label">Mobile Reference Identifier:</span> <?php echo htmlspecialchars($user['phone']); ?></div>
            </div>
            <div class="col-6 text-end">
                <div><span class="meta-label">Assigned Core IBAN:</span> <span class="font-monospace fw-bold"><?php echo $account_no; ?></span></div>
                <div><span class="meta-label">Liquid Balance State:</span> <span class="text-success fw-bold">PKR <?php echo number_format($user['balance'], 2); ?></span></div>
            </div>
        </div>

        <h5 class="fw-bold mb-3" style="color: #3b224c;">General Ledger Historical Postings</h5>
        <table class="table table-bordered table-striped align-middle table-ledger small">
            <thead>
                <tr>
                    <th>Timestamp Location</th>
                    <th>Classification Type</th>
                    <th>Memo Narration Description</th>
                    <th class="text-end">Value Impact (PKR)</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($tx_query) == 0): ?>
                    <tr><td colspan="4" class="text-center text-muted py-3">No posting trails logged on this profile matrix.</td></tr>
                <?php endif; while($tx = mysqli_fetch_assoc($tx_query)): ?>
                <tr>
                    <td class="font-monospace text-muted"><?php echo $tx['created_at']; ?></td>
                    <td class="fw-bold text-uppercase"><?php echo $tx['type']; ?></td>
                    <td><?php echo htmlspecialchars($tx['description']); ?></td>
                    <td class="text-end font-monospace fw-bold <?php echo ($tx['type'] === 'deposit') ? 'text-success' : 'text-danger'; ?>">
                        <?php echo ($tx['type'] === 'deposit') ? '+ Rs. ' : '- Rs. '; echo number_format($tx['amount'], 2); ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>