<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Login - MS JOBS</title>
  <link rel="icon" type="image/png" href="img/MS copy.png" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{
      --brand:#1a73e8;
      --brand-600:#1666cf;
      --text:#111827;
      --muted:#6b7280;
      --bg:#f5f7fa;
      --card:#ffffff;
      --border:#e6ebf2;
      --cta:#e74c3c;
      --cta-600:#c93f30;
      --radius:12px;
    }
    *{box-sizing:border-box}
    body{
      margin:0;
      font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
      background:var(--bg);
      color:var(--text);
    }

    /* Header */
    .site-header{background:#fff;border-bottom:1px solid var(--border)}
    .header-inner{
      max-width:1200px;margin:0 auto;padding:12px 20px;
      display:flex;align-items:center;justify-content:space-between;gap:12px;
    }
    .brand{display:flex;align-items:center;gap:12px;text-decoration:none;color:inherit}
    .brand img{height:28px;width:auto}
    .brand .title{font-weight:800}
    .brand .sub{font-size:12px;color:#10b981;font-weight:700}
    .nav{display:flex;align-items:center;gap:22px}
    .nav a{font-size:14px;text-decoration:none;color:#1f2937;font-weight:600}
    .nav a:hover{color:var(--brand)}
    .pill{border:1px solid var(--border);padding:8px 12px;border-radius:999px}

    /* Hero sweep */
    .hero{
      background:linear-gradient(180deg,var(--brand) 0%,#3b82f6 60%,#e8f0fe 60%,#e8f0fe 100%);
      height:200px;
    }

    /* Card */
    .wrap{max-width:1100px;margin:-100px auto 40px;padding:0 18px}
    .card{
      background:var(--card);
      border-radius:var(--radius);
      box-shadow:0 8px 24px rgba(0,0,0,.08);
      display:grid;grid-template-columns:1fr 1fr;overflow:hidden;
      border:1px solid var(--border);
    }

    /* Left */
    .left{
      background:#f9fbff;padding:40px;display:flex;flex-direction:column;justify-content:center;
    }
    .left h3{margin:0 0 10px;font-size:22px}
    .left p{margin:0 0 18px;color:var(--muted)}
    .benefits{list-style:none;margin:0 0 24px;padding:0}
    .benefits li{margin:10px 0;font-size:15px;color:var(--text);position:relative;padding-left:22px}
    .benefits li::before{content:"✔";position:absolute;left:0;color:var(--brand);font-weight:700}
    .cta{
      background:var(--cta);color:#fff;padding:12px 18px;border-radius:8px;font-weight:700;
      text-decoration:none;display:inline-block;text-align:center
    }
    .cta:hover{background:var(--cta-600)}

    /* Right */
    .right{padding:40px;display:flex;flex-direction:column;justify-content:center}
    .right h2{text-align:center;font-size:22px;margin:0 0 20px}
    form label{display:block;margin:14px 0 6px;font-size:13px;font-weight:600;color:#374151}
    form input{
      width:100%;padding:14px 14px;font-size:15px;border:1px solid #d1d5db;border-radius:8px;
      outline:none;transition:border-color .2s, box-shadow .2s
    }
    form input:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(26,115,232,.15)}
    button[type="submit"]{
      width:100%;margin-top:18px;padding:14px;border:0;border-radius:999px;background:var(--brand);
      color:#fff;font-weight:700;font-size:15px;cursor:pointer;transition:background .2s
    }
    button[type="submit"]:hover{background:var(--brand-600)}
    .helper{text-align:right;margin-top:6px}
    .helper a{color:var(--brand);text-decoration:none;font-weight:600}
    .helper a:hover{text-decoration:underline}
    .signup{text-align:center;margin-top:16px;font-size:14px;color:var(--muted)}
    .signup a{color:var(--brand);font-weight:700;text-decoration:none}
    .signup a:hover{text-decoration:underline}

    /* Responsive */
    @media(max-width: 900px){
      .nav{display:none}
      .card{grid-template-columns:1fr}
      .right{order:1}
      .left{order:2;border-top:1px solid var(--border)}
    }
    @media(max-width: 480px){
      .header-inner{padding:10px}
      .right,.left{padding:24px}
      form input, button[type="submit"]{font-size:14px}
    }
  </style>
</head>
<body>

  <!-- Header -->
  <header class="site-header">
    <div class="header-inner">
      <a class="brand" href="/">
        <img src="img/MS copy.png" alt="MSJOBS" />
        <div>
          <div class="title">MSJOBS</div>
          <div class="sub">No. 1 Job Site for Professionals</div>
        </div>
      </a>
      <nav class="nav">
        <a href="index.php">Home</a>
        <!--<a href="jobs">JOBS</a>-->
        <!--<a href="services">SERVICES</a>-->
        <!--<a href="explore">EXPLORE</a>-->
        <a href="login">LOGIN</a>
        <a class="pill" href="register">REGISTER</a>

      </nav>
    </div>
  </header>

  <!-- Hero -->
  <div class="hero"></div>

  <!-- Login card -->
  <main class="wrap">
    <div class="card">
      <!-- Left (benefits) -->
      <div class="left">
        <h3>Let Employers Find You!</h3>
        <p>You are just a step away from being searchable by employers.</p>
        <ul class="benefits">
          <li>Get discovered by 9,000+ Employers</li>
          <li>Apply to jobs in a single click</li>
          <li>Get matching job recommendations</li>
        </ul>
        <a href="register" class="cta">Register For Free</a>
      </div>

      <!-- Right (login form) -->
      <div class="right">
        <h2>Login to MSJOBS</h2>
        <form action="login_process" method="POST">
          <label for="email">Enter Email ID</label>
          <input type="email" name="email" required />

          <label for="password">Enter Password</label>
          <input type="password" name="password" required />

          <div class="helper"><a href="forgot_password">Forgot Password?</a></div>

          <button type="submit" name="login">Login</button>

          <div class="signup">
            New to MSJOBS? <a href="register">Register For Free</a>
          </div>
        </form>
      </div>
    </div>
  </main>

</body>
</html>
