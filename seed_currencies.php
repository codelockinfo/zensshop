<?php
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();

$defaultCurrencies = [
    [
        'code' => 'in',
        'name' => 'India',
        'currency_name' => 'INR',
        'symbol' => '₹',
        'flag' => 'https://cdn.shopify.com/static/images/flags/in.svg'
    ],
    [
        'code' => 'cn',
        'name' => 'China',
        'currency_name' => 'CNY',
        'symbol' => '¥',
        'flag' => 'https://cdn.shopify.com/static/images/flags/cn.svg'
    ],
    [
        'code' => 'fr',
        'name' => 'France',
        'currency_name' => 'EUR',
        'symbol' => '€',
        'flag' => 'https://cdn.shopify.com/static/images/flags/fr.svg'
    ],
    [
        'code' => 'gb',
        'name' => 'United Kingdom',
        'currency_name' => 'GBP',
        'symbol' => '£',
        'flag' => 'https://cdn.shopify.com/static/images/flags/gb.svg'
    ],
    [
        'code' => 'us',
        'name' => 'United States',
        'currency_name' => 'USD',
        'symbol' => '$',
        'flag' => 'https://cdn.shopify.com/static/images/flags/us.svg'
    ]
];

$json = json_encode($defaultCurrencies, JSON_PRETTY_PRINT);

try {
    $exists = $db->fetchOne("SELECT setting_key FROM site_settings WHERE setting_key = 'supported_currencies'");
    if (!$exists) {
        $db->insert("INSERT INTO site_settings (setting_key, setting_value) VALUES ('supported_currencies', ?)", [$json]);
        echo "Currencies seeded.";
    } else {
        echo "Currencies already exist. Skipping.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
