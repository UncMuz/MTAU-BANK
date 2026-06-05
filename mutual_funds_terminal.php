<?php
include 'db.php'; session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_id = $_SESSION['user_id']; $msg = "";
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id"));
if ($user['status'] === 'frozen') {
    session_unset(); session_destroy();
    header("Location: login.php?error=frozen"); exit();
}

if (!isset($_SESSION['txn_token'])) { $_SESSION['txn_token'] = bin2hex(random_bytes(16)); }

if (isset($_POST['invest'])) {
    if (!isset($_POST['post_token']) || $_POST['post_token'] !== $_SESSION['txn_token']) {
        $msg = "<div class='alert alert-danger text-center glass-card text-white'>Security Validation Failed: CSRF Handshake Exception.</div>";
    } else {
        $fund_name = mysqli_real_escape_string($conn, $_POST['fund_name']); $amount = floatval($_POST['amount']);
        $yield_rate = ($fund_name === 'MTAU Alpha Growth') ? 18.25 : 11.40;

        if ($amount <= 0) { 
            $msg = "<div class='alert alert-danger text-center glass-card text-white'>Enter a valid amount.</div>"; 
        } else if ($user['balance'] < $amount) { 
            $msg = "<div class='alert alert-danger text-center glass-card text-white'>Declined: Insufficient core balance capital arrays.</div>"; 
        } else {
            if (mysqli_query($conn, "UPDATE users SET balance = balance - $amount WHERE id=$user_id")) {
                mysqli_query($conn, "INSERT INTO mutual_funds (user_id, fund_name, amount_invested, expected_yield_rate) VALUES ($user_id, '$fund_name', $amount, $yield_rate)");
                mysqli_query($conn, "INSERT INTO transactions (account_no, type, amount, description) VALUES ('".$user['account_no']."', 'withdrawal', $amount, 'Invested in mutual fund: $fund_name')");
                mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES ($user_id, 'Investment', 'Invested Rs. " . number_format($amount) . " in $fund_name')");
                
                $msg = "<div class='alert alert-success text-center glass-card text-white fw-bold'>Investment successful! PKR " . number_format($amount) . " has been invested.</div>";
                unset($_SESSION['txn_token']); $_SESSION['txn_token'] = bin2hex(random_bytes(16));
                $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id"));
            } else {
                $msg = "<div class='alert alert-danger text-center glass-card text-white'>Database error processing investment.</div>";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MTAU Bank - Mutual Funds</title>
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

    <div class="container my-5" style="max-width: 600px;">
        <?php echo $msg; ?>
        
        <div class="glass-card shadow-lg text-center">
            <h3 class="fw-bold text-white mb-2"><i data-lucide="trending-up" class="inline-icon text-glow"></i> Mutual Funds Investment</h3>
            <p class="text-white-50 small mb-4">Invest in high-yield mutual funds to grow your personal savings.</p>

            <div class="p-3 bg-dark border border-secondary rounded font-monospace mb-4 text-center">
                Available Balance: <span class="text-glow">PKR <?php echo number_format($user['balance'], 2); ?></span>
            </div>

            <form action="" method="POST">
                <input type="hidden" name="post_token" value="<?php echo $_SESSION['txn_token']; ?>">
                
                <div class="mb-3 text-start">
                    <label class="form-label text-glow small">Select Mutual Fund</label>
                    <select name="fund_name" class="form-select font-monospace">
                        <option value="MTAU Alpha Growth">MTAU Alpha Growth (High-Yield Equities: 18.25% Expected Yield)</option>
                        <option value="MTAU Islamic Reserves">MTAU Islamic Reserves (Shariah-Compliant: 11.40% Expected Yield)</option>
                    </select>
                </div>
                
                <div class="mb-4 text-start">
                    <label class="form-label text-white-50 small">Investment Amount (PKR)</label>
                    <input type="number" step="0.01" name="amount" class="form-control font-monospace" placeholder="0.00" required>
                    <small class="text-white-50 d-block mt-1">Funds will be deducted from your account balance instantly.</small>
                </div>
                
                <button type="submit" name="invest" class="btn btn-gradient w-100 py-3 text-uppercase">Confirm Investment</button>
            </form>
        </div>
    </div>
    <script>
        lucide.createIcons();
    </script>
    <?php include 'chatbot.php'; ?>
</body>
</html>