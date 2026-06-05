<?php
include 'db.php'; session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_id = $_SESSION['user_id'];
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id")); 
if ($user['status'] === 'frozen') {
    session_unset(); session_destroy();
    header("Location: login.php?error=frozen"); exit();
}
$account_no = $user['account_no']; $tier_class = $user['account_class'];

// Generate matching deterministic card suffix based on user's relational IBAN
$card_suffix = substr(preg_replace('/[^0-9]/', '', $account_no), -4);

// Fetch mobile history statement streams matching real relational database records
$mobile_tx_query = mysqli_query($conn, "SELECT * FROM transactions WHERE account_no='$account_no' ORDER BY id DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MTAU Mobile - Purple Glass Edition</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap');
        
        body { 
            background: radial-gradient(circle at top left, #261338 0%, #0d0414 100%);
            color: #ffffff; display: flex; align-items: center; justify-content: center; 
            min-height: 100vh; padding: 20px; font-family: 'Outfit', sans-serif; 
        }
        
        .phone-shell { 
            width: 380px; height: 820px; background: #12071c; 
            border: 8px solid #261338; border-radius: 50px; overflow: hidden; 
            display: flex; flex-direction: column; position: relative;
            box-shadow: 0 30px 60px rgba(0,0,0,0.8), inset 0 0 20px rgba(224, 176, 255, 0.05);
        }
        
        .bank-watermark {
            position: absolute; top: 25%; left: 5%;
            width: 90%; height: 50%; opacity: 0.015; pointer-events: none; z-index: 1;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 80' fill='none' stroke='%23e0b0ff' stroke-width='0.6'><path d='M5 22h90M10 22v45M25 22v45M40 22v45M60 22v45M75 22v45M90 22v45M2 22l48-18 48 18M2 67h96M0 72h100M0 77h100M43 38h14v16H43z'/></svg>");
            background-repeat: no-repeat; background-size: contain; background-position: center;
        }

        .status-bar { height: 40px; width: 100%; padding: 0 25px; display: flex; justify-content: space-between; align-items: center; font-size: 12px; font-weight: 600; z-index: 10; opacity: 0.8; }
        
        .app-body { flex: 1; overflow-y: auto; padding: 15px 20px 30px; z-index: 2; }
        .app-body::-webkit-scrollbar { width: 4px; }
        .app-body::-webkit-scrollbar-thumb { background: #3b224c; border-radius: 10px; }
        
        .glass-panel {
            background: rgba(59, 34, 76, 0.25);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(224, 176, 255, 0.15);
            border-radius: 20px; padding: 15px; margin-bottom: 15px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
        }
        
        .balance-label { font-size: 10px; color: #c4b9d0; font-weight: 700; letter-spacing: 1px; cursor: pointer; text-transform: uppercase; }
        .balance-amount { font-size: 26px; font-weight: 800; font-family: monospace; color: #fff; margin: 4px 0; text-shadow: 0 0 20px rgba(224, 176, 255, 0.3); }
        
        .premium-card {
            background: linear-gradient(135deg, #e0b0ff 0%, #6b21a8 100%);
            border-radius: 18px; padding: 18px; color: #ffffff;
            box-shadow: 0 12px 25px rgba(107, 33, 168, 0.35);
            position: relative; overflow: hidden; margin-bottom: 20px;
        }
        
        .horizontal-actions { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .action-circle-item { text-decoration: none; text-align: center; width: 75px; }
        .icon-box { 
            width: 50px; height: 50px; background: rgba(224, 176, 255, 0.1); 
            border: 1px solid rgba(224, 176, 255, 0.2); border-radius: 18px; 
            display: flex; align-items: center; justify-content: center; 
            margin: 0 auto 6px; font-size: 20px; transition: 0.3s; 
        }
        .action-circle-item:hover .icon-box { background: #e0b0ff; color: #12071c; transform: translateY(-2px); }
        .action-label { font-size: 11px; color: #e0b0ff; font-weight: 600; }
        
        .action-btn-link { background: rgba(224, 176, 255, 0.12); color: #e0b0ff; border: 1px solid rgba(224, 176, 255, 0.2); font-weight: bold; border-radius: 12px; height: 44px; text-decoration: none; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; transition: 0.2s; }
        .action-btn-link:hover { background: #e0b0ff; color: #12071c; }
        .text-glow { color: #e0b0ff; text-shadow: 0 0 15px rgba(224, 176, 255, 0.5); font-weight: 700; }
        
        .form-select-sm {
            background-color: rgba(0,0,0,0.5);
            border: 1px solid rgba(224,176,255,0.25);
            color: #fff;
        }
        .lucide-icon { width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: 4px; }
        .horizontal-actions .lucide-icon { width: 22px; height: 22px; margin-right: 0; }
    </style>
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <div class="phone-shell">
        <div class="bank-watermark"></div>
        <div class="status-bar text-white-50 d-flex justify-content-between align-items-center"><span>19:17 PM</span><span class="d-flex align-items-center gap-1"><i data-lucide="signal" style="width: 12px; height: 12px;"></i><i data-lucide="battery" style="width: 12px; height: 12px;"></i> 100%</span></div>
        <div class="app-body">
            
            <div class="d-flex justify-content-between align-items-center mb-3 mt-1">
                <div class="d-flex align-items-center">
                    <i data-lucide="landmark" class="inline-icon text-glow fs-4 me-2" style="width: 20px; height: 20px; vertical-align: middle;"></i>
                    <div>
                        <h6 class="mb-0 fw-bold small text-white" style="font-size:12px;"><?php echo htmlspecialchars($user['full_name']); ?></h6>
                        <span class="badge bg-dark text-warning d-inline-flex align-items-center gap-1" style="font-size:8px; padding: 2px 5px;"><i data-lucide="crown" style="width: 8px; height: 8px;"></i> <?php echo $user['account_class']; ?></span>
                    </div>
                </div>
                <a href="dashboard.php" class="btn btn-sm btn-dark font-monospace" style="border-radius:10px; font-size:9px; background:#261338; border:1px solid #e0b0ff; padding: 2px 8px;">DESKTOP</a>
            </div>

            <!-- Balance Display -->
            <div class="glass-panel text-center">
                <span class="balance-label text-decoration-underline d-inline-flex align-items-center gap-1" onclick="toggleMobileMask()">Core Liquidity <i data-lucide="eye" id="mobileEyeIcon" style="width: 12px; height: 12px; vertical-align: middle;"></i></span>
                <div class="balance-amount" id="mobileBalanceField" data-real-value="PKR <?php echo number_format($user['balance'], 2); ?>">••••••••</div>
                <div class="d-flex justify-content-center gap-2 mt-2">
                    <span class="badge font-monospace small" style="background:rgba(0,0,0,0.5); color:#e0b0ff; border:1px solid rgba(224,176,255,0.3); font-size:9px;"><?php echo $account_no; ?></span>
                    <span class="badge font-monospace small d-inline-flex align-items-center gap-1" style="background:rgba(255,193,7,0.15); color:#ffc107; border:1px solid rgba(255,193,7,0.3); font-size:9px;"><i data-lucide="award" style="width: 10px; height: 10px;"></i> <?php echo $user['loyalty_points']; ?> PTS</span>
                </div>
            </div>

            <!-- Card Render -->
            <?php if($user['card_status'] === 'issued'): ?>
                <div class="premium-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small font-monospace fw-bold opacity-75" style="font-size:10px;">MTAU DIGITAL</span>
                        <span class="fw-bold d-inline-flex align-items-center" style="font-size:14px;"><?php echo ($user['card_blocked']) ? '<i data-lucide="lock" style="width: 14px; height: 14px;" class="text-danger"></i>' : '<i data-lucide="rss" style="width: 14px; height: 14px;"></i>'; ?></span>
                    </div>
                    <h5 class="font-monospace my-3 tracking-widest text-center" style="letter-spacing: 3px; font-weight: 800; font-size:16px;">
                        4532 8812 9431 <?php echo $card_suffix; ?>
                    </h5>
                    <div class="d-flex justify-content-between align-items-end text-uppercase" style="font-size: 10px;">
                        <span class="fw-bold"><?php echo htmlspecialchars($user['full_name']); ?></span>
                        <span class="fw-bold" style="font-size: 12px;"><?php echo ($user['card_blocked']) ? 'FROZEN' : 'VISA'; ?></span>
                    </div>
                </div>
            <?php else: ?>
                <div class="glass-panel text-center py-3" style="border: 1px dashed rgba(224, 176, 255, 0.4);">
                    <h6 class="text-glow mb-1 fw-bold" style="font-size:12px;"><i data-lucide="credit-card" class="inline-icon"></i> No Active Debit Layer</h6>
                    <p class="small text-white-50 mb-0" style="font-size:10px;">Deploy your card template within the desktop workspace terminal first.</p>
                </div>
            <?php endif; ?>

            <!-- QR Pay Trigger Card -->
            <div class="glass-panel text-center py-2.5 cursor-pointer" data-bs-toggle="modal" data-bs-target="#qrMobileModal">
                <span class="balance-label mb-2 d-block"><i data-lucide="qr-code" class="inline-icon"></i> QR Pay Scanner (Click to Expand)</span>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?php echo urlencode($account_no); ?>&bgcolor=12071c&color=e0b0ff" alt="QR Code" style="height: 70px; width: 70px; border-radius: 8px; border: 1px solid rgba(224,176,255,0.2); padding: 2px;">
            </div>

            <!-- WebSocket Coin Switcher Panel -->
            <div class="glass-panel text-center">
                <span class="balance-label mb-2 d-block">Live Crypto Ticker</span>
                <h4 id="liveCryptoPrice" class="text-glow font-monospace mb-0" style="font-size: 20px;">Syncing...</h4>
                <small id="cryptoPriceChange" class="fw-bold font-monospace d-block mb-2" style="font-size: 10px;">--</small>
                <select id="cryptoStreamSelector" class="form-select form-select-sm text-center font-monospace" onchange="alterWebSocketStream()" style="font-size:10px; border-radius: 8px; padding: 4px;">
                    <option value="btcusdt" selected>BTC / USDT</option>
                    <option value="ethusdt">ETH / USDT</option>
                    <option value="bnbusdt">BNB / USDT</option>
                    <option value="solusdt">SOL / USDT</option>
                    <option value="xrpusdt">XRP / USDT</option>
                </select>
            </div>

            <div class="horizontal-actions">
                <a href="transfer_terminal.php" class="action-circle-item"><div class="icon-box"><i data-lucide="send"></i></div><span class="action-label">Wire</span></a>
                <a href="bill_terminal.php" class="action-circle-item"><div class="icon-box"><i data-lucide="receipt"></i></div><span class="action-label">Bills</span></a>
                <a href="deals_terminal.php" class="action-circle-item"><div class="icon-box"><i data-lucide="gift"></i></div><span class="action-label">Perks</span></a>
                <a href="statement_pdf.php" target="_blank" class="action-circle-item"><div class="icon-box"><i data-lucide="file-text"></i></div><span class="action-label">Ledger</span></a>
            </div>

            <h6 class="fw-bold mb-2 tracking-wide text-uppercase" style="color:#e0b0ff; font-size:11px;"><i data-lucide="grid" class="inline-icon"></i> Banking Services</h6>
            <div class="row g-2 mb-3">
                <div class="col-6"><a href="loan_terminal.php" class="action-btn-link"><i data-lucide="building" class="lucide-icon"></i> Credit Desk</a></div>
                <div class="col-6"><a href="manage_card.php" class="action-btn-link"><i data-lucide="credit-card" class="lucide-icon"></i> EMV Controls</a></div>
                <div class="col-6"><a href="trading_terminal.php" class="action-btn-link"><i data-lucide="candlestick-chart" class="lucide-icon"></i> Trading Desk</a></div>
                <div class="col-6"><a href="support_terminal.php" class="action-btn-link"><i data-lucide="message-square" class="lucide-icon"></i> Helpdesk</a></div>
            </div>

            <h6 class="fw-bold mb-2 text-uppercase tracking-wider" style="color:#e0b0ff; font-size:11px;"><i data-lucide="list" class="inline-icon"></i> Recent Transactions</h6>
            <div class="glass-panel p-0 overflow-hidden mb-3" style="border-radius:14px;">
                <?php if(mysqli_num_rows($mobile_tx_query) == 0): ?><div class="text-center py-3 text-white-50 small" style="font-size:11px;">No transactions tracked yet.</div><?php endif; while($mtx = mysqli_fetch_assoc($mobile_tx_query)): ?>
                    <div class="d-flex justify-content-between align-items-center p-2.5 border-bottom" style="border-color: rgba(224,176,255,0.08) !important;">
                        <div>
                            <span class="d-block fw-bold small text-white text-capitalize" style="font-size:11px;"><?php echo $mtx['type']; ?></span>
                            <small style="font-size:9px; color:#a195ad;"><?php echo substr($mtx['created_at'], 5, 11); ?></small>
                        </div>
                        <span class="font-monospace fw-bold small <?php echo ($mtx['type'] === 'deposit') ? 'text-success' : 'text-danger'; ?>" style="font-size:11px;">
                            <?php echo ($mtx['type'] === 'deposit') ? '+' : '-'; ?> Rs. <?php echo number_format($mtx['amount']); ?>
                        </span>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <a href="logout.php" class="btn btn-outline-danger btn-sm w-100 py-2 fw-bold text-uppercase tracking-wide mt-1" style="border-radius:12px; font-size:11px;">Terminate Session</a>
        </div>
    </div>

    <!-- Mobile QR Modal -->
    <div class="modal fade" id="qrMobileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content text-center text-white" style="background: #12071c; border: 2px solid #e0b0ff; border-radius: 24px; padding: 20px;">
                <h6 class="fw-bold text-glow mb-3">MTAU Mobile QR Node</h6>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?php echo urlencode($account_no); ?>&bgcolor=12071c&color=e0b0ff" alt="QR Code" class="img-fluid mx-auto mb-3" style="border-radius:12px; border: 1px solid rgba(224,176,255,0.3); max-width: 180px;">
                <div class="font-monospace bg-dark p-2 rounded small border border-secondary mb-3" style="font-size:11px;"><?php echo $account_no; ?></div>
                <button type="button" class="btn btn-outline-light btn-sm w-100" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>

    <script>
    var mobMasked = true;
    function toggleMobileMask() {
        var el = document.getElementById('mobileBalanceField');
        var eye = document.getElementById('mobileEyeIcon');
        if(mobMasked) {
            el.innerText = el.getAttribute('data-real-value');
            eye.setAttribute('data-lucide', 'eye-off');
            mobMasked = false;
        } else {
            el.innerText = "••••••••";
            eye.setAttribute('data-lucide', 'eye');
            mobMasked = true;
        }
        lucide.createIcons();
    }

    let currentSocketConnection = null;
    let fallbackTickerPrice = 0;

    function alterWebSocketStream() {
        let coin = document.getElementById('cryptoStreamSelector').value;
        if (currentSocketConnection) { currentSocketConnection.close(); }
        fallbackTickerPrice = 0;
        document.getElementById('liveCryptoPrice').innerText = "Syncing...";
        
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

    window.onload = function() {
        lucide.createIcons();
        alterWebSocketStream();
    };
    </script>
</body>
</html>