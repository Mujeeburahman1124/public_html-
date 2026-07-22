<?php 
session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Super Admin Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {
              DEFAULT: '#2563eb',
              dark: '#1e40af',
              light: '#60a5fa',
            },
          },
        },
      },
    }
  </script>
</head>
<body class="h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900">

  <!-- Card -->
  <div class="relative w-full max-w-md bg-white/10 backdrop-blur-xl rounded-2xl shadow-2xl border border-white/20 p-8 animate-fadeIn">
    <div class="text-center">
      <div class="mx-auto w-14 h-14 flex items-center justify-center bg-brand/20 text-brand rounded-full mb-4 shadow-lg">
        <!-- Lucide Lock Icon -->
        <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-7a2 2 0 00-2-2h-1V7a5 5 0 10-10 0v3H6a2 2 0 00-2 2v7a2 2 0 002 2z" />
        </svg>
      </div>
      <h2 class="text-3xl font-extrabold text-white tracking-tight">Super Admin Login</h2>
      <p class="text-slate-300 text-sm mt-1">Secure access panel</p>
    </div>

    <!-- Form -->
    <form method="post" action="admin_auth" class="mt-8 space-y-5">
      <div>
        <label class="block text-slate-200 font-semibold mb-1">Username</label>
        <input type="text" name="username" required
          class="w-full px-4 py-2.5 rounded-lg border border-white/20 bg-white/10 text-white placeholder-slate-400 focus:ring-2 focus:ring-brand focus:border-transparent transition" placeholder="Enter username">
      </div>

      <div>
        <label class="block text-slate-200 font-semibold mb-1">Password</label>
        <input type="password" name="password" required
          class="w-full px-4 py-2.5 rounded-lg border border-white/20 bg-white/10 text-white placeholder-slate-400 focus:ring-2 focus:ring-brand focus:border-transparent transition" placeholder="••••••••">
      </div>

      <button type="submit"
        class="w-full py-3 rounded-lg bg-gradient-to-r from-brand to-brand-dark text-white font-semibold tracking-wide shadow-lg hover:scale-[1.02] active:scale-[0.98] transition-transform duration-200">
        Login
      </button>
      
      <div class="text-right -mt-2 mb-2">
  <a href="forgot_password.php" class="text-sm text-brand-light hover:underline">Forgot password?</a>
</div>

    </form>
  </div>

  <!-- Animations -->
  <style>
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .animate-fadeIn { animation: fadeIn 0.8s ease-out; }
  </style>
</body>
</html>
