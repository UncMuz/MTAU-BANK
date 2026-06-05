<?php
// Ensure database connection and session are active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// If $conn is not set, dynamically include db.php to prevent errors
if (!isset($conn)) {
    @include 'db.php';
}

$chat_user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
$chat_user_name = "Guest";
$chat_loans = [];
$chat_funds = [];
$chat_insurances = [];
$chat_bills = [];

if ($chat_user_id && isset($conn) && $conn) {
    // Fetch user name
    $u_res = mysqli_query($conn, "SELECT full_name FROM users WHERE id = $chat_user_id");
    if ($u_res && $row = mysqli_fetch_assoc($u_res)) {
        $chat_user_name = htmlspecialchars($row['full_name']);
    }
    
    // Fetch active loans
    $l_res = mysqli_query($conn, "SELECT * FROM loans WHERE user_id = $chat_user_id ORDER BY applied_at DESC");
    if ($l_res) {
        while ($l = mysqli_fetch_assoc($l_res)) {
            $chat_loans[] = [
                'loan_type' => $l['loan_type'],
                'amount' => floatval($l['amount']),
                'emi' => floatval($l['emi']),
                'status' => $l['status']
            ];
        }
    }
    
    // Fetch active mutual funds
    $f_res = mysqli_query($conn, "SELECT * FROM mutual_funds WHERE user_id = $chat_user_id ORDER BY invested_at DESC");
    if ($f_res) {
        while ($f = mysqli_fetch_assoc($f_res)) {
            $chat_funds[] = [
                'fund_name' => $f['fund_name'],
                'amount_invested' => floatval($f['amount_invested']),
                'expected_yield_rate' => floatval($f['expected_yield_rate'])
            ];
        }
    }
    
    // Fetch active insurance policies
    $i_res = mysqli_query($conn, "SELECT * FROM insurance_policies WHERE user_id = $chat_user_id ORDER BY enrolled_at DESC");
    if ($i_res) {
        while ($i = mysqli_fetch_assoc($i_res)) {
            $chat_insurances[] = [
                'policy_type' => $i['policy_type'],
                'coverage_amount' => floatval($i['coverage_amount']),
                'monthly_premium' => floatval($i['monthly_premium']),
                'status' => $i['status']
            ];
        }
    }
    
    // Fetch recent bill payments
    $b_res = mysqli_query($conn, "SELECT * FROM bill_payments WHERE user_id = $chat_user_id ORDER BY paid_at DESC LIMIT 3");
    if ($b_res) {
        while ($b = mysqli_fetch_assoc($b_res)) {
            $chat_bills[] = [
                'biller_name' => $b['biller_name'],
                'amount' => floatval($b['amount']),
                'paid_at' => $b['paid_at']
            ];
        }
    }
}
?>

<script src="cooleffectslite.js"></script>

<!-- Chatbot Floating Button -->
<div id="chatbot-trigger" class="chatbot-trigger" onclick="toggleChatbot()">
    <i data-lucide="message-square"></i>
</div>

<!-- Chatbot Box -->
<div id="chatbot-box" class="chatbot-box">
    <!-- Header -->
    <div class="chatbot-header">
        <div class="chatbot-header-title">
            <i data-lucide="bot"></i>
            <span>MTAU Assistant</span>
        </div>
        <div class="chatbot-header-actions">
            <i data-lucide="minus" onclick="toggleChatbot()" title="Minimize"></i>
            <i data-lucide="refresh-cw" onclick="resetChatbot()" title="Clear Chat"></i>
        </div>
    </div>

    <!-- Messages Container -->
    <div id="chatbot-messages" class="chatbot-messages">
        <!-- Messages will render dynamically here -->
    </div>

    <!-- Input Area -->
    <div class="chatbot-input-area">
        <input type="text" id="chatbot-input" placeholder="Type a message or click choices..." onkeydown="handleChatbotKey(event)">
        <button class="chatbot-send-btn" onclick="sendChatbotMessage()">
            <i data-lucide="send"></i>
        </button>
    </div>
</div>

