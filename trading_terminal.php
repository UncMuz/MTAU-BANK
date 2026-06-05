<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = "";

// 1. Session Status Validation (Frozen check)
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id"));
if ($user['status'] === 'frozen') {
    session_unset();
    session_destroy();
    header("Location: login.php?error=frozen");
    exit();
}

// 2. Helper to fetch real-time market rates
function fetch_realtime_price($symbol) {
    $ctx = stream_context_create(['http' => ['timeout' => 1.5]]);
    if (strpos($symbol, 'USDT') !== false) {
        // Crypto from Binance API
        $url = "https://api.binance.com/api/v3/ticker/price?symbol=" . urlencode($symbol);
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp) {
            $json = json_decode($resp, true);
            if (isset($json['price'])) return floatval($json['price']);
        }
    } else {
        // Forex with 15-second session cache to prevent blocking on ExchangeRate API
        $now = time();
        if (isset($_SESSION['forex_rates']) && isset($_SESSION['forex_rates_ts']) && ($now - $_SESSION['forex_rates_ts'] < 15)) {
            $rates = $_SESSION['forex_rates'];
        } else {
            $url = "https://open.er-api.com/v6/latest/USD";
            $resp = @file_get_contents($url, false, $ctx);
            if ($resp) {
                $json = json_decode($resp, true);
                if (isset($json['rates'])) {
                    $rates = $json['rates'];
                    $_SESSION['forex_rates'] = $rates;
                    $_SESSION['forex_rates_ts'] = $now;
                } else {
                    $rates = isset($_SESSION['forex_rates']) ? $_SESSION['forex_rates'] : null;
                }
            } else {
                $rates = isset($_SESSION['forex_rates']) ? $_SESSION['forex_rates'] : null;
            }
        }

        if ($rates) {
            if ($symbol === 'EURUSD' && isset($rates['EUR'])) return 1.0 / floatval($rates['EUR']);
            if ($symbol === 'GBPUSD' && isset($rates['GBP'])) return 1.0 / floatval($rates['GBP']);
            if ($symbol === 'USDJPY' && isset($rates['JPY'])) return floatval($rates['JPY']);
        }
    }

    // Fallback static baseline prices
    $fallbacks = [
        'BTCUSDT' => 67450.50,
        'ETHUSDT' => 3780.20,
        'SOLUSDT' => 165.40,
        'EURUSD' => 1.0850,
        'GBPUSD' => 1.2720,
        'USDJPY' => 156.40
    ];
    return isset($fallbacks[$symbol]) ? $fallbacks[$symbol] : 1.0;
}

// 3. API Price Ticker Endpoint for Live JS Updating
if (isset($_GET['get_prices'])) {
    header('Content-Type: application/json');
    $prices = [
        'BTCUSDT' => fetch_realtime_price('BTCUSDT'),
        'ETHUSDT' => fetch_realtime_price('ETHUSDT'),
        'SOLUSDT' => fetch_realtime_price('SOLUSDT'),
        'EURUSD' => fetch_realtime_price('EURUSD'),
        'GBPUSD' => fetch_realtime_price('GBPUSD'),
        'USDJPY' => fetch_realtime_price('USDJPY'),
    ];
    echo json_encode($prices);
    exit();
}

// Ensure txn token is active
if (!isset($_SESSION['txn_token'])) {
    $_SESSION['txn_token'] = bin2hex(random_bytes(16));
}

// 4. Handle Demo Account Opening Action
if (isset($_POST['open_demo_account'])) {
    if (!isset($_POST['post_token']) || $_POST['post_token'] !== $_SESSION['txn_token']) {
        $msg = "<div class='alert alert-danger text-center glass-card text-white'>Security Validation Failed: CSRF Handshake Exception.</div>";
    } else {
        $asset_class = mysqli_real_escape_string($conn, $_POST['asset_class']);
        if ($asset_class === 'forex' || $asset_class === 'crypto') {
            $check = mysqli_query($conn, "SELECT id FROM trading_accounts WHERE user_id=$user_id AND asset_class='$asset_class'");
            if (mysqli_num_rows($check) == 0) {
                if (mysqli_query($conn, "INSERT INTO trading_accounts (user_id, asset_class, balance) VALUES ($user_id, '$asset_class', 10000.00)")) {
                    mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES ($user_id, 'Trading Account', 'Opened demo trading account for $asset_class with $10,000 credit.')");
                    $msg = "<div class='alert alert-success text-center glass-card text-white fw-bold'>Demo Trading Account activated! $10,000 USD virtual credit has been assigned.</div>";
                }
            }
        }
    }
}

