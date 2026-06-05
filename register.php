<?php
include 'db.php'; 

// Initialize PHPMailer namespaces
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer dynamically if the folder exists
if (file_exists('PHPMailer/Exception.php')) {
    require 'PHPMailer/Exception.php'; 
    require 'PHPMailer/PHPMailer.php'; 
    require 'PHPMailer/SMTP.php';
}

$message = "";

if (isset($_GET['status']) && $_GET['status'] == 'terminated') {
    $message = "<div class='alert text-center text-white bg-info fw-bold border-0' style='border-radius:8px;'>Account deleted successfully. Your data has been removed.</div>";
}

if (isset($_POST['register'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $age = intval($_POST['age']);
    $roll_no = mysqli_real_escape_string($conn, $_POST['roll_no']);
    $account_class = $_POST['account_class'];
    $account_type = $_POST['account_type'];
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $cnic = mysqli_real_escape_string($conn, $_POST['cnic']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    
    $account_no = "PKMTAU" . rand(100000, 999999) . rand(1000, 9999);

    if ($account_class === 'Student' && ($age < 18 || $age > 25)) {
        $message = "<div class='alert text-center text-white border-0' style='background: #9d174d; border-radius:8px;'>Registration Failed: Student accounts require age between 18 and 25.</div>";
    } else {
        $duplicate_check = mysqli_query($conn, "SELECT id FROM users WHERE roll_no='$roll_no' OR email='$email' OR cnic='$cnic'");
        if (mysqli_num_rows($duplicate_check) > 0) {
            $message = "<div class='alert text-center text-white border-0' style='background: #b45309; border-radius:8px;'>Registration Failed: An account matching this Roll Number, CNIC, or Email already exists.</div>";
        } else {
            // Updated query to include the Loyalty Points default value (100)
            $query = "INSERT INTO users (roll_no, account_class, account_type, full_name, age, email, phone, cnic, password, account_no, loyalty_points, role) VALUES ('$roll_no', '$account_class', '$account_type', '$full_name', $age, '$email', '$phone', '$cnic', '$password', '$account_no', 100, 'user')";
            
            if (mysqli_query($conn, $query)) {
                
                // Email Dispatch Logic
                if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP(); 
                        $mail->Host = 'smtp.gmail.com'; 
                        $mail->SMTPAuth = true;
                        $mail->Username = 'mtaubank@gmail.com'; // Your Bank's official Gmail
                        $mail->Password = 'cqvljnzelbdqhqam'; // 16-digit Google App Password
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
                        $mail->Port = 587;

                        // Bypass local SSL verification errors on XAMPP
                        $mail->SMTPOptions = array(
                            'ssl' => array(
                                'verify_peer' => false, 
                                'verify_peer_name' => false, 
                                'allow_self_signed' => true
                            )
                        );

                        $mail->setFrom('mtaubank@gmail.com', 'MTAU Bank'); 
                        $mail->addAddress($email, $full_name); 
                        $mail->isHTML(true);
                        
                        $mail->Subject = 'Account Opened Successfully - MTAU Bank';
                        $mail->Body    = "<div style='background-color: #1e112a; padding: 30px; font-family: Arial, sans-serif; color: #ffffff; border-radius: 12px;'>
                                            <h2 style='color: #e0b0ff; text-align: center; border-bottom: 2px solid #e0b0ff; padding-bottom: 10px;'>MTAU BANK</h2>
                                            <p>Dear <b>" . htmlspecialchars($full_name) . "</b>,</p>
                                            <p>Your banking account has been successfully created.</p>
                                            <div style='background-color: #3b224c; padding: 20px; border-radius: 8px; border: 1px solid #e0b0ff;'>
                                                <b>Account Number (IBAN):</b> " . $account_no . "<br>
                                                <b>Account Type:</b> " . $account_class . " Account
                                            </div>
                                           </div>";
                        $mail->send();
                    } catch (Exception $e) {
                        // Fails silently if email doesn't send so user creation still succeeds
                    }
                }
                
                $message = "<div class='alert border-0 text-center fw-bold text-white' style='background: #15803d; border-radius:8px;'>Account Created Successfully!<br>Account Number: <span class='font-monospace'>$account_no</span><br><a href='login.php' class='text-white fw-bold d-block mt-2'>Proceed to Login →</a></div>";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>MTAU Bank - Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="d-flex align-items-center justify-content-center py-5">
    <div class="bank-watermark"></div>
    <div class="container" style="max-width: 700px;">
        <div class="text-center mb-4">
             <div class="fs-2 fw-bold mb-3 d-flex align-items-center justify-content-center" style="color: #e0b0ff;">
                 <i data-lucide="landmark" class="lucide-icon text-glow" style="width: 32px; height: 32px; margin-right: 8px;"></i> MTAU BANK
             </div>
             <h6 class="text-glow small tracking-widest">ACCOUNT REGISTRATION</h6>
         </div>
        <div class="glass-card p-5">
            <?php echo $message; ?>
            <form action="" method="POST">
                <div class="row g-3">
                    <div class="col-md-8"><label class="form-label text-glow">Full Name</label><input type="text" name="full_name" class="form-control" required></div>
                    <div class="col-md-4"><label class="form-label text-glow">Age</label><input type="number" name="age" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label text-glow">Account Class</label><select name="account_class" class="form-select" required><option value="Standard">Standard Account</option><option value="Business">Business Account</option><option value="Student">Student Account</option></select></div>
                    <div class="col-md-6"><label class="form-label text-glow">Account Type</label><select name="account_type" class="form-select" required><option value="Current">Current Account</option><option value="Saving">Savings Account</option></select></div>
                    <div class="col-md-6"><label class="form-label text-glow">University Roll Number</label><input type="text" name="roll_no" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label text-glow">CNIC Number</label><input type="text" name="cnic" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label text-glow">Email Address</label><input type="email" name="email" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label text-glow">Mobile Phone Number</label><input type="text" name="phone" class="form-control" required></div>
                    <div class="col-12"><label class="form-label text-glow">Password</label><input type="password" name="password" class="form-control" required></div>
                </div>
                <button type="submit" name="register" class="btn btn-gradient w-100 py-3 mt-4 text-uppercase">Register Account</button>
            </form>
            <p class="text-center mt-4 mb-0 small">Already have an account? <a href="login.php" class="text-glow fw-bold">Login Here →</a></p>
        </div>
    </div>
    <script>
        lucide.createIcons();
    </script>
    <script src="cooleffectslite.js"></script>
</body>
</html>