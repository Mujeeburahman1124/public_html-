<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Register - MS JOBS</title>
  <link rel="icon" type="image/png" href="img/MS copy.png" />

  <!-- Select2 (kept exactly as you had) -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

  <style>
    :root{
      --brand:#1a73e8;           /* primary blue (Naukrigulf-like) */
      --brand-600:#1666cf;
      --muted:#6b7280;
      --bg:#f5f7fa;
      --card:#ffffff;
      --accent:#e74c3c;          /* red CTA */
      --accent-600:#c93f30;
      --border:#e6ebf2;
      --radius:12px;
    }

    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0;
      font-family:Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      background:var(--bg);
      color:#111827;
    }

    /* Header (simple brand bar) */
    .site-header{
      background:#fff;
      border-bottom:1px solid var(--border);
    }
    .header-inner{
      max-width:1200px; margin:0 auto; padding:12px 20px;
      display:flex; align-items:center; gap:12px;
    }
    .brand{
      display:flex; align-items:center; gap:12px; text-decoration:none; color:inherit;
    }
    .brand img{height:28px; width:auto; display:block}
    .brand .title{font-weight:800; letter-spacing:.2px}
    .brand .sub{font-size:12px; color:#10b981; font-weight:700}

    /* Blue sweep hero */
    .hero{
      background:linear-gradient(180deg, var(--brand) 0%, #3b82f6 60%, #e8f0fe 60%, #e8f0fe 100%);
      height:220px;
    }

    /* Card wrapper overlapping hero */
    .wrap{
      max-width:1100px; margin:-120px auto 60px; padding:0 18px;
    }
    .card{
      background:var(--card);
      border-radius:var(--radius);
      box-shadow:0 12px 30px rgba(0,0,0,.08);
      display:grid; grid-template-columns:1fr 1fr;
      overflow:hidden; border:1px solid var(--border);
    }

    /* Left info panel */
    .left{
      background:#f9fbff; border-right:1px solid var(--border);
      padding:40px 36px; display:flex; flex-direction:column; justify-content:center;
    }
    .left h2{margin:0 0 10px; font-size:22px}
    .left p{margin:0 0 18px; color:var(--muted)}
    .benefits{list-style:none; padding:0; margin:0 0 24px}
    .benefits li{display:flex; gap:10px; align-items:flex-start; margin:10px 0; font-size:15px}
    .benefits svg{flex:0 0 20px}
    .cta{
      width:max-content; background:var(--accent); color:#fff; border:0;
      padding:12px 18px; border-radius:8px; font-weight:700; cursor:pointer;
    }
    .cta:hover{background:var(--accent-600)}
    .mini-note{margin-top:10px; font-size:12px; color:var(--muted)}

    /* Right form panel */
    .right{
      padding:40px 36px; display:flex; flex-direction:column; justify-content:center;
    }
    .right h2{margin:0 0 18px; text-align:center; font-size:22px}
    .divider{height:1px; background:#eceff4; margin:8px 0 18px}

    /* Form elements (keep IDs/names intact) */
    form{width:100%}
    label{display:block; margin:14px 0 6px; font-size:13px; color:#374151; font-weight:600}
    input, select, textarea{
      width:100%; padding:12px 14px; border:1px solid #d1d5db; border-radius:8px;
      outline:none; font-size:14px; background:#fff; transition:border-color .2s, box-shadow .2s;
    }
    input:focus, select:focus, textarea:focus{
      border-color:var(--brand);
      box-shadow:0 0 0 3px rgba(26,115,232,.15);
    }
    textarea{min-height:110px; resize:vertical}

    /* Select2 width fix */
    .select2-container{width:100% !important}

    /* Role sections (unchanged behavior) */
    #jobseeker_fields, #employer_fields{display:none}

    /* Submit */
    button[type="submit"]{
      width:100%; margin-top:18px; padding:12px 16px; border:0; border-radius:999px;
      background:var(--brand); color:#fff; font-weight:800; cursor:pointer;
      transition:background .2s, transform .06s ease;
    }
    button[type="submit"]:hover{background:var(--brand-600)}
    button[type="submit"]:active{transform:translateY(1px)}

    /* Simple grid helpers inside form */
    .grid-2{display:grid; grid-template-columns:1fr 1fr; gap:14px}
    .grid-3{display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px}

    /* Responsive */
    @media (max-width: 980px){
      .card{grid-template-columns:1fr}
      .left{order:2}
      .right{order:1}
    }
  </style>
</head>
<body>

  <!-- Header -->
  <header class="site-header">
    <div class="header-inner">
      <a class="brand" href="/">
        <img src="img/MS copy.png" alt="MS JOBS" />
        <div>
          <div class="title">MS JOBS</div>
          <div class="sub">No. 1 Job Site for Professionals</div>
        </div>
      </a>
    </div>
  </header>

  <!-- Blue sweep -->
  <div class="hero" aria-hidden="true"></div>

  <!-- Card -->
  <main class="wrap">
    <section class="card" role="region" aria-label="Register panel">
      <!-- Left info panel -->
      <div class="left">
        <h2>Let Employers Find You!</h2>
        <p>Create your profile and get discovered by top employers.</p>
        <ul class="benefits">
          <li>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <circle cx="12" cy="12" r="10" stroke="#1a73e8" stroke-width="2"></circle>
              <path d="M7 12.5l3 3 7-7" stroke="#1a73e8" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Get discovered by 9,000+ Employers
          </li>
          <li>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <circle cx="12" cy="12" r="10" stroke="#1a73e8" stroke-width="2"></circle>
              <path d="M7 12.5l3 3 7-7" stroke="#1a73e8" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Apply to jobs in a single click
          </li>
          <li>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <circle cx="12" cy="12" r="10" stroke="#1a73e8" stroke-width="2"></circle>
              <path d="M7 12.5l3 3 7-7" stroke="#1a73e8" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Get matching job recommendations
          </li>
        </ul>
        <a href="login" class="cta">Already registered? Login</a>
        <div class="mini-note">It takes less than 2 minutes.</div>
      </div>

      <!-- Right form panel -->
      <div class="right">
        <h2>Create Your MS JOBS Account</h2>
        <div class="divider" aria-hidden="true"></div>

        <!-- FORM (kept same action, ids, names, enctype) -->
        <form action="register_process" method="POST" enctype="multipart/form-data" id="registerForm">
          <label for="user_type">I am a:</label>
          <select name="user_type" id="user_type" required>
            <option value="">-- Select Role --</option>
            <option value="jobseeker">Job Seeker</option>
            <option value="employer">Employer</option>
          </select>

          <div class="grid-2">
            <div>
              <label for="email">Email:</label>
              <input type="email" name="email" id="email" required>
            </div>
            <div>
              <label for="password">Password:</label>
              <input type="password" name="password" id="password" required>
            </div>
          </div>

          <!-- JOB SEEKER -->
          <div id="jobseeker_fields">
            <div class="grid-2">
              <div>
                <label for="full_name">Full Name:</label>
                <input type="text" name="full_name" id="full_name" class="jobseeker_field">
              </div>
              <div>
                <label for="nationality">Nationality:</label>
                <select name="nationality" id="nationality" class="jobseeker_field">
                  <option value="">-- Select Nationality --</option>
                  <option value="Sri Lankan">Sri Lankan</option>
                  <option value="Indian">Indian</option>
                  <option value="Pakistani">Pakistani</option>
                  <option value="Bangladeshi">Bangladeshi</option>
                  <option value="Nepali">Nepali</option>
                  <option value="Maldivian">Maldivian</option>
                  <option value="Chinese">Chinese</option>
                  <option value="Japanese">Japanese</option>
                  <option value="British">British</option>
                  <option value="American">American</option>
                  <option value="Australian">Australian</option>
                  <option value="Canadian">Canadian</option>
                  <option value="Other">Other</option>
                </select>
              </div>
            </div>

            <div class="grid-3">
              <div>
                <label for="age">Age:</label>
                <input type="number" name="age" id="age" class="jobseeker_field">
              </div>
              <div>
                <label for="gender">Gender:</label>
                <select name="gender" id="gender" class="jobseeker_field">
                  <option value="">-- Select Gender --</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                </select>
              </div>
              <div>
                <label for="language">Language:</label>
                <select name="language" id="language" class="jobseeker_field">
                  <option value="">-- Select Language --</option>
                  <option value="English">English</option>
                  <option value="Spanish">Spanish</option>
                  <option value="Mandarin">Mandarin</option>
                  <option value="Hindi">Hindi</option>
                  <option value="Arabic">Arabic</option>
                  <option value="Portuguese">Portuguese</option>
                  <option value="Bengali">Bengali</option>
                  <option value="Russian">Russian</option>
                  <option value="Japanese">Japanese</option>
                  <option value="Punjabi">Punjabi</option>
                  <option value="German">German</option>
                  <option value="French">French</option>
                  <option value="Tamil">Tamil</option>
                  <option value="Telugu">Telugu</option>
                  <option value="Urdu">Urdu</option>
                  <option value="Korean">Korean</option>
                  <option value="Italian">Italian</option>
                  <option value="Turkish">Turkish</option>
                  <option value="Vietnamese">Vietnamese</option>
                  <option value="Malay">Malay</option>
                  <option value="Sinhala">Sinhala</option>
                </select>
              </div>
            </div>

            <div class="grid-2">
              <div>
                <label for="religion">Religion:</label>
                <select name="religion" id="religion" class="jobseeker_field">
                  <option value="">-- Select Religion --</option>
                  <option value="Hinduism">Hinduism</option>
                  <option value="Buddhism">Buddhism</option>
                  <option value="Islam">Islam</option>
                  <option value="Christianity">Christianity</option>
                  <option value="Catholicism">Catholicism</option>
                  <option value="Sikhism">Sikhism</option>
                  <option value="Judaism">Judaism</option>
                  <option value="Atheist">Atheist</option>
                  <option value="Agnostic">Agnostic</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div>
                <label for="position">Position Applied:</label>
                <input type="text" name="position" id="position" class="jobseeker_field">
              </div>
            </div>

            <div class="grid-2">
              <div>
                <label for="whatsapp">WhatsApp Number (insert Country code):</label>
                <input type="text" name="whatsapp" id="whatsapp" class="jobseeker_field" placeholder="Enter your WhatsApp number">
              </div>
              <div>
                <label for="salary_range">Salary Range (USD):</label>
                <select name="salary_range" id="salary_range" class="jobseeker_field">
                  <option value="">Select Salary Range</option>
                  <option value="500 - 1000">500 - 1000</option>
                  <option value="1000 - 1500">1000 - 1500</option>
                  <option value="1500 - 2000">1500 - 2000</option>
                  <option value="2000 - 3000">2000 - 3000</option>
                  <option value="3000 - 5000">3000 - 5000</option>
                  <option value="5000 - 7000">5000 - 7000</option>
                  <option value="7000 - 10000">7000 - 10000</option>
                  <option value="10000 - 15000">10000 - 15000</option>
                  <option value="15000 - 20000">15000 - 20000</option>
                  <option value="20000+">20000+</option>
                </select>
              </div>
            </div>

            <div class="grid-2">
              <div>
                <label for="expected_position">Expected Position:</label>
                <input type="text" name="expected_position" id="expected_position" class="jobseeker_field">
              </div>
              <div>
                <label for="current_job_status">Current Job Status:</label>
                <input type="text" name="current_job_status" id="current_job_status" class="jobseeker_field">
              </div>
            </div>

            <label for="experience">Experience:</label>
            <select name="experience" id="experience" class="jobseeker_field">
              <option value="">Select experience</option>
              <option value="0-2">0–2 years</option>
              <option value="2-3">2–3 years</option>
              <option value="3-5">3–5 years</option>
              <option value="5-10">5–10 years</option>
              <option value="10-15">10–15 years</option>
              <option value="15-20">15–20 years</option>
              <option value="20+">20+ years</option>
            </select>

            <div class="grid-2">
              <div>
                <label for="countryDropdown">Country:</label>
                <!-- (Kept your full list) -->
                <select name="country" id="countryDropdown" class="jobseeker_field">
                  <option value="">-- Select Country --</option>
                  <!-- your long country list stays here unchanged -->
                  <option value="Afghanistan">Afghanistan</option>
                  <option value="Albania">Albania</option>
                  <option value="Algeria">Algeria</option>
                  <option value="Andorra">Andorra</option>
                  <option value="Angola">Angola</option>
                  <option value="Antigua and Barbuda">Antigua and Barbuda</option>
                  <option value="Argentina">Argentina</option>
                  <option value="Armenia">Armenia</option>
                  <option value="Australia">Australia</option>
                  <option value="Austria">Austria</option>
                  <option value="Azerbaijan">Azerbaijan</option>
                  <option value="Bahamas">Bahamas</option>
                  <option value="Bahrain">Bahrain</option>
                  <option value="Bangladesh">Bangladesh</option>
                  <option value="Barbados">Barbados</option>
                  <option value="Belarus">Belarus</option>
                  <option value="Belgium">Belgium</option>
                  <option value="Belize">Belize</option>
                  <option value="Benin">Benin</option>
                  <option value="Bhutan">Bhutan</option>
                  <option value="Bolivia">Bolivia</option>
                  <option value="Bosnia and Herzegovina">Bosnia and Herzegovina</option>
                  <option value="Botswana">Botswana</option>
                  <option value="Brazil">Brazil</option>
                  <option value="Brunei">Brunei</option>
                  <option value="Bulgaria">Bulgaria</option>
                  <option value="Burkina Faso">Burkina Faso</option>
                  <option value="Burundi">Burundi</option>
                  <option value="Cambodia">Cambodia</option>
                  <option value="Cameroon">Cameroon</option>
                  <option value="Canada">Canada</option>
                  <option value="Cape Verde">Cape Verde</option>
                  <option value="Central African Republic">Central African Republic</option>
                  <option value="Chad">Chad</option>
                  <option value="Chile">Chile</option>
                  <option value="China">China</option>
                  <option value="Colombia">Colombia</option>
                  <option value="Comoros">Comoros</option>
                  <option value="Congo (Brazzaville)">Congo (Brazzaville)</option>
                  <option value="Congo (Kinshasa)">Congo (Kinshasa)</option>
                  <option value="Costa Rica">Costa Rica</option>
                  <option value="Croatia">Croatia</option>
                  <option value="Cuba">Cuba</option>
                  <option value="Cyprus">Cyprus</option>
                  <option value="Czech Republic">Czech Republic</option>
                  <option value="Denmark">Denmark</option>
                  <option value="Djibouti">Djibouti</option>
                  <option value="Dominica">Dominica</option>
                  <option value="Dominican Republic">Dominican Republic</option>
                  <option value="Ecuador">Ecuador</option>
                  <option value="Egypt">Egypt</option>
                  <option value="El Salvador">El Salvador</option>
                  <option value="Equatorial Guinea">Equatorial Guinea</option>
                  <option value="Eritrea">Eritrea</option>
                  <option value="Estonia">Estonia</option>
                  <option value="Eswatini">Eswatini</option>
                  <option value="Ethiopia">Ethiopia</option>
                  <option value="Fiji">Fiji</option>
                  <option value="Finland">Finland</option>
                  <option value="France">France</option>
                  <option value="Gabon">Gabon</option>
                  <option value="Gambia">Gambia</option>
                  <option value="Georgia">Georgia</option>
                  <option value="Germany">Germany</option>
                  <option value="Ghana">Ghana</option>
                  <option value="Greece">Greece</option>
                  <option value="Grenada">Grenada</option>
                  <option value="Guatemala">Guatemala</option>
                  <option value="Guinea">Guinea</option>
                  <option value="Guinea-Bissau">Guinea-Bissau</option>
                  <option value="Guyana">Guyana</option>
                  <option value="Haiti">Haiti</option>
                  <option value="Honduras">Honduras</option>
                  <option value="Hungary">Hungary</option>
                  <option value="Iceland">Iceland</option>
                  <option value="India">India</option>
                  <option value="Indonesia">Indonesia</option>
                  <option value="Iran">Iran</option>
                  <option value="Iraq">Iraq</option>
                  <option value="Ireland">Ireland</option>
                  <option value="Israel">Israel</option>
                  <option value="Italy">Italy</option>
                  <option value="Jamaica">Jamaica</option>
                  <option value="Japan">Japan</option>
                  <option value="Jordan">Jordan</option>
                  <option value="Kazakhstan">Kazakhstan</option>
                  <option value="Kenya">Kenya</option>
                  <option value="Kiribati">Kiribati</option>
                  <option value="Korea, North">Korea, North</option>
                  <option value="Korea, South">Korea, South</option>
                  <option value="Kosovo">Kosovo</option>
                  <option value="Kuwait">Kuwait</option>
                  <option value="Kyrgyzstan">Kyrgyzstan</option>
                  <option value="Laos">Laos</option>
                  <option value="Latvia">Latvia</option>
                  <option value="Lebanon">Lebanon</option>
                  <option value="Lesotho">Lesotho</option>
                  <option value="Liberia">Liberia</option>
                  <option value="Libya">Libya</option>
                  <option value="Liechtenstein">Liechtenstein</option>
                  <option value="Lithuania">Lithuania</option>
                  <option value="Luxembourg">Luxembourg</option>
                  <option value="Madagascar">Madagascar</option>
                  <option value="Malawi">Malawi</option>
                  <option value="Malaysia">Malaysia</option>
                  <option value="Maldives">Maldives</option>
                  <option value="Mali">Mali</option>
                  <option value="Malta">Malta</option>
                  <option value="Marshall Islands">Marshall Islands</option>
                  <option value="Mauritania">Mauritania</option>
                  <option value="Mauritius">Mauritius</option>
                  <option value="Mexico">Mexico</option>
                  <option value="Micronesia">Micronesia</option>
                  <option value="Moldova">Moldova</option>
                  <option value="Monaco">Monaco</option>
                  <option value="Mongolia">Mongolia</option>
                  <option value="Montenegro">Montenegro</option>
                  <option value="Morocco">Morocco</option>
                  <option value="Mozambique">Mozambique</option>
                  <option value="Myanmar (Burma)">Myanmar (Burma)</option>
                  <option value="Namibia">Namibia</option>
                  <option value="Nauru">Nauru</option>
                  <option value="Nepal">Nepal</option>
                  <option value="Netherlands">Netherlands</option>
                  <option value="New Zealand">New Zealand</option>
                  <option value="Nicaragua">Nicaragua</option>
                  <option value="Niger">Niger</option>
                  <option value="Nigeria">Nigeria</option>
                  <option value="North Macedonia">North Macedonia</option>
                  <option value="Norway">Norway</option>
                  <option value="Oman">Oman</option>
                  <option value="Pakistan">Pakistan</option>
                  <option value="Palau">Palau</option>
                  <option value="Palestine">Palestine</option>
                  <option value="Panama">Panama</option>
                  <option value="Papua New Guinea">Papua New Guinea</option>
                  <option value="Paraguay">Paraguay</option>
                  <option value="Peru">Peru</option>
                  <option value="Philippines">Philippines</option>
                  <option value="Poland">Poland</option>
                  <option value="Portugal">Portugal</option>
                  <option value="Qatar">Qatar</option>
                  <option value="Romania">Romania</option>
                  <option value="Russia">Russia</option>
                  <option value="Rwanda">Rwanda</option>
                  <option value="Saint Kitts and Nevis">Saint Kitts and Nevis</option>
                  <option value="Saint Lucia">Saint Lucia</option>
                  <option value="Saint Vincent and the Grenadines">Saint Vincent and the Grenadines</option>
                  <option value="Samoa">Samoa</option>
                  <option value="San Marino">San Marino</option>
                  <option value="Sao Tome and Principe">Sao Tome and Principe</option>
                  <option value="Saudi Arabia">Saudi Arabia</option>
                  <option value="Senegal">Senegal</option>
                  <option value="Serbia">Serbia</option>
                  <option value="Seychelles">Seychelles</option>
                  <option value="Sierra Leone">Sierra Leone</option>
                  <option value="Singapore">Singapore</option>
                  <option value="Slovakia">Slovakia</option>
                  <option value="Slovenia">Slovenia</option>
                  <option value="Solomon Islands">Solomon Islands</option>
                  <option value="Somalia">Somalia</option>
                  <option value="South Africa">South Africa</option>
                  <option value="South Sudan">South Sudan</option>
                  <option value="Spain">Spain</option>
                  <option value="Sri Lanka">Sri Lanka</option>
                  <option value="Sudan">Sudan</option>
                  <option value="Suriname">Suriname</option>
                  <option value="Sweden">Sweden</option>
                  <option value="Switzerland">Switzerland</option>
                  <option value="Syria">Syria</option>
                  <option value="Taiwan">Taiwan</option>
                  <option value="Tajikistan">Tajikistan</option>
                  <option value="Tanzania">Tanzania</option>
                  <option value="Thailand">Thailand</option>
                  <option value="Timor-Leste">Timor-Leste</option>
                  <option value="Togo">Togo</option>
                  <option value="Tonga">Tonga</option>
                  <option value="Trinidad and Tobago">Trinidad and Tobago</option>
                  <option value="Tunisia">Tunisia</option>
                  <option value="Turkey">Turkey</option>
                  <option value="Turkmenistan">Turkmenistan</option>
                  <option value="Tuvalu">Tuvalu</option>
                  <option value="Uganda">Uganda</option>
                  <option value="Ukraine">Ukraine</option>
                  <option value="United Arab Emirates">United Arab Emirates</option>
                  <option value="United Kingdom">United Kingdom</option>
                  <option value="United States">United States</option>
                  <option value="Uruguay">Uruguay</option>
                  <option value="Uzbekistan">Uzbekistan</option>
                  <option value="Vanuatu">Vanuatu</option>
                  <option value="Vatican City">Vatican City</option>
                  <option value="Venezuela">Venezuela</option>
                  <option value="Vietnam">Vietnam</option>
                  <option value="Yemen">Yemen</option>
                  <option value="Zambia">Zambia</option>
                  <option value="Zimbabwe">Zimbabwe</option>
                </select>
              </div>
              <div>
                <label for="expected_countries">Select Your Job Expected Countries:</label>
                <select name="expected_countries[]" id="expected_countries" multiple>
                  <!-- (Kept your full list) -->
                  <option value="Afghanistan">Afghanistan</option>
                  <option value="Albania">Albania</option>
                  <option value="Algeria">Algeria</option>
                  <option value="Andorra">Andorra</option>
                  <option value="Angola">Angola</option>
                  <option value="Antigua and Barbuda">Antigua and Barbuda</option>
                  <option value="Argentina">Argentina</option>
                  <option value="Armenia">Armenia</option>
                  <option value="Australia">Australia</option>
                  <option value="Austria">Austria</option>
                  <option value="Azerbaijan">Azerbaijan</option>
                  <option value="Bahamas">Bahamas</option>
                  <option value="Bahrain">Bahrain</option>
                  <option value="Bangladesh">Bangladesh</option>
                  <option value="Barbados">Barbados</option>
                  <option value="Belarus">Belarus</option>
                  <option value="Belgium">Belgium</option>
                  <option value="Belize">Belize</option>
                  <option value="Benin">Benin</option>
                  <option value="Bhutan">Bhutan</option>
                  <option value="Bolivia">Bolivia</option>
                  <option value="Bosnia and Herzegovina">Bosnia and Herzegovina</option>
                  <option value="Botswana">Botswana</option>
                  <option value="Brazil">Brazil</option>
                  <option value="Brunei">Brunei</option>
                  <option value="Bulgaria">Bulgaria</option>
                  <option value="Burkina Faso">Burkina Faso</option>
                  <option value="Burundi">Burundi</option>
                  <option value="Côte d'Ivoire">Côte d'Ivoire</option>
                  <option value="Cabo Verde">Cabo Verde</option>
                  <option value="Cambodia">Cambodia</option>
                  <option value="Cameroon">Cameroon</option>
                  <option value="Canada">Canada</option>
                  <option value="Central African Republic">Central African Republic</option>
                  <option value="Chad">Chad</option>
                  <option value="Chile">Chile</option>
                  <option value="China">China</option>
                  <option value="Colombia">Colombia</option>
                  <option value="Comoros">Comoros</option>
                  <option value="Congo, Democratic Republic of the">Congo, Democratic Republic of the</option>
                  <option value="Congo, Republic of the">Congo, Republic of the</option>
                  <option value="Costa Rica">Costa Rica</option>
                  <option value="Croatia">Croatia</option>
                  <option value="Cuba">Cuba</option>
                  <option value="Cyprus">Cyprus</option>
                  <option value="Czechia">Czechia</option>
                  <option value="Denmark">Denmark</option>
                  <option value="Djibouti">Djibouti</option>
                  <option value="Dominica">Dominica</option>
                  <option value="Dominican Republic">Dominican Republic</option>
                  <option value="Ecuador">Ecuador</option>
                  <option value="Egypt">Egypt</option>
                  <option value="El Salvador">El Salvador</option>
                  <option value="Equatorial Guinea">Equatorial Guinea</option>
                  <option value="Eritrea">Eritrea</option>
                  <option value="Estonia">Estonia</option>
                  <option value="Eswatini">Eswatini</option>
                  <option value="Ethiopia">Ethiopia</option>
                  <option value="Federated States of Micronesia">Federated States of Micronesia</option>
                  <option value="Fiji">Fiji</option>
                  <option value="Finland">Finland</option>
                  <option value="France">France</option>
                  <option value="Gabon">Gabon</option>
                  <option value="Gambia">Gambia</option>
                  <option value="Georgia">Georgia</option>
                  <option value="Germany">Germany</option>
                  <option value="Ghana">Ghana</option>
                  <option value="Greece">Greece</option>
                  <option value="Grenada">Grenada</option>
                  <option value="Guatemala">Guatemala</option>
                  <option value="Guinea">Guinea</option>
                  <option value="Guinea-Bissau">Guinea-Bissau</option>
                  <option value="Guyana">Guyana</option>
                  <option value="Haiti">Haiti</option>
                  <option value="Honduras">Honduras</option>
                  <option value="Hungary">Hungary</option>
                  <option value="Iceland">Iceland</option>
                  <option value="India">India</option>
                  <option value="Indonesia">Indonesia</option>
                  <option value="Iran">Iran</option>
                  <option value="Iraq">Iraq</option>
                  <option value="Ireland">Ireland</option>
                  <option value="Israel">Israel</option>
                  <option value="Italy">Italy</option>
                  <option value="Jamaica">Jamaica</option>
                  <option value="Japan">Japan</option>
                  <option value="Jordan">Jordan</option>
                  <option value="Kazakhstan">Kazakhstan</option>
                  <option value="Kenya">Kenya</option>
                  <option value="Kiribati">Kiribati</option>
                  <option value="Kuwait">Kuwait</option>
                  <option value="Kyrgyzstan">Kyrgyzstan</option>
                  <option value="Laos">Laos</option>
                  <option value="Latvia">Latvia</option>
                  <option value="Lebanon">Lebanon</option>
                  <option value="Lesotho">Lesotho</option>
                  <option value="Liberia">Liberia</option>
                  <option value="Libya">Libya</option>
                  <option value="Liechtenstein">Liechtenstein</option>
                  <option value="Lithuania">Lithuania</option>
                  <option value="Luxembourg">Luxembourg</option>
                  <option value="Madagascar">Madagascar</option>
                  <option value="Malawi">Malawi</option>
                  <option value="Malaysia">Malaysia</option>
                  <option value="Maldives">Maldives</option>
                  <option value="Mali">Mali</option>
                  <option value="Malta">Malta</option>
                  <option value="Marshall Islands">Marshall Islands</option>
                  <option value="Mauritania">Mauritania</option>
                  <option value="Mauritius">Mauritius</option>
                  <option value="Mexico">Mexico</option>
                  <option value="Moldova">Moldova</option>
                  <option value="Monaco">Monaco</option>
                  <option value="Mongolia">Mongolia</option>
                  <option value="Montenegro">Montenegro</option>
                  <option value="Morocco">Morocco</option>
                  <option value="Mozambique">Mozambique</option>
                  <option value="Myanmar">Myanmar</option>
                  <option value="Namibia">Namibia</option>
                  <option value="Nauru">Nauru</option>
                  <option value="Nepal">Nepal</option>
                  <option value="Netherlands">Netherlands</option>
                  <option value="New Zealand">New Zealand</option>
                  <option value="Nicaragua">Nicaragua</option>
                  <option value="Niger">Niger</option>
                  <option value="Nigeria">Nigeria</option>
                  <option value="North Korea">North Korea</option>
                  <option value="North Macedonia">North Macedonia</option>
                  <option value="Norway">Norway</option>
                  <option value="Oman">Oman</option>
                  <option value="Pakistan">Pakistan</option>
                  <option value="Palau">Palau</option>
                  <option value="Panama">Panama</option>
                  <option value="Papua New Guinea">Papua New Guinea</option>
                  <option value="Paraguay">Paraguay</option>
                  <option value="Peru">Peru</option>
                  <option value="Philippines">Philippines</option>
                  <option value="Poland">Poland</option>
                  <option value="Portugal">Portugal</option>
                  <option value="Qatar">Qatar</option>
                  <option value="Romania">Romania</option>
                  <option value="Russia">Russia</option>
                  <option value="Rwanda">Rwanda</option>
                  <option value="Saint Kitts and Nevis">Saint Kitts and Nevis</option>
                  <option value="Saint Lucia">Saint Lucia</option>
                  <option value="Saint Vincent and the Grenadines">Saint Vincent and the Grenadines</option>
                  <option value="Samoa">Samoa</option>
                  <option value="San Marino">San Marino</option>
                  <option value="Sao Tome and Principe">Sao Tome and Principe</option>
                  <option value="Saudi Arabia">Saudi Arabia</option>
                  <option value="Senegal">Senegal</option>
                  <option value="Serbia">Serbia</option>
                  <option value="Seychelles">Seychelles</option>
                  <option value="Sierra Leone">Sierra Leone</option>
                  <option value="Singapore">Singapore</option>
                  <option value="Slovakia">Slovakia</option>
                  <option value="Slovenia">Slovenia</option>
                  <option value="Solomon Islands">Solomon Islands</option>
                  <option value="Somalia">Somalia</option>
                  <option value="South Africa">South Africa</option>
                  <option value="South Korea">South Korea</option>
                  <option value="South Sudan">South Sudan</option>
                  <option value="Spain">Spain</option>
                  <option value="Sri Lanka">Sri Lanka</option>
                  <option value="Sudan">Sudan</option>
                  <option value="Suriname">Suriname</option>
                  <option value="Sweden">Sweden</option>
                  <option value="Switzerland">Switzerland</option>
                  <option value="Syria">Syria</option>
                  <option value="Taiwan">Taiwan</option>
                  <option value="Tajikistan">Tajikistan</option>
                  <option value="Tanzania">Tanzania</option>
                  <option value="Thailand">Thailand</option>
                  <option value="Timor-Leste">Timor-Leste</option>
                  <option value="Togo">Togo</option>
                  <option value="Tonga">Tonga</option>
                  <option value="Trinidad and Tobago">Trinidad and Tobago</option>
                  <option value="Tunisia">Tunisia</option>
                  <option value="Turkey">Turkey</option>
                  <option value="Turkmenistan">Turkmenistan</option>
                  <option value="Tuvalu">Tuvalu</option>
                  <option value="Uganda">Uganda</option>
                  <option value="Ukraine">Ukraine</option>
                  <option value="United Arab Emirates">United Arab Emirates</option>
                  <option value="United Kingdom">United Kingdom</option>
                  <option value="United States">United States</option>
                  <option value="Uruguay">Uruguay</option>
                  <option value="Uzbekistan">Uzbekistan</option>
                  <option value="Vanuatu">Vanuatu</option>
                  <option value="Vatican City">Vatican City</option>
                  <option value="Venezuela">Venezuela</option>
                  <option value="Vietnam">Vietnam</option>
                  <option value="Yemen">Yemen</option>
                  <option value="Zambia">Zambia</option>
                  <option value="Zimbabwe">Zimbabwe</option>
                </select>
              </div>
            </div>

            <label for="category">Expected category:</label>
            <select name="category" required>
              <option value="">Select Category</option>
              <option value="Cleaning & Hospitality">Cleaning & Hospitality</option>
              <option value="Engineering & Contractions">Engineering & Contractions</option>
              <option value="Maintenance">Maintenance</option>
              <option value="Manufacturing">Manufacturing</option>
              <option value="Hotels & Restaurants">Hotels & Restaurants</option>
              <option value="Transportation">Transportation</option>
              <option value="Delivery Service">Delivery Service</option>
              <option value="Helpers">Helpers</option>
              <option value="Accounting & Finance">Accounting & Finance</option>
              <option value="Auto Mobile">Auto Mobile</option>
              <option value="Beauty/Salon">Beauty/Salon</option>
              <option value="Customer Service / Call Center">Customer Service / Call Center</option>
              <option value="Data Management & Analyst">Data Management & Analyst</option>
              <option value="Graphic Designer">Graphic Designer</option>
              <option value="Admin & HR">Admin & HR</option>
              <option value="Sales / Business Development">Sales / Business Development</option>
              <option value="Secretarial / Front Office">Secretarial / Front Office</option>
              <option value="Security Guard">Security Guard</option>
              <option value="Sports & Fitness">Sports & Fitness</option>
              <option value="Travel & Tourism">Travel & Tourism</option>
              <option value="Medical & Health Care">Medical & Health Care</option>
              <option value="Media, Art & Entertainment">Media, Art & Entertainment</option>
              <option value="Marketing & Advertising">Marketing & Advertising</option>
              <option value="Marine Captain / Crew">Marine Captain / Crew</option>
              <option value="Logistics & Distribution">Logistics & Distribution</option>
              <option value="Legal Services">Legal Services</option>
              <option value="Education">Education</option>
              <option value="Drivers">Drivers</option>
              <option value="hypermarket">hypermarket</option>
              <option value="supermarket">supermarket</option>
              <option value="Other">Other</option>
            </select>

            <label for="present_location">Presently Located In:</label>
            <select name="present_location" id="present_location" required>
              <option value="">Select Country</option>
              <!-- kept your list as-is (shortened here for brevity) -->
              <option value="Afghanistan">Afghanistan</option>
              <option value="Albania">Albania</option>
              <option value="Algeria">Algeria</option>
              <option value="Andorra">Andorra</option>
              <option value="Angola">Angola</option>
              <!-- ... (your full list remains) ... -->
              <option value="Zimbabwe">Zimbabwe</option>
              <option value="Other">Other</option>
            </select>

            <div class="grid-2">
              <div>
                <label for="cv_file">Upload CV (PDF Only):</label>
                <input type="file" name="cv_file" id="cv_file" accept="application/pdf" class="jobseeker_field">
              </div>
              <div>
                <label for="profile_picture">Profile Picture:</label>
                <input type="file" name="profile_picture" id="profile_picture" accept="image/*" class="jobseeker_field">
              </div>
            </div>
          </div>

          <!-- EMPLOYER -->
          <div id="employer_fields">
            <label for="company_name">Company Name:</label>
            <input type="text" name="company_name" id="company_name" class="employer_field">

            <label for="company_description">Company Description:</label>
            <textarea name="company_description" id="company_description" class="employer_field"></textarea>

            <div class="grid-2">
              <div>
                <label for="contact_person">Contact Person:</label>
                <input type="text" name="contact_person" id="contact_person" class="employer_field">
              </div>
              <div>
                <label for="phone">Phone:</label>
                <input type="text" name="phone" id="phone" class="employer_field">
              </div>
            </div>

            <div class="grid-2">
              <div>
                <label for="employer_country">Country:</label>
                <input type="text" name="employer_country" id="employer_country" class="employer_field">
              </div>
              <div>
                <label for="logo">Upload Company Logo:</label>
                <input type="file" name="logo" id="logo" accept="image/*" class="employer_field">
              </div>
            </div>

            <label for="company_license">Upload Company License:</label>
            <input type="file" name="company_license" id="company_license" accept="image/*" class="employer_field">
          </div>

          <button type="submit" name="register">Register</button>
        </form>
      </div>
    </section>
  </main>

  <script>
    // Keep your existing behavior
    document.getElementById('user_type').addEventListener('change', function () {
      const jobFields = document.getElementById('jobseeker_fields');
      const empFields = document.getElementById('employer_fields');
      const value = this.value;

      jobFields.style.display = value === 'jobseeker' ? 'block' : 'none';
      empFields.style.display = value === 'employer' ? 'block' : 'none';

      document.querySelectorAll('.jobseeker_field, .employer_field').forEach(el => el.required = false);
      if (value === 'jobseeker') {
        document.querySelectorAll('.jobseeker_field').forEach(el => el.required = true);
      } else if (value === 'employer') {
        document.querySelectorAll('.employer_field').forEach(el => el.required = true);
      }
    });

    // Select2 init (unchanged)
    $(document).ready(function() {
      $('#expected_countries').select2({
        placeholder: "Select countries",
        allowClear: true
      });
    });
  </script>
</body>
</html>
