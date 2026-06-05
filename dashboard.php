<?php
include 'db.php'; session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') { header("Location: login.php"); exit(); }
// Security Check: Grab Geolocation Data for realistic session auditing nodes (cached to prevent page load blocking)
// Security Check: Geolocation is resolved asynchronously in the browser to prevent PHP page load blocking
$user_id = $_SESSION['user_id']; $msg = "";
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id")); 
if ($user['status'] === 'frozen') {
    session_unset(); session_destroy();
    header("Location: login.php?error=frozen"); exit();
}

// 1.5 Loyalty Rewards Checks
$today = date('Y-m-d');
if ($user['last_login_date'] !== $today) {
    mysqli_query($conn, "UPDATE users SET loyalty_points = loyalty_points + 1, last_login_date = '$today' WHERE id=$user_id");
    mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES ($user_id, 'Loyalty Reward', 'Daily login loyalty point (+1 PTS) collected.')");
    $user['loyalty_points'] += 1;
    $user['last_login_date'] = $today;
}

$current_month = date('Y-m');
if ($user['balance'] >= 80000.00 && $user['last_maintenance_claim'] !== $current_month) {
    mysqli_query($conn, "UPDATE users SET loyalty_points = loyalty_points + 25, last_maintenance_claim = '$current_month' WHERE id=$user_id");
    mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES ($user_id, 'Loyalty Reward', 'Monthly balance maintenance loyalty bonus (+25 PTS) collected.')");
    $user['loyalty_points'] += 25;
    $user['last_maintenance_claim'] = $current_month;
}

$account_no = $user['account_no']; $tier_class = $user['account_class'];

// Generate deterministic card suffix directly based on user's relational IBAN
$card_suffix = substr(preg_replace('/[^0-9]/', '', $account_no), -4);

if (!isset($_SESSION['txn_token'])) { $_SESSION['txn_token'] = bin2hex(random_bytes(16)); }

// Sandbox Simulator Core Mechanics
if (isset($_POST['process_transaction'])) {
    $type = $_POST['type']; $amount = floatval($_POST['amount']);
    if ($amount > 0) {
        if ($type === 'deposit') {
            mysqli_query($conn, "UPDATE users SET balance = balance + $amount WHERE id=$user_id");
            mysqli_query($conn, "INSERT INTO transactions (account_no, type, amount, description) VALUES ('$account_no', 'deposit', $amount, 'Simulator Cash Inflow Sync')");
            mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES ($user_id, 'Deposit', 'Deposited Rs. " . number_format($amount) . "')");
        } else {
            if ($user['balance'] >= $amount) {
                mysqli_query($conn, "UPDATE users SET balance = balance - $amount WHERE id=$user_id");
                mysqli_query($conn, "INSERT INTO transactions (account_no, type, amount, description) VALUES ('$account_no', 'withdrawal', $amount, 'Simulator Cash Outflow Sync')");
                mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES ($user_id, 'Withdrawal', 'Withdrew Rs. " . number_format($amount) . "')");
            } else { $msg = "<div class='alert alert-danger text-center glass-card text-white'>Simulation Aborted: Insufficient available capital logs.</div>"; }
        }
        $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id"));
    }
}

// EMV Secure Card Activation Mapping Logic with Handshake Token Lock
if (isset($_POST['apply_card'])) {
    if ($_POST['post_token'] === $_SESSION['txn_token']) {
        if ($user['card_status'] === 'issued') {
            $msg = "<div class='alert alert-warning text-center fw-bold glass-card text-white'>System Exception: Card architecture profile already verified live on-network.</div>";
        } else {
            $tier = ($tier_class === 'Business') ? 'Business Card' : (($tier_class === 'Student') ? 'Student Card' : 'Standard Card');
            $card_mode = $_POST['card_mode']; $pin = mysqli_real_escape_string($conn, $_POST['card_pin']);
            
            if (strlen($pin) !== 4 || !is_numeric($pin)) {
                $msg = "<div class='alert alert-danger text-center fw-bold glass-card text-white'>Security Alert: PIN constraints require exactly 4 numeric elements.</div>";
            } else {
                $charge = ($tier_class === 'Student') ? (($card_mode === 'physical') ? 500.00 : 0.00) : 2500.00;
                if ($tier_class === 'Business' && $user['balance'] >= 80000) $charge = 0.00;
                if ($tier_class === 'Standard' && $user['balance'] >= 50000) $charge = 0.00;
                
                if ($user['balance'] < $charge) {
                    $msg = "<div class='alert alert-danger text-center fw-bold glass-card text-white'>Aborted: Insufficient balance sums to cover hardware processing fees.</div>";
                } else {
                    if ($charge > 0) {
                        mysqli_query($conn, "UPDATE users SET balance = balance - $charge WHERE id=$user_id");
                        mysqli_query($conn, "INSERT INTO transactions (account_no, type, amount, description) VALUES ('$account_no', 'withdrawal', $charge, 'Debit Card Hardware Issuance Fee')");
                    }
                    $default_limit = ($tier_class === 'Business') ? 1000000.00 : (($tier_class === 'Student') ? 50000.00 : 100000.00);
                    mysqli_query($conn, "UPDATE users SET card_status='issued', card_tier='$tier', card_pin='$pin', card_limit=$default_limit WHERE id=$user_id");
                    mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES ($user_id, 'Card Config', 'Activated secure EMV $tier configuration wrapper. Assigned limit: Rs. " . number_format($default_limit) . "')");
                    $msg = "<div class='alert alert-success text-center fw-bold glass-card text-white'>EMV Hardware Live: Card Activated!</div>";
                    unset($_SESSION['txn_token']); $_SESSION['txn_token'] = bin2hex(random_bytes(16)); 
                    $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id"));
                }
            }
        }
    }
}

