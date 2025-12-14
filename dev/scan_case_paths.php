<?php
// /dev/scan_case_paths.php
header('Content-Type: text/plain; charset=utf-8');
$root = dirname(__DIR__);
$patterns = [
  '#href\s*=\s*"(/ProLink/?)#i',
  '#action\s*=\s*"(/ProLink/?)#i',
  '#Location:\s*/ProLink#i',
  '#http://localhost/ProLink#i',
];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$hits = [];
foreach ($rii as $f) {
  if ($f->isDir()) continue;
  $ext = strtolower(pathinfo($f->getFilename(), PATHINFO_EXTENSION));
  if (!in_array($ext, ['php','html','htm','js','css'])) continue;
  $text = @file_get_contents($f->getPathname());
  if ($text === false) continue;
  $lines = preg_split('/\R/', $text);
  foreach ($lines as $i => $line) {
    foreach ($patterns as $p) {
      if (preg_match($p, $line)) {
        $hits[] = [$f->getPathname(), $i+1, trim($line)];
      }
    }
  }
}
if (!$hits) { echo "No hard-coded /ProLink paths found.\n"; exit; }
echo "Found hard-coded /ProLink paths. Replace with url('/...') or change to /Prolink:\n\n";
foreach ($hits as [$file,$ln,$snippet]) {
  echo $file . ':' . $ln . "\n  " . $snippet . "\n\n";
}