// 5. Handle Opening a Trade Position
if (isset($_POST['open_trade'])) {
    if (!isset($_POST['post_token']) || $_POST['post_token'] !== $_SESSION['txn_token']) {
        $msg = "<div class='alert alert-danger text-center glass-card text-white'>Security Validation Failed: CSRF Handshake Exception.</div>";
    } else {
        $asset_class = mysqli_real_escape_string($conn, $_POST['asset_class']);
        $symbol = mysqli_real_escape_string($conn, $_POST['symbol']);
        $type = mysqli_real_escape_string($conn, $_POST['trade_type']); // buy / sell
        $size = floatval($_POST['size']);

        // Fetch trading account details
        $acc_q = mysqli_query($conn, "SELECT * FROM trading_accounts WHERE user_id=$user_id AND asset_class='$asset_class'");
        if (mysqli_num_rows($acc_q) > 0 && $size > 0 && ($type === 'buy' || $type === 'sell')) {
            $acc = mysqli_fetch_assoc($acc_q);
            $account_id = $acc['id'];
            $entry_price = fetch_realtime_price($symbol);

            // Compute margin requirements based on simulated leverage
            // Forex has 100x leverage, Crypto has 10x leverage
            $leverage = ($asset_class === 'forex') ? 100 : 10;
            $nominal_value = $size * $entry_price;
            
            // Adjust USDJPY nominal calculation (USDJPY quote nominal is in JPY)
            if ($symbol === 'USDJPY') {
                $nominal_value = $size; // size represents USD quantity for USDJPY conversion ease
            }
            
            $required_margin = $nominal_value / $leverage;

            if ($acc['balance'] >= $required_margin) {
                // Open trade
                $trade_query = "INSERT INTO trading_positions (user_id, account_id, asset_symbol, type, entry_price, size, status) 
                                VALUES ($user_id, $account_id, '$symbol', '$type', $entry_price, $size, 'open')";
                if (mysqli_query($conn, $trade_query)) {
                    // Logs activity
                    mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES ($user_id, 'Open Position', 'Opened demo $type trade on $symbol (Size: $size at $entry_price)')");
                    $msg = "<div class='alert alert-success text-center glass-card text-white fw-bold'>Trade Executed successfully! Position opened.</div>";
                    unset($_SESSION['txn_token']); $_SESSION['txn_token'] = bin2hex(random_bytes(16));
                } else {
                    $msg = "<div class='alert alert-danger text-center glass-card text-white'>Database error opening position.</div>";
                }
            } else {
                $msg = "<div class='alert alert-danger text-center glass-card text-white'>Trade Rejected: Insufficient free margin balance. Required Margin: $" . number_format($required_margin, 2) . "</div>";
            }
        }
    }
}

// 6. Handle Closing an Active Trade Position
if (isset($_POST['close_trade'])) {
    if (!isset($_POST['post_token']) || $_POST['post_token'] !== $_SESSION['txn_token']) {
        $msg = "<div class='alert alert-danger text-center glass-card text-white'>Security Validation Failed: CSRF Handshake Exception.</div>";
    } else {
        $position_id = intval($_POST['position_id']);
        $pos_q = mysqli_query($conn, "SELECT tp.*, ta.asset_class, ta.balance FROM trading_positions tp JOIN trading_accounts ta ON tp.account_id=ta.id WHERE tp.id=$position_id AND tp.user_id=$user_id AND tp.status='open'");
        
        if (mysqli_num_rows($pos_q) > 0) {
            $pos = mysqli_fetch_assoc($pos_q);
            $account_id = $pos['account_id'];
            $close_price = fetch_realtime_price($pos['asset_symbol']);
            
            // Calculate Profit and Loss
            $pnl = 0.00;
            if ($pos['type'] === 'buy') {
                $pnl = ($close_price - $pos['entry_price']) * $pos['size'];
            } else {
                $pnl = ($pos['entry_price'] - $close_price) * $pos['size'];
            }

            // Adjust USDJPY quote currency structure to USD base
            if ($pos['asset_symbol'] === 'USDJPY') {
                $pnl_jpy = ($pos['type'] === 'buy') ? ($close_price - $pos['entry_price']) * $pos['size'] : ($pos['entry_price'] - $close_price) * $pos['size'];
                $pnl = $pnl_jpy / $close_price;
            }

            // Update user balance and close position
            mysqli_query($conn, "UPDATE trading_accounts SET balance = balance + $pnl WHERE id=$account_id");
            mysqli_query($conn, "UPDATE trading_positions SET status='closed', profit_loss=$pnl, close_price=$close_price, closed_at=CURRENT_TIMESTAMP WHERE id=$position_id");
            
            mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES ($user_id, 'Close Position', 'Closed demo trade on " . $pos['asset_symbol'] . " (PnL: $" . number_format($pnl, 2) . ")')");
            
            $msg = "<div class='alert alert-info text-center glass-card text-white fw-bold'>Position closed successfully! Realized PnL: " . ($pnl >= 0 ? "+" : "") . "$" . number_format($pnl, 2) . "</div>";
            unset($_SESSION['txn_token']); $_SESSION['txn_token'] = bin2hex(random_bytes(16));
        }
    }
}

