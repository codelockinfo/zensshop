<?php
/**
 * Sections API
 * Returns HTML for lazy-loaded sections
 */

header('Content-Type: text/html; charset=utf-8');

$section = $_GET['section'] ?? '';

$allowedSections = [
    'categories',
    'best-selling',
    'special-offers',
    'videos',
    'trending',
    'philosophy',
    'newsletter'
];

if (!in_array($section, $allowedSections)) {
    http_response_code(404);
    echo '<div class="text-center py-8 text-gray-500">Section not found</div>';
    exit;
}

$sectionFile = __DIR__ . '/../sections/' . $section . '.php';

if (file_exists($sectionFile)) {
    ob_start();
    include $sectionFile;
    $html = ob_get_clean();
    echo $html;
} else {
    http_response_code(404);
    echo '<div class="text-center py-8 text-gray-500">Section file not found</div>';
}

