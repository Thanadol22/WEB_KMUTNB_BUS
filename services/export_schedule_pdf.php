<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/firebase_config.php';
require_once __DIR__ . '/FirebaseService.php';

use Dompdf\Dompdf;
use Dompdf\Options;

date_default_timezone_set("Asia/Bangkok");

// Initialize Firebase Service
global $firebase;
$firebaseService = new \FirebaseService($firebase['db'] ?? null, $firebase['auth'] ?? null);

// Get data
$schedules = $firebaseService->getAllDocuments("detailed_schedules");
$users = $firebaseService->getAllDocuments("users");

// Filter drivers from users
$drivers = array_filter($users, function($user) {
    return isset($user['role']) && strtolower($user['role']) === 'driver';
});

// Sort schedules by ID
usort($schedules, function($a, $b) {
    // พยายามดึงตัวเลขจาก ID เพื่อเรียงลำดับให้ถูกต้อง (เช่น round_1, round_2)
    preg_match('/\d+/', $a['id'], $matchesA);
    preg_match('/\d+/', $b['id'], $matchesB);
    $numA = isset($matchesA[0]) ? (int)$matchesA[0] : 0;
    $numB = isset($matchesB[0]) ? (int)$matchesB[0] : 0;
    
    if ($numA == $numB) {
        return strcmp($a['id'], $b['id']);
    }
    return $numA - $numB;
});

// Find all unique stops to create dynamic columns
$stopHeaders = [];
foreach ($schedules as $doc) {
    if (isset($doc['stops']) && is_array($doc['stops'])) {
        $stops = $doc['stops'];
        usort($stops, function($a, $b) {
            return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
        });
        foreach ($stops as $stop) {
            $name = trim($stop['name'] ?? '');
            if (!empty($name) && !in_array($name, $stopHeaders)) {
                $stopHeaders[] = $name;
            }
        }
    }
}

// Generate HTML Content
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;700&display=swap");
        @page { margin: 30px; }
        body { font-family: "Noto Sans Thai", sans-serif; font-size: 11px; color: #000; line-height: 1.1; }
        h2 { text-align: center; color: #000; font-weight: 700; margin-top: 0; margin-bottom: 10px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th, td { border: 1px solid #000; padding: 3px 2px; font-size: 10px; text-align: center; vertical-align: middle; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .footer-note { margin-top: 10px; font-size: 11px; margin-left: 5%; line-height: 1.3; }
    </style>
</head>
<body>
    <h2>ตารางเวลาวิ่งรถสองแถว มจพ. วิทยาเขตปราจีนบุรี</h2>
    
    <table>
        <thead>
            <tr>
                <th rowspan="2" width="60px">รอบที่</th>';

foreach ($stopHeaders as $header) {
    $html .= '<th>' . htmlspecialchars($header) . '</th>';
}

$html .= '</tr><tr>';

foreach ($stopHeaders as $header) {
    $html .= '<th>เวลา</th>';
}

$html .= '</tr>
        </thead>
        <tbody>';

if (count($schedules) > 0) {
    $counter = 1;
    foreach ($schedules as $doc) {
        $stopsMap = [];
        if (isset($doc['stops']) && is_array($doc['stops'])) {
            foreach ($doc['stops'] as $stop) {
                $name = trim($stop['name'] ?? '');
                $stopsMap[$name] = $stop['time'] ?? '-';
            }
        }
        
        $html .= '<tr>
                    <td>' . $counter++ . '</td>';
        
        foreach ($stopHeaders as $header) {
            $time = isset($stopsMap[$header]) ? $stopsMap[$header] : '-';
            
            // Format time if it exists
            if ($time !== '-' && !empty($time)) {
                // Remove seconds if any (e.g., "08:10:00" -> "08:10")
                $parts = explode(':', $time);
                if (count($parts) >= 2) {
                    $timeFormatted = $parts[0] . '.' . $parts[1]; // Change : to .
                } else {
                    $timeFormatted = str_replace(':', '.', $time);
                }
                $timeDisplay = $timeFormatted . ' น.';
            } else {
                $timeDisplay = '-';
            }
            
            $html .= '<td>' . htmlspecialchars($timeDisplay) . '</td>';
        }
        
        $html .= '</tr>';
    }
} else {
    $colspan = count($stopHeaders) + 1;
    $html .= '<tr><td colspan="' . $colspan . '" class="text-center">ไม่พบข้อมูลตารางเดินรถ</td></tr>';
}

$html .= '
        </tbody>
    </table>
    
    <div class="footer-note">
        <strong>ผู้รับผิดชอบ</strong><br>';

if (!empty($drivers)) {
    $dCount = 1;
    foreach ($drivers as $driver) {
        $name = isset($driver['name']) ? $driver['name'] : 'ไม่ได้ระบุชื่อ';
        $phone = isset($driver['phone']) ? $driver['phone'] : 'ไม่ได้ระบุเบอร์โทร';
        $html .= $dCount . '. ' . htmlspecialchars($name) . ' เบอร์โทร. ' . htmlspecialchars($phone) . '<br>';
        $dCount++;
    }
} else {
    $html .= '<span style="color: #666;">- ไม่มีรายชื่อผู้ขับรถในระบบ -</span>';
}

$html .= '
    </div>
</body>
</html>';

// Setup DOMPDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$filename = 'bus_schedules_' . date('Ymd_His') . '.pdf';
// Attachment => false means the browser will display it as a preview instead of downloading directly
$dompdf->stream($filename, ["Attachment" => false]);
exit;