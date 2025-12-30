<?php
/**
 * Helper Functions
 */

// Load constants if not already loaded
if (!defined('SITE_URL')) {
    require_once __DIR__ . '/../config/constants.php';
}

/**
 * Get base URL for the site
 * ALWAYS returns /zensshop/ based on actual file system directory
 * This ensures consistency regardless of access path (/oecom/ or /zensshop/)
 */
function getBaseUrl() {
    // ALWAYS use the actual file system directory name (zensshop)
    // This ensures all URLs point to /zensshop/ even when accessed via /oecom/
    $projectRoot = dirname(__DIR__);
    $projectDir = basename($projectRoot);
    
    // Return the actual directory name (should be 'zensshop')
    return '/' . $projectDir;
}

/**
 * Generate clean URL without .php extension
 */
function url($path = '') {
    $baseUrl = getBaseUrl();
    
    // Remove leading slash if present
    $path = ltrim($path, '/');
    
    // Handle query strings
    $queryString = '';
    if (strpos($path, '?') !== false) {
        $parts = explode('?', $path, 2);
        $path = $parts[0];
        $queryString = '?' . $parts[1];
    }
    
    // Remove .php extension if present
    $path = preg_replace('/\.php$/', '', $path);
    
    // If path is empty, return base URL
    if (empty($path)) {
        return $baseUrl . '/' . $queryString;
    }
    
    return $baseUrl . '/' . $path . $queryString;
}

/**
 * Get full image URL from relative path
 */
function getImageUrl($path) {
    if (empty($path)) {
        return 'https://via.placeholder.com/300';
    }
    
    // If already a full URL, return as is
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        return $path;
    }
    
    // If starts with /, it's already a relative path from root
    if (strpos($path, '/') === 0) {
        return $path;
    }
    
    // Otherwise, prepend base path
    $baseUrl = getBaseUrl();
    return $baseUrl . '/assets/images/uploads/' . $path;
}

/**
 * Get product image with fallback
 */
function getProductImage($product, $index = 0) {
    if (empty($product) || !is_array($product)) {
        return 'data:image/svg+xml;base64,' . base64_encode('<svg width="300" height="300" viewBox="0 0 300 300" xmlns="http://www.w3.org/2000/svg"><rect width="300" height="300" fill="#F3F4F6"/><circle cx="150" cy="150" r="40" fill="#9B7A8A"/><path d="M80 250C80 220 110 190 150 190C190 190 220 220 220 250" fill="#9B7A8A"/></svg>');
    }
    
    $images = json_decode($product['images'] ?? '[]', true);
    
    // Try featured image first
    if (!empty($product['featured_image'])) {
        return getImageUrl($product['featured_image']);
    }
    
    // Try images array
    if (!empty($images[$index])) {
        return getImageUrl($images[$index]);
    }
    
    // Fallback to inline SVG placeholder
    return 'data:image/svg+xml;base64,' . base64_encode('<svg width="300" height="300" viewBox="0 0 300 300" xmlns="http://www.w3.org/2000/svg"><rect width="300" height="300" fill="#F3F4F6"/><circle cx="150" cy="150" r="40" fill="#9B7A8A"/><path d="M80 250C80 220 110 190 150 190C190 190 220 220 220 250" fill="#9B7A8A"/></svg>');
}

