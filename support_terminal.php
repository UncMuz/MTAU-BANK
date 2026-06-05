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

if (isset($_POST['submit_ticket'])) {
    if (!isset($_POST['post_token']) || $_POST['post_token'] !== $_SESSION['txn_token']) {
        $msg = "<div class='alert alert-danger text-center glass-card text-white'>Security Validation Failed: CSRF Handshake Exception.</div>";
    } else {
        $subject = mysqli_real_escape_string($conn, $_POST['subject']);
        $message = mysqli_real_escape_string($conn, $_POST['message']);

        if (empty($subject) || empty($message)) {
            $msg = "<div class='alert alert-danger text-center glass-card text-white'>Validation Error: Please fill in all fields.</div>";
        } else {
            if (mysqli_query($conn, "INSERT INTO support_tickets (user_id, subject, message) VALUES ($user_id, '$subject', '$message')")) {
                mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES ($user_id, 'Support Ticket', 'Filed ticket: $subject')");
                $msg = "<div class='alert alert-success text-center glass-card text-white fw-bold'>Support Ticket Submitted Successfully! Our team will review it.</div>";
                unset($_SESSION['txn_token']); $_SESSION['txn_token'] = bin2hex(random_bytes(16));
            } else {
                $msg = "<div class='alert alert-danger text-center glass-card text-white'>Database error submitting ticket.</div>";
            }
        }
    }
}

$tickets_query = mysqli_query($conn, "SELECT * FROM support_tickets WHERE user_id=$user_id ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MTAU Bank - Helpdesk Support</title>
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

    <div class="container my-5">
        <?php echo $msg; ?>
        
        <div class="row g-4">
            <div class="col-md-5">
                <div class="glass-card shadow-lg">
                    <h5 class="fw-bold text-white mb-3 border-bottom border-secondary pb-2">Submit Support Ticket</h5>
                    <form action="" method="POST">
                        <input type="hidden" name="post_token" value="<?php echo $_SESSION['txn_token']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label text-glow small">Ticket Subject</label>
                            <input type="text" name="subject" class="form-control" placeholder="e.g. Card transaction issue" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label text-white-50 small">Describe Your Issue</label>
                            <textarea name="message" class="form-control" rows="5" placeholder="Provide complete details..." required></textarea>
                        </div>

                        <button type="submit" name="submit_ticket" class="btn btn-gradient w-100 py-3 text-uppercase">Submit Ticket</button>
                    </form>
                </div>
            </div>
            
            <div class="col-md-7">
                <div class="glass-card shadow-lg h-100">
                    <h5 class="fw-bold text-white border-bottom border-secondary pb-2 mb-3">Your Support Tickets</h5>
                    <div class="table-responsive">
                        <table class="table table-dark table-borderless align-middle mb-0 table-custom">
                            <thead>
                                <tr class="text-glow border-bottom border-secondary">
                                    <th>Subject</th>
                                    <th>Description</th>
                                    <th>Date Filed</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(mysqli_num_rows($tickets_query) == 0): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-white-50 py-4">No support tickets submitted yet.</td>
                                    </tr>
                                <?php endif; while($t = mysqli_fetch_assoc($tickets_query)): ?>
                                    <tr>
                                        <td class="fw-bold text-white"><?php echo htmlspecialchars($t['subject']); ?></td>
                                        <td><?php echo htmlspecialchars($t['message']); ?></td>
                                        <td class="font-monospace small"><?php echo $t['created_at']; ?></td>
                                        <td class="text-center">
                                            <?php if($t['status'] == 'open'): ?>
                                                <span class="badge bg-warning text-dark px-2.5 py-1.5 fw-bold text-uppercase">Open</span>
                                            <?php else: ?>
                                                <span class="badge bg-success text-white px-2.5 py-1.5 fw-bold text-uppercase">Resolved</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        lucide.createIcons();
    </script>
    <?php include 'chatbot.php'; ?>
</body>
</html>
