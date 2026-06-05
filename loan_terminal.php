<?php
include 'db.php'; session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_id = $_SESSION['user_id']; $msg = "";

$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id")); 
if ($user['status'] === 'frozen') {
    session_unset(); session_destroy();
    header("Location: login.php?error=frozen"); exit();
}
$tier_class = $user['account_class'];

if (!isset($_SESSION['txn_token'])) { $_SESSION['txn_token'] = bin2hex(random_bytes(16)); }

// Fetch dynamic loan markup rate from system settings
$markup_query = mysqli_query($conn, "SELECT value FROM system_settings WHERE name='loan_markup_rate'");
$markup_row = mysqli_fetch_assoc($markup_query);
$markup_rate = $markup_row ? floatval($markup_row['value']) : 0.12;

if (isset($_POST['apply_loan'])) {
    if (!isset($_POST['post_token']) || $_POST['post_token'] !== $_SESSION['txn_token']) {
        $msg = "<div class='alert alert-danger text-center glass-card text-white'>Security Validation Failed: CSRF Handshake Exception.</div>";
    } else {
        $loan_type = mysqli_real_escape_string($conn, $_POST['loan_type']);
        $amount = floatval($_POST['amount']); $months = intval($_POST['months']);
        
        if ($tier_class === 'Student') {
            $msg = "<div class='alert alert-danger text-center glass-card text-white fw-bold'>Compliance Lock: Academic Student accounts are restricted from accessing credit loan tracks.</div>";
        } else if ($amount <= 0 || $months <= 0) {
            $msg = "<div class='alert alert-danger text-center glass-card text-white'>Validation Error: Enter a valid principal and term.</div>";
        } else {
            $emi = ($amount * (1 + $markup_rate)) / $months;
            if (mysqli_query($conn, "INSERT INTO loans (user_id, loan_type, amount, months, emi, status) VALUES ($user_id, '$loan_type', $amount, $months, $emi, 'pending')")) {
                mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES ($user_id, 'Credit Request', 'Applied for credit allocation line: Rs. " . number_format($amount) . "')");
                $msg = "<div class='alert alert-success text-center glass-card text-white fw-bold'>Loan Form Filed Successfully! Status Pending Board Approvals.</div>";
                unset($_SESSION['txn_token']); $_SESSION['txn_token'] = bin2hex(random_bytes(16));
            } else {
                $msg = "<div class='alert alert-danger text-center glass-card text-white'>Database error filing loan application.</div>";
            }
        }
    }
}
$loan_tracking_query = mysqli_query($conn, "SELECT * FROM loans WHERE user_id=$user_id ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MTAU Bank - Credit Management Desk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <div class="bank-watermark"></div>

    <nav class="navbar navbar-dark sticky-top px-4 py-3 shadow">
        <a class="navbar-brand text-white fw-bold d-flex align-items-center" href="dashboard.php" style="color: #e0b0ff; text-shadow: 0 0 15px rgba(224, 176, 255, 0.6);">
            <i data-lucide="landmark" class="lucide-icon text-glow" style="width: 24px; height: 24px;"></i> MTAU BANK
        </a>
        <span class="navbar-text text-white">
            <a href="dashboard.php" class="btn btn-sm btn-outline-light fw-bold" style="border-radius: 8px;">
                <i data-lucide="arrow-left" class="lucide-icon"></i> Back to Hub
            </a>
        </span>
    </nav>

    <div class="container my-5">
        <?php echo $msg; ?>
        
        <div class="row g-4">
            <div class="col-md-5">
                <div class="glass-card shadow-lg">
                    <h5 class="fw-bold text-white mb-3 border-bottom border-secondary pb-2">Apply for a Loan</h5>
                    
                    <?php if($tier_class === 'Student'): ?>
                        <div class="alert bg-dark text-white border border-danger p-3 rounded text-center small mb-0">
                            <div class="text-danger mb-2"><i data-lucide="lock" style="width: 36px; height: 36px;"></i></div>
                            <strong>Compliance Lock:</strong> Academic Student accounts are restricted from accessing credit loan tracks.
                        </div>
                    <?php else: ?>
                        <form action="" method="POST">
                            <input type="hidden" name="post_token" value="<?php echo $_SESSION['txn_token']; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label text-glow small">Credit Framework Track</label>
                                <select name="loan_type" class="form-select">
                                    <option value="Personal Line">Personal Line of Credit</option>
                                    <option value="Commercial Venture Funding">Commercial Venture Funding</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label text-white-50 small">Principal Requests Value (PKR)</label>
                                <input type="number" name="amount" id="loanAmount" class="form-control font-monospace" placeholder="0.00" oninput="calculateAmortization()" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label text-white-50 small">Amortization lifespan Term (Months)</label>
                                <input type="number" name="months" id="loanMonths" class="form-control font-monospace" placeholder="12" min="1" oninput="calculateAmortization()" required>
                            </div>
                            
                            <!-- Dynamic Amortization Simulator Widget -->
                            <div class="p-3 bg-dark border border-secondary rounded mb-4 font-monospace small">
                                <div class="text-glow fw-bold mb-2 d-flex align-items-center gap-1"><i data-lucide="bar-chart-3" class="inline-icon text-glow"></i> Real-Time Amortization Simulation</div>
                                <div class="row mb-1">
                                    <div class="col-7 text-white-50">Current Markup Rate:</div>
                                    <div class="col-5 text-end text-white fw-bold" id="simMarkupRate"><?php echo ($markup_rate * 100); ?>%</div>
                                </div>
                                <div class="row mb-1">
                                    <div class="col-7 text-white-50">Monthly Installment:</div>
                                    <div class="col-5 text-end text-white fw-bold" id="simMonthlyInstallment">PKR 0.00</div>
                                </div>
                                <div class="row mb-1">
                                    <div class="col-7 text-white-50">Total Repayment Amount:</div>
                                    <div class="col-5 text-end text-white fw-bold" id="simTotalRepay">PKR 0.00</div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-7 text-white-50">Total Interest Surcharge:</div>
                                    <div class="col-5 text-end text-danger fw-bold" id="simTotalInterest">PKR 0.00</div>
                                </div>
                                
                                <div class="progress-bar-custom d-flex mb-1">
                                    <div class="bg-primary" id="simPrincipalBar" style="width: 100%;"></div>
                                    <div class="bg-danger" id="simInterestBar" style="width: 0%;"></div>
                                </div>
                                <div class="d-flex justify-content-between text-white-50" style="font-size:9px;">
                                    <span id="lblPrincipalWeight">Principal: 100%</span>
                                    <span id="lblInterestWeight">Markup: 0%</span>
                                </div>
                            </div>

                            <button type="submit" name="apply_loan" class="btn btn-gradient w-100 py-3 text-uppercase">Submit Loan Application</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-md-7">
                <div class="glass-card shadow-lg h-100">
                    <h5 class="fw-bold text-white border-bottom border-secondary pb-2 mb-3">Your Loan Applications</h5>
                    <div class="table-responsive">
                        <table class="table table-dark table-borderless align-middle mb-0 table-custom">
                            <thead>
                                <tr class="text-glow border-bottom border-secondary">
                                    <th>Track</th>
                                    <th>Principal</th>
                                    <th>EMI Amortization</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(mysqli_num_rows($loan_tracking_query) == 0): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-white-50 py-4">No historic credit applications logged.</td>
                                    </tr>
                                <?php endif; while($l = mysqli_fetch_assoc($loan_tracking_query)): ?>
                                    <tr>
                                        <td class="fw-bold text-white"><?php echo htmlspecialchars($l['loan_type']); ?></td>
                                        <td class="text-white font-monospace">Rs. <?php echo number_format($l['amount']); ?></td>
                                        <td class="font-monospace">Rs. <?php echo number_format($l['emi'], 2); ?>/mo (<?php echo $l['months']; ?> M)</td>
                                        <td class="text-center">
                                            <?php if($l['status'] == 'pending'): ?>
                                                <span class="badge bg-warning text-dark px-2.5 py-1.5 fw-bold text-uppercase">Evaluating</span>
                                            <?php elseif($l['status'] == 'approved'): ?>
                                                <span class="badge bg-success text-white px-2.5 py-1.5 fw-bold text-uppercase">Disbursed</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger text-white px-2.5 py-1.5 fw-bold text-uppercase">Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        var globalMarkupRate = <?php echo $markup_rate; ?>;
        
        function calculateAmortization() {
            var amount = parseFloat(document.getElementById('loanAmount').value);
            var months = parseInt(document.getElementById('loanMonths').value);
            
            var simEMI = document.getElementById('simMonthlyInstallment');
            var simTotal = document.getElementById('simTotalRepay');
            var simInterest = document.getElementById('simTotalInterest');
            var pBar = document.getElementById('simPrincipalBar');
            var iBar = document.getElementById('simInterestBar');
            var pLbl = document.getElementById('lblPrincipalWeight');
            var iLbl = document.getElementById('lblInterestWeight');

            if (isNaN(amount) || amount <= 0 || isNaN(months) || months <= 0) {
                simEMI.innerText = "PKR 0.00";
                simTotal.innerText = "PKR 0.00";
                simInterest.innerText = "PKR 0.00";
                pBar.style.width = "100%";
                iBar.style.width = "0%";
                pLbl.innerText = "Principal: 100%";
                iLbl.innerText = "Markup: 0%";
                return;
            }

            var interest = amount * globalMarkupRate;
            var totalRepay = amount + interest;
            var emi = totalRepay / months;

            simEMI.innerText = "PKR " + emi.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            simTotal.innerText = "PKR " + totalRepay.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            simInterest.innerText = "PKR " + interest.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});

            var pWeight = (amount / totalRepay) * 100;
            var iWeight = 100 - pWeight;

            pBar.style.width = pWeight + "%";
            iBar.style.width = iWeight + "%";

            pLbl.innerText = "Principal: " + pWeight.toFixed(0) + "%";
            iLbl.innerText = "Markup: " + iWeight.toFixed(0) + "%";
        }
        
        window.onload = function() {
            lucide.createIcons();
            calculateAmortization();
        };
    </script>
    <?php include 'chatbot.php'; ?>
</body>
</html>