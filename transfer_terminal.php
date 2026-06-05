<?php
include 'db.php'; session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_id = $_SESSION['user_id']; $msg = "";

$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id")); 
if ($user['status'] === 'frozen') {
    session_unset(); session_destroy();
    header("Location: login.php?error=frozen"); exit();
}
$account_no = $user['account_no'];

if (!isset($_SESSION['txn_token'])) { $_SESSION['txn_token'] = bin2hex(random_bytes(16)); }

// Save Beneficiary Action
if (isset($_POST['save_beneficiary'])) {
    if (!isset($_POST['post_token']) || $_POST['post_token'] !== $_SESSION['txn_token']) {
        $msg = "<div class='alert alert-danger text-center glass-card text-white'>Security Validation Failed: CSRF Handshake Exception.</div>";
    } else {
        $nickname = mysqli_real_escape_string($conn, $_POST['nickname']);
        $b_acc = mysqli_real_escape_string($conn, $_POST['b_acc_no']);
        $b_bank = mysqli_real_escape_string($conn, $_POST['b_bank_name']);

        if (empty($nickname) || empty($b_acc)) {
            $msg = "<div class='alert alert-danger text-center glass-card text-white'>Validation Error: Please enter a valid nickname and account number.</div>";
        } else {
            if (mysqli_query($conn, "INSERT INTO beneficiaries (user_id, nickname, account_no, bank_name) VALUES ($user_id, '$nickname', '$b_acc', '$b_bank')")) {
                $msg = "<div class='alert alert-success text-center glass-card text-white'>Beneficiary saved successfully!</div>";
                unset($_SESSION['txn_token']); $_SESSION['txn_token'] = bin2hex(random_bytes(16));
            } else {
                $msg = "<div class='alert alert-danger text-center glass-card text-white'>Database error saving beneficiary.</div>";
            }
        }
    }
}

// Execute Transfer Action
if (isset($_POST['execute_transfer'])) {
    if (!isset($_POST['post_token']) || $_POST['post_token'] !== $_SESSION['txn_token']) {
        $msg = "<div class='alert alert-danger text-center glass-card text-white'>Security Validation Failed: CSRF Handshake Exception.</div>";
    } else {
        $transfer_type = $_POST['transfer_type']; 
        $amount = floatval($_POST['amount']); 
        $description = mysqli_real_escape_string($conn, $_POST['description']);

        if ($amount <= 0) {
            $msg = "<div class='alert alert-danger text-center glass-card text-white'>Validation Error: Enter a valid remittance sum.</div>";
        } else {
            if ($transfer_type === 'internal') {
                $recipient_no = mysqli_real_escape_string($conn, $_POST['recipient_account']);
                if ($recipient_no === $account_no) {
                    $msg = "<div class='alert alert-danger text-center glass-card text-white'>Loop Error: Cannot wire funds to yourself.</div>";
                } else if ($user['balance'] < $amount) {
                    $msg = "<div class='alert alert-danger text-center glass-card text-white'>Declined: Insufficient liquid reserves.</div>";
                } else {
                    $recipient_query = mysqli_query($conn, "SELECT id FROM users WHERE account_no='$recipient_no'");
                    if (mysqli_num_rows($recipient_query) === 0) {
                        $msg = "<div class='alert alert-danger text-center glass-card text-white'>Routing Error: Target IBAN not found.</div>";
                    } else {
                        mysqli_query($conn, "UPDATE users SET balance = balance - $amount WHERE id=$user_id");
                        mysqli_query($conn, "UPDATE users SET balance = balance + $amount WHERE account_no='$recipient_no'");
                        mysqli_query($conn, "INSERT INTO transactions (account_no, type, amount, recipient_account, description) VALUES ('$account_no', 'transfer', $amount, '$recipient_no', '$description')");
                        mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES ($user_id, 'Transfer', 'Wired Rs. " . number_format($amount) . " to account $recipient_no')");
                        
                        $msg = "<div class='alert alert-success text-center glass-card text-white fw-bold'>Wire Authorized! PKR " . number_format($amount) . " remitted internally.</div>";
                        unset($_SESSION['txn_token']); $_SESSION['txn_token'] = bin2hex(random_bytes(16));
                        $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id"));
                    }
                }
            } else if ($transfer_type === 'ibft') {
                $target_bank = mysqli_real_escape_string($conn, $_POST['target_bank']);
                $recipient_no = mysqli_real_escape_string($conn, $_POST['recipient_account_external']);
                
                $fee = ($user['account_class'] === 'Student') ? 0.00 : (($user['account_class'] === 'Business') ? 50.00 : 15.00);
                $total_deduction = $amount + $fee;

                if ($user['balance'] < $total_deduction) {
                    $msg = "<div class='alert alert-danger text-center glass-card text-white'>Declined: Insufficient liquid reserves to cover transfer + fee (Total: PKR " . number_format($total_deduction) . ").</div>";
                } else {
                    mysqli_query($conn, "UPDATE users SET balance = balance - $total_deduction WHERE id=$user_id");
                    
                    $tx_desc = "IBFT to $target_bank ($recipient_no) - Memo: $description (Fee: Rs. " . number_format($fee) . ")";
                    mysqli_query($conn, "INSERT INTO transactions (account_no, type, amount, recipient_account, description) VALUES ('$account_no', 'transfer', $amount, '$recipient_no', '$tx_desc')");
                    mysqli_query($conn, "INSERT INTO system_logs (user_id, activity_type, description) VALUES ($user_id, 'Transfer', 'IBFT transfer of Rs. " . number_format($amount) . " to $target_bank ($recipient_no) authorized.')");
                    
                    $msg = "<div class='alert alert-success text-center glass-card text-white fw-bold'>IBFT Remittance Dispatched! PKR " . number_format($amount) . " sent to $target_bank. Fee charged: PKR " . number_format($fee) . ".</div>";
                    unset($_SESSION['txn_token']); $_SESSION['txn_token'] = bin2hex(random_bytes(16));
                    $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id"));
                }
            }
        }
    }
}

