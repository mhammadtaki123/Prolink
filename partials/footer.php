<?php
/**
 * ProLink - Shared Footer (Expanded, absolute links via BASE_URL)
 * Path: /Prolink/partials/footer.php
 */
$__ft_try1 = __DIR__ . '/../Lib/config.php';
$__ft_try2 = __DIR__ . '/../lib/config.php';
if (file_exists($__ft_try1)) { include_once $__ft_try1; }
elseif (file_exists($__ft_try2)) { include_once $__ft_try2; }

$__base_raw = (defined('BASE_URL') ? trim(BASE_URL) : '');
$baseUrl = ($__base_raw !== '') ? rtrim($__base_raw, '/') : '/Prolink';

$year = date('Y');
?>
<footer class="mt-12 border-t bg-white/70">
  <div class="max-w-7xl mx-auto px-4 py-10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 text-sm">
    <div>
      <div class="font-bold text-base mb-2">ProLink</div>
      <p class="text-gray-600">Find trusted workers for household and outdoor tasks. Book online in minutes.</p>
    </div>

    <div>
      <div class="font-semibold mb-2">Company</div>
      <ul class="space-y-1">
        <li><a class="hover:underline" href="<?= $baseUrl ?>/pages/about.php">About</a></li>
        <li><a class="hover:underline" href="<?= $baseUrl ?>/pages/contact.php">Contact</a></li>
        <li><a class="hover:underline" href="<?= $baseUrl ?>/pages/privacy.php">Privacy</a></li>
        <li><a class="hover:underline" href="<?= $baseUrl ?>/pages/terms.php">Terms</a></li>
      </ul>
    </div>

    <div>
      <div class="font-semibold mb-2">For users</div>
<ul class="space-y-1 text-sm">
    <li><a href="<?= $baseUrl ?>/browse.php" class="hover:underline">Browse Services</a></li>
    <li><a href="<?= $baseUrl ?>/auth/register.php" class="hover:underline">User Signup</a></li> <!-- ✅ Added -->
    <li><a href="<?= $baseUrl ?>/auth/login.php" class="hover:underline">User Login</a></li>   <!-- ✅ Added earlier -->
</ul>


    </div>

    <div>
      <div class="font-semibold mb-2">For workers</div>
      <ul class="space-y-1">
        <li><a class="hover:underline" href="<?= $baseUrl ?>/auth/worker-login.php">Worker login</a></li>
        <li><a class="hover:underline" href="<?= $baseUrl ?>/auth/worker-register.php">Worker sign up</a></li>
        <li><a class="hover:underline" href="<?= $baseUrl ?>/worker/services.php">My services</a></li>
      </ul>
    </div>
  </div>

  <div class="border-t">
    <div class="max-w-7xl mx-auto px-4 py-4 text-xs text-gray-600 flex flex-col sm:flex-row items-center justify-between gap-2">
      <div>© <?= htmlspecialchars($year, ENT_QUOTES) ?> ProLink. All rights reserved.</div>
      <div class="flex gap-4">
        <a class="hover:underline" href="<?= $baseUrl ?>/pages/privacy.php">Privacy</a>
        <a class="hover:underline" href="<?= $baseUrl ?>/pages/terms.php">Terms</a>
        <a class="hover:underline" href="<?= $baseUrl ?>/pages/contact.php">Contact</a>
      </div>
    </div>
  </div>
</footer>