// 7. Handle Transferring Demo Funds to main Bank Account (Current Account)
if (isset($_POST['transfer_to_main'])) {
    if (!isset($_POST['post_token']) || $_POST['post_token'] !== $_SESSION['txn_token']) {
        $msg = "<div class='alert alert-danger text-center glass-card text-white'>Security Validation Failed: CSRF Handshake Exception.</div>";
    } else {
        $source_class = mysqli_real_escape_string($conn, $_POST['source_class']);
        $amount = floatval($_POST['amount']);

        if ($amount > 0 && ($source_class === 'forex' || $source_class === 'crypto')) {
            $acc_q = mysqli_query($conn, "SELECT * FROM trading_accounts WHERE user_id=$user_id AND asset_class='$source_class'");
            if (mysqli_num_rows($acc_q) > 0) {
                $acc = mysqli_fetch_assoc($acc_q);
                if ($acc['balance'] >= $amount) {
                    $pkr_credit = $amount * 278.40;
                    
                    // Deduct from demo account
                    mysqli_query($conn, "UPDATE trading_accounts SET balance = balance - $amount WHERE id=" . $acc['id']);
                    // Add to main user balance
                    mysqli_query($conn, "UPDATE users SET balance = balance + $pkr_credit WHERE id=$user_id");
                    
                    // Log transactions and logs
                    $account_no = $user['account_no'];
                    mysqli_query($conn, "INSERT INTO transactions (account_no, type, amount, description) VALUES ('$account_no', 'deposit', $pkr_credit, 'Simulated Trading Profit Payout')");
                    mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES ($user_id, 'Trading Payout', 'Transferred $amount USD from $source_class demo desk to Current Account (Rs. " . number_format($pkr_credit, 2) . ")')");
                    
                    $msg = "<div class='alert alert-success text-center glass-card text-white fw-bold'>Transfer Successful! Credited Rs. " . number_format($pkr_credit, 2) . " into your main Bank Account.</div>";
                    unset($_SESSION['txn_token']); $_SESSION['txn_token'] = bin2hex(random_bytes(16));
                    
                    // Refresh data
                    $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id"));
                } else {
                    $msg = "<div class='alert alert-danger text-center glass-card text-white'>Transfer Aborted: Insufficient trading demo balance.</div>";
                }
            } else {
                $msg = "<div class='alert alert-danger text-center glass-card text-white'>Transfer Aborted: No active " . ucfirst($source_class) . " Demo Desk found.</div>";
            }
        } else {
            $msg = "<div class='alert alert-danger text-center glass-card text-white'>Transfer Aborted: Enter a valid USD amount greater than 0.</div>";
        }
    }
}

// 8. Handle Funding Demo Wallet from main Bank Account (Current Account)
if (isset($_POST['fund_demo_wallet'])) {
    if (!isset($_POST['post_token']) || $_POST['post_token'] !== $_SESSION['txn_token']) {
        $msg = "<div class='alert alert-danger text-center glass-card text-white'>Security Validation Failed: CSRF Handshake Exception.</div>";
    } else {
        $target_class = mysqli_real_escape_string($conn, $_POST['target_class']);
        $pkr_amount = floatval($_POST['pkr_amount']);

        if ($pkr_amount > 0 && ($target_class === 'forex' || $target_class === 'crypto')) {
            $acc_q = mysqli_query($conn, "SELECT * FROM trading_accounts WHERE user_id=$user_id AND asset_class='$target_class'");
            if (mysqli_num_rows($acc_q) > 0) {
                $acc = mysqli_fetch_assoc($acc_q);
                if ($user['balance'] >= $pkr_amount) {
                    $usd_credit = $pkr_amount / 278.40;
                    
                    // Deduct from main balance
                    mysqli_query($conn, "UPDATE users SET balance = balance - $pkr_amount WHERE id=$user_id");
                    // Add to demo account balance
                    mysqli_query($conn, "UPDATE trading_accounts SET balance = balance + $usd_credit WHERE id=" . $acc['id']);
                    
                    // Log transactions and logs
                    $account_no = $user['account_no'];
                    mysqli_query($conn, "INSERT INTO transactions (account_no, type, amount, description) VALUES ('$account_no', 'withdrawal', $pkr_amount, 'Funding " . ucfirst($target_class) . " Demo Desk')");
                    mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES ($user_id, 'Wallet Funding', 'Transferred Rs. " . number_format($pkr_amount, 2) . " from Current Account to fund $target_class demo desk (+$" . number_format($usd_credit, 2) . " USD)')");
                    
                    $msg = "<div class='alert alert-success text-center glass-card text-white fw-bold'>Funding Successful! Credited $" . number_format($usd_credit, 2) . " USD into your " . ucfirst($target_class) . " Demo Desk.</div>";
                    unset($_SESSION['txn_token']); $_SESSION['txn_token'] = bin2hex(random_bytes(16));
                    
                    // Refresh data
                    $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id"));
                } else {
                    $msg = "<div class='alert alert-danger text-center glass-card text-white'>Funding Aborted: Insufficient Available Liquidity.</div>";
                }
            } else {
                $msg = "<div class='alert alert-danger text-center glass-card text-white'>Funding Aborted: Please activate the " . ucfirst($target_class) . " Demo Desk first.</div>";
            }
        } else {
            $msg = "<div class='alert alert-danger text-center glass-card text-white'>Funding Aborted: Enter a valid PKR amount greater than 0 and select an active desk.</div>";
        }
    }
}

// Fetch trading details
$forex_acc = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM trading_accounts WHERE user_id=$user_id AND asset_class='forex'"));
$crypto_acc = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM trading_accounts WHERE user_id=$user_id AND asset_class='crypto'"));

