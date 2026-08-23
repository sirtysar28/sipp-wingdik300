<?php
// SIMONIK SMART - Diagnostic Check
// Upload file ini ke public_html/simonik-smart/ lalu akses via browser
// DELETE setelah selesai cek!

echo "<h2>🔍 SIMONIK SMART - Diagnostic Check</h2>";
echo "<pre>";

// 1. File exists check
echo "═══ FILE CHECK ═══\n";
$files = [
    '.env' => 'File konfigurasi',
    'artisan' => 'Laravel artisan',
    'composer.json' => 'Composer config',
    'vendor/autoload.php' => 'Vendor autoload (composer install)',
    'bootstrap/cache/config.php' => 'Config cache',
];
foreach ($files as $f => $desc) {
    $exists = file_exists($f);
    echo ($exists ? '✅' : '❌') . " {$f} — {$desc}\n";
}

// 2. .env content check
echo "\n═══ .env CHECK ═══\n";
if (file_exists('.env')) {
    $envContent = file_get_contents('.env');
    $envLines = explode("\n", $envContent);
    foreach ($envLines as $line) {
        if (preg_match('/^(APP_NAME|APP_URL|APP_KEY|APP_ENV|DB_DATABASE|DB_HOST|DB_USERNAME|SESSION_DRIVER)/', $line)) {
            echo "📄 {$line}\n";
        }
    }
} else {
    echo "❌ .env TIDAK DITEMUKAN!\n";
}

// 3. Document root check
echo "\n═══ PATH CHECK ═══\n";
echo "📂 CWD: " . getcwd() . "\n";
echo "📂 __DIR__: " . __DIR__ . "\n";
echo "📂 Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "\n";
echo "📂 Script Filename: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'N/A') . "\n";
echo "📄 Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";

// 4. Check if this is inside a subfolder
$realpath = realpath(__DIR__);
$docroot = realpath($_SERVER['DOCUMENT_ROOT'] ?? __DIR__);
if ($realpath !== $docroot) {
    echo "⚠️  WARNING: File ini TIDAK di document root!\n";
    echo "   Realpath: {$realpath}\n";
    echo "   DocRoot:  {$docroot}\n";
    echo "   Selisih:  " . str_replace($docroot, '', $realpath) . "\n";
}

// 5. Check public folder structure  
echo "\n═══ PUBLIC FOLDER CHECK ═══\n";
$publicItems = @scandir(__DIR__);
if ($publicItems) {
    foreach ($publicItems as $item) {
        if ($item === '.' || $item === '..') continue;
        if (preg_match('/\.(php|jpg|png|ico|css|js|html)$/', $item)) {
            echo "📄 {$item}\n";
        }
    }
}

// 6. Check if index.php points to correct vendor path
echo "\n═══ INDEX.PHP CHECK ═══\n";
if (file_exists('index.php')) {
    $indexContent = file_get_contents('index.php');
    if (strpos($indexContent, 'penilaian.dovlenseventy.com') !== false) {
        echo "❌ index.php MASIH merujuk ke path LAMA (penilaian.dovlenseventy.com)!\n";
        echo "   → Perlu diganti ke path yang benar\n";
    } elseif (strpos($indexContent, "require __DIR__.'/../vendor/autoload.php'") !== false) {
        echo "✅ index.php sudah benar (vendor/autoload.php)\n";
    } else {
        echo "⚠️  index.php merujuk ke: ";
        preg_match("/require.*vendor.*autoload/", $indexContent, $m);
        echo ($m[0] ?? 'TIDAK DITEMUKAN') . "\n";
    }
}

// 7. Check vendor
echo "\n═══ VENDOR CHECK ═══\n";
if (file_exists('../vendor/autoload.php')) {
    echo "✅ vendor/autoload.php ADA\n";
} else {
    echo "❌ vendor/autoload.php TIDAK ADA!\n";
    echo "   → Jalankan: composer install --no-dev\n";
}

// 8. Check logo & favicon
echo "\n═══ ASSET CHECK ═══\n";
$assets = [
    'logo_simonik-smart.jpg' => 'Logo login',
    'favicon.png' => 'Favicon',
    'images/Logo_Skadik_302.png' => 'Logo Skadik',
    'images/Logo_Wingdik_300_Tek.png' => 'Logo Wingdik',
];
foreach ($assets as $a => $desc) {
    $path = __DIR__ . '/' . $a;
    echo (file_exists($path) ? '✅' : '❌') . " {$a} — {$desc}\n";
}

// 9. PHP Version
echo "\n═══ PHP CHECK ═══\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Required: 8.1+\n";

echo "</pre>";
echo "<hr><p><strong>⚠️ HAPUS file ini setelah selesai cek!</strong></p>";
