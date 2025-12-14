<?php
// dev/scan_links.php
require_once __DIR__ . '/../Lib/config.php';

$root = APP_ROOT;
$bad = [
  'href-leading-slash' => [],
  'href-relatives'     => [],
  'action-leading-slash'=>[],
  'action-relatives'   => [],
  'raw-header-location'=>[],
  'wrong-config-case'  => [],
];

$allow = ['/Lib/config.php'];
if (in_array($path, $allow, true)) { /* skip raw header check */ }

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($rii as $f) {
  if (!$f->isFile()) continue;
  $path = $f->getPathname();
  if (!preg_match('/\.php$/', $path)) continue;
  if (str_contains($path, '/dev/')) continue;

  $code = @file_get_contents($path);
  if ($code === false) continue;

  // href="/something" not using url()
  if (preg_match_all('/href="\s*\/(?!\?|\#)(?!\s*<\?= *url\()/i', $code)) {
    $bad['href-leading-slash'][] = str_replace($root,'',$path);
  }
  // href="dashboard/...|user/...|worker/...|admin/..." (relative) not using url()
  if (preg_match_all('/href="\s*(dashboard|user|worker|admin)\//i', $code)) {
    $bad['href-relatives'][] = str_replace($root,'',$path);
  }
  // action="/something" not using url()
  if (preg_match_all('/action="\s*\/(?!\s*<\?= *url\()/i', $code)) {
    $bad['action-leading-slash'][] = str_replace($root,'',$path);
  }
  // action="dashboard/... etc" relative
  if (preg_match_all('/action="\s*(dashboard|user|worker|admin)\//i', $code)) {
    $bad['action-relatives'][] = str_replace($root,'',$path);
  }
  // header("Location: ...")
  if (preg_match_all('/header\(\s*[\'"]Location:/i', $code)) {
    $bad['raw-header-location'][] = str_replace($root,'',$path);
  }
  // wrong-case lib/config.php
  if (preg_match_all('/require(_once)?\s*\(.*[\'"]\.\.\/lib\/config\.php[\'"]/i', $code)) {
    $bad['wrong-config-case'][] = str_replace($root,'',$path);
  }
}

echo "<h1>Link & Redirect Scan</h1>";
foreach ($bad as $k => $list) {
  echo "<h3>$k (".count($list).")</h3>";
  if (!$list) { echo "<p>✅ none</p>"; continue; }
  echo "<ul>";
  foreach (array_unique($list) as $p) echo "<li><code>$p</code></li>";
  echo "</ul>";
}

echo "<hr><p>Fix recipe:</p>
<pre>
1) Replace href/action with: href=\"<?= url('/...') ?>\"
2) Replace header('Location: ...') with: redirect_to('/...')
3) Use: require_once __DIR__ . '/../Lib/config.php';
</pre>";
