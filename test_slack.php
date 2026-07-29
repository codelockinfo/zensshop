<?php
$url = 'https://slack.com/api/chat.postMessage';
$token = 'xoxb-8593020489572-11698388133346-aG3D5KogeSDPQTJHRB7vJeHV';
$channel = 'C0BLN1VB3L4';

$data = [
    'channel' => $channel,
    'text' => 'Hello from Antigravity! This is a test notification to verify the Slack integration setup.'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json; charset=utf-8'
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
$response = curl_exec($ch);
curl_close($ch);

echo $response;
