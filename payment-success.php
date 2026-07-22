<?php
declare(strict_types=1);
session_start();

/*
 * payment-success.php
 * Redesigned for a premium, dynamic look as requested by the user.
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

// USE TEST SECRET KEY (Currently in Test Mode)
$stripe_secret = 'sk_test_51SzbqzPYPqvc9pAHO0qXnIoi7KLe7sBchFUcj0TjSGzp3F9OMgXsLzB6AriDrZA44uvicQpXABlIN1FKVjUxb8VJ000wlMlG41';
// Live: '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'

\Stripe\Stripe::setApiKey($stripe_secret);

$session_id = $_GET['session_id'] ?? '';
$plan = $_GET['plan'] ?? '';
$email = $_GET['email'] ?? '';

if (!$session_id || !$plan || !$email) {
    die("Invalid request parameters.");
}

$type = 'loading';
$msg = 'Verifying your payment...';
$plan_name = '';
$credits = 0;
$validity = '';

try {
    $session = \Stripe\Checkout\Session::retrieve($session_id);

    if ($session->payment_status === 'paid') {
        
        $plan_config = [
            'starter'      => ['name' => 'Starter', 'limit' => 1000,  'months' => 3, 'price' => '$500'],
            'professional' => ['name' => 'Professional', 'limit' => 2500,  'months' => 6, 'price' => '$1000'],
            'enterprise'   => ['name' => 'Enterprise', 'limit' => 6000, 'months' => 12, 'price' => '$2000'],
        ];

        if (!isset($plan_config[$plan])) {
            throw new Exception("Unknown plan: " . $plan);
        }

        $plan_info = $plan_config[$plan];
        $plan_name = $plan_info['name'];
        $credits = $plan_info['limit'];
        $validity = $plan_info['months'] . ' Months';
        $limit = $plan_info['limit'];
        $expiry = date('Y-m-d', strtotime("+{$plan_info['months']} months"));

        // 1. Update User Record
        $stmt = $conn->prepare("UPDATE users SET subscription_status = 'active', expiry_date = ? WHERE email = ?");
        $stmt->bind_param("ss", $expiry, $email);
        if (!$stmt->execute()) throw new Exception("Failed to update user table.");
        $stmt->close();

        // 2. Fetch User ID
        $userQuery = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $userQuery->bind_param("s", $email);
        $userQuery->execute();
        $user = $userQuery->get_result()->fetch_assoc();
        $user_id = (int)($user['id'] ?? 0);
        $userQuery->close();

        if ($user_id > 0) {
            // 3. Update or Insert Limits
            $limitCheck = $conn->prepare("SELECT 1 FROM employer_limits WHERE employer_id = ?");
            $limitCheck->bind_param("i", $user_id);
            $limitCheck->execute();
            $exists = $limitCheck->get_result()->num_rows > 0;
            $limitCheck->close();

            if ($exists) {
                $upLimit = $conn->prepare("UPDATE employer_limits SET view_limit = ? WHERE employer_id = ?");
                $upLimit->bind_param("ii", $limit, $user_id);
                $upLimit->execute();
                $upLimit->close();
            } else {
                $inLimit = $conn->prepare("INSERT INTO employer_limits (employer_id, view_limit) VALUES (?, ?)");
                $inLimit->bind_param("ii", $user_id, $limit);
                $inLimit->execute();
                $inLimit->close();
            }
        }

        $_SESSION['company_logged_in'] = true;
        $_SESSION['company_email'] = $email;
        $_SESSION['user_id'] = $user_id;

        $msg = "Subscription activated successfully.";
        $type = "success";
    } else {
        $msg = "Payment pending or failed.";
        $type = "error";
    }
} catch (Exception $e) {
    $msg = "System error: " . $e->getMessage();
    $type = "error";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status • MS JOBS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .success-accent { background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); }
        .error-accent { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
        .glass-card { 
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
        }
        .animate-pop { animation: pop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards; }
        @keyframes pop { 
            0% { transform: scale(0.8); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        .confetti { height: 6px; width: 6px; position: fixed; border-radius: 50%; opacity: 0.8; z-index: 0; }
    </style>
</head>
<body class="bg-[#f8fafc] flex items-center justify-center min-h-screen p-6 relative overflow-hidden">
    
    <?php if ($type === 'success'): ?>
    <script>
        function createConfetti() {
            const colors = ['#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#7c3aed'];
            for(let i=0; i<100; i++) {
                const c = document.createElement('div');
                c.className = 'confetti';
                c.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                c.style.left = Math.random() * 100 + 'vw';
                c.style.top = -10 + 'px';
                c.style.transform = `rotate(${Math.random() * 360}deg)`;
                document.body.appendChild(c);
                
                const destY = Math.random() * 100 + 100 + 'vh';
                const duration = Math.random() * 3000 + 2000;
                
                c.animate([
                    { top: '-10px', transform: 'translateX(0) rotate(0deg)' },
                    { top: destY, transform: `translateX(${Math.random() * 200 - 100}px) rotate(${Math.random() * 1000}deg)` }
                ], { duration: duration, easing: 'cubic-bezier(0.1, 0.5, 0.5, 1)' });
                setTimeout(() => c.remove(), duration);
            }
        }
        window.onload = createConfetti;
    </script>
    <?php endif; ?>

    <div class="max-w-md w-full glass-card rounded-[2.5rem] overflow-hidden border border-white/40 animate-pop relative z-10">
        <!-- Header Strip -->
        <div class="h-4 <?php echo ($type === 'success' ? 'success-accent' : 'error-accent'); ?>"></div>
        
        <div class="p-10 text-center">
            <?php if ($type === 'success'): ?>
                <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-green-50 text-green-500 mb-6 shadow-sm">
                    <i class="fa-solid fa-check text-4xl"></i>
                </div>
                <h1 class="text-3xl font-extrabold text-slate-900 mb-2">Order Confirmed!</h1>
                <p class="text-slate-500 mb-8"><?php echo htmlspecialchars($msg); ?></p>

                <!-- Receipt Box -->
                <div class="bg-slate-50 rounded-3xl p-6 text-left border border-slate-100 mb-8">
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Plan</span>
                            <span class="font-bold text-slate-900"><?php echo htmlspecialchars($plan_name); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">CV Credits</span>
                            <span class="font-bold text-blue-600 bg-blue-50 px-3 py-1 rounded-full text-sm">+<?php echo number_format($credits); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Validity</span>
                            <span class="font-bold text-slate-900"><?php echo htmlspecialchars($validity); ?></span>
                        </div>
                        <div class="pt-4 border-t border-dashed border-slate-200 flex justify-between items-center">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Amount Paid</span>
                            <span class="text-xl font-extrabold text-slate-900"><?php echo $plan_config[$plan]['price']; ?></span>
                        </div>
                    </div>
                </div>

                <a href="Jobseekers.php" class="block w-full py-5 bg-slate-900 text-white rounded-2xl font-bold shadow-xl shadow-slate-200 hover:scale-[1.02] active:scale-[0.98] transition-all no-underline">
                    Access Jobseeker Database <i class="fa-solid fa-arrow-right ml-2 bg-white/10 p-1 rounded-md"></i>
                </a>

            <?php else: ?>
                <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-red-50 text-red-500 mb-6 shadow-sm">
                    <i class="fa-solid fa-xmark text-4xl"></i>
                </div>
                <h1 class="text-3xl font-extrabold text-slate-900 mb-2">Payment Failed</h1>
                <p class="text-slate-500 mb-8"><?php echo htmlspecialchars($msg); ?></p>
                
                <div class="bg-red-50/50 rounded-3xl p-6 text-sm text-red-600 border border-red-100 mb-8">
                    Something went wrong during the checkout process. Please check your card or contact support if the problem persists.
                </div>

                <a href="Companylogin.php" class="block w-full py-5 border-2 border-slate-200 text-slate-900 rounded-2xl font-bold hover:bg-slate-50 transition-all no-underline">
                    Return to Pricing
                </a>
            <?php endif; ?>
        </div>
        
        <!-- Footer Info -->
        <div class="bg-slate-50 px-10 py-5 text-center flex items-center justify-center gap-2">
            <img src="img/MS copy.png" class="h-6 opacity-40 grayscale" alt="Logo">
            <span class="text-xs font-bold tracking-tight text-slate-400 uppercase">MS JOBS SECURE CHECKOUT</span>
        </div>
    </div>

</body>
</html>