$beneficiaries_query = mysqli_query($conn, "SELECT * FROM beneficiaries WHERE user_id=$user_id ORDER BY nickname ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MTAU Bank - Fund Transfer Console</title>
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
            <!-- Left Side: Fund Transfer Panel -->
            <div class="col-md-7">
                <div class="glass-card shadow-lg">
                    <h3 class="fw-bold text-center text-white mb-1"><i data-lucide="send" class="inline-icon text-glow"></i> Fund Transfer Terminal</h3>
                    <p class="text-center text-white-50 small mb-4">Transfer funds instantly to other MTAU Bank accounts or execute an Inter-Bank Transfer (IBFT).</p>
                    
                    <div class="p-3 bg-dark border border-secondary rounded font-monospace mb-4 text-center">
                        Available Balance: <span class="text-glow">PKR <?php echo number_format($user['balance'], 2); ?></span>
                    </div>

                    <!-- Tab Selectors -->
                    <div class="d-flex justify-content-center gap-3 mb-4">
                        <button type="button" class="tab-btn active" id="internalTab" onclick="switchTransferTab('internal')">MTAU Internal Wire</button>
                        <button type="button" class="tab-btn" id="ibftTab" onclick="switchTransferTab('ibft')">Inter-Bank (IBFT)</button>
                    </div>

                    <form action="" method="POST" id="transferForm">
                        <input type="hidden" name="post_token" value="<?php echo $_SESSION['txn_token']; ?>">
                        <input type="hidden" name="transfer_type" id="transferTypeField" value="internal">

                        <!-- Recipient Section -->
                        <div id="internalRecipientContainer">
                            <div class="mb-3">
                                <label class="form-label text-glow small">Target MTAU Recipient IBAN</label>
                                <input type="text" name="recipient_account" id="recipientAccountInternal" class="form-control font-monospace" placeholder="PKMTAU0000000000">
                            </div>
                        </div>

                        <div id="ibftRecipientContainer" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label text-glow small">Destination Bank Name</label>
                                <select name="target_bank" id="targetBankSelect" class="form-select font-monospace">
                                    <option value="Meezan Bank Limited">Meezan Bank Limited (Islamic)</option>
                                    <option value="Habib Bank Limited (HBL)">Habib Bank Limited (HBL)</option>
                                    <option value="United Bank Limited (UBL)">United Bank Limited (UBL)</option>
                                    <option value="MCB Bank Limited">MCB Bank Limited</option>
                                    <option value="Bank Alfalah Limited">Bank Alfalah Limited</option>
                                    <option value="Allied Bank Limited (ABL)">Allied Bank Limited (ABL)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-glow small">Recipient Account Number / IBAN</label>
                                <input type="text" name="recipient_account_external" id="recipientAccountExternal" class="form-control font-monospace" placeholder="Enter target card/account/IBAN code">
                            </div>
                            <!-- Dynamic Fee Alert based on User Class -->
                            <div class="alert bg-dark text-white-50 border border-secondary p-2.5 small font-monospace">
                                <i data-lucide="info" class="inline-icon text-glow"></i> Network Surcharge Fee Ruleset:<br>
                                - Student Tier: <span class="text-success">Rs. 0.00 (Waived)</span><br>
                                - Standard Tier: <span class="text-warning">Rs. 15.00</span><br>
                                - Business Tier: <span class="text-danger">Rs. 50.00</span><br>
                                Your active Class is: <span class="text-glow"><?php echo $user['account_class']; ?> Tier</span>.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-white-50 small">Transfer Amount (PKR)</label>
                            <input type="number" step="0.01" name="amount" class="form-control font-monospace" placeholder="0.00" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-white-50 small">Transaction Memo Description</label>
                            <input type="text" name="description" class="form-control" placeholder="Memo narration notes" required>
                        </div>

                        <button type="submit" name="execute_transfer" class="btn btn-gradient w-100 py-3">Authorize Remittance Wire</button>
                    </form>
                </div>
            </div>

            <!-- Right Side: Beneficiaries Manager -->
            <div class="col-md-5">
                <div class="glass-card shadow-lg mb-4">
                    <h5 class="fw-bold text-white mb-3 border-bottom border-secondary pb-2">Save Beneficiary Contact</h5>
                    <form action="" method="POST">
                        <input type="hidden" name="post_token" value="<?php echo $_SESSION['txn_token']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label text-white-50 small">Contact Nickname</label>
                            <input type="text" name="nickname" class="form-control" placeholder="e.g. Brother, Landlord" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-white-50 small">Account No / IBAN</label>
                            <input type="text" name="b_acc_no" class="form-control font-monospace" placeholder="Enter card/account number" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-white-50 small">Bank Name</label>
                            <select name="b_bank_name" class="form-select font-monospace">
                                <option value="MTAU Bank" selected>MTAU Bank (Internal)</option>
                                <option value="Meezan Bank Limited">Meezan Bank Limited</option>
                                <option value="Habib Bank Limited (HBL)">Habib Bank Limited (HBL)</option>
                                <option value="United Bank Limited (UBL)">United Bank Limited (UBL)</option>
                                <option value="MCB Bank Limited">MCB Bank Limited</option>
                                <option value="Bank Alfalah Limited">Bank Alfalah Limited</option>
                                <option value="Allied Bank Limited (ABL)">Allied Bank Limited (ABL)</option>
                            </select>
                        </div>

                        <button type="submit" name="save_beneficiary" class="btn btn-gradient btn-sm w-100 py-2">Save Contact</button>
                    </form>
                </div>

                <div class="glass-card shadow-lg">
                    <h5 class="fw-bold text-white mb-3 border-bottom border-secondary pb-2">Saved Beneficiary Directory</h5>
                    <div class="list-group list-group-flush bg-transparent rounded" style="max-height: 300px; overflow-y: auto;">
                        <?php if(mysqli_num_rows($beneficiaries_query) == 0): ?>
                            <div class="text-center py-4 text-white-50 small">No saved beneficiary contacts yet.</div>
                        <?php endif; while($b = mysqli_fetch_assoc($beneficiaries_query)): ?>
                            <div class="list-group-item bg-transparent text-white p-2.5 clickable-beneficiary" onclick="useBeneficiary('<?php echo htmlspecialchars($b['nickname']); ?>', '<?php echo htmlspecialchars($b['account_no']); ?>', '<?php echo htmlspecialchars($b['bank_name']); ?>')">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <h6 class="mb-1 fw-bold text-glow small"><?php echo htmlspecialchars($b['nickname']); ?></h6>
                                    <span class="badge bg-secondary font-monospace" style="font-size:9px;"><?php echo $b['bank_name'] === 'MTAU Bank' ? 'Internal' : 'IBFT'; ?></span>
                                </div>
                                <small class="text-white-50 d-block font-monospace" style="font-size:10px;"><?php echo $b['account_no']; ?></small>
                                <small class="text-white-50 d-block" style="font-size:9px;"><?php echo $b['bank_name']; ?></small>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTransferTab(type) {
            var internalTab = document.getElementById('internalTab');
            var ibftTab = document.getElementById('ibftTab');
            var internalContainer = document.getElementById('internalRecipientContainer');
            var ibftContainer = document.getElementById('ibftRecipientContainer');
            var typeField = document.getElementById('transferTypeField');
            
            var internalInput = document.getElementById('recipientAccountInternal');
            var ibftInput = document.getElementById('recipientAccountExternal');

            if (type === 'internal') {
                internalTab.classList.add('active');
                ibftTab.classList.remove('active');
                internalContainer.style.display = 'block';
                ibftContainer.style.display = 'none';
                typeField.value = 'internal';
                
                internalInput.required = true;
                ibftInput.required = false;
            } else {
                internalTab.classList.remove('active');
                ibftTab.classList.add('active');
                internalContainer.style.display = 'none';
                ibftContainer.style.display = 'block';
                typeField.value = 'ibft';
                
                internalInput.required = false;
                ibftInput.required = true;
            }
        }
        
        function useBeneficiary(nickname, accountNo, bankName) {
            if (bankName === 'MTAU Bank') {
                switchTransferTab('internal');
                document.getElementById('recipientAccountInternal').value = accountNo;
            } else {
                switchTransferTab('ibft');
                document.getElementById('recipientAccountExternal').value = accountNo;
                var bankSelect = document.getElementById('targetBankSelect');
                for (var i = 0; i < bankSelect.options.length; i++) {
                    if (bankSelect.options[i].value.includes(bankName) || bankName.includes(bankSelect.options[i].value)) {
                        bankSelect.selectedIndex = i;
                        break;
                    }
                }
            }
        }

        window.onload = function() {
            lucide.createIcons();
            switchTransferTab('internal');
        };
    </script>
    <?php include 'chatbot.php'; ?>
</body>
</html>