<?php
/**
 * Direct & Exhaustive WordPress Media Downloader
 * Fetches all media records directly from WordPress REST API + Lovable portal
 */

set_time_limit(600);
ini_set('memory_limit', '512M');

function fetchJson(string $url): ?array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 && is_string($data)) {
        return json_decode($data, true);
    }
    return null;
}

function downloadFile(string $url, string $destPath): bool {
    $dir = dirname($destPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $ch = curl_init($url);
    $fp = fopen($destPath, 'w+');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if ($code === 200 && file_exists($destPath) && filesize($destPath) > 50) {
        return true;
    }
    if (file_exists($destPath)) {
        unlink($destPath);
    }
    return false;
}

echo "=== 1. FETCHING ALL MEDIA FROM WORDPRESS REST API ===\n";

$page = 1;
$allMediaUrls = [];

while (true) {
    $apiUrl = "https://onboltlive.com/wp-json/wp/v2/media?per_page=100&page=" . $page;
    echo "Querying API page $page... ";
    $items = fetchJson($apiUrl);

    if (empty($items) || !is_array($items)) {
        echo "Done (no more items).\n";
        break;
    }

    echo "Found " . count($items) . " media items.\n";

    foreach ($items as $item) {
        $sourceUrl = $item['source_url'] ?? '';
        if (!empty($sourceUrl)) {
            $allMediaUrls[] = $sourceUrl;
        }

        // Also check intermediate sizes (medium, large, full)
        if (!empty($item['media_details']['sizes'])) {
            foreach ($item['media_details']['sizes'] as $sz) {
                if (!empty($sz['source_url'])) {
                    $allMediaUrls[] = $sz['source_url'];
                }
            }
        }
    }

    $page++;
    if ($page > 10) break; // Safety limit
}

$uniqueMediaUrls = array_unique($allMediaUrls);
echo "Total unique media URLs discovered from WP API: " . count($uniqueMediaUrls) . "\n";

// Target base directory
$baseImgDir = __DIR__ . '/assets/images';

echo "\n=== 2. DOWNLOADING ALL ORIGINAL MEDIA FILES ===\n";

$success = 0;
$skipped = 0;
$failed = 0;

foreach ($uniqueMediaUrls as $idx => $url) {
    $filename = basename(strtok($url, '?'));
    if (empty($filename) || !preg_match('/\.(png|jpg|jpeg|webp|svg|gif|ico)$/i', $filename)) {
        continue;
    }

    // Determine smart target destination folder
    $subfolder = 'home';
    if (preg_match('/logo|favicon/i', $filename)) {
        $subfolder = 'logo';
    } elseif (preg_match('/product|t-shirt|shirt|hoodie|pant|short|sock/i', $filename)) {
        $subfolder = 'products';
    } elseif (preg_match('/school|vidhyalaya|trins/i', $filename)) {
        $subfolder = 'schools';
    } elseif (preg_match('/corporate|gift|hamper/i', $filename)) {
        $subfolder = 'portfolio';
    }

    $destPath = $baseImgDir . '/' . $subfolder . '/' . $filename;

    echo "[" . ($idx + 1) . "/" . count($uniqueMediaUrls) . "] $filename: ";

    if (file_exists($destPath) && filesize($destPath) > 500) {
        echo "Already exists (" . round(filesize($destPath) / 1024, 1) . " KB)\n";
        $skipped++;
        continue;
    }

    if (downloadFile($url, $destPath)) {
        echo "DOWNLOADED (" . round(filesize($destPath) / 1024, 1) . " KB)\n";
        $success++;
    } else {
        echo "FAILED\n";
        $failed++;
    }
}

echo "\n============================================\n";
echo "SUMMARY: $success downloaded, $skipped already present, $failed failed.\n";
echo "============================================\n";
