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

if (isset($_POST['redeem_deal'])) {
    if (!isset($_POST['post_token']) || $_POST['post_token'] !== $_SESSION['txn_token']) {
        $msg = "<div class='alert alert-danger text-center glass-card text-white'>Security Validation Failed: CSRF Handshake Exception.</div>";
    } else {
        $cost = intval($_POST['points_cost']);
        $deal_desc = mysqli_real_escape_string($conn, $_POST['deal_name']);
        
        $user_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT loyalty_points FROM users WHERE id=$user_id"));
        if ($user_check['loyalty_points'] < $cost) {
            $msg = "<div class='alert alert-danger text-center glass-card text-white fw-bold'>Redemption Denied: Insufficient loyalty points inside database.</div>";
        } else {
            if (mysqli_query($conn, "UPDATE users SET loyalty_points = loyalty_points - $cost WHERE id=$user_id")) {
                mysqli_query($conn, "INSERT INTO transactions (account_no, type, amount, description) VALUES ('".$_SESSION['account_no']."', 'withdrawal', 0.00, 'Claimed Reward: $deal_desc (-$cost pts)')");
                mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES ($user_id, 'Reward Claim', 'Exchanged $cost points for reward: $deal_desc')");
                $msg = "<div class='alert alert-success text-center glass-card text-white fw-bold'>Reward claimed successfully! Your voucher code has been sent to your email.</div>";
                unset($_SESSION['txn_token']); $_SESSION['txn_token'] = bin2hex(random_bytes(16));
            } else {
                $msg = "<div class='alert alert-danger text-center glass-card text-white'>Database error processing redemption.</div>";
            }
        }
    }
}
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id"));

$deals = array(
    array("id" => 1, "name" => "PIA Upgrade Voucher", "type" => "Travel", "benefit" => "Free business class cabin upgrade on your next flight.", "cost" => 120),
    array("id" => 2, "name" => "The Espresso Lounge Discount", "type" => "Dining", "benefit" => "Flat 50% off on your total bill.", "cost" => 40),
    array("id" => 3, "name" => "Sui Southern Gas Discount", "type" => "Utilities", "benefit" => "Rs. 1,500 discount voucher on your gas bill.", "cost" => 80),
    array("id" => 4, "name" => "Bella Italia BOGO", "type" => "Dining", "benefit" => "Buy 1 Get 1 Free main course voucher.", "cost" => 60)
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MTAU Bank - Loyalty Rewards</title>
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

    <div class="container my-5" style="max-width: 900px;">
        <div class="text-center mb-5">
            <h2 class="fw-bold text-gold"><i data-lucide="award" class="inline-icon text-gold" style="width: 28px; height: 28px;"></i> Loyalty Rewards Portal</h2>
            <p class="text-white-50">Exchange your loyalty points for vouchers and exclusive discounts.</p>
            <span class="badge bg-dark border border-warning px-4 py-2.5 font-monospace fs-6">
                Your Balance: <span class="text-warning"><?php echo number_format($user['loyalty_points']); ?> PTS</span>
            </span>
        </div>
        
        <?php echo $msg; ?>
        
        <div class="row g-4">
            <?php foreach($deals as $deal): ?>
                <div class="col-md-6">
                    <div class="glass-card h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="fw-bold text-white mb-0"><?php echo htmlspecialchars($deal['name']); ?></h5>
                                <span class="badge bg-secondary font-monospace" style="font-size:10px;"><?php echo $deal['type']; ?></span>
                            </div>
                            <hr class="border-secondary opacity-25 my-3">
                            <p class="small text-white-50 mb-4"><?php echo htmlspecialchars($deal['benefit']); ?></p>
                        </div>
                        <form action="" method="POST">
                            <input type="hidden" name="post_token" value="<?php echo $_SESSION['txn_token']; ?>">
                            <input type="hidden" name="deal_name" value="<?php echo $deal['name']; ?>">
                            <input type="hidden" name="points_cost" value="<?php echo $deal['cost']; ?>">
                            
                            <?php if($user['loyalty_points'] >= $deal['cost']): ?>
                                <button type="submit" name="redeem_deal" class="btn btn-warning-custom w-100 py-2.5">Redeem for <?php echo $deal['cost']; ?> Pts</button>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary w-100 py-2.5 opacity-50 cursor-not-allowed" disabled>Locked (Requires <?php echo $deal['cost']; ?> Pts)</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <script>
        lucide.createIcons();
    </script>
    <?php include 'chatbot.php'; ?>
</body>
</html>