// Multi-Tier Insurances Verification Router Subsystem
if (isset($_POST['enroll_insurance'])) {
    $policy_type = mysqli_real_escape_string($conn, $_POST['policy_type']);
    $coverage = floatval($_POST['coverage_amount']);
    $rate = ($policy_type === 'Health Insurance') ? 0.005 : (($policy_type === 'Property Insurance') ? 0.008 : 0.01);
    $premium = $coverage * $rate;

    if ($user['balance'] < $premium) {
        $msg = "<div class='alert alert-danger text-center glass-card text-white'>Insufficient liquidity to clear premium lines initialization cost of Rs. " . number_format($premium) . "</div>";
    } else {
        mysqli_query($conn, "UPDATE users SET balance = balance - $premium WHERE id=$user_id");
        mysqli_query($conn, "INSERT INTO insurance_policies (user_id, policy_type, coverage_amount, monthly_premium) VALUES ($user_id, '$policy_type', $coverage, $premium)");
        mysqli_query($conn, "INSERT INTO transactions (account_no, type, amount, description) VALUES ('$account_no', 'withdrawal', $premium, 'Premium Paid: $policy_type')");
        mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES ($user_id, 'Insurance', 'Enrolled within insurance liability options framework: $policy_type')");
        $msg = "<div class='alert alert-success text-center fw-bold glass-card text-white'>Policy Verified! Initial Premium processed.</div>";
        $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id"));
    }
}

// Stock & Real Estate Fractional Profit-Sharing Investment Handler
if (isset($_POST['commit_asset_investment'])) {
    if ($_POST['post_token'] === $_SESSION['txn_token']) {
        $asset_name = mysqli_real_escape_string($conn, $_POST['asset_name']);
        $invest_amount = floatval($_POST['invest_amount']);
        
        $yield_map = [
            "Clifton Residency Property Share" => 9.8,
            "Islamabad Commercial Plaza Share" => 11.2,
            "PSX Tech Dividend Index" => 15.4,
            "Energy Giants Dividend Stock" => 13.2
        ];
        
        if (!array_key_exists($asset_name, $yield_map)) {
            $msg = "<div class='alert alert-danger text-center glass-card text-white'>Invalid asset selection.</div>";
        } else if ($invest_amount <= 0) {
            $msg = "<div class='alert alert-danger text-center glass-card text-white'>Investment amount must be greater than zero.</div>";
        } else if ($user['balance'] < $invest_amount) {
            $msg = "<div class='alert alert-danger text-center glass-card text-white'>Declined: Insufficient liquid reserves.</div>";
        } else {
            $yield_rate = $yield_map[$asset_name];
            if (mysqli_query($conn, "UPDATE users SET balance = balance - $invest_amount WHERE id=$user_id")) {
                mysqli_query($conn, "INSERT INTO mutual_funds (user_id, fund_name, amount_invested, expected_yield_rate) VALUES ($user_id, '$asset_name', $invest_amount, $yield_rate)");
                mysqli_query($conn, "INSERT INTO transactions (account_no, type, amount, description) VALUES ('$account_no', 'withdrawal', $invest_amount, 'Profit Share Buy: $asset_name')");
                mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES ($user_id, 'Asset Investment', 'Committed Rs. " . number_format($invest_amount) . " to $asset_name')");
                
                $msg = "<div class='alert alert-success text-center glass-card text-white fw-bold'>Investment Committed! Allocated PKR " . number_format($invest_amount) . " to $asset_name at " . $yield_rate . "% yield.</div>";
                unset($_SESSION['txn_token']); $_SESSION['txn_token'] = bin2hex(random_bytes(16));
                $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id"));
            } else {
                $msg = "<div class='alert alert-danger text-center glass-card text-white'>Database error committing investment.</div>";
            }
        }
    }
}

