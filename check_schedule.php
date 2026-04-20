<?php
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;

header('Content-Type: application/json');

// === 1. โหลด Service Account จาก .env ===
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$projectId = $_ENV['FIREBASE_PROJECT_ID'] ?? getenv('FIREBASE_PROJECT_ID');
$clientEmail = $_ENV['FIREBASE_CLIENT_EMAIL'] ?? getenv('FIREBASE_CLIENT_EMAIL');
$privateKey = $_ENV['FIREBASE_PRIVATE_KEY'] ?? getenv('FIREBASE_PRIVATE_KEY');

if (!$projectId || !$clientEmail || !$privateKey) {
    die(json_encode(['status' => 'error', 'message' => 'Firebase credentials not found in .env / Environment Variables.']));
}

$serviceAccount = [
    'project_id' => $projectId,
    'client_email' => $clientEmail,
    'private_key' => str_replace('\\n', "\n", $privateKey)
];

// === 2. สร้าง Access Token (JWT → OAuth2) ===
function getAccessToken($serviceAccount) {
    $now = time();
    $payload = [
        'iss' => $serviceAccount['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging https://www.googleapis.com/auth/datastore',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
    ];
    $jwt = JWT::encode($payload, $serviceAccount['private_key'], 'RS256');
    
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

$accessToken = getAccessToken($serviceAccount);
if (!$accessToken) {
    die(json_encode(['status' => 'error', 'message' => 'Failed to obtain access token.']));
}

// === 3. ดึงข้อมูล Schedules จาก Firestore ===
function getSchedules($projectId, $accessToken) {
    $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/schedules";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$accessToken}"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $response['documents'] ?? [];
}

// === ค้นหา Driver ID จาก Bus ID ในคอลเล็กชั่น buses ===
function getDriverIdByBusId($projectId, $accessToken, $busId) {
    $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents:runQuery";
    $query = [
        'structuredQuery' => [
            'from' => [['collectionId' => 'buses']],
            'where' => [
                'fieldFilter' => [
                    'field' => ['fieldPath' => 'bus_id'],
                    'op' => 'EQUAL',
                    'value' => ['stringValue' => $busId]
                ]
            ],
            'limit' => 1
        ]
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($query));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$accessToken}",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!empty($response) && isset($response[0]['document']['fields']['driver_id']['stringValue'])) {
        return $response[0]['document']['fields']['driver_id']['stringValue'];
    }
    return null;
}

// === ดึง FCM Token ของ User (คนขับ) ===
function getUserFcmToken($projectId, $accessToken, $driverId) {
    $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/users/{$driverId}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$accessToken}"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    return $response['fields']['fcm_token']['stringValue'] ?? null;
}

// === ส่ง Push Notification ผ่าน FCM HTTP v1 API ===
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

// === 4. เปรียบเทียบเวลา ===
$now = time();
$target_time_start = date('H:i', strtotime('+14 minutes', $now));
$target_time_end = date('H:i', strtotime('+16 minutes', $now));

$schedules = getSchedules($projectId, $accessToken);
$notifications_sent = 0;
$log = [];

foreach ($schedules as $doc) {
    $fields = $doc['fields'] ?? [];
    $startTime = $fields['start_time']['stringValue'] ?? '';
    $endTime = $fields['end_time']['stringValue'] ?? '';
    $busId = $fields['bus_id']['stringValue'] ?? '';
    
    // ตรวจสอบว่าช่วงเวลาเริ่มรอบรถตรงกับอีก 14-16 นาทีข้างหน้าหรือไม่
    if ($startTime >= $target_time_start && $startTime <= $target_time_end) {
        
        // === 5. ค้นหาคนขับ ===
        $driverId = getDriverIdByBusId($projectId, $accessToken, $busId);
        if ($driverId) {
            $fcmToken = getUserFcmToken($projectId, $accessToken, $driverId);
            if ($fcmToken) {
                // === 6. ส่ง Push Notification ===
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

// === 7. บันทึก Log ===
echo json_encode([
    'status' => 'success',
    'message' => 'Schedule check completed',
    'target_time_checked' => "{$target_time_start} - {$target_time_end}",
    'notifications_sent' => $notifications_sent,
    'log' => $log
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

?>