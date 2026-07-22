<?php
include 'config.php';

$stmt = $conn->prepare("SELECT * FROM faqs ORDER BY id ASC");
$stmt->execute();
$result = $stmt->get_result();
$faqs = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>FAQ - Frequently Asked Questions</title>
  <link rel="icon" href="images/msjobs-logo.png" type="image/png">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#4f46e5',
            whatsapp: '#25D366',
          },
          animation: {
            bounceOnce: 'bounce 1s ease-in-out 1',
          }
        }
      }
    }
  </script>


       <!-- Tailwind Navbar Start -->
<nav class="bg-white shadow sticky top-0 z-50">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center h-16">
      <!-- Logo Section -->
      <a href="index.html" class="flex items-center space-x-2">
        <img src="img/MS copy.png" alt="MSJOBS Logo" class="h-10">
        <h1 class="text-xl font-bold text-blue-600">MSJOBS</h1>
      </a>

      <!-- Mobile menu button -->
      <div class="-mr-2 flex md:hidden">
        <button type="button" class="inline-flex items-center justify-center p-2 rounded-md text-gray-500 hover:text-blue-600 hover:bg-gray-100 focus:outline-none" onclick="document.getElementById('mobile-menu').classList.toggle('hidden')">
          <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
            <path class="inline" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
      </div>

      <!-- Desktop menu -->
      <div class="hidden md:flex md:items-center space-x-6">
        <a href="index.html" class="text-gray-700 hover:text-blue-600 font-medium">Home</a>

     

        <!-- Pages Dropdown -->
        
        <a href="contact.html" class="text-gray-700 hover:text-blue-600 font-medium">Contact</a>
        <a href="login.php" class="ml-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2 rounded transition">LOGIN <i class="fa fa-arrow-right ml-2"></i></a>
      </div>
    </div>
  </div>

  <!-- Mobile Menu -->
  <div class="md:hidden hidden" id="mobile-menu">
    <div class="pt-4 pb-4 space-y-2">
      <a href="index.html" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Home</a>

      <div class="border-t border-gray-200"></div>
      <span class="block px-4 py-2 font-medium text-gray-500">Pages</span>
      <a href="contact.html" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Contact</a>
      <a href="login.php" class="block px-4 py-2 text-white bg-blue-600 hover:bg-blue-700 font-medium text-center rounded">LOGIN</a>
    </div>
  </div>
</nav>
<!-- Tailwind Navbar End -->
</head>
<body class="bg-gray-50 text-slate-800 font-sans relative">

  <!-- FAQ Section -->
  <div class="max-w-4xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
    <div class="text-center mb-12">
      <h2 class="text-4xl font-extrabold text-primary">Frequently Asked Questions</h2>
      <p class="mt-4 text-slate-600 text-base">Find quick answers to common queries about our services.</p>
    </div>

    <div class="space-y-6">
      <?php foreach ($faqs as $faq): ?>
        <div x-data="{ open: false }" class="bg-white rounded-xl shadow p-5 border border-gray-200">
          <button 
            @click="open = !open" 
            class="w-full flex justify-between items-center text-left text-lg font-medium text-primary focus:outline-none"
          >
            <span><?= htmlspecialchars($faq['question']) ?></span>
            <svg :class="{ 'rotate-180': open }" class="h-5 w-5 transform transition-transform duration-200 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>
          <div x-show="open" x-transition class="mt-3 text-sm text-slate-600 leading-relaxed">
            <?= nl2br(htmlspecialchars($faq['answer'])) ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- WhatsApp Enquiry Button -->
  <a href="https://wa.me/qr/VPAU2D3ZF5SHB1" 
     class="fixed bottom-6 right-6 z-50 bg-whatsapp text-white rounded-full shadow-lg p-4 hover:scale-110 transition-transform duration-300 animate-bounce"
     target="_blank"
     title="Chat with us on WhatsApp">
    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="white" viewBox="0 0 448 512">
      <path d="M380.9 97.1C339-6.3 223.4-31.8 138.5 25.1S21.1 210.3 80.6 291l-23.6 84.7c-4.6 16.5 11.1 31.2 27.6 26.6l85-23.5c77.7 48.8 180.3 14.4 217.3-69.5 32.4-71.8 11.2-155.1-54.6-211.2zM214.7 371.2c-32.5 0-64.4-9.9-91.1-28.3l-6.5-4.4-50.4 13.9 13.5-48.4-4.9-6.3c-35.7-45.7-43.2-109.4-17.9-162.7s81.1-85.1 141.5-85.1c98.3 0 178 79.7 178 178s-79.7 178-178 178zm98.2-133.3c-5.3-2.6-31.2-15.4-36-17.2-4.8-1.8-8.3-2.6-11.9 2.6-3.5 5.3-13.6 17.2-16.6 20.7-3 3.5-6.1 3.9-11.3 1.3-30.8-15.4-50.9-27.6-71.1-62.4-5.4-9.2 5.4-8.5 15.4-28.2 1.7-3.5.9-6.5-.4-9.1-1.3-2.6-11.9-28.7-16.3-39.3-4.3-10.3-8.7-8.8-11.9-9-3.1-.2-6.6-.2-10.1-.2s-9.1 1.3-13.9 6.5c-4.8 5.2-18.3 17.9-18.3 43.5s18.7 50.4 21.2 53.9c2.6 3.5 36.8 56.3 89.4 79.1 12.5 5.4 22.2 8.6 29.8 11 12.5 3.9 23.9 3.4 32.9 2.1 10-1.5 31.2-12.7 35.6-24.9 4.4-12.3 4.4-22.8 3.1-24.9-1.3-2.2-4.8-3.5-10.1-6.1z"/>
    </svg>
  </a>

  <!-- AlpineJS for accordion -->
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>
