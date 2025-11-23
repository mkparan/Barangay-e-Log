<?php
// Generate a square favicon from the logo
header('Content-Type: image/png');
header('Cache-Control: public, max-age=31536000'); // Cache for 1 year

$logoPath = __DIR__ . '/assets/images/logo.png';
$size = isset($_GET['size']) ? (int)$_GET['size'] : 32; // Favicon size (default 32)

if (!file_exists($logoPath)) {
    // Return a simple square with "B" if logo doesn't exist
    $img = imagecreatetruecolor($size, $size);
    $bg = imagecolorallocate($img, 0, 51, 160); // Government blue
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefill($img, 0, 0, $bg);
    // Add a "B" letter
    imagestring($img, 5, $size/2 - 6, $size/2 - 8, 'B', $white);
    imagepng($img);
    imagedestroy($img);
    exit;
}

// Check if GD extension is available
if (!extension_loaded('gd')) {
    // If GD not available, try to read and output the logo directly
    if (file_exists($logoPath)) {
        header('Content-Type: image/png');
        readfile($logoPath);
        exit;
    }
}

// Load the original logo
$source = @imagecreatefrompng($logoPath);
if (!$source) {
    // If imagecreatefrompng fails, output original
    header('Content-Type: image/png');
    readfile($logoPath);
    exit;
}

$sourceWidth = imagesx($source);
$sourceHeight = imagesy($source);

// Create a square canvas with white background (better for favicons)
$favicon = imagecreatetruecolor($size, $size);
$white = imagecolorallocate($favicon, 255, 255, 255);
imagefill($favicon, 0, 0, $white);

// Calculate dimensions to fit logo in square (centered, maintaining aspect ratio)
// Leave some padding (90% of size)
$padding = 0.1;
$maxWidth = $size * (1 - $padding * 2);
$maxHeight = $size * (1 - $padding * 2);
$scale = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
$newWidth = (int)($sourceWidth * $scale);
$newHeight = (int)($sourceHeight * $scale);
$x = (int)(($size - $newWidth) / 2);
$y = (int)(($size - $newHeight) / 2);

// Enable alpha blending for transparency
imagealphablending($favicon, true);
imagesavealpha($favicon, true);

// Resize and copy logo to center of square with better quality
imagecopyresampled($favicon, $source, $x, $y, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);

imagepng($favicon, null, 9); // Highest quality
imagedestroy($source);
imagedestroy($favicon);
?>

