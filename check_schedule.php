<?php
/**
 * Schedule Checker — Sends FCM push notifications to drivers
 * 15 minutes before their scheduled bus round.
 * 
 * Uses shared Firebase services instead of manual JWT/REST.
 * Designed to be called via cron job every minute.
 */
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/includes/firebase_config.php';
require_once __DIR__ . '/services/FirebaseService.php';

header('Content-Type: application/json');

/** @var array $firebase Defined in includes/firebase_config.php */
if ($firebase['status'] !== 'connected' || !$firebase['db']) {
    die(json_encode(['status' => 'error', 'message' => 'Firebase connection not available.']));
}

$firebaseService = new FirebaseService($firebase['db']);

// --- Get Access Token for FCM (still needed for FCM HTTP v1 API) ---
$projectId = $_ENV['FIREBASE_PROJECT_ID'] ?? '';
$clientEmail = $_ENV['FIREBASE_CLIENT_EMAIL'] ?? '';
$privateKey = $_ENV['FIREBASE_PRIVATE_KEY'] ?? '';

if (!$projectId || !$clientEmail || !$privateKey) {
    die(json_encode(['status' => 'error', 'message' => 'Firebase credentials not found.']));
}

// Use firebase/php-jwt for access token
use Firebase\JWT\JWT;

function getAccessToken($clientEmail, $privateKey) {
    $now = time();
    $payload = [
        'iss' => $clientEmail,
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
    ];
    $jwt = JWT::encode($payload, str_replace('\\n', "\n", $privateKey), 'RS256');
    
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    return $response['access_token'] ?? null;
}

$accessToken = getAccessToken($clientEmail, $privateKey);
if (!$accessToken) {
    die(json_encode(['status' => 'error', 'message' => 'Failed to obtain access token.']));
}

// --- Send FCM Notification ---
function sendFcmMessage($projectId, $accessToken, $fcmToken, $title, $body) {
    $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
    $payload = [
        'message' => [
            'token' => $fcmToken,
            'notification' => [
                'title' => $title,
                'body' => $body
            ],
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'channel_id' => 'high_importance_channel',
                    'sound' => 'default'
                ]
            ]
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$accessToken}",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    return $response;
}

// --- Main Logic: Check Schedules ---
$now = time();
$target_time_start = date('H:i', strtotime('+14 minutes', $now));
$target_time_end = date('H:i', strtotime('+16 minutes', $now));

// Use shared service to get schedules
$schedules = $firebaseService->getAllDocuments('schedules');
$notifications_sent = 0;
$log = [];

foreach ($schedules as $schedule) {
    $startTime = $schedule['start_time'] ?? '';
    $endTime = $schedule['end_time'] ?? '';
    $busId = $schedule['bus_id'] ?? '';
    
    // Check if start_time falls within 14-16 minutes from now
    if ($startTime >= $target_time_start && $startTime <= $target_time_end) {
        
        // Find driver via buses collection using shared service
        $buses = $firebaseService->getAllDocuments('buses');
        $driverId = null;
        foreach ($buses as $bus) {
            if (($bus['bus_id'] ?? '') === $busId || ($bus['id'] ?? '') === $busId) {
                $driverId = $bus['driver_id'] ?? null;
                break;
            }
        }

        if ($driverId) {
            $driver = $firebaseService->getDocument('users', $driverId);
            $fcmToken = $driver['fcm_token'] ?? null;
            
            if ($fcmToken) {
                $title = "⏰ เตรียมตัวออกรถ!";
                $body = "รถของคุณมีรอบวิ่งในอีก 15 นาที (รอบ {$startTime} - {$endTime})";
                $result = sendFcmMessage($projectId, $accessToken, $fcmToken, $title, $body);
                
                $log[] = [
                    'bus_id' => $busId, 
                    'driver_id' => $driverId, 
                    'status' => 'sent', 
                    'result' => $result
                ];
                $notifications_sent++;
            } else {
                $log[] = ['bus_id' => $busId, 'driver_id' => $driverId, 'status' => 'failed', 'reason' => 'No FCM token found'];
            }
        } else {
            $log[] = ['bus_id' => $busId, 'status' => 'failed', 'reason' => 'No driver_id found for this bus_id'];
        }
    }
}

echo json_encode([
    'status' => 'success',
    'message' => 'Schedule check completed',
    'target_time_checked' => "{$target_time_start} - {$target_time_end}",
    'notifications_sent' => $notifications_sent,
    'log' => $log
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);