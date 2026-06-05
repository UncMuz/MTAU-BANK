<?php
include 'db.php'; session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_id = $_SESSION['user_id']; $msg = "";

if (!isset($_SESSION['txn_token'])) { $_SESSION['txn_token'] = bin2hex(random_bytes(16)); }
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id"));
if ($user['status'] === 'frozen') {
    session_unset(); session_destroy();
    header("Location: login.php?error=frozen"); exit();
}

// Toggle Freeze State
if (isset($_POST['toggle_block'])) {
    if (!isset($_POST['post_token']) || $_POST['post_token'] !== $_SESSION['txn_token']) {
        $msg = "<div class='alert alert-danger text-center glass-card text-white'>Security Validation Failed: CSRF Handshake Exception.</div>";
    } else {
        $current = intval($_POST['current_block_state']); $new_state = ($current === 1) ? 0 : 1;
        if (mysqli_query($conn, "UPDATE users SET card_blocked=$new_state WHERE id=$user_id")) {
            $log_desc = $new_state ? 'Card frozen temporarily.' : 'Card unblocked/released.';
            mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES ($user_id, 'Card Config', '$log_desc')");
            $msg = "<div class='alert alert-success text-center glass-card text-white'>Card Security State Modified!</div>";
            unset($_SESSION['txn_token']); $_SESSION['txn_token'] = bin2hex(random_bytes(16));
        } else {
            $msg = "<div class='alert alert-danger text-center glass-card text-white'>Error modifying security state.</div>";
        }
    }
}

// Update Usage parameters and spending cap limits
if (isset($_POST['update_parameters'])) {
    if (!isset($_POST['post_token']) || $_POST['post_token'] !== $_SESSION['txn_token']) {
        $msg = "<div class='alert alert-danger text-center glass-card text-white'>Security Validation Failed: CSRF Handshake Exception.</div>";
    } else {
        $local_usage = isset($_POST['local_usage']) ? 1 : 0;
        $intl_usage = isset($_POST['intl_usage']) ? 1 : 0;
        $card_limit = floatval($_POST['card_limit']);
        
        $max_limit = ($user['account_class'] === 'Business') ? 1000000.00 : (($user['account_class'] === 'Student') ? 50000.00 : 100000.00);

        if ($card_limit <= 0) {
            $msg = "<div class='alert alert-danger text-center glass-card text-white'>Validation Error: Spend limit must be greater than zero.</div>";
        } else if ($card_limit > $max_limit) {
            $msg = "<div class='alert alert-danger text-center glass-card text-white'>Compliance Limit Exceeded: Daily limits for " . $user['account_class'] . " class cannot exceed PKR " . number_format($max_limit) . ".</div>";
        } else {
            if (mysqli_query($conn, "UPDATE users SET local_usage=$local_usage, intl_usage=$intl_usage, card_limit=$card_limit WHERE id=$user_id")) {
                mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES ($user_id, 'Card Config', 'Card parameters updated. Local: $local_usage, Intl: $intl_usage, Limit: Rs. " . number_format($card_limit) . "')");
                $msg = "<div class='alert alert-success text-center glass-card text-white'>Card configuration updated successfully!</div>";
                unset($_SESSION['txn_token']); $_SESSION['txn_token'] = bin2hex(random_bytes(16));
            } else {
                $msg = "<div class='alert alert-danger text-center glass-card text-white'>Database error updating parameters.</div>";
            }
        }
    }
}

// PIN Reset Configuration
if (isset($_POST['change_pin'])) {
    if (!isset($_POST['post_token']) || $_POST['post_token'] !== $_SESSION['txn_token']) {
        $msg = "<div class='alert alert-danger text-center glass-card text-white'>Security Validation Failed: CSRF Handshake Exception.</div>";
    } else {
        $new_pin = mysqli_real_escape_string($conn, $_POST['new_pin']);
        if (strlen($new_pin) === 4 && is_numeric($new_pin)) {
            if (mysqli_query($conn, "UPDATE users SET card_pin='$new_pin' WHERE id=$user_id")) {
                mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES ($user_id, 'Card Config', 'Card security access PIN modified.')");
                $msg = "<div class='alert alert-success text-center glass-card text-white'>PIN Parameter Reset Complete.</div>";
                unset($_SESSION['txn_token']); $_SESSION['txn_token'] = bin2hex(random_bytes(16));
            } else {
                $msg = "<div class='alert alert-danger text-center glass-card text-white'>Database error updating PIN.</div>";
            }
        } else {
            $msg = "<div class='alert alert-danger text-center glass-card text-white'>PIN must be exactly 4 numeric characters.</div>";
        }
    }
}

