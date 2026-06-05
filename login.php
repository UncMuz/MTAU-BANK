<?php
include 'db.php'; session_start(); $message = "";

if (isset($_GET['error']) && $_GET['error'] === 'frozen') {
    $message = "<div class='alert bg-danger text-center text-white small py-2 shadow'>Your account has been frozen by administration. Access denied.</div>";
}

if (isset($_POST['login'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']); $password = $_POST['password'];

    // 1. Direct Administrative Verification Routing
    if ($email === 'mtaubank@gmail.com' && $password === 'mtau123') {
        $_SESSION['user_id'] = 0; $_SESSION['user_name'] = 'MTAU Core Board Admin'; $_SESSION['account_no'] = '0000000000'; $_SESSION['role'] = 'admin';
        $_SESSION['txn_token'] = bin2hex(random_bytes(16));
        header("Location: admin_dashboard.php"); exit();
    }

    // 2. Client Profile Registry Query
    $query = "SELECT * FROM users WHERE email='$email' AND role='user'"; $result = mysqli_query($conn, $query);
    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        if (password_verify($password, $user['password'])) {
            if ($user['status'] === 'frozen') {
                $message = "<div class='alert bg-danger text-center text-white small py-2 shadow'>Your account is currently frozen. Please contact administrative support.</div>";
            } else {
                $_SESSION['user_id'] = $user['id']; $_SESSION['user_name'] = $user['full_name']; $_SESSION['account_no'] = $user['account_no']; $_SESSION['role'] = 'user';
                $_SESSION['txn_token'] = bin2hex(random_bytes(16));
                mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES (".$user['id'].", 'Login', 'Secure interface gateway authentication initialized.')");
                header("Location: dashboard.php"); exit();
            }
        } else { $message = "<div class='alert bg-danger text-center text-white small py-2 shadow'>Invalid access credentials password configuration.</div>"; }
    } else { $message = "<div class='alert bg-danger text-center text-white small py-2 shadow'>Target account routing profile not located.</div>"; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>MTAU Bank - Login Terminal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .login-card { max-width: 420px; width: 100%; }
    </style>
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="d-flex align-items-center justify-content-center">
    <div class="bank-watermark"></div>
    <div class="glass-card login-card p-4">
        <div class="text-center mb-2 fs-3 fw-bold d-flex align-items-center justify-content-center" style="color: #e0b0ff;">
            <i data-lucide="landmark" class="lucide-icon text-glow" style="width: 28px; height: 28px; margin-right: 8px;"></i> MTAU BANK
        </div>
        <h6 class="text-center text-light small mb-4 text-uppercase tracking-wider">Secure Access Terminal</h6>
        
        <?php echo $message; ?>
        
        <form action="" method="POST">
            <div class="mb-3"><label class="form-label text-white-50 small">Network Email</label><input type="email" name="email" class="form-control" required placeholder="name@mtaubank.pk"></div>
            <div class="mb-3"><label class="form-label text-white-50 small">Verification Password</label><input type="password" name="password" class="form-control" required placeholder="••••••••"></div>
            <button type="submit" name="login" class="btn btn-gradient w-100 py-2.5 mt-2">INITIALIZE SESSION ENTRY</button>
        </form>
        <p class="text-center mt-3 mb-0 small">New student or corporate track? <a href="register.php" class="text-glow fw-bold">Register Here</a></p>
    </div>
    <script>
        lucide.createIcons();
    </script>
    <script src="cooleffectslite.js"></script>
</body>
</html>