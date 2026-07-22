<?php
session_start();

$message = "";

// Check if form is submitted (UNCHANGED)
if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // DB connection (UNCHANGED)
    $conn = new mysqli("127.0.0.1:3306", "u903588615_root", "Msjobs#1", "u903588615_exaple");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Check user by email (UNCHANGED)
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    // If user exists (UNCHANGED)
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        if (isset($row['password']) && password_verify($password, $row['password'])) {
            $subscription_status = $row['subscription_status'] ?? null;
            $expiry_date = $row['expiry_date'] ?? null;
            $current_date = date('Y-m-d');

            if ($subscription_status === 'active' && $expiry_date >= $current_date) {
                $_SESSION['company_logged_in'] = true;
                $_SESSION['company_email'] = $email;
                $_SESSION['user_id'] = $row['id'];
                header("Location: Jobseekers.php");
                exit();
            } else {
                $message = "inactive";
            }
        } else {
            $message = "invalid";
        }
    } else {
        $message = "notfound";
    }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Company Login • MS JOBS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Fonts & Icons -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

  <!-- Bootstrap (kept) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Tailwind -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- SweetAlert -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <link rel="icon" type="image/png" href="img/MS copy.png" />

  <style>
    :root{
      --brand:#2563eb; /* blue-600 */
      --ink:#0f172a;   /* slate-900 */
      --muted:#64748b; /* slate-500 */
    }
    body{font-family:'Inter', system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;}
    /* Navbar */
    .nav-shadow{box-shadow:0 6px 20px rgba(2,6,23,.08);}
    /* Hero band */
    .hero{
      background:
        radial-gradient(1200px 400px at 20% -10%, rgba(37,99,235,.35), transparent 60%),
        radial-gradient(1200px 400px at 80% -10%, rgba(99,102,241,.25), transparent 60%),
        #0b1220;
    }
    /* Card aesthetic */
    .glass{
      background: rgba(255,255,255,.9);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(15,23,42,.06);
      box-shadow: 0 10px 30px rgba(2,6,23,.12);
      border-radius: 20px;
    }
    .input{
      border:1px solid rgba(15,23,42,.12);
      border-radius:12px;
      padding:12px 14px;
      outline:none;
    }
    .input:focus{
      border-color: rgba(37,99,235,.5);
      box-shadow: 0 0 0 4px rgba(37,99,235,.12);
    }
    .btn-brand{
      background:linear-gradient(180deg,#2563eb,#1d4ed8);
      border:none; color:#fff; border-radius:12px; padding:12px 16px; font-weight:700;
    }
    .btn-brand:hover{filter:brightness(1.02)}
    /* Pricing cards */
    .pcard{
      border-radius: 18px;
      border:1px solid rgba(15,23,42,.08);
      transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
      box-shadow:0 8px 24px rgba(2,6,23,.08);
      background:#fff;
    }
    .pcard:hover{transform:translateY(-4px); box-shadow:0 16px 36px rgba(2,6,23,.12); border-color:rgba(37,99,235,.25);}
    .leftbar{width:10px; background:#4f46e5; border-radius:18px 0 0 18px;}
    /* Footer */
    .footer a.btn.btn-link{padding-left:0}
  </style>
</head>
<body class="bg-gray-50 text-slate-900">

  <!-- Navbar -->
  <nav class="bg-white nav-shadow sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center h-16">
        <a href="index.php" class="flex items-center gap-3">
          <img class="h-10 w-10 rounded-md object-contain" src="img/MS copy.png" alt="Logo">
          <span class="text-xl md:text-2xl font-extrabold text-blue-600 tracking-tight">MS JOBS</span>
        </a>

        <div class="hidden sm:flex items-center gap-6">
          <a href="company.php" class="text-slate-700 hover:text-blue-600 font-medium">Back to Home</a>
        </div>

        <!-- Mobile Menu Button -->
        <button id="menu-toggle" class="sm:hidden text-slate-700 hover:text-blue-600" aria-label="Open Menu">
          <i class="fas fa-bars text-xl"></i>
        </button>
      </div>
    </div>
    <!-- Mobile menu (expandable) -->
    <div id="mobile-menu" class="hidden sm:hidden border-t">
      <div class="px-4 py-3 space-y-2">
        <a href="Companylogin.php" class="block px-2 py-2 rounded-lg text-slate-800 hover:bg-gray-100">Jobseeker Database</a>
      </div>
    </div>
  </nav>

  <!-- Hero -->
  <header class="hero">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="py-10 md:py-14 flex flex-col items-center text-center">
        <span class="inline-flex items-center gap-2 text-blue-200 bg-white/10 px-3 py-1 rounded-full text-xs md:text-sm">
          <i class="fa-solid fa-building-user"></i> Employer Access
        </span>
        <h1 class="text-3xl md:text-5xl font-extrabold text-white mt-4">Company Login</h1>
        <p class="text-blue-100 mt-2 max-w-2xl">Sign in to view your subscribed MSJOBS Jobseeker Database.</p>
      </div>
    </div>
  </header>

  <!-- Login Card -->
  <section class="relative -mt-12 mb-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
        <div class="glass p-6 md:p-8 lg:p-10">
          <div class="flex items-center gap-3 mb-6">
            <img class="h-10 w-10 rounded-md object-contain" src="img/MS copy.png" alt="MS JOBS">
            <h3 class="text-xl md:text-2xl font-bold">Welcome back!</h3>
          </div>
          <form method="POST" class="space-y-4">
            <div>
              <label class="block text-sm font-semibold mb-1">Company Email</label>
              <input type="email" name="email" class="input w-100" placeholder="you@company.com" required>
            </div>
            <div>
              <label class="block text-sm font-semibold mb-1">Password</label>
              <div class="relative">
                <input id="pwd" type="password" name="password" class="input w-100 pr-12" placeholder="••••••••" required>
                <button type="button" id="togglePwd"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700"
                        aria-label="Show password">
                  <i class="fa-regular fa-eye"></i>
                </button>
              </div>
            </div>
            <button type="submit" name="login" class="btn-brand w-100">
              Sign In
            </button>
            <p class="text-xs text-slate-500 mt-2">Need access? Contact <a class="text-blue-600 hover:underline" href="mailto:support@msjobs.net">support@msjobs.net</a></p>
          </form>
        </div>

        <!-- Side Info -->
        <div class="space-y-4">
          <div class="pcard p-6">
            <div class="flex items-start gap-4">
              <div class="shrink-0">
                <div class="h-12 w-12 rounded-xl bg-blue-50 flex items-center justify-center">
                  <i class="fa-solid fa-database text-blue-600"></i>
                </div>
              </div>
              <div>
                <h4 class="text-lg font-bold">Access Curated CV Database</h4>
                <p class="text-slate-600 text-sm mt-1">Search quality profiles vetted by MSJOBS. Your subscription determines the number of CV views and validity.</p>
              </div>
            </div>
          </div>
          <div class="pcard p-6">
            <div class="flex items-start gap-4">
              <div class="h-12 w-12 rounded-xl bg-blue-50 flex items-center justify-center">
                <i class="fa-solid fa-shield-halved text-blue-600"></i>
              </div>
              <div>
                <h4 class="text-lg font-bold">Secure & Private</h4>
                <p class="text-slate-600 text-sm mt-1">Your company workspace is protected. Only active subscriptions can enter the Jobseeker Database.</p>
              </div>
            </div>
          </div>
          <div class="pcard p-6">
            <div class="flex items-start gap-4">
              <div class="h-12 w-12 rounded-xl bg-blue-50 flex items-center justify-center">
                <i class="fa-solid fa-headset text-blue-600"></i>
              </div>
              <div>
                <h4 class="text-lg font-bold">Priority Support</h4>
                <p class="text-slate-600 text-sm mt-1">Need help with access or plans? Message us on WhatsApp and we’ll assist.</p>
              </div>
            </div>
          </div>
        </div>

      </div> <!-- grid -->
    </div>
  </section>

  <!-- Pricing Section -->
  <section class="py-8 md:py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-end justify-between gap-4 mb-6">
        <div>
          <p class="text-sm text-slate-500">Choose your plan</p>
          <h2 class="text-2xl md:text-3xl font-extrabold">Our Pricing Plans</h2>
          <p class="text-slate-600 mt-1 max-w-2xl text-sm">Automated access. Pick a bundle and start hiring immediately.</p>
        </div>
      </div>

      <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <!-- Starter Plan -->
        <div class="pcard overflow-hidden">
          <div class="p-6 md:p-8">
            <div class="flex items-center justify-between gap-4">
              <h3 class="text-xl font-bold">Starter</h3>
              <div class="text-2xl font-extrabold text-blue-600">$500</div>
            </div>
            <p class="text-slate-600 mt-2"><b>1,000 CVs</b> Profile View with <b>3-month</b> validity.</p>
            <div class="mt-6 flex flex-col gap-2">
              <button onclick="payWithStripe('starter')" class="btn-brand w-full flex items-center justify-center gap-2">
                Pay with Card <i class="fa-solid fa-credit-card"></i>
              </button>
              <button onclick="showBankModal('Starter', '$500')" class="w-full py-2.5 rounded-xl border border-slate-200 text-sm font-semibold hover:bg-slate-50 transition-colors">
                Bank Transfer
              </button>
            </div>
          </div>
        </div>

        <!-- Professional Plan -->
        <div class="pcard overflow-hidden ring-2 ring-blue-600/20">
          <div class="p-6 md:p-8">
            <div class="flex items-center justify-between gap-4">
              <h3 class="text-xl font-bold">Professional</h3>
              <div class="text-2xl font-extrabold text-blue-600">$1000</div>
            </div>
            <p class="text-slate-600 mt-2"><b>2,500 CVs</b> Profile View with <b>6-month</b> validity.</p>
            <div class="mt-6 flex flex-col gap-2">
              <button onclick="payWithStripe('professional')" class="btn-brand w-full flex items-center justify-center gap-2">
                Pay with Card <i class="fa-solid fa-credit-card"></i>
              </button>
              <button onclick="showBankModal('Professional', '$1000')" class="w-full py-2.5 rounded-xl border border-slate-200 text-sm font-semibold hover:bg-slate-50 transition-colors">
                Bank Transfer
              </button>
            </div>
          </div>
        </div>

        <!-- Enterprise Plan -->
        <div class="pcard overflow-hidden">
          <div class="p-6 md:p-8">
            <div class="flex items-center justify-between gap-4">
              <h3 class="text-xl font-bold">Enterprise</h3>
              <div class="text-2xl font-extrabold text-blue-600">$2000</div>
            </div>
            <p class="text-slate-600 mt-2"><b>6,000 CVs</b> Profile View with <b>12-month</b> validity.</p>
            <div class="mt-6 flex flex-col gap-2">
              <button onclick="payWithStripe('enterprise')" class="btn-brand w-full flex items-center justify-center gap-2">
                Pay with Card <i class="fa-solid fa-credit-card"></i>
              </button>
              <button onclick="showBankModal('Enterprise', '$2000')" class="w-full py-2.5 rounded-xl border border-slate-200 text-sm font-semibold hover:bg-slate-50 transition-colors">
                Bank Transfer
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Bank Transfer Modal -->
  <div id="bankModal" class="fixed inset-0 bg-black/50 z-[100] hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl w-full max-w-md p-8 shadow-2xl relative">
      <button onclick="closeBankModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 text-2xl">&times;</button>
      <div class="text-center mb-6">
        <div class="h-14 w-14 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
          <i class="fa-solid fa-building-columns text-2xl"></i>
        </div>
        <h3 class="text-2xl font-bold">Bank Transfer</h3>
        <p class="text-slate-500 text-sm mt-1">Plan: <span id="modalPlanName" class="font-bold text-slate-800"></span></p>
      </div>

      <div class="space-y-4 bg-slate-50 p-6 rounded-2xl border border-slate-100">
        <div>
          <label class="text-[10px] uppercase tracking-wider font-bold text-slate-400">Beneficiary Name</label>
          <div class="font-bold text-slate-800">Ms human resource Consultancies. Co. L. L. C</div>
        </div>
        <div>
          <label class="text-[10px] uppercase tracking-wider font-bold text-slate-400">Bank Name</label>
          <div class="font-bold text-slate-800">ABUDHABI COMMERCIAL BANK</div>
        </div>
        <div>
          <label class="text-[10px] uppercase tracking-wider font-bold text-slate-400">Account Number</label>
          <div class="font-bold text-slate-800 tracking-wider">13667241920001</div>
        </div>
        <div>
          <label class="text-[10px] uppercase tracking-wider font-bold text-slate-400">IBAN Number</label>
          <div class="font-bold text-slate-800 tracking-wider select-all">AE520030013667241920001</div>
        </div>
      </div>

      <div class="mt-6 text-center space-y-4">
        <p class="text-xs text-slate-500 italic">Please share the transfer receipt on WhatsApp for manual verification.</p>
        <a id="whatsappReceiptLink" href="#" target="_blank" class="flex items-center justify-center gap-2 bg-emerald-500 text-white rounded-xl py-3 font-bold no-underline hover:bg-emerald-600 transition-colors">
          Send Receipt <i class="fa-brands fa-whatsapp"></i>
        </a>
      </div>
    </div>
  </div>

  <script src="https://js.stripe.com/v3/"></script>
  <script>
    // Stripe Keys
    const STRIPE_PUBLISHABLE_KEY = 'pk_live_51SzbqpPZ9uu6mb2JW4Xb1mY649dv4Ij03MJyt6zfG6oulcYzFMC0aHOlXDLVSHsjubXoMMwGuNnMUL6CXd4uqrEF00Dm217GWM';
    
    const stripe = Stripe(STRIPE_PUBLISHABLE_KEY);

    async function payWithStripe(plan) {
      const { value: email } = await Swal.fire({
        title: 'Enter your Company Email',
        input: 'email',
        inputLabel: 'We need this to activate your subscription',
        inputPlaceholder: 'email@company.com',
        showCancelButton: true,
        confirmButtonColor: '#2563eb'
      });

      if (email) {
        Swal.fire({
          title: 'Redirecting to Payment...',
          allowOutsideClick: false,
          didOpen: () => { Swal.showLoading(); }
        });

        try {
          const response = await fetch('create-checkout-session', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ plan: plan, email: email })
          });
          
          if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`Server Error: ${response.status}. ${errorText.substring(0, 100)}`);
          }

          const session = await response.json();
          if (session.id) {
            stripe.redirectToCheckout({ sessionId: session.id });
          } else {
            Swal.fire('Error', session.error || 'Failed to create session', 'error');
          }
        } catch (err) {
          console.error(err);
          Swal.fire('Error', `Details: ${err.message}`, 'error');
        }
      }
    }

    function showBankModal(plan, price) {
      document.getElementById('modalPlanName').textContent = plan + ' (' + price + ')';
      document.getElementById('whatsappReceiptLink').href = 'https://wa.me/971585323967?text=Paid%20' + encodeURIComponent(price) + '%20for%20' + encodeURIComponent(plan) + '%20plan.%20Here%20is%20the%20receipt:';
      const m = document.getElementById('bankModal');
      m.classList.remove('hidden');
      m.classList.add('flex');
    }

    function closeBankModal() {
      const m = document.getElementById('bankModal');
      m.classList.add('hidden');
      m.classList.remove('flex');
    }
  </script>

  <!-- Footer (lightweight) -->
  <footer class="bg-white border-t">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="py-6 flex flex-col md:flex-row items-center justify-between gap-3">
        <div class="text-sm text-slate-500">
          © <strong>MS JOBS</strong>. All rights reserved.
        </div>
        <div class="flex items-center gap-4 text-sm">
          <a href="mailto:support@msjobs.net" class="text-slate-600 hover:text-blue-600">support@msjobs.net</a>
          <a href="https://www.linkedin.com/company/ms-group-of-companies-uae/" class="text-slate-600 hover:text-blue-600"><i class="fab fa-linkedin-in"></i></a>
          <a href="https://www.instagram.com/ms_group2023?igsh=ZXVnYmN5dXJwNnZt" class="text-slate-600 hover:text-blue-600"><i class="fab fa-instagram"></i></a>
          <a href="https://www.facebook.com/share/1BqcPQMQEC/" class="text-slate-600 hover:text-blue-600"><i class="fab fa-facebook-f"></i></a>
        </div>
      </div>
    </div>
  </footer>

  <!-- Alerts -->
  <script>
    // Mobile menu toggle
    document.getElementById('menu-toggle')?.addEventListener('click', function(){
      const m = document.getElementById('mobile-menu');
      if(m) m.classList.toggle('hidden');
    });

    // Show/hide password
    const togglePwd = document.getElementById('togglePwd');
    const pwd = document.getElementById('pwd');
    if (togglePwd && pwd) {
      togglePwd.addEventListener('click', () => {
        const isPwd = pwd.getAttribute('type') === 'password';
        pwd.setAttribute('type', isPwd ? 'text' : 'password');
        togglePwd.innerHTML = isPwd
          ? '<i class="fa-regular fa-eye-slash"></i>'
          : '<i class="fa-regular fa-eye"></i>';
      });
    }

    // SweetAlert feedback (UNCHANGED logic)
    <?php if ($message == "inactive"): ?>
      Swal.fire('Subscription Error', 'Your subscription is inactive or expired!', 'warning');
    <?php elseif ($message == "invalid"): ?>
      Swal.fire('Login Failed', 'Invalid email or password!', 'error');
    <?php elseif ($message == "notfound"): ?>
      Swal.fire('Not Found', 'No company found with that email!', 'info');
    <?php endif; ?>
  </script>

  <!-- Bootstrap JS (optional, for any Bootstrap behavior you keep) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
