<?php
$token = 'xoxb-8593020489572-11698388133346-717JV5jumuvpRUpY0URqbBwc';
$ch = curl_init('https://slack.com/api/auth.test');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json; charset=utf-8'
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
$response = curl_exec($ch);
curl_close($ch);
echo "Auth Test: " . $response . "\n";
