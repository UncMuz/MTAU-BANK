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

if (isset($_POST['pay_bill'])) {
    if (!isset($_POST['post_token']) || $_POST['post_token'] !== $_SESSION['txn_token']) {
        $msg = "<div class='alert alert-danger text-center glass-card text-white'>Security Validation Failed: CSRF Handshake Exception.</div>";
    } else {
        $super_category = mysqli_real_escape_string($conn, $_POST['super_category']);
        $biller_name = mysqli_real_escape_string($conn, $_POST['biller_name']);
        $consumer_number = mysqli_real_escape_string($conn, $_POST['consumer_number']);
        $amount = floatval($_POST['amount']);

        if ($amount <= 0) { 
            $msg = "<div class='alert alert-danger text-center glass-card text-white'>Provide valid metrics parameters.</div>"; 
        } else if ($user['balance'] < $amount) { 
            $msg = "<div class='alert alert-danger text-center glass-card text-white'>Deduction Rejection: Insufficient line balances.</div>"; 
        } else {
            // High-Yield flight booking points vs regular utility points rule mapping configuration
            $points_bonus = ($biller_name === 'PIA Airline Flights') ? 150 : 25;

            if (mysqli_query($conn, "UPDATE users SET balance = balance - $amount, loyalty_points = loyalty_points + $points_bonus WHERE id=$user_id")) {
                mysqli_query($conn, "INSERT INTO bill_payments (user_id, super_category, biller_name, consumer_number, amount, points_earned) VALUES ($user_id, '$super_category', '$biller_name', '$consumer_number', $amount, $points_bonus)");
                mysqli_query($conn, "INSERT INTO transactions (account_no, type, amount, description) VALUES ('".$user['account_no']."', 'withdrawal', $amount, 'Settled Bill: $biller_name (+ $points_bonus pts)')");
                mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES ($user_id, 'Payment', 'Paid $super_category dues to $biller_name matching code $consumer_number')");
                
                $msg = "<div class='alert alert-success text-center glass-card text-white fw-bold'>Invoice Settled! PKR " . number_format($amount) . " transferred. Earned " . $points_bonus . " Loyalty Points!</div>";
                unset($_SESSION['txn_token']); $_SESSION['txn_token'] = bin2hex(random_bytes(16));
                $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id"));
            } else {
                $msg = "<div class='alert alert-danger text-center glass-card text-white'>Database error processing payment.</div>";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MTAU Bank - Ecosystem Bills Portal</title>
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

    <div class="container my-5" style="max-width: 700px;">
        <?php echo $msg; ?>
        
        <div class="glass-card shadow-lg">
            <h3 class="fw-bold text-center text-white mb-2"><i data-lucide="receipt" class="inline-icon text-glow"></i> Ecosystem Bills Portal</h3>
            <p class="text-center text-white-50 small mb-4">Settle utility networks, wellness subscriptions, transit tolls, or academic dues directly.</p>

            <div class="p-3 bg-dark border border-secondary rounded font-monospace mb-4 text-center">
                Available Balance: <span class="text-glow">PKR <?php echo number_format($user['balance'], 2); ?></span> | Loyalty Points: <span class="text-warning"><?php echo number_format($user['loyalty_points']); ?> PTS</span>
            </div>

            <form action="" method="POST" class="row g-3">
                <input type="hidden" name="post_token" value="<?php echo $_SESSION['txn_token']; ?>">
                
                <div class="col-md-6">
                    <label class="form-label text-glow small">Ecosystem Category Layer</label>
                    <select name="super_category" id="superCat" class="form-select" onchange="populateBillerOptions()" required>
                        <option value="Utilities" selected>Utilities Networks</option>
                        <option value="Lifestyle/Wellness">Lifestyle/Wellness</option>
                        <option value="Transit">Transit</option>
                        <option value="Education">Education</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-glow small">Biller Corporate Node</label>
                    <select name="biller_name" id="billerNode" class="form-select" onchange="checkExternalPortalLink()" required></select>
                </div>
                
                <div class="col-12 d-none" id="externalPortalContainer">
                    <div class="portal-link-box text-center">
                        <span class="text-warning fw-bold"><i data-lucide="info" class="inline-icon text-warning"></i> Official Portal Routing Verified:</span> 
                        <a href="#" id="externalPortalUrl" target="_blank" class="text-glow font-monospace ms-1 fw-bold text-decoration-underline">Launch External Checkout Node <i data-lucide="external-link" class="inline-icon" style="width: 12px; height: 12px; margin-right: 0; vertical-align: middle;"></i></a>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label text-white-50 small">Consumer Reference / Registration Code</label>
                    <input type="text" name="consumer_number" class="form-control" placeholder="Enter bill reference number" required>
                </div>
                
                <div class="col-12">
                    <label class="form-label text-white-50 small">Invoiced Value Sum (PKR)</label>
                    <input type="number" step="0.01" name="amount" class="form-control font-monospace" placeholder="0.00" required>
                    <small class="text-white-50 d-block mt-1">Flights booking awards +150 PTS bonus, all other bills award +25 PTS loyalty points.</small>
                </div>
                
                <div class="col-12 mt-4">
                    <button type="submit" name="pay_bill" class="btn btn-gradient w-100 py-3 text-uppercase">Authorize Networks Surcharge</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    var billingMatrix = {
        "Utilities": ["Water Supply Board", "K-Electric Grid", "Sui Southern Gas Corp", "StormFiber Internet Services"],
        "Lifestyle/Wellness": ["Karachi Golf Club", "Premium Sports Club Arena", "Oxygen Fitness Gym", "Funzone Theme Park"],
        "Transit": ["PIA Airline Flights", "M-9 Toll Plaza Payment Gates"],
        "Education": ["Sir Syed University (SSUET Dues)", "SSUET Admission Entry Tests", "Regional Sector High School", "Intermediate Higher Secondary College"]
    };
    
    var externalPortals = {
        "Water Supply Board": "https://www.kwsb.gos.pk/",
        "K-Electric Grid": "https://ke.org.pk/",
        "Sui Southern Gas Corp": "https://www.ssgc.com.pk/web/",
        "StormFiber Internet Services": "https://stormfiber.com/",
        "Karachi Golf Club": "http://www.karachigolf.com.pk/",
        "Premium Sports Club Arena": "https://www.facebook.com/PinnacleFitnessPK/",
        "Oxygen Fitness Gym": "https://www.instagram.com/oxygenfitnessclub/",
        "Funzone Theme Park": "https://www.chunkymonkey.com.pk/",
        "PIA Airline Flights": "https://www.piac.com.pk/",
        "M-9 Toll Plaza Payment Gates": "https://nha.gov.pk/",
        "Sir Syed University (SSUET Dues)": "https://www.ssuet.edu.pk/",
        "SSUET Admission Entry Tests": "https://admissions.ssuet.edu.pk/",
        "Regional Sector High School": "https://www.bisekarachi.edu.pk/",
        "Intermediate Higher Secondary College": "https://biek.edu.pk/"
    };

    function populateBillerOptions() {
        var cat = document.getElementById('superCat').value;
        var nodeSelect = document.getElementById('billerNode');
        nodeSelect.innerHTML = "";
        var activeArray = billingMatrix[cat];
        for (var i = 0; i < activeArray.length; i++) {
            var opt = document.createElement('option');
            opt.value = activeArray[i]; opt.innerText = activeArray[i];
            nodeSelect.appendChild(opt);
        }
        checkExternalPortalLink();
    }

    function checkExternalPortalLink() {
        var biller = document.getElementById('billerNode').value;
        var container = document.getElementById('externalPortalContainer');
        var linkElement = document.getElementById('externalPortalUrl');
        
        if (externalPortals[biller]) {
            linkElement.href = externalPortals[biller];
            container.classList.remove("d-none");
            container.classList.add("d-block");
        } else {
            container.classList.add("d-none");
            container.classList.remove("d-block");
        }
    }
    window.onload = function() {
        lucide.createIcons();
        populateBillerOptions();
    };
    </script>
    <?php include 'chatbot.php'; ?>
</body>
</html>