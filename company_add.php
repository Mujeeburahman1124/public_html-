<?php
declare(strict_types=1);
session_start();
$old = $_SESSION['form_old']   ?? [];
$err = $_SESSION['form_error'] ?? '';
unset($_SESSION['form_old'], $_SESSION['form_error']);
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Add Company — MSJOBS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="icon" type="image/png" href="img/MS copy.png" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{colors:{brand:"#1f57c3",brandDark:"#153c83"},fontFamily:{sans:["Inter","system-ui"]}}}}</script>
</head>
<body class="bg-white text-gray-900">
  <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-2xl font-bold">Add a company</h1>
    <p class="text-gray-600">Create a company profile that will appear in Company reviews.</p>

    <?php if ($err): ?>
      <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
        <?= h($err) ?>
      </div>
    <?php endif; ?>

    <form action="company_add_submit.php" method="post" enctype="multipart/form-data" class="mt-6 grid gap-4 rounded-2xl border p-5">
      <div>
        <label class="block text-sm font-medium">Company name *</label>
        <input name="name" value="<?=h($old['name']??'')?>" required class="mt-1 px-3 py-2 rounded border w-full" placeholder="Acme Inc.">
      </div>
      <div>
        <label class="block text-sm font-medium">Industry *</label>
        <input name="industry" value="<?=h($old['industry']??'')?>" required class="mt-1 px-3 py-2 rounded border w-full" placeholder="Logistics & Distribution">
      </div>
      <div>
        <label class="block text-sm font-medium">HQ location</label>
        <input name="hq_location" value="<?=h($old['hq_location']??'')?>" class="mt-1 px-3 py-2 rounded border w-full" placeholder="Dubai, UAE">
      </div>
      <div>
        <label class="block text-sm font-medium">Website</label>
        <input name="website" type="url" value="<?=h($old['website']??'')?>" class="mt-1 px-3 py-2 rounded border w-full" placeholder="https://example.com">
      </div>
      <div>
        <label class="block text-sm font-medium">Description</label>
        <textarea name="description" rows="4" class="mt-1 px-3 py-2 rounded border w-full"><?=h($old['description']??'')?></textarea>
      </div>
      <div>
        <label class="block text-sm font-medium">Logo (PNG/JPG, ≤ 1MB)</label>
        <input type="file" name="logo" accept=".png,.jpg,.jpeg" class="mt-1 block">
      </div>
      <div class="flex items-center gap-3">
        <button class="px-5 py-2 rounded bg-brand text-white font-semibold hover:bg-brandDark">Save company</button>
        <a href="companies.php" class="px-4 py-2 rounded border">Cancel</a>
      </div>
    </form>
  </div>
</body>
</html>
