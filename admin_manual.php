<?php
include 'db.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
$admin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MTAU Bank - Admin Override Operations Manual</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #ffffff; color: #15101a; padding: 40px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; }
        .manual-header { border-bottom: 4px solid #1e0933; padding-bottom: 20px; margin-bottom: 40px; }
        .manual-title { color: #1e0933; font-weight: 800; }
        .section-title { color: #1e0933; font-weight: 700; border-left: 5px solid #6b21a8; padding-left: 12px; margin-top: 40px; margin-bottom: 20px; }
        .meta-box { background: #f8f6fa; border: 1px solid #ebdff5; border-radius: 12px; padding: 20px; margin-bottom: 30px; }
        .rule-table th { background: #1e0933 !important; color: white !important; font-weight: 600; }
        .rule-table td { vertical-align: middle; }
        .no-print { margin-bottom: 20px; }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="container" style="max-width: 950px;">
        <!-- Print Trigger Alert -->
        <div class="alert alert-warning no-print d-flex justify-content-between align-items-center shadow-sm">
            <span><strong>Admin Override Manual PDF:</strong> The browser's print dialog has opened automatically. Select "Save as PDF" to export this admin override manual.</span>
            <button onclick="window.print()" class="btn btn-dark btn-sm fw-bold">Open Print Dialog</button>
        </div>

        <div class="manual-header row align-items-end">
            <div class="col-8">
                <h1 class="manual-title mb-1">MTAU BANK ADMIN CONSOLE</h1>
                <h4 class="text-secondary fw-normal">Administrative Overrides & System Operations Manual</h4>
            </div>
            <div class="col-4 text-end">
                <span class="badge bg-dark px-3 py-2 text-white" style="background:#1e0933;">Security Level: Admin</span>
                <div class="small text-muted mt-2">Export Date: <?php echo date('F d, Y'); ?></div>
            </div>
        </div>

        <div class="meta-box">
            <h5 class="fw-bold mb-3">Authenticated Administrative Node Info</h5>
            <div class="row small">
                <div class="col-md-6">
                    <div><strong>Admin Operator Name:</strong> <?php echo htmlspecialchars($admin['full_name']); ?></div>
                    <div><strong>System Identity Email:</strong> <?php echo htmlspecialchars($admin['email']); ?></div>
                    <div><strong>CNIC Verification:</strong> <?php echo htmlspecialchars($admin['cnic']); ?></div>
                </div>
                <div class="col-md-6">
                    <div><strong>Security Class Role:</strong> Central Administrator</div>
                    <div><strong>Access Rights:</strong> General Ledger / User Registry Overrides</div>
                    <div><strong>Status:</strong> Active Session Node</div>
                </div>
            </div>
        </div>

        <!-- Section 1 -->
        <h4 class="section-title">1. System Summary & Financial Metrics</h4>
        <p>The <strong>MTAU Administration Override Console</strong> offers direct database insight to monitor core financial metrics across the system network:</p>
        <ul>
            <li><strong>Active Clients Count:</strong> Tracks the total number of registered client profiles. Excludes administrative roles.</li>
            <li><strong>Total System Transactions:</strong> Displays the complete count of entries inside the `transactions` general ledger table.</li>
            <li><strong>Total Network Deposits:</strong> Computes the cumulative balance sum (PKR) across all client checking/savings accounts to audit total liquid reserves.</li>
        </ul>

        <!-- Section 2 -->
        <h4 class="section-title">2. Global Parameter Settings</h4>
        <p>Administrators can dynamically reconfigure core banking settings on the fly. These update immediately in the database and affect all active client accounts:</p>
        <table class="table table-bordered table-striped rule-table small">
            <thead>
                <tr>
                    <th>Parameter Name</th>
                    <th>Functional Description</th>
                    <th>Standard Operational Bounds</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Loan Markup Rate</strong></td>
                    <td>Determines the annual interest markup rate applied during new client loan evaluations. Defined in decimal format (e.g. 0.12 = 12%).</td>
                    <td>Must be a positive decimal between 0.00 and 1.00. Default is 0.12.</td>
                </tr>
                <tr>
                    <td><strong>Operational Mode</strong></td>
                    <td>Enables the admin to lock the entire application in Maintenance Mode if system updates are required.</td>
                    <td>Operational Mode (Live) or Maintenance Mode (Locked). In Maintenance Mode, clients are redirected to a security block page.</td>
                </tr>
            </tbody>
        </table>

        <!-- Section 3 -->
        <h4 class="section-title">3. Pending Loan Appraisals & Disbursements</h4>
        <p>When clients apply for corporate, student, or property loans, the requests enter a pending state and must be audited by an administrator. The override workflow follows:</p>
        <ol>
            <li><strong>Review Loan Parameters:</strong> Verify the principal loan sum, the repayment duration (months), and the monthly EMI calculations.</li>
            <li><strong>Approve Action:</strong> Sets the loan status to `approved`. The system automatically updates the database:
                <ul>
                    <li>Increments the target client's checking balance by the principal loan amount.</li>
                    <li>Inserts a new `deposit` record in the `transactions` ledger with a clear disbursal description.</li>
                    <li>Writes a permanent security log in `system_logs`.</li>
                </ul>
            </li>
            <li><strong>Reject Action:</strong> Updates the loan status to `rejected`, allowing the client to reapply under different metrics. No funds are disbursed.</li>
        </ol>

        <!-- Section 4 -->
        <h4 class="section-title">4. User Registry Control & Accounts Management</h4>
        <p>Administrators have complete security authority over client profile registries inside the **User Control Panel**. Actions include:</p>
        <ul>
            <li><strong>Profile Freezing/Unfreezing:</strong> In cases of suspicious transfer activity, administrators can freeze client profiles. A frozen profile blocks login access immediately. Unfreezing restores operational access.</li>
            <li><strong>Reset Credentials:</strong> Temporarily resets client login passwords to the baseline string <code>mtau123</code>. The client can use this default string to restore login access and set a new password.</li>
            <li><strong>Class Modification:</strong> Dynamically alter user tier classifications (Student, Business, Standard) based on credit checks or asset balances. This updates card issuance cost tiers and transfer fee calculations.</li>
            <li><strong>Delete User Profile:</strong> Permanent cascading delete of client records from the master `users` table. Removes all linked logs, transactions, and portfolios. (Use with caution).</li>
        </ul>

        <!-- Section 5 -->
        <h4 class="section-title">5. Helpdesk Dispute resolution</h4>
        <p>The <strong>Support Desk Resolver</strong> lists open dispute tickets created by clients. To resolve an issue:</p>
        <ul>
            <li>Read the client's submitted subject line and message body description.</li>
            <li>Click the **Resolve** button to close the ticket state. The ticket status updates to `resolved`, indicating to the client that the issue has been cleared.</li>
        </ul>

        <!-- Section 6 -->
        <h4 class="section-title">6. Audit Trail Logging & Categories filtering</h4>
        <p>Every critical action executed on the network (transfers, PIN changes, deposits, card locks) triggers an automatic entry in the security audit logs registry:</p>
        <ul>
            <li><strong>Action Categories:</strong> Logs track specific activity types (e.g. Deposit, Withdrawal, Transfer, Card Config, Loyalty Reward).</li>
            <li><strong>Dynamic Filtering:</strong> Use the category filter dropdown to isolate specific security events. This allows administrators to audit logs efficiently and identify abnormalities.</li>
        </ul>

        <hr class="my-5">
        <div class="text-center text-muted small">
            CONFIDENTIALITY NOTICE: This manual and the console operations described herein are restricted to authorized administrators. All override actions are logged to the central database auditing nodes.
        </div>
    </div>
</body>
</html>