// Re-fetch user details for updated states
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id"));
$card_suffix = substr(preg_replace('/[^0-9]/', '', $user['account_no']), -4);
$max_allowed_limit = ($user['account_class'] === 'Business') ? 1000000.00 : (($user['account_class'] === 'Student') ? 50000.00 : 100000.00);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MTAU Bank - Card Control Center</title>
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

    <div class="container my-5" style="max-width: 750px;">
        <?php echo $msg; ?>
        
        <div class="glass-card shadow-lg text-center">
            <h3 class="fw-bold text-white mb-2"><i data-lucide="credit-card" class="inline-icon text-glow"></i> Card Settings & Limits</h3>
            <p class="text-white-50 small mb-4">Manage card status, daily spending limits, domestic/international usage, and update your ATM PIN.</p>
            
            <?php if($user['card_status'] !== 'issued'): ?>
                <div class="py-5 text-white-50">
                    <div class="text-white-50 mb-3"><i data-lucide="user-check" style="width: 48px; height: 48px;"></i></div>
                    No active debit card detected on this profile. Please activate a card on the dashboard first.
                </div>
            <?php else: ?>
                <!-- Render Card Graphics -->
                <div class="card-render p-4 mb-4 text-start d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small font-monospace text-white fw-bold opacity-75">MTAU DIGITAL</span>
                        <span class="text-white fw-bold fs-5"><?php echo ($user['card_blocked']) ? '<i data-lucide="lock" class="inline-icon text-danger" style="margin-right:0;"></i>' : '<i data-lucide="rss" class="inline-icon text-white" style="margin-right:0;"></i>'; ?></span>
                    </div>
                    <h4 class="font-monospace my-3 tracking-widest fw-bold text-white text-center" style="letter-spacing: 4px; color:#12071c;">
                        4532 8812 9431 <?php echo $card_suffix; ?>
                    </h4>
                    <div class="d-flex justify-content-between align-items-end">
                        <span class="small text-uppercase text-white fw-bold" style="color:#12071c;"><?php echo htmlspecialchars($user['full_name']); ?></span>
                        <span class="fw-bold fs-5" style="color:#12071c;">VISA</span>
                    </div>
                </div>

                <div class="p-3 bg-dark border border-secondary rounded font-monospace mb-4 text-start small">
                    <div>Active Card Tier Class: <span class="text-glow"><?php echo $user['card_tier']; ?></span></div>
                    <div>Daily Spending Cap Rule: <span class="text-warning">Max PKR <?php echo number_format($max_allowed_limit); ?></span></div>
                    <div>Current Active Limit Setting: <span class="text-white fw-bold">PKR <?php echo number_format($user['card_limit'], 2); ?></span></div>
                    <div>Network Connection State: <?php echo ($user['card_blocked']) ? '<span class="text-danger fw-bold"><span class="status-dot maintenance"></span>FROZEN / SUSPENDED</span>' : '<span class="text-success fw-bold"><span class="status-dot active"></span>ACTIVE ON-NETWORK</span>'; ?></div>
                </div>

                <!-- Toggle Freeze State Form -->
                <form action="" method="POST" class="mb-4">
                    <input type="hidden" name="post_token" value="<?php echo $_SESSION['txn_token']; ?>">
                    <input type="hidden" name="current_block_state" value="<?php echo $user['card_blocked']; ?>">
                    <button type="submit" name="toggle_block" class="btn w-100 py-3 <?php echo ($user['card_blocked']) ? 'btn-success' : 'btn-danger'; ?> fw-bold text-uppercase d-flex align-items-center justify-content-center gap-2" style="border-radius: 12px;">
                        <?php echo ($user['card_blocked']) ? '<i data-lucide="unlock" class="inline-icon" style="margin-right:0;"></i> Authorize Card Release' : '<i data-lucide="lock" class="inline-icon" style="margin-right:0;"></i> Freeze Card Operations'; ?>
                    </button>
                </form>

                <!-- Usage Settings Form -->
                <form action="" method="POST" class="text-start border-top border-secondary pt-4 mb-4">
                    <input type="hidden" name="post_token" value="<?php echo $_SESSION['txn_token']; ?>">
                    <h5 class="fw-bold text-glow mb-3">Channel Usage & Spending Limit Controls</h5>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <div class="form-check form-switch glass-card p-3 d-flex align-items-center justify-content-between mb-0">
                                <label class="form-check-label text-white small fw-bold" for="localUsageSwitch">Domestic Usage</label>
                                <input class="form-check-input me-0" type="checkbox" name="local_usage" id="localUsageSwitch" <?php echo ($user['local_usage']) ? 'checked' : ''; ?>>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-check form-switch glass-card p-3 d-flex align-items-center justify-content-between mb-0">
                                <label class="form-check-label text-white small fw-bold" for="intlUsageSwitch">International Usage</label>
                                <input class="form-check-input me-0" type="checkbox" name="intl_usage" id="intlUsageSwitch" <?php echo ($user['intl_usage']) ? 'checked' : ''; ?>>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-white-50 small">Custom Daily Spending Ceiling Limit (PKR)</label>
                        <input type="number" step="100" name="card_limit" class="form-control font-monospace" value="<?php echo floatval($user['card_limit']); ?>" min="1" max="<?php echo $max_allowed_limit; ?>" required>
                        <small class="text-white-50 d-block mt-1">Spend limits must fall under tier classification caps (PKR <?php echo number_format($max_allowed_limit); ?> max).</small>
                    </div>

                    <button type="submit" name="update_parameters" class="btn btn-gradient w-100 py-2.5">Apply Parameters Update</button>
                </form>

                <!-- PIN Modification Form -->
                <form action="" method="POST" class="text-start border-top border-secondary pt-4">
                    <input type="hidden" name="post_token" value="<?php echo $_SESSION['txn_token']; ?>">
                    <h5 class="fw-bold text-glow mb-3">Modify ATM Access PIN</h5>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <label class="form-label text-white-50 small">Reset Secure 4-Digit ATM PIN</label>
                            <input type="password" name="new_pin" class="form-control text-center font-monospace" maxlength="4" placeholder="••••" required>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" name="change_pin" class="btn btn-gradient w-100 py-2.5">Commit PIN Reset</button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <script>
        lucide.createIcons();
    </script>
    <?php include 'chatbot.php'; ?>
</body>
</html>