$open_positions = mysqli_query($conn, "SELECT tp.*, ta.asset_class FROM trading_positions tp JOIN trading_accounts ta ON tp.account_id=ta.id WHERE tp.user_id=$user_id AND tp.status='open' ORDER BY tp.id DESC");
$closed_positions = mysqli_query($conn, "SELECT tp.*, ta.asset_class FROM trading_positions tp JOIN trading_accounts ta ON tp.account_id=ta.id WHERE tp.user_id=$user_id AND tp.status='closed' ORDER BY tp.closed_at DESC LIMIT 10");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MTAU Bank - Global Trading Desk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="bank-watermark"></div>

    <nav class="navbar navbar-dark sticky-top px-4 py-3 shadow">
        <a class="navbar-brand text-white fw-bold d-flex align-items-center" href="dashboard.php">
            <i data-lucide="landmark" class="lucide-icon text-glow" style="width:24px; height:24px;"></i>
            MTAU BANK
        </a>
        <span class="navbar-text text-white">
            <a href="dashboard.php" class="btn btn-sm btn-outline-light fw-bold" style="border-radius: 8px;">
                <i data-lucide="arrow-left" class="lucide-icon" style="width:14px; height:14px;"></i> Back to Hub
            </a>
        </span>
    </nav>

    <div class="container my-5">
        <?php echo $msg; ?>

        <!-- Balances Grid -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="glass-card mb-0 text-center">
                    <h5 class="text-white-50 text-uppercase tracking-wider small">
                        <i data-lucide="trending-up" class="lucide-icon"></i> Crypto Demo Trading
                    </h5>
                    <?php if ($crypto_acc): ?>
                        <h2 class="font-monospace text-glow fw-bold mt-2" id="cryptoDemoBalance">
                            $<?php echo number_format($crypto_acc['balance'], 2); ?> <span class="fs-6 text-white-50">USD</span>
                        </h2>
                        <small class="text-white-50 d-block mt-1">Simulated 10x Leverage Available</small>
                    <?php else: ?>
                        <form action="" method="POST" class="mt-3">
                            <input type="hidden" name="post_token" value="<?php echo $_SESSION['txn_token']; ?>">
                            <input type="hidden" name="asset_class" value="crypto">
                            <button type="submit" name="open_demo_account" class="btn btn-gradient px-4 py-2">
                                <i data-lucide="power" class="lucide-icon"></i> Open Crypto Demo Desk
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-6">
                <div class="glass-card mb-0 text-center">
                    <h5 class="text-white-50 text-uppercase tracking-wider small">
                        <i data-lucide="coins" class="lucide-icon"></i> Forex Demo Trading
                    </h5>
                    <?php if ($forex_acc): ?>
                        <h2 class="font-monospace text-glow fw-bold mt-2" id="forexDemoBalance">
                            $<?php echo number_format($forex_acc['balance'], 2); ?> <span class="fs-6 text-white-50">USD</span>
                        </h2>
                        <small class="text-white-50 d-block mt-1">Simulated 100x Leverage Available</small>
                    <?php else: ?>
                        <form action="" method="POST" class="mt-3">
                            <input type="hidden" name="post_token" value="<?php echo $_SESSION['txn_token']; ?>">
                            <input type="hidden" name="asset_class" value="forex">
                            <button type="submit" name="open_demo_account" class="btn btn-gradient px-4 py-2">
                                <i data-lucide="power" class="lucide-icon"></i> Open Forex Demo Desk
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Side: Interactive Chart & History -->
            <div class="col-lg-8">
                <!-- Market Toggle Tabs -->
                <div class="d-flex nav-tabs-custom mb-3">
                    <button class="nav-link active" id="tabCrypto" onclick="switchMarket('crypto')">
                        <i data-lucide="bitcoin" class="lucide-icon"></i> Crypto Markets
                    </button>
                    <button class="nav-link" id="tabForex" onclick="switchMarket('forex')">
                        <i data-lucide="dollar-sign" class="lucide-icon"></i> Forex Markets
                    </button>
                </div>

                <!-- TradingView Embed Widget -->
                <div class="glass-card" style="padding: 10px;">
                    <div class="tradingview-widget-container" style="height: 480px; width: 100%;">
                        <div id="tradingview_chart" style="height: 100%;"></div>
                    </div>
                </div>

                <!-- Open Positions -->
                <div class="glass-card">
                    <h5 class="fw-bold text-white border-bottom border-secondary pb-2 mb-3">
                        <i data-lucide="list-collapse" class="lucide-icon"></i> Open Positions
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-dark table-striped align-middle mb-0 table-custom">
                            <thead>
                                <tr>
                                    <th>Asset</th>
                                    <th>Type</th>
                                    <th>Volume (Size)</th>
                                    <th>Entry Price</th>
                                    <th>Live Price</th>
                                    <th>Floating P&L</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(mysqli_num_rows($open_positions) == 0): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-white-50 py-3">No active positions open.</td>
                                    </tr>
                                <?php endif; while($pos = mysqli_fetch_assoc($open_positions)): ?>
                                    <tr id="pos-row-<?php echo $pos['id']; ?>" class="pos-row" 
                                        data-symbol="<?php echo $pos['asset_symbol']; ?>"
                                        data-entry="<?php echo $pos['entry_price']; ?>"
                                        data-size="<?php echo $pos['size']; ?>"
                                        data-type="<?php echo $pos['type']; ?>">
                                        
                                        <td class="fw-bold">
                                            <i data-lucide="<?php echo ($pos['asset_class'] === 'crypto') ? 'bitcoin' : 'dollar-sign'; ?>" class="lucide-icon text-glow"></i>
                                            <?php echo htmlspecialchars($pos['asset_symbol']); ?>
                                        </td>
                                        <td>
                                            <span class="market-badge bg-<?php echo ($pos['type'] === 'buy') ? 'success' : 'danger'; ?>">
                                                <?php echo $pos['type']; ?>
                                            </span>
                                        </td>
                                        <td class="font-monospace"><?php echo $pos['size']; ?></td>
                                        <td class="font-monospace">$<?php echo number_format($pos['entry_price'], $pos['asset_class'] === 'forex' ? 4 : 2); ?></td>
                                        <td class="font-monospace live-price-col">Syncing...</td>
                                        <td class="font-monospace live-pnl-col fw-bold">Syncing...</td>
                                        <td class="text-center">
                                            <form action="" method="POST" style="margin:0;">
                                                <input type="hidden" name="post_token" value="<?php echo $_SESSION['txn_token']; ?>">
                                                <input type="hidden" name="position_id" value="<?php echo $pos['id']; ?>">
                                                <button type="submit" name="close_trade" class="btn btn-sm btn-danger px-3 py-1">Close</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- History Trades -->
                <div class="glass-card">
                    <h5 class="fw-bold text-white border-bottom border-secondary pb-2 mb-3">
                        <i data-lucide="history" class="lucide-icon"></i> Trade History (Last 10)
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-dark table-striped align-middle mb-0 table-custom">
                            <thead>
                                <tr>
                                    <th>Asset</th>
                                    <th>Type</th>
                                    <th>Size</th>
                                    <th>Entry Price</th>
                                    <th>Close Price</th>
                                    <th>Closed At</th>
                                    <th>Realized P&L</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(mysqli_num_rows($closed_positions) == 0): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-white-50 py-3">No trade history recorded yet.</td>
                                    </tr>
                                <?php endif; while($cpos = mysqli_fetch_assoc($closed_positions)): ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo htmlspecialchars($cpos['asset_symbol']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo ($cpos['type'] === 'buy') ? 'success' : 'danger'; ?> text-uppercase">
                                                <?php echo $cpos['type']; ?>
                                            </span>
                                        </td>
                                        <td class="font-monospace"><?php echo $cpos['size']; ?></td>
                                        <td class="font-monospace">$<?php echo number_format($cpos['entry_price'], $cpos['asset_class'] === 'forex' ? 4 : 2); ?></td>
                                        <td class="font-monospace">$<?php echo number_format($cpos['close_price'], $cpos['asset_class'] === 'forex' ? 4 : 2); ?></td>
                                        <td class="text-white-50 font-monospace small"><?php echo $cpos['closed_at']; ?></td>
                                        <td class="font-monospace fw-bold <?php echo ($cpos['profit_loss'] >= 0) ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo ($cpos['profit_loss'] >= 0) ? '+' : ''; ?>$<?php echo number_format($cpos['profit_loss'], 2); ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Side: Order Entry Panel -->
            <div class="col-lg-4">
                <div class="glass-card shadow-lg">
                    <h5 class="fw-bold text-white mb-3 border-bottom border-secondary pb-2">
                        <i data-lucide="plus-circle" class="lucide-icon"></i> Place New Order
                    </h5>

                    <form action="" method="POST" id="orderForm">
                        <input type="hidden" name="post_token" value="<?php echo $_SESSION['txn_token']; ?>">
                        <input type="hidden" name="asset_class" id="orderFormAssetClass" value="crypto">
                        <input type="hidden" name="open_trade" value="1">

                        <!-- Symbol Selector -->
                        <div class="mb-3">
                            <label class="form-label text-glow small">Select Symbol</label>
                            <select name="symbol" id="symbolSelect" class="form-select font-monospace" onchange="alterTradingSymbol()">
                                <!-- Dynamic Options loaded via JS -->
                            </select>
                        </div>

                        <!-- Trade Direction -->
                        <div class="mb-3">
                            <label class="form-label text-white-50 small">Trade Direction</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="trade_type" id="dirBuy" value="buy" checked onchange="updateEstimatedCost()">
                                    <label class="btn btn-buy w-100 py-2.5" for="dirBuy">
                                        <i data-lucide="trending-up" class="lucide-icon"></i> Buy / Long
                                    </label>
                                </div>
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="trade_type" id="dirSell" value="sell" onchange="updateEstimatedCost()">
                                    <label class="btn btn-sell w-100 py-2.5" for="dirSell">
                                        <i data-lucide="trending-down" class="lucide-icon"></i> Sell / Short
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Lot/Volume Size -->
                        <div class="mb-3">
                            <label class="form-label text-white-50 small" id="sizeLabel">Volume Size (BTC)</label>
                            <input type="number" step="0.0001" min="0.0001" id="orderSize" name="size" class="form-control font-monospace" value="0.1" oninput="updateEstimatedCost()" required>
                        </div>

                        <!-- Details Panel -->
                        <div class="p-3 bg-dark border border-secondary rounded font-monospace mb-4">
                            <div class="d-flex justify-content-between small text-white-50 mb-1">
                                <span>Estimated Price:</span>
                                <span class="text-white" id="estPrice">Syncing...</span>
                            </div>
                            <div class="d-flex justify-content-between small text-white-50 mb-1">
                                <span>Simulated Leverage:</span>
                                <span class="text-white" id="estLeverage">10x</span>
                            </div>
                            <hr class="border-secondary my-2">
                            <div class="d-flex justify-content-between small font-weight-bold">
                                <span class="text-glow">Required Margin:</span>
                                <span class="text-glow" id="estMargin">$0.00</span>
                            </div>
                        </div>

                        <!-- Execution Button -->
                        <button type="submit" id="executeOrderBtn" class="btn btn-gradient w-100 py-3 text-uppercase">
                            Execute Demo Order
                        </button>
                    </form>
                </div>

                <!-- Asset Remittance & Funding Center -->
                <div class="glass-card shadow-lg mt-4">
                    <h5 class="fw-bold text-white mb-3 border-bottom border-secondary pb-2">
                        <i data-lucide="wallet-cards" class="lucide-icon text-glow"></i> Asset Remittance & Funding Center
                    </h5>
                    
                    <ul class="nav nav-pills nav-justified mb-3" id="transferTab" role="tablist" style="background: rgba(0,0,0,0.3); padding: 5px; border-radius: 12px; border: 1px solid rgba(224,176,255,0.15);">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-bold" id="payout-tab" data-bs-toggle="pill" data-bs-target="#payout-panel" type="button" role="tab">Withdraw Profits</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold" id="fund-tab" data-bs-toggle="pill" data-bs-target="#fund-panel" type="button" role="tab">Fund Demo Wallet</button>
                        </li>
                    </ul>

                    <div class="tab-content" id="transferTabContent">
                        <!-- Tab 1: Payout (Withdraw Profit) -->
                        <div class="tab-pane fade show active" id="payout-panel" role="tabpanel">
                            <form action="" method="POST">
                                <input type="hidden" name="post_token" value="<?php echo $_SESSION['txn_token']; ?>">
                                <input type="hidden" name="transfer_to_main" value="1">
                                
                                <div class="mb-3">
                                    <label class="form-label text-white-50 small">Source Demo Account</label>
                                    <select name="source_class" id="payoutSource" class="form-select font-monospace" onchange="updatePayoutConversion()">
                                        <option value="crypto" <?php echo !$crypto_acc ? 'disabled' : ''; ?>>Crypto Demo Desk ($<?php echo $crypto_acc ? number_format($crypto_acc['balance'], 2) : '0.00'; ?>)</option>
                                        <option value="forex" <?php echo !$forex_acc ? 'disabled' : ''; ?>>Forex Demo Desk ($<?php echo $forex_acc ? number_format($forex_acc['balance'], 2) : '0.00'; ?>)</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label text-white-50 small">Amount to Transfer (USD)</label>
                                    <input type="number" step="0.01" min="0.01" id="payoutAmount" name="amount" class="form-control font-monospace" placeholder="0.00" oninput="updatePayoutConversion()" required>
                                </div>

                                <div class="p-3 bg-dark border border-secondary rounded font-monospace mb-4">
                                    <div class="d-flex justify-content-between small text-white-50">
                                        <span>Exchange Rate:</span>
                                        <span class="text-white">$1 USD = 278.40 PKR</span>
                                    </div>
                                    <hr class="border-secondary my-2">
                                    <div class="d-flex justify-content-between small font-weight-bold">
                                        <span class="text-glow">PKR Credit Value:</span>
                                        <span class="text-glow" id="payoutPkrCredit">0.00 PKR</span>
                                    </div>
                                </div>

                                <button type="submit" name="transfer_to_main" class="btn btn-gradient w-100 py-3 text-uppercase">
                                    Transfer to Current Account
                                </button>
                            </form>
                        </div>
                        
                        <!-- Tab 2: Funding (Add Funds) -->
                        <div class="tab-pane fade" id="fund-panel" role="tabpanel">
                            <form action="" method="POST">
                                <input type="hidden" name="post_token" value="<?php echo $_SESSION['txn_token']; ?>">
                                <input type="hidden" name="fund_demo_wallet" value="1">
                                
                                <div class="mb-3">
                                    <label class="form-label text-white-50 small">Destination Demo Account</label>
                                    <select name="target_class" id="fundTarget" class="form-select font-monospace" onchange="updateFundingConversion()">
                                        <option value="crypto" <?php echo !$crypto_acc ? 'disabled' : ''; ?>>Crypto Demo Desk</option>
                                        <option value="forex" <?php echo !$forex_acc ? 'disabled' : ''; ?>>Forex Demo Desk</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label text-white-50 small">Amount to Transfer (PKR)</label>
                                    <input type="number" step="1" min="1" id="fundAmount" name="pkr_amount" class="form-control font-monospace" placeholder="0" oninput="updateFundingConversion()" required>
                                    <div class="form-text text-white-50" style="font-size: 11px;">Current checking balance: PKR <?php echo number_format($user['balance'], 2); ?></div>
                                </div>

                                <div class="p-3 bg-dark border border-secondary rounded font-monospace mb-4">
                                    <div class="d-flex justify-content-between small text-white-50">
                                        <span>Exchange Rate:</span>
                                        <span class="text-white">$1 USD = 278.40 PKR</span>
                                    </div>
                                    <hr class="border-secondary my-2">
                                    <div class="d-flex justify-content-between small font-weight-bold">
                                        <span class="text-glow">USD Value to Credit:</span>
                                        <span class="text-glow" id="fundUsdCredit">$0.00 USD</span>
                                    </div>
                                </div>

                                <button type="submit" name="fund_demo_wallet" class="btn btn-gradient w-100 py-3 text-uppercase">
                                    Transfer to Demo Desk
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Script Block for Charting & Live Prices -->
    <script type="text/javascript" src="https://s3.tradingview.com/tv.js"></script>
    <script>
        const symbolsMap = {
            crypto: [
                { symbol: 'BTCUSDT', name: 'Bitcoin / Tether (BTC)', tvSymbol: 'BINANCE:BTCUSDT', defaultSize: 0.1, step: 0.001 },
                { symbol: 'ETHUSDT', name: 'Ethereum / Tether (ETH)', tvSymbol: 'BINANCE:ETHUSDT', defaultSize: 1, step: 0.01 },
                { symbol: 'SOLUSDT', name: 'Solana / Tether (SOL)', tvSymbol: 'BINANCE:SOLUSDT', defaultSize: 10, step: 0.1 }
            ],
            forex: [
                { symbol: 'EURUSD', name: 'Euro / US Dollar (EURUSD)', tvSymbol: 'FX:EURUSD', defaultSize: 10000, step: 1000 },
                { symbol: 'GBPUSD', name: 'British Pound / US Dollar (GBPUSD)', tvSymbol: 'FX:GBPUSD', defaultSize: 10000, step: 1000 },
                { symbol: 'USDJPY', name: 'US Dollar / Japanese Yen (USDJPY)', tvSymbol: 'FX:USDJPY', defaultSize: 10000, step: 1000 }
            ]
        };

        let activeMarket = 'crypto';
        let tvWidget = null;
        let cachedPrices = {};

        function loadTradingViewChart(symbol) {
            tvWidget = new TradingView.widget({
                "autosize": true,
                "symbol": symbol,
                "interval": "60",
                "timezone": "Etc/UTC",
                "theme": "dark",
                "style": "1",
                "locale": "en",
                "enable_publishing": false,
                "hide_side_toolbar": false,
                "allow_symbol_change": true,
                "container_id": "tradingview_chart"
            });
        }

        function switchMarket(marketClass) {
            activeMarket = marketClass;
            
            // Toggle Tab button highlight styles
            document.getElementById('tabCrypto').classList.toggle('active', marketClass === 'crypto');
            document.getElementById('tabForex').classList.toggle('active', marketClass === 'forex');
            
            // Set order class
            document.getElementById('orderFormAssetClass').value = marketClass;

            // Load selector options
            const selectEl = document.getElementById('symbolSelect');
            selectEl.innerHTML = '';
            symbolsMap[marketClass].forEach(item => {
                let opt = document.createElement('option');
                opt.value = item.symbol;
                opt.innerText = item.name;
                selectEl.appendChild(opt);
            });

            // Adjust input size step & defaults
            const item = symbolsMap[marketClass][0];
            const sizeInput = document.getElementById('orderSize');
            sizeInput.step = item.step;
            sizeInput.value = item.defaultSize;
            
            document.getElementById('sizeLabel').innerText = (marketClass === 'crypto') ? `Volume Size (${item.symbol.replace('USDT', '')})` : `Volume Size (Base Units)`;
            document.getElementById('estLeverage').innerText = (marketClass === 'crypto') ? '10x' : '100x';

            // Check if demo account exists
            const hasAcc = (marketClass === 'crypto') ? <?php echo $crypto_acc ? 'true' : 'false'; ?> : <?php echo $forex_acc ? 'true' : 'false'; ?>;
            document.getElementById('executeOrderBtn').disabled = !hasAcc;
            if(!hasAcc) {
                document.getElementById('executeOrderBtn').innerText = "Activate Demo Desk First";
            } else {
                document.getElementById('executeOrderBtn').innerText = "Execute Demo Order";
            }

            // Alter chart
            alterTradingSymbol();
        }

        function alterTradingSymbol() {
            const sym = document.getElementById('symbolSelect').value;
            const marketArray = symbolsMap[activeMarket];
            const currentItem = marketArray.find(item => item.symbol === sym);
            
            if (currentItem) {
                loadTradingViewChart(currentItem.tvSymbol);
                document.getElementById('sizeLabel').innerText = (activeMarket === 'crypto') ? `Volume Size (${currentItem.symbol.replace('USDT', '')})` : `Volume Size (Units)`;
                updateEstimatedCost();
            }
        }

        function updateEstimatedCost() {
            const sym = document.getElementById('symbolSelect').value;
            const size = parseFloat(document.getElementById('orderSize').value) || 0;
            const livePrice = cachedPrices[sym] || 0;

            if (livePrice > 0) {
                document.getElementById('estPrice').innerText = "$" + livePrice.toLocaleString('en-US', { minimumFractionDigits: activeMarket === 'forex' ? 4 : 2 });
                const nominal = (sym === 'USDJPY') ? size : (size * livePrice);
                const leverage = (activeMarket === 'crypto') ? 10 : 100;
                const margin = nominal / leverage;
                document.getElementById('estMargin').innerText = "$" + margin.toLocaleString('en-US', { minimumFractionDigits: 2 });
            } else {
                document.getElementById('estPrice').innerText = "Syncing...";
                document.getElementById('estMargin').innerText = "$0.00";
            }
        }

        // Live prices polling function (optimized to fetch directly from APIs in browser to prevent PHP page lag)
        async function pollLivePrices() {
            try {
                // 1. Fetch Crypto from Binance directly
                const cryptoRes = await fetch('https://api.binance.com/api/v3/ticker/price?symbols=["BTCUSDT","ETHUSDT","SOLUSDT"]');
                const cryptoData = await cryptoRes.json();
                
                // 2. Fetch Forex from Open ER-API directly with localStorage caching (15 second cache window)
                let forexRates = null;
                const cachedForex = localStorage.getItem('forex_rates');
                const cachedForexTs = localStorage.getItem('forex_rates_ts');
                const now = Date.now();
                if (cachedForex && cachedForexTs && (now - parseInt(cachedForexTs) < 15000)) {
                    forexRates = JSON.parse(cachedForex);
                } else {
                    const forexRes = await fetch('https://open.er-api.com/v6/latest/USD');
                    const forexData = await forexRes.json();
                    if (forexData && forexData.rates) {
                        forexRates = forexData.rates;
                        localStorage.setItem('forex_rates', JSON.stringify(forexRates));
                        localStorage.setItem('forex_rates_ts', now.toString());
                    }
                }

                // 3. Map to cachedPrices format
                const data = {};
                if (cryptoData && Array.isArray(cryptoData)) {
                    cryptoData.forEach(item => {
                        data[item.symbol] = parseFloat(item.price);
                    });
                }
                if (forexRates) {
                    data['EURUSD'] = 1.0 / parseFloat(forexRates['EUR']);
                    data['GBPUSD'] = 1.0 / parseFloat(forexRates['GBP']);
                    data['USDJPY'] = parseFloat(forexRates['JPY']);
                }

                // Fallbacks if API is temporarily unavailable
                const fallbacks = {
                    'BTCUSDT': 67450.50,
                    'ETHUSDT': 3780.20,
                    'SOLUSDT': 165.40,
                    'EURUSD': 1.0850,
                    'GBPUSD': 1.2720,
                    'USDJPY': 156.40
                };
                for (let key in fallbacks) {
                    if (!data[key]) data[key] = fallbacks[key];
                }

                cachedPrices = data;
                
                // Update current order screen
                updateEstimatedCost();

                // Loop over active positions in table
                const rows = document.querySelectorAll('.pos-row');
                rows.forEach(row => {
                    const sym = row.getAttribute('data-symbol');
                    const entry = parseFloat(row.getAttribute('data-entry'));
                    const size = parseFloat(row.getAttribute('data-size'));
                    const type = row.getAttribute('data-type');
                    
                    const livePrice = cachedPrices[sym];
                    if (livePrice) {
                        // Update live price column
                        row.querySelector('.live-price-col').innerText = "$" + livePrice.toLocaleString('en-US', { minimumFractionDigits: sym.includes('USDT') ? 2 : 4 });

                        // Calculate floating Profit & Loss
                        let pnl = 0;
                        if (type === 'buy') {
                            pnl = (livePrice - entry) * size;
                        } else {
                            pnl = (entry - livePrice) * size;
                        }

                        // Adjustment for JPY conversion
                        if (sym === 'USDJPY') {
                            let pnl_jpy = (type === 'buy') ? (livePrice - entry) * size : (entry - livePrice) * size;
                            pnl = pnl_jpy / livePrice;
                        }

                        const pnlCol = row.querySelector('.live-pnl-col');
                        pnlCol.innerText = (pnl >= 0 ? "+" : "") + "$" + pnl.toFixed(2);
                        pnlCol.className = "font-monospace live-pnl-col fw-bold " + (pnl >= 0 ? "text-success" : "text-danger");
                    }
                });
            } catch (err) {
                console.error("Pricing sync error:", err);
            }
        }

        function updatePayoutConversion() {
            const amt = parseFloat(document.getElementById('payoutAmount').value) || 0;
            const credit = amt * 278.40;
            document.getElementById('payoutPkrCredit').innerText = credit.toLocaleString('en-US', { minimumFractionDigits: 2 }) + " PKR";
        }

        function updateFundingConversion() {
            const amt = parseFloat(document.getElementById('fundAmount').value) || 0;
            const credit = amt / 278.40;
            document.getElementById('fundUsdCredit').innerText = "$" + credit.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + " USD";
        }

        window.onload = function() {
            // Initialize lucide
            lucide.createIcons();
            // Start price syncing
            switchMarket('crypto');
            pollLivePrices();
            setInterval(pollLivePrices, 2500);
            updatePayoutConversion();
            updateFundingConversion();
        };
    </script>
    <?php include 'chatbot.php'; ?>
</body>
</html>
