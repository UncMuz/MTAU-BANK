<?php
include 'db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MTAU Bank - Client Operations Manual</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #ffffff; color: #1a0f24; padding: 40px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; }
        .manual-header { border-bottom: 4px solid #6b21a8; padding-bottom: 20px; margin-bottom: 40px; }
        .manual-title { color: #6b21a8; font-weight: 800; }
        .section-title { color: #6b21a8; font-weight: 700; border-left: 5px solid #a855f7; padding-left: 12px; margin-top: 40px; margin-bottom: 20px; }
        .meta-box { background: #f5f3f7; border: 1px solid #e9e3ed; border-radius: 12px; padding: 20px; margin-bottom: 30px; }
        .rule-table th { background: #6b21a8 !important; color: white !important; font-weight: 600; }
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
        <div class="alert alert-info no-print d-flex justify-content-between align-items-center shadow-sm">
            <span><strong>User Manual PDF Generator:</strong> The browser's print dialog has opened automatically. Select "Save as PDF" to export this manual.</span>
            <button onclick="window.print()" class="btn btn-primary btn-sm fw-bold">Open Print Dialog</button>
        </div>

        <div class="manual-header row align-items-end">
            <div class="col-8">
                <h1 class="manual-title mb-1">MTAU DIGITAL BANK</h1>
                <h4 class="text-secondary fw-normal">Client Operations & User System Manual</h4>
            </div>
            <div class="col-4 text-end">
                <span class="badge bg-purple px-3 py-2 text-white" style="background:#6b21a8;">Version 3.0 (Purple Glass)</span>
                <div class="small text-muted mt-2">Export Date: <?php echo date('F d, Y'); ?></div>
            </div>
        </div>

        <div class="meta-box">
            <h5 class="fw-bold mb-3">Active User Credentials Meta Node</h5>
            <div class="row small">
                <div class="col-md-6">
                    <div><strong>Registered Name:</strong> <?php echo htmlspecialchars($user['full_name']); ?></div>
                    <div><strong>National ID Number (CNIC):</strong> <?php echo htmlspecialchars($user['cnic']); ?></div>
                    <div><strong>Assigned Account IBAN:</strong> <span class="font-monospace fw-bold"><?php echo $user['account_no']; ?></span></div>
                </div>
                <div class="col-md-6">
                    <div><strong>Current Account Class:</strong> <span class="badge bg-secondary"><?php echo $user['account_class']; ?> Tier</span></div>
                    <div><strong>Loyalty Level:</strong> <span class="text-warning fw-bold"><?php echo number_format($user['loyalty_points']); ?> PTS</span></div>
                    <div><strong>Routing State:</strong> Live System Core (SSUET Nodes)</div>
                </div>
            </div>
        </div>

        <!-- Section 1 -->
        <h4 class="section-title">1. Command Center Dashboard</h4>
        <p>The **MTAU Command Center** is your primary dashboard, combining real-time liquidity analytics, physical debit card simulations, and multi-asset trackers. Key modules include:</p>
        <ul>
            <li><strong>Available Liquidity Masking:</strong> To protect privacy in public environments, click the eye icon next to your balance header. This toggles between a masked bullet view (••••••••) and your real PKR balance.</li>
            <li><strong>Global Currency Converter:</strong> Computes real-time conversion rates across 5 primary fiat assets (PKR, USD, EUR, GBP, AED) for swift remittance estimations.</li>
            <li><strong>Dynamic Websocket Ticker:</strong> Located on the dashboard sidebar, this ticker maintains active websocket connections directly to global feeds to fetch cryptocurrency exchange rates (BTC, ETH, SOL, BNB, XRP) in real time.</li>
        </ul>

        <!-- Section 2 -->
        <h4 class="section-title">2. EMV Smart Debit Card Controls</h4>
        <p>Manage your visa hardware cards directly from the <strong>Card Control Center</strong>. Standard operations include:</p>
        <table class="table table-bordered table-striped rule-table small">
            <thead>
                <tr>
                    <th>Feature Element</th>
                    <th>Functional Action</th>
                    <th>Default Limit Constraints</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Issuance Registry</strong></td>
                    <td>Initialize a physical/virtual EMV card directly from the Command Center dashboard by supplying a custom 4-digit PIN.</td>
                    <td>Student cards cost 500 PKR (Physical) or 0 PKR (Virtual). Business and Standard are 2,500 PKR (waived for balances > 80k/50k respectively).</td>
                </tr>
                <tr>
                    <td><strong>Local/Intl Routing</strong></td>
                    <td>Toggle toggle-switches to selectively enable or block international gateways and local POS merchants.</td>
                    <td>Controlled live via network routers.</td>
                </tr>
                <tr>
                    <td><strong>PIN Updates</strong></td>
                    <td>Change your card security access pin at any time using a CSRF-validated security block.</td>
                    <td>Must be exactly 4 numeric characters.</td>
                </tr>
                <tr>
                    <td><strong>Lock & Freeze</strong></td>
                    <td>Freeze your card instantly if misplaced. Blocks all transactions at simulated POS terminals.</td>
                    <td>Instantly active (updates database flags).</td>
                </tr>
            </tbody>
        </table>

        <!-- Section 3 -->
        <h4 class="section-title">3. Fund Transfer & IBFT Core</h4>
        <p>Move funds instantly using two transfer modes available in the <strong>Fund Transfer Terminal</strong>:</p>
        <ol>
            <li><strong>MTAU Internal Wire:</strong> Allows zero-fee, instant transfers to any other client registered under the MTAU database. Enter the target IBAN to authorize the wire transfer.</li>
            <li><strong>Inter-Bank Fund Transfer (IBFT):</strong> Send money externally to major banks (e.g. HBL, Alfalah, Standard Chartered). Fees are calculated dynamically based on account class: Business accounts pay 50 PKR, Standard accounts pay 15 PKR, and Student accounts enjoy fee-free transfers.</li>
            <li><strong>Beneficiary Contact Directory:</strong> Save target accounts with descriptive nicknames to bypass typing account numbers during repetitive wires. Click any beneficiary to auto-fill transfer parameters.</li>
        </ol>

        <!-- Section 4 -->
        <h4 class="section-title">4. Ecosystem Utility Bills Portal</h4>
        <p>Clear invoices across utilities, educational boards, transit networks, and lifestyle clubs. Invoices are paid using your checking balance:</p>
        <ul>
            <li><strong>Official Routing Validation:</strong> Selecting a biller displays verified external portals (e.g., official K-Electric or Water Supply Board portals) to allow side-by-side checkout verification.</li>
            <li><strong>Ecosystem Loyalty Points:</strong> Utility bill payments grant a baseline of **+25 Loyalty Points**, while booking tickets with *PIA Airline Flights* grants **+150 Loyalty Points** per purchase.</li>
        </ul>

        <!-- Section 5 -->
        <h4 class="section-title">5. Stock & Real Estate Fractional Profit-Sharing</h4>
        <p>Earn returns through fractional equity share pools on local premier dividend assets. Available allocations include:</p>
        <table class="table table-bordered table-striped rule-table small">
            <thead>
                <tr>
                    <th>Asset Profile Name</th>
                    <th>Asset Class</th>
                    <th>Annual Rental/Dividend Yield</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Clifton Residency Property Share</td>
                    <td>Real Estate Equity</td>
                    <td>9.8% expected yearly yield</td>
                </tr>
                <tr>
                    <td>Islamabad Commercial Plaza Share</td>
                    <td>Commercial Property</td>
                    <td>11.2% expected yearly yield</td>
                </tr>
                <tr>
                    <td>PSX Tech Dividend Index</td>
                    <td>Equity Mutual Fund</td>
                    <td>15.4% expected yearly yield</td>
                </tr>
                <tr>
                    <td>Energy Giants Dividend Stock</td>
                    <td>Energy Sector Shares</td>
                    <td>13.2% expected yearly yield</td>
                </tr>
            </tbody>
        </table>

        <!-- Section 6 -->
        <h4 class="section-title">6. Forex & Crypto Demo Trading Desk</h4>
        <p>Practice live-market trading on the MTAU simulated **Trading Terminal** without risking real capital:</p>
        <ul>
            <li><strong>Demo Account Activating:</strong> Open demo Forex and Crypto wallets loaded with **$10,000 USD** virtual credits each.</li>
            <li><strong>Leverage Options:</strong> Test strategies with high-leverage profiles: **100x leverage** on Forex markets and **10x leverage** on Cryptocurrency desks.</li>
            <li><strong>TradingView Chart Integration:</strong> Utilizes embedded TradingView widgets to stream live candlesticks and charting tools directly inside the dashboard.</li>
            <li><strong>Bidirectional Funding Center:</strong>
                <ul>
                    <li>Convert demo profits to checking liquidity at a fixed rate of <strong>$1 USD = 278.40 PKR</strong> via the "Withdraw Profits" console.</li>
                    <li>Fund USD demo wallets from checking reserves at the same rate via the "Fund Demo Wallet" tab.</li>
                </ul>
            </li>
        </ul>

        <!-- Section 7 -->
        <h4 class="section-title">7. Loyalty Rewards Program</h4>
        <p>Acquire loyalty points through everyday actions and redeem them for premium rewards:</p>
        <ul>
            <li><strong>Daily Login Claim:</strong> Load the dashboard daily to collect a **+1 loyalty point** reward.</li>
            <li><strong>High-Value Transaction Reward:</strong> Any transaction of **1,000 PKR** or more automatically awards **+5 loyalty points** (processed via system database triggers).</li>
            <li><strong>Monthly Account Maintenance:</strong> Maintain a balance of **80,000 PKR** or more throughout the month to claim a **+25 loyalty point bonus** on login.</li>
            <li><strong>Perk Catalog:</strong> Redeem points for PIA Cabin Upgrade vouchers, Espresso Lounge coupons, and utility bill credit cards.</li>
        </ul>

        <!-- Section 8 -->
        <h4 class="section-title">8. Customer AI Support Chatbot</h4>
        <p>The floating <strong>MTAU AI Assistant</strong> is available on all primary pages in the bottom right corner. Type questions in plain text or click quick choices to fetch active loan data, mutual fund investments, insurance coverage details, and billing schedules compiled directly from your database profile.</p>

        <hr class="my-5">
        <div class="text-center text-muted small">
            © 2026 MTAU Digital Banking Corp. All audit logs, system triggers, and simulation features are authorized for demonstration purposes.
        </div>
    </div>
</body>
</html>
