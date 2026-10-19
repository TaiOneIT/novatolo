<?php
$file = $_GET['file'] ?? '';
if (empty($file)) {
    http_response_code(400);
    exit("No file specified.");
}

$filename = basename($file);
$filepath = __DIR__ . '/uploads/' . $filename;

if (file_exists($filepath) && is_file($filepath)) {
    $mime = function_exists('mime_content_type') ? mime_content_type($filepath) : 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($filepath));
    header('Content-Disposition: inline; filename="' . $filename . '"');
    readfile($filepath);
    exit;
}

http_response_code(404);
echo "File not found: " . htmlspecialchars($filename);