// Chart Allocation Query Matrix Setup
$res_dep = mysqli_query($conn, "SELECT SUM(amount) as total FROM transactions WHERE account_no='$account_no' AND type='deposit'");
$row_dep = mysqli_fetch_assoc($res_dep); $chart_dep = $row_dep['total'] ? $row_dep['total'] : 0;
$res_with = mysqli_query($conn, "SELECT SUM(amount) as total FROM transactions WHERE account_no='$account_no' AND type='withdrawal'");
$row_with = mysqli_fetch_assoc($res_with); $chart_with = $row_with['total'] ? $row_with['total'] : 0;
$res_trans = mysqli_query($conn, "SELECT SUM(amount) as total FROM transactions WHERE account_no='$account_no' AND type='transfer'");
$row_trans = mysqli_fetch_assoc($res_trans); $chart_trans = $row_trans['total'] ? $row_trans['total'] : 0;

$logs_query = mysqli_query($conn, "SELECT * FROM system_logs WHERE user_id=$user_id ORDER BY id DESC LIMIT 8");
$qr_api_url = "https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=" . urlencode($account_no) . "&bgcolor=261338&color=e0b0ff";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>MTAU Central Bank - Command Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="bank-watermark"></div>

    <nav class="navbar navbar-dark sticky-top px-4 py-3 shadow">
        <a class="navbar-brand d-flex align-items-center fw-bold fs-4" href="dashboard.php" style="color: #e0b0ff; text-shadow: 0 0 15px rgba(224, 176, 255, 0.6);">
            <i data-lucide="landmark" class="lucide-icon text-glow" style="width: 24px; height: 24px;"></i> MTAU BANK
        </a>
        <div class="ms-auto d-flex align-items-center">
            <span class="badge bg-dark border border-secondary me-3 px-3 py-2" id="geoNodeBadge">
                <i data-lucide="map-pin" class="lucide-icon" style="width:14px; height:14px;"></i> Node: <span id="geoNodeText">Syncing...</span>
            </span>
            <a href="user_manual.php" target="_blank" class="btn btn-sm btn-outline-light font-weight-bold me-3" style="border-radius: 8px;">
                <i data-lucide="book-open" class="lucide-icon" style="width:14px; height:14px;"></i> User Manual
            </a>
            <a href="support_terminal.php" class="btn btn-sm btn-outline-light font-weight-bold me-3" style="border-radius: 8px;">
                <i data-lucide="message-square" class="lucide-icon" style="width:14px; height:14px;"></i> Support Desk
            </a>
            <a href="mobile_app.php" class="btn btn-sm btn-outline-light font-weight-bold me-3" style="border-radius: 8px;">
                <i data-lucide="smartphone" class="lucide-icon" style="width:14px; height:14px;"></i> Mobile View
            </a>
            <button class="btn btn-warning me-3 btn-sm fw-bold shadow text-dark" data-bs-toggle="modal" data-bs-target="#alertModal" style="border-radius: 8px;">
                <i data-lucide="shield-alert" class="lucide-icon text-dark" style="width:14px; height:14px;"></i> Audit Logs
            </button>
            <span class="navbar-text text-white border-start border-secondary ps-3">
                <i data-lucide="user" class="lucide-icon" style="width:14px; height:14px;"></i> Holder: <b class="text-glow ms-1"><?php echo htmlspecialchars($user['full_name']); ?></b> | 
                <a href="logout.php" class="text-danger fw-bold text-decoration-none ms-2">
                    <i data-lucide="log-out" class="lucide-icon text-danger" style="width:14px; height:14px;"></i> Logout
                </a>
            </span>
        </div>
    </nav>

    <div class="container my-5">
        <?php echo $msg; ?>
        
        <div class="row g-4 mb-3">
            <div class="col-md-3">
                <div class="glass-card text-center h-100 d-flex flex-column justify-content-between mb-0 anti-gravity">
                    <div><h6 class="text-white-50 text-uppercase tracking-widest small mb-2">Network Routing IBAN</h6><h5 class="text-white font-monospace text-break fw-bold"><?php echo $user['account_no']; ?></h5></div>
                    <div class="border-top border-secondary pt-3 mt-2 row small opacity-75"><div class="col-6 text-start">CNIC: <span class="text-white"><?php echo $user['cnic']; ?></span></div><div class="col-6 text-end">Phone: <span class="text-white"><?php echo $user['phone']; ?></span></div></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="glass-card text-center h-100 d-flex flex-column align-items-center justify-content-center mb-0 anti-gravity" style="border-color: rgba(224, 176, 255, 0.35);">
                    <h6 class="text-white-50 text-uppercase tracking-widest small mb-2 d-flex align-items-center justify-content-center gap-1">
                        Available Liquidity 
                        <button type="button" class="eye-toggle-btn d-inline-flex align-items-center justify-content-center" onclick="toggleBalanceDisplay()" id="balanceToggleBtn"><i data-lucide="eye" style="width: 16px; height: 16px;"></i></button>
                    </h6>
                    <h2 class="text-white fw-bold mb-3 font-monospace" id="balanceDisplayField" data-real-balance="PKR <?php echo number_format($user['balance'], 2); ?>">••••••••</h2>
                    <div><span class="badge bg-dark text-glow border border-purple d-inline-flex align-items-center gap-1"><i data-lucide="crown" class="inline-icon text-glow"></i> <?php echo $user['account_class']; ?> Tier</span></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="glass-card text-center h-100 d-flex flex-column align-items-center justify-content-center mb-0 anti-gravity" style="border-color: rgba(255,193,7,0.25);">
                    <h6 class="text-gold text-uppercase tracking-widest small mb-2"><i data-lucide="trophy" class="inline-icon text-gold"></i> Loyalty Pool</h6>
                    <h2 class="text-white fw-bold mb-2 font-monospace"><?php echo number_format($user['loyalty_points']); ?> <span class="text-gold fs-6">PTS</span></h2>
                    <div><span class="badge bg-warning text-dark fw-bold px-3">Ecosystem Active</span></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="glass-card h-100 mb-0 anti-gravity">
                    <?php if($user['card_status'] === 'issued'): ?>
                        <div id="cardMaskedView" class="text-center d-flex flex-column justify-content-center h-100">
                            <h6 class="text-white-50 text-uppercase small mb-3"><i data-lucide="cpu" class="inline-icon"></i> EMV Secure Chip</h6>
                            <input type="password" id="inputPinField" class="form-control text-center mx-auto mb-3 font-monospace" placeholder="Enter PIN" maxlength="4" style="max-width: 160px;">
                            <button onclick="revealCardData('<?php echo $user['card_pin']; ?>')" class="btn btn-gradient btn-sm mx-auto px-4">Decrypt Card</button>
                        </div>
                        <div id="cardRealView" class="card-render p-4 h-100 d-flex flex-column justify-content-between d-none">
                            <div class="d-flex justify-content-between align-items-center"><span class="small font-monospace text-white fw-bold opacity-75">MTAU DIGITAL</span><span class="text-white fw-bold fs-5" id="cardStatusIcon"><?php echo ($user['card_blocked']) ? '<i data-lucide="lock" class="inline-icon text-danger" style="margin-right: 0;"></i>' : '<i data-lucide="rss" class="inline-icon text-white" style="margin-right: 0;"></i>'; ?></span></div>
                            <h4 class="font-monospace my-3 tracking-widest fw-bold text-white text-center" style="letter-spacing: 4px; color:#12071c;">4532 8812 9431 <?php echo $card_suffix; ?></h4>
                            <div class="d-flex justify-content-between align-items-end"><span class="small text-uppercase text-white fw-bold" style="color:#12071c;"><?php echo htmlspecialchars($user['full_name']); ?></span><span class="fw-bold fs-5" style="color:#12071c;">VISA</span></div>
                        </div>
                    <?php else: ?>
                        <div class="text-center h-100 d-flex flex-column justify-content-between">
                            <h6 class="text-white-50 text-uppercase tracking-widest small mb-1">EMV Debit Layer</h6>
                            <form action="" method="POST" class="row g-2 small">
                                <input type="hidden" name="post_token" value="<?php echo $_SESSION['txn_token']; ?>">
                                <div class="col-6"><select name="card_mode" class="form-select form-select-sm" required><option value="virtual">Virtual</option><option value="physical">Physical</option></select></div>
                                <div class="col-6"><input type="password" name="card_pin" class="form-control form-control-sm text-center" placeholder="Set PIN" maxlength="4" required></div>
                                <div class="col-12"><button type="submit" name="apply_card" class="btn btn-gradient btn-sm w-100 mt-2">Deploy Card Layer</button></div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="glass-card anti-gravity">
            <h5 class="text-white fw-bold mb-4"><i data-lucide="calculator" class="lucide-icon text-glow"></i> Currency Calculator</h5>
            <div class="row g-3 align-items-end">
                <div class="col-md-3"><label class="form-label text-glow small fw-bold">Remittance Input Sum</label><input type="number" step="0.01" id="convAmountInput" value="1000" class="form-control font-monospace" oninput="calculatePkrExchange()"></div>
                <div class="col-md-3"><label class="form-label text-white small">Source Asset</label><select id="convSourceAsset" class="form-select font-monospace" onchange="calculatePkrExchange()"><option value="PKR" selected>PKR - Pakistani Rupee</option><option value="USD">USD - United States Dollar</option><option value="EUR">EUR - Eurozone Currency</option><option value="GBP">GBP - British Pound</option><option value="AED">AED - Arab Emirates Dirham</option></select></div>
                <div class="col-md-3"><label class="form-label text-white small">Destination Asset</label><select id="convTargetAsset" class="form-select font-monospace" onchange="calculatePkrExchange()"><option value="PKR">PKR - Pakistani Rupee</option><option value="USD" selected>USD - United States Dollar</option><option value="EUR">EUR - Eurozone Currency</option><option value="GBP">GBP - British Pound</option><option value="AED">AED - Arab Emirates Dirham</option></select></div>
                <div class="col-md-3"><div class="p-2.5 rounded text-center" style="background: rgba(0,0,0,0.4); border: 1px solid rgba(224,176,255,0.1);"><small class="text-glow d-block font-monospace">Exchange Value Output</small><h4 class="text-white fw-bold mt-1 mb-0 font-monospace" id="convCalculatedOutput">0.00</h4></div></div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="glass-card text-center h-100 d-flex flex-column justify-content-between align-items-center mb-0 anti-gravity">
                    <h6 class="text-white-50 text-uppercase tracking-widest small mb-1">Live Crypto Ticker</h6>
                    <div>
                        <h2 id="liveCryptoPrice" class="text-glow font-monospace mb-1">Connecting...</h2>
                        <small id="cryptoPriceChange" class="fw-bold font-monospace d-block mb-2">--</small>
                    </div>
                    <select id="cryptoStreamSelector" class="form-select form-select-sm text-center font-monospace small mt-2" onchange="alterWebSocketStream()" style="border-color: rgba(224,176,255,0.3); color: #e0b0ff; background: rgba(0,0,0,0.4);">
                        <option value="btcusdt" selected>BTC / USDT (Bitcoin)</option>
                        <option value="ethusdt">ETH / USDT (Ethereum)</option>
                        <option value="bnbusdt">BNB / USDT (Binance Coin)</option>
                        <option value="solusdt">SOL / USDT (Solana)</option>
                        <option value="xrpusdt">XRP / USDT (Ripple)</option>
                    </select>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="glass-card text-center h-100 d-flex flex-column justify-content-center align-items-center mb-0 anti-gravity qr-interactive" data-bs-toggle="modal" data-bs-target="#qrExpansionModal">
                    <h6 class="text-white-50 small text-uppercase mb-2"><i data-lucide="qr-code" class="inline-icon"></i> Account QR Code (Click to Expand)</h6>
                    <img src="<?php echo $qr_api_url; ?>" alt="IBAN QR Code" style="border-radius:14px; border:1px solid rgba(224,176,255,0.2); padding:4px; height: 110px; width: 110px;">
                </div>
            </div>
        </div>

        <h4 class="text-white fw-bold mb-4"><i data-lucide="grid" class="lucide-icon text-glow"></i> Banking & Investment Services</h4>
        <div class="row g-4 mb-4">
            <div class="col-md-3"><a href="transfer_terminal.php" class="text-decoration-none text-white"><div class="glass-card hover-float p-4 text-center mb-0"><h2><i data-lucide="arrow-left-right" class="text-glow" style="width: 32px; height: 32px;"></i></h2><h5 class="fw-bold text-white mb-1">Fund Transfer</h5><p class="small text-white-50 mb-0">Secure Inter-Account Wire Transfers</p></div></a></div>
            <div class="col-md-3"><a href="bill_terminal.php" class="text-decoration-none text-white"><div class="glass-card hover-float p-4 text-center mb-0"><h2><i data-lucide="receipt" class="text-glow" style="width: 32px; height: 32px;"></i></h2><h5 class="fw-bold text-white mb-1">Ecosystem Bills</h5><p class="small text-white-50 mb-0">Utilities, Education, Travel, Wellness</p></div></a></div>
            <div class="col-md-3"><a href="mutual_funds_terminal.php" class="text-decoration-none text-white"><div class="glass-card hover-float p-4 text-center mb-0"><h2><i data-lucide="line-chart" class="text-glow" style="width: 32px; height: 32px;"></i></h2><h5 class="fw-bold text-white mb-1">Mutual Investments</h5><p class="small text-white-50 mb-0">Allocate High-Yield Income Pools</p></div></a></div>
            <div class="col-md-3"><a href="trading_terminal.php" class="text-decoration-none text-white"><div class="glass-card hover-float p-4 text-center mb-0"><h2><i data-lucide="candlestick-chart" class="text-glow" style="width: 32px; height: 32px;"></i></h2><h5 class="fw-bold text-white mb-1">Trading Desk</h5><p class="small text-white-50 mb-0">Forex & Crypto Demo Exchange</p></div></a></div>
            <div class="col-md-3"><a href="loan_terminal.php" class="text-decoration-none text-white"><div class="glass-card hover-float p-4 text-center mb-0"><h2><i data-lucide="building" class="text-glow" style="width: 32px; height: 32px;"></i></h2><h5 class="fw-bold text-white mb-1">Loan Center</h5><p class="small text-white-50 mb-0">File Strategic Corporate Line Credit</p></div></a></div>
            <div class="col-md-3"><a href="manage_card.php" class="text-decoration-none text-white"><div class="glass-card hover-float p-4 text-center mb-0"><h2><i data-lucide="credit-card" class="text-glow" style="width: 32px; height: 32px;"></i></h2><h5 class="fw-bold text-white mb-1">Manage Card</h5><p class="small text-white-50 mb-0">PIN Controls, Limits & Freezing</p></div></a></div>
            <div class="col-md-3"><a href="deals_terminal.php" class="text-decoration-none text-white"><div class="glass-card hover-float p-4 text-center mb-0"><h2><i data-lucide="gift" class="text-warning" style="width: 32px; height: 32px;"></i></h2><h5 class="fw-bold text-warning mb-1">Loyalty Perks</h5><p class="small text-white-50 mb-0">Claim High-Yield Rewards Pool</p></div></a></div>
            <div class="col-md-3"><a href="support_terminal.php" class="text-decoration-none text-white"><div class="glass-card hover-float p-4 text-center mb-0"><h2><i data-lucide="message-square" class="text-glow" style="width: 32px; height: 32px;"></i></h2><h5 class="fw-bold text-white mb-1">Helpdesk Support</h5><p class="small text-white-50 mb-0">Submit Tickets & View Resolution Logs</p></div></a></div>
        </div>
        
        <div class="text-center mb-5"><a href="statement_pdf.php" target="_blank" class="btn btn-outline-light text-uppercase fw-bold rounded-pill px-5"><i data-lucide="file-text" class="lucide-icon"></i> Open Account Ledger Statement →</a></div>

        <div class="row g-4">
            <div class="col-md-7">
                <div class="glass-card h-100 anti-gravity">
                    <h5 class="text-white fw-bold mb-1"><i data-lucide="shield" class="lucide-icon text-glow"></i> Multi-Category Risk Assurance Panel</h5>
                    <p class="text-white-50 small mb-4">Enroll inside risk containment coverage options with dynamic custom premium calculation metrics rulesets.</p>
                    <form action="" method="POST" class="row g-3 align-items-end small">
                        <div class="col-md-6"><label class="form-label text-glow fw-bold">Protection Strategy Framework Track</label><select name="policy_type" class="form-select" required><option value="Health Insurance">Health Insurance Protection Plan (0.5% Prem)</option><option value="Property Insurance">Property Insurance Structural Coverage (0.8% Prem)</option><option value="Car Insurance">Car Insurance / Transit Asset Cover (1.0% Prem)</option></select></div>
                        <div class="col-md-6"><label class="form-label text-white">Target Coverage Capacity Ceiling Block</label><select name="coverage_amount" class="form-select" required><option value="500000">PKR 500,000 Premium Liability Umbrella</option><option value="1000000">PKR 1,000,000 Mid Tier Asset Coverage</option><option value="5000000">PKR 5,000,000 Enterprise Scale Coverage Line</option></select></div>
                        <div class="col-12"><button type="submit" name="enroll_insurance" class="btn btn-gradient w-100 py-2.5">Authorize Coverage Plan</button></div>
                    </form>
                </div>
            </div>
            <div class="col-md-5">
                <div class="glass-card text-center h-100 anti-gravity">
                    <h5 class="text-white font-weight-bold mb-3">Asset Allocation Breakdown Chart</h5>
                    <div class="mx-auto" style="position: relative; width: 200px; height: 200px;"><canvas id="analyticsChart" width="200" height="200"></canvas></div>
                </div>
            </div>
        </div>

        <!-- Stock & Property Fractional Profit-Sharing Simulator -->
        <div class="glass-card mt-4 anti-gravity">
            <h5 class="text-white fw-bold mb-1"><i data-lucide="bar-chart-3" class="lucide-icon text-glow"></i> Wealth Builder: Fractional Assets Profit Sharing</h5>
            <p class="text-white-50 small mb-4">Calculate real-time simulated earnings for fractional real estate rentals and stock dividend yields, and invest to secure your share.</p>
            
            <form action="" method="POST">
                <input type="hidden" name="post_token" value="<?php echo $_SESSION['txn_token']; ?>">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label text-glow small fw-bold">Select Investment Asset</label>
                        <select name="asset_name" id="assetSelector" class="form-select" onchange="runAssetSimulation()" required>
                            <option value="Clifton Residency Property Share" data-yield="9.8" data-type="Property" data-price="25000">Clifton Residency Share (9.8% Yield - Rent)</option>
                            <option value="Islamabad Commercial Plaza Share" data-yield="11.2" data-type="Property" data-price="50000">Islamabad Commercial Share (11.2% Yield - Rent)</option>
                            <option value="PSX Tech Dividend Index" data-yield="15.4" data-type="Stock" data-price="500">PSX Tech Dividend Index (15.4% Yield - Dividend)</option>
                            <option value="Energy Giants Dividend Stock" data-yield="13.2" data-type="Stock" data-price="1200">Energy Giants Stock (13.2% Yield - Dividend)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-white small fw-bold">Investment Amount (PKR)</label>
                        <input type="number" step="100" name="invest_amount" id="simulateAmountInput" class="form-control font-monospace" value="50000" oninput="runAssetSimulation()" required>
                    </div>
                    <div class="col-md-5">
                        <div class="p-3 rounded text-start" style="background: rgba(0,0,0,0.4); border: 1px solid rgba(224,176,255,0.1);">
                            <div class="row small font-monospace">
                                <div class="col-6">Est. Annual Profit:</div>
                                <div class="col-6 text-end text-glow fw-bold" id="simAnnualProfit">PKR 0.00</div>
                                <div class="col-6">Payout Share:</div>
                                <div class="col-6 text-end text-white fw-bold" id="simMonthlyProfit">PKR 0.00</div>
                                <div class="col-6">Fractional Share:</div>
                                <div class="col-6 text-end text-success fw-bold" id="simFractionOwned">0.000%</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" name="commit_asset_investment" class="btn btn-gradient px-5 py-2.5">Commit Capital & Lock Share</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="glass-card mt-4 anti-gravity">
            <h5 class="text-white fw-bold mb-3"><i data-lucide="settings" class="lucide-icon text-glow"></i> Base Cash Simulator Terminal</h5>
            <form action="" method="POST" class="row g-3 align-items-end">
                <div class="col-md-4"><label class="form-label text-glow small fw-bold">Select Operation Type</label><select name="type" class="form-select"><option value="deposit">Deposit Funds (Inflow)</option><option value="withdrawal">Withdraw Cash (Outflow)</option></select></div>
                <div class="col-md-4"><label class="form-label text-white small fw-bold">Sum Amount (PKR)</label><input type="number" step="0.01" name="amount" class="form-control font-monospace" required placeholder="0.00"></div>
                <div class="col-md-4"><button type="submit" name="process_transaction" class="btn btn-gradient w-100 py-2">Commit Operation</button></div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="qrExpansionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content text-center" style="background: #12071c; border: 2px solid #e0b0ff; border-radius: 24px; padding: 20px;">
                <h5 class="modal-title fw-bold text-glow mb-3">Your Routing QR Node</h5>
                <img src="<?php echo $qr_api_url; ?>&size=300x300" alt="Expanded QR Code" class="img-fluid mx-auto mb-3 shadow" style="border-radius:16px; background:#fff; padding: 8px;">
                <div class="font-monospace text-white bg-dark p-2 rounded small border border-secondary mb-3"><?php echo $account_no; ?></div>
                <button type="button" class="btn btn-gradient w-100 btn-sm text-dark" data-bs-dismiss="modal">Close Mirror View</button>
            </div>
        </div>
    </div>

    <div class="modal fade" id="alertModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="background: rgba(18,7,28,0.96); backdrop-filter: blur(15px); border: 1px solid rgba(224,176,255,0.25); color: white; border-radius: 24px;">
                <div class="modal-header border-0 border-bottom border-secondary"><h5 class="modal-title fw-bold text-warning d-flex align-items-center"><i data-lucide="shield-alert" class="lucide-icon text-warning me-2"></i> Secure Operational System Audit Logs</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-4"><div class="table-responsive"><table class="table table-dark table-striped table-borderless align-middle small mb-0" style="background: transparent;"><thead><tr class="text-glow font-monospace border-bottom border-secondary"><th>Timestamp</th><th>Core Class</th><th>Security Event Narrative Description</th></tr></thead><tbody><?php if(mysqli_num_rows($logs_query) == 0): ?><tr><td colspan="3" class="text-center text-white-50 py-3">No log trails recorded.</td></tr><?php endif; while($log = mysqli_fetch_assoc($logs_query)): ?><tr><td class="font-monospace text-white-50"><?php echo $log['logged_at']; ?></td><td><span class="badge bg-secondary"><?php echo $log['activity_type']; ?></span></td><td class="text-white"><?php echo htmlspecialchars($log['description']); ?></td></tr><?php endwhile; ?></tbody></table></div></div>
            </div>
        </div>
    </div>

    <script>
    var balanceHidden = true;
    function toggleBalanceDisplay() {
        var field = document.getElementById('balanceDisplayField');
        var btn = document.getElementById('balanceToggleBtn');
        if (balanceHidden) {
            field.innerText = field.getAttribute('data-real-balance');
            btn.innerHTML = '<i data-lucide="eye-off" class="inline-icon"></i>';
            balanceHidden = false;
        } else {
            field.innerText = "••••••••";
            btn.innerHTML = '<i data-lucide="eye" class="inline-icon"></i>';
            balanceHidden = true;
        }
        lucide.createIcons();
    }

    function calculatePkrExchange() {
        var amt = parseFloat(document.getElementById('convAmountInput').value);
        var src = document.getElementById('convSourceAsset').value;
        var tgt = document.getElementById('convTargetAsset').value;
        var out = document.getElementById('convCalculatedOutput');
        if (isNaN(amt) || amt <= 0) { out.innerText = "0.00 " + tgt; return; }
        var matrixRates = { "PKR": 1.0, "USD": 278.40, "EUR": 301.15, "GBP": 353.60, "AED": 75.80 };
        var amountInPkr = amt * matrixRates[src];
        var targetValue = amountInPkr / matrixRates[tgt];
        out.innerText = targetValue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + " " + tgt;
    }

    function revealCardData(serverPin) {
        var inputPin = document.getElementById('inputPinField').value;
        if (inputPin === serverPin) {
            document.getElementById('cardMaskedView').className = 'd-none';
            document.getElementById('cardRealView').className = 'card-render p-4 h-100 d-flex flex-column justify-content-between';
        } else { alert('Security Handshake Exception: Incorrect PIN.'); }
    }

    // WebSocket Stream Controller Switcher
    let currentSocketConnection = null;
    let fallbackTickerPrice = 0;

    function alterWebSocketStream() {
        let coin = document.getElementById('cryptoStreamSelector').value;
        if (currentSocketConnection) { currentSocketConnection.close(); }
        fallbackTickerPrice = 0;
        document.getElementById('liveCryptoPrice').innerText = "Syncing Node...";
        
        currentSocketConnection = new WebSocket(`wss://stream.binance.com:9443/ws/${coin}@ticker`);
        currentSocketConnection.onmessage = (event) => {
            let dataPacket = JSON.parse(event.data);
            let activePrice = parseFloat(dataPacket.c).toFixed(2);
            let displayVal = document.getElementById('liveCryptoPrice');
            let signalText = document.getElementById('cryptoPriceChange');
            
            displayVal.innerText = "$" + activePrice;
            if(fallbackTickerPrice !== 0) {
                if(activePrice > fallbackTickerPrice) { signalText.innerHTML = "▲ Gain Node"; signalText.style.color = "#00ff88"; }
                else if(activePrice < fallbackTickerPrice) { signalText.innerHTML = "▼ Loss Node"; signalText.style.color = "#ff0055"; }
            }
            fallbackTickerPrice = activePrice;
        };
    }

    function runAssetSimulation() {
        var selector = document.getElementById('assetSelector');
        var selectedOpt = selector.options[selector.selectedIndex];
        var yieldRate = parseFloat(selectedOpt.getAttribute('data-yield'));
        var assetType = selectedOpt.getAttribute('data-type');
        var unitPrice = parseFloat(selectedOpt.getAttribute('data-price'));
        var investAmount = parseFloat(document.getElementById('simulateAmountInput').value);

        if (isNaN(investAmount) || investAmount <= 0) {
            document.getElementById('simAnnualProfit').innerText = "PKR 0.00";
            document.getElementById('simMonthlyProfit').innerText = "PKR 0.00";
            document.getElementById('simFractionOwned').innerText = "0.0000%";
            return;
        }

        var annualProfit = investAmount * (yieldRate / 100);
        var payoutRate = (assetType === 'Property') ? (annualProfit / 12) : (annualProfit / 4); 
        var fraction = (investAmount / (unitPrice * 1000)) * 100;

        document.getElementById('simAnnualProfit').innerText = "PKR " + annualProfit.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('simMonthlyProfit').innerText = "PKR " + payoutRate.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + (assetType === 'Property' ? '/mo' : '/qtr');
        document.getElementById('simFractionOwned').innerText = fraction.toFixed(4) + "%";
    }

    window.onload = function() {
        lucide.createIcons();
        
        // Asynchronously load geolocation node
        fetch('https://ipapi.co/json/')
            .then(res => res.json())
            .then(data => {
                if (data && data.city && data.country_name) {
                    document.getElementById('geoNodeText').innerText = data.city + ', ' + data.country_name;
                } else {
                    document.getElementById('geoNodeText').innerText = 'Local Deployment Sector';
                }
            })
            .catch(() => {
                document.getElementById('geoNodeText').innerText = 'Local Deployment Sector';
            });

        calculatePkrExchange();
        alterWebSocketStream();
        runAssetSimulation();
        var ctx = document.getElementById('analyticsChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: { 
                labels: ['Inflows', 'Outflows', 'Transfers'], 
                datasets: [{ data: [<?php echo floatval($chart_dep); ?>, <?php echo floatval($chart_with); ?>, <?php echo floatval($chart_trans); ?>], 
                backgroundColor: ['#ffffff', '#e0b0ff', '#7e22ce'], borderWidth: 0 }] 
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: '#ffffff', font: {family: 'Outfit', size: 11} } } }, cutout: '75%' }
        });
    };
    </script>
    <?php include 'chatbot.php'; ?>
</body>
</html>