<script>
    // Initialize Lucide Icons for chatbot elements specifically if script triggers late
    setTimeout(() => {
        if (window.lucide) {
            lucide.createIcons();
        }
    }, 100);

    const chatMessagesEl = document.getElementById('chatbot-messages');
    const chatInputEl = document.getElementById('chatbot-input');
    const chatBoxEl = document.getElementById('chatbot-box');

    // Dynamic User Session Data Injected from PHP
    const userSessionData = {
        userName: <?php echo json_encode($chat_user_name); ?>,
        loggedIn: <?php echo $chat_user_id ? 'true' : 'false'; ?>,
        loans: <?php echo json_encode($chat_loans); ?>,
        funds: <?php echo json_encode($chat_funds); ?>,
        insurances: <?php echo json_encode($chat_insurances); ?>,
        recentBills: <?php echo json_encode($chat_bills); ?>
    };

    function escapeHTML(str) {
        if (!str) return '';
        return str.replace(/[&<>'"]/g, 
            tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
        );
    }

    function formatNumber(num) {
        return new Intl.NumberFormat('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num);
    }

    const isLoggedIn = userSessionData.loggedIn;

    // Build dynamic replies based on user stats
    let mutualFundsReply = "";
    if (isLoggedIn && userSessionData.funds && userSessionData.funds.length > 0) {
        mutualFundsReply = `You have <b>${userSessionData.funds.length}</b> active mutual fund investment(s):<ul class="my-2 ps-3 small">`;
        userSessionData.funds.forEach(fund => {
            mutualFundsReply += `<li><b>${escapeHTML(fund.fund_name)}</b>: PKR ${formatNumber(fund.amount_invested)} (Expected Yield: ${fund.expected_yield_rate}%)</li>`;
        });
        mutualFundsReply += `</ul><a href="mutual_funds_terminal.php" class="btn btn-gradient btn-sm mt-2 text-dark font-weight-bold d-block text-center" style="font-size: 11px;">Manage Investments</a>`;
    } else {
        mutualFundsReply = `MTAU Bank offers high-yield fractional shares in premier local assets:
            <ul class="my-2 ps-3 small">
                <li><b>Clifton Residency Share</b>: 9.8% annual rental yield.</li>
                <li><b>Islamabad Commercial Share</b>: 11.2% annual rental yield.</li>
                <li><b>PSX Tech Dividend Index</b>: 15.4% annual dividend stock.</li>
                <li><b>Energy Giants Stock</b>: 13.2% annual dividend yield.</li>
            </ul>
            You can buy shares directly from your Current Account.
            <a href="mutual_funds_terminal.php" class="btn btn-gradient btn-sm mt-2 text-dark font-weight-bold d-block text-center" style="font-size: 11px;">Go to Mutual Investments</a>`;
    }

    let loansReply = "";
    if (isLoggedIn && userSessionData.loans && userSessionData.loans.length > 0) {
        loansReply = `You have <b>${userSessionData.loans.length}</b> registered loan(s):<ul class="my-2 ps-3 small">`;
        userSessionData.loans.forEach(loan => {
            let badgeClass = 'bg-secondary';
            if (loan.status === 'approved') badgeClass = 'bg-success';
            if (loan.status === 'rejected') badgeClass = 'bg-danger';
            loansReply += `<li><b>${escapeHTML(loan.loan_type)}</b>: PKR ${formatNumber(loan.amount)}<br><span class="text-white-50">Status: <span class="badge ${badgeClass}">${escapeHTML(loan.status)}</span> | EMI: PKR ${formatNumber(loan.emi)}/mo</span></li>`;
        });
        loansReply += `</ul><a href="loan_terminal.php" class="btn btn-gradient btn-sm mt-2 text-dark font-weight-bold d-block text-center" style="font-size: 11px;">Manage Loans</a>`;
    } else {
        loansReply = `Need capital funding? Apply for corporate credit lines, home, or student credit buffers directly under our markup guidelines (12% baseline).
            <ul class="my-2 ps-3 small">
                <li>Markups are calculated dynamically based on maturity months.</li>
                <li>Loans undergo administrative override checks before disbursement.</li>
            </ul>
            <a href="loan_terminal.php" class="btn btn-gradient btn-sm mt-2 text-dark font-weight-bold d-block text-center" style="font-size: 11px;">Go to Loan Center</a>`;
    }

    let insuranceReply = "";
    if (isLoggedIn && userSessionData.insurances && userSessionData.insurances.length > 0) {
        insuranceReply = `You have <b>${userSessionData.insurances.length}</b> active insurance policy(ies):<ul class="my-2 ps-3 small">`;
        userSessionData.insurances.forEach(ins => {
            insuranceReply += `<li><b>${escapeHTML(ins.policy_type)}</b>: Premium PKR ${formatNumber(ins.monthly_premium)}/mo (Coverage: PKR ${formatNumber(ins.coverage_amount)}) - <span class="text-success fw-bold">${escapeHTML(ins.status)}</span></li>`;
        });
        insuranceReply += `</ul><a href="dashboard.php" class="btn btn-gradient btn-sm mt-2 text-dark font-weight-bold d-block text-center" style="font-size: 11px;">Go to Command Center</a>`;
    } else {
        insuranceReply = `Secure your assets with our dynamic risk coverage wrappers:
            <ul class="my-2 ps-3 small">
                <li><b>Health Protection</b> (0.5% premium rate)</li>
                <li><b>Property Structural Coverage</b> (0.8% premium rate)</li>
                <li><b>Car / Transit Asset Cover</b> (1.0% premium rate)</li>
            </ul>
            Enrollment is accessible on your main command hub under the risk panel.
            <a href="dashboard.php" class="btn btn-gradient btn-sm mt-2 text-dark font-weight-bold d-block text-center" style="font-size: 11px;">Go to Command Center</a>`;
    }

    let billsReply = "";
    if (isLoggedIn) {
        billsReply = `<b>Billing Due Dates (Monthly Cycle):</b>
            <ul class="my-2 ps-3 small">
                <li><b>Water Supply Board</b>: Due on 10th of every month.</li>
                <li><b>K-Electric Grid</b>: Due on 15th of every month.</li>
                <li><b>Sui Southern Gas Corp</b>: Due on 20th of every month.</li>
                <li><b>StormFiber Internet</b>: Due on 25th of every month.</li>
            </ul>`;
        if (userSessionData.recentBills && userSessionData.recentBills.length > 0) {
            billsReply += `<span class="small fw-bold">Your recent bill payments:</span><ul class="my-1 ps-3 small text-white-50">`;
            userSessionData.recentBills.forEach(bill => {
                billsReply += `<li>Paid PKR ${formatNumber(bill.amount)} to <b>${escapeHTML(bill.biller_name)}</b></li>`;
            });
            billsReply += `</ul>`;
        } else {
            billsReply += `<p class="small text-white-50 mb-1">No recent payments recorded in this session.</p>`;
        }
        billsReply += `<a href="bill_terminal.php" class="btn btn-gradient btn-sm mt-2 text-dark font-weight-bold d-block text-center" style="font-size: 11px;">Go to Bill Terminal</a>`;
    } else {
        billsReply = `Clear bills instantly across multiple utility grids (electricity, water, gas, internet) and educational/wellness categories.
            <ul class="my-2 ps-3 small">
                <li><b>Loyalty Points</b>: Earn points for every transaction completed.</li>
                <li>Loyalty perks can be claimed inside the Perks section.</li>
            </ul>
            <a href="bill_terminal.php" class="btn btn-gradient btn-sm mt-2 text-dark font-weight-bold d-block text-center" style="font-size: 11px;">Go to Bill Terminal</a>`;
    }

    // Bot Response Map
    const botResponseTree = {
        'mutual_funds': mutualFundsReply,
        'loans': loansReply,
        'insurance': insuranceReply,
        'bills': billsReply,
        
        'trading': `Test your trading strategy on our simulated Trading Desk:
            <ul class="my-2 ps-3 small">
                <li><b>Demo Account Credits</b>: Single-click activation of Forex and Crypto demo accounts, each pre-loaded with $10,000 USD virtual credit.</li>
                <li><b>Leverage</b>: Forex (100x leverage), Crypto (10x leverage).</li>
                <li><b>Two-way Transfer</b>: Convert profits to Current Account or deposit PKR from your Current Account to USD wallets at $1 USD = 278.40 PKR.</li>
            </ul>
            <a href="trading_terminal.php" class="btn btn-gradient btn-sm mt-2 text-dark font-weight-bold d-block text-center" style="font-size: 11px;">Go to Trading Desk</a>`,
        
        'transfer': `Move funds instantly between accounts using standard IBAN configurations or write secure wires via our transfer console.
            <a href="transfer_terminal.php" class="btn btn-gradient btn-sm mt-2 text-dark font-weight-bold d-block text-center" style="font-size: 11px;">Go to Fund Transfer</a>`,
        
        'card': `Configure and secure your EMV hardware card:
            <ul class="my-2 ps-3 small">
                <li>Change PIN and limits.</li>
                <li>Freeze/unfreeze functions.</li>
                <li>Toggle local and international transaction routers.</li>
            </ul>
            <a href="manage_card.php" class="btn btn-gradient btn-sm mt-2 text-dark font-weight-bold d-block text-center" style="font-size: 11px;">Go to Card Manager</a>`,
        
        'support': `If you have a dispute or system error, submit a support request. Our admins monitor the support queue and resolve issues from the administrative override boards.
            <a href="support_terminal.php" class="btn btn-gradient btn-sm mt-2 text-dark font-weight-bold d-block text-center" style="font-size: 11px;">Go to Helpdesk Support</a>`
    };

    // NLP Keyword parsing logic for free-text inputs
    function parseCustomQuery(text) {
        const query = text.toLowerCase().trim();
        
        // 1. Checks for specific User profiles (loans, mutual funds, insurance, bills)
        if (query.includes('my loan') || query.includes('active loan') || (query.includes('loan') && (query.includes('i have') || query.includes('my') || query.includes('list')))) {
            if (isLoggedIn && userSessionData.loans && userSessionData.loans.length > 0) {
                return loansReply;
            } else if (isLoggedIn) {
                return `You don't have any active loans right now. Let's change that! Here's how you can get one:<br>${loansReply}`;
            }
        }
        if (query.includes('my mutual') || query.includes('my fund') || query.includes('my invest') || (query.includes('fund') && (query.includes('i have') || query.includes('my') || query.includes('list')))) {
            if (isLoggedIn && userSessionData.funds && userSessionData.funds.length > 0) {
                return mutualFundsReply;
            } else if (isLoggedIn) {
                return `You don't have any active mutual fund investments yet. Here are the packages we offer:<br>${mutualFundsReply}`;
            }
        }
        if (query.includes('my insurance') || query.includes('my policy') || (query.includes('insurance') && (query.includes('i have') || query.includes('my') || query.includes('list')))) {
            if (isLoggedIn && userSessionData.insurances && userSessionData.insurances.length > 0) {
                return insuranceReply;
            } else if (isLoggedIn) {
                return `You don't have any active insurance policies. Here are the coverage packages available:<br>${insuranceReply}`;
            }
        }
        if (query.includes('billing date') || query.includes('due date') || query.includes('when is') || query.includes('bill schedule') || query.includes('my bill') || query.includes('bill')) {
            return billsReply;
        }

        // 2. Generic matches
        if (query.includes('mutual') || query.includes('fund') || query.includes('invest') || query.includes('share') || query.includes('yield') || query.includes('profit share')) {
            return botResponseTree['mutual_funds'];
        }
        if (query.includes('loan') || query.includes('borrow') || query.includes('mortgage') || query.includes('markup') || query.includes('emi')) {
            return botResponseTree['loans'];
        }
        if (query.includes('insurance') || query.includes('premium') || query.includes('policy') || query.includes('coverage') || query.includes('health') || query.includes('property')) {
            return botResponseTree['insurance'];
        }
        if (query.includes('trade') || query.includes('trading') || query.includes('crypto') || query.includes('forex') || query.includes('bitcoin') || query.includes('wallet') || query.includes('leverage')) {
            return botResponseTree['trading'];
        }
        if (query.includes('transfer') || query.includes('wire') || query.includes('send') || query.includes('payee') || query.includes('beneficiary')) {
            return botResponseTree['transfer'];
        }
        if (query.includes('card') || query.includes('pin') || query.includes('debit') || query.includes('limit') || query.includes('freeze')) {
            return botResponseTree['card'];
        }
        if (query.includes('support') || query.includes('ticket') || query.includes('help') || query.includes('issue') || query.includes('dispute') || query.includes('admin')) {
            return botResponseTree['support'];
        }
        if (query.includes('iban') || query.includes('account number') || query.includes('routing')) {
            return `Your primary routing IBAN and account details are displayed at the top left of your Command Center dashboard (dashboard.php).`;
        }
        if (query.includes('hello') || query.includes('hi') || query.includes('hey') || query.includes('greet')) {
            const nameGreet = isLoggedIn ? `, ${userSessionData.userName}` : "";
            return `Hello${nameGreet}! How can I assist you with your MTAU banking profile today? You can select a quick action or ask about mutual funds, loans, transfers, cards, or trading.`;
        }

        return `I'm sorry, I couldn't find a direct match for your question. You can choose one of the quick options below or ask about 'loans', 'mutual funds', 'transfers', or 'debit cards'.`;
    }

    // Toggle Chatbot Open/Close
    function toggleChatbot() {
        chatBoxEl.classList.toggle('open');
        const isOpen = chatBoxEl.classList.contains('open');
        sessionStorage.setItem('chatbot_open', isOpen ? '1' : '0');
        if (isOpen) {
            scrollToBottom();
            chatInputEl.focus();
        }
    }

    // Append Message to UI
    function appendMessage(sender, text, isOptions = false) {
        const msgDiv = document.createElement('div');
        msgDiv.className = `chatbot-msg ${sender}`;
        msgDiv.innerHTML = text;
        chatMessagesEl.appendChild(msgDiv);

        if (isOptions) {
            const optDiv = document.createElement('div');
            optDiv.className = 'chatbot-options';
            
            const options = [
                { id: 'mutual_funds', text: 'Mutual Funds' },
                { id: 'loans', text: 'Apply Loan' },
                { id: 'insurance', text: 'Insurances' },
                { id: 'bills', text: 'Bill Payments' },
                { id: 'trading', text: 'Demo Trading' }
            ];

            options.forEach(opt => {
                const btn = document.createElement('button');
                btn.className = 'chatbot-opt-btn';
                btn.innerText = opt.text;
                btn.onclick = () => selectQuickOption(opt.id, opt.text);
                optDiv.appendChild(btn);
            });
            chatMessagesEl.appendChild(optDiv);
        }

        scrollToBottom();
    }

    // Handle Quick Option clicks
    function selectQuickOption(id, text) {
        // Log user selection
        saveMessage('user', text);
        appendMessage('user', text);
        
        // Log bot response
        setTimeout(() => {
            const reply = botResponseTree[id];
            saveMessage('bot', reply);
            appendMessage('bot', reply, true);
        }, 350);
    }

    // Send Input Box message
    function sendChatbotMessage() {
        const query = chatInputEl.value.trim();
        if (!query) return;

        chatInputEl.value = '';
        saveMessage('user', query);
        appendMessage('user', query);

        setTimeout(() => {
            const botReply = parseCustomQuery(query);
            saveMessage('bot', botReply);
            appendMessage('bot', botReply, true);
        }, 400);
    }

    function handleChatbotKey(event) {
        if (event.key === 'Enter') {
            sendChatbotMessage();
        }
    }

    // Scroll chat window to bottom
    function scrollToBottom() {
        chatMessagesEl.scrollTop = chatMessagesEl.scrollHeight;
    }

    // SessionStorage persistence
    function saveMessage(sender, text) {
        let history = JSON.parse(sessionStorage.getItem('chatbot_history')) || [];
        history.push({ sender, text });
        sessionStorage.setItem('chatbot_history', JSON.stringify(history));
    }

    function loadChatHistory() {
        chatMessagesEl.innerHTML = '';
        let history = JSON.parse(sessionStorage.getItem('chatbot_history'));
        
        if (!history || history.length === 0) {
            const greeting = "Hello! Welcome to MTAU Digital Banking Assistance. How can I help support your financial profile today?";
            appendMessage('bot', greeting, true);
            saveMessage('bot', greeting);
        } else {
            history.forEach((msg, index) => {
                // If it is the last bot message, show the options below it for convenience
                const isLast = (index === history.length - 1 && msg.sender === 'bot');
                appendMessage(msg.sender, msg.text, isLast);
            });
        }
        
        // Re-open chat if was open in session
        if (sessionStorage.getItem('chatbot_open') === '1') {
            chatBoxEl.classList.add('open');
        }
    }

    function resetChatbot() {
        sessionStorage.removeItem('chatbot_history');
        loadChatHistory();
    }

    // Load history on initialization
    loadChatHistory();
</script>
