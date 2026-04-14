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

// Get filters
$selectedDate = isset($_GET['date']) ? $_GET['date'] : '';
$selectedBus = isset($_GET['bus']) ? $_GET['bus'] : '';

// Get data from Firebase
$tickets = $firebaseService->getAllDocuments("ticket_reports");
$users = $firebaseService->getAllDocuments("users");
$buses = $firebaseService->getAllDocuments("buses");

// Create Maps for user names and bus info
$userMap = [];
foreach ($users as $user) {
    if (isset($user['id'])) {
        $userMap[$user['id']] = $user;
    }
}

$busMap = [];
foreach ($buses as $bus) {
    $bId = $bus['bus_id'] ?? $bus['id'] ?? '';
    if (!empty($bId)) {
        $busMap[$bId] = $bus;
    }
}

// Filter data
$filteredTickets = array_filter($tickets, function($ticket) use ($selectedDate, $selectedBus) {
    if (!empty($selectedDate)) {
        $ticketTime = isset($ticket['timestamp']) ? 
            (is_numeric($ticket['timestamp']) ? $ticket['timestamp'] : strtotime($ticket['timestamp'])) : 
            (isset($ticket['created_at']) ? strtotime($ticket['created_at']) : time());
            
        $ticketDate = date("Y-m-d", $ticketTime);
        if ($ticketDate !== $selectedDate) {
            return false;
        }
    }
    
    if (!empty($selectedBus)) {
        $ticketBusId = isset($ticket['bus_id']) ? $ticket['bus_id'] : '';
        if ($ticketBusId !== $selectedBus) {
            return false;
        }
    }
    
    return true;
});

// Sort data: new to old
usort($filteredTickets, function($a, $b) {
    $timeA = isset($a['timestamp']) ? (is_numeric($a['timestamp']) ? $a['timestamp'] : strtotime($a['timestamp'])) : (isset($a['created_at']) ? strtotime($a['created_at']) : 0);
    $timeB = isset($b['timestamp']) ? (is_numeric($b['timestamp']) ? $b['timestamp'] : strtotime($b['timestamp'])) : (isset($b['created_at']) ? strtotime($b['created_at']) : 0);
    return $timeB - $timeA;
});

$totalTicketsCount = 0;
foreach ($filteredTickets as $ticket) {
    $count = isset($ticket['ticket_count']) ? (int)$ticket['ticket_count'] : 0;
    $totalTicketsCount += $count;
}

// Generate HTML Content
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;700&display=swap");
        body { font-family: "Noto Sans Thai", sans-serif; font-size: 14px; }
        h2 { text-align: center; color: #333; font-weight: 700; }
        p { text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; font-size: 12px; }
        th { background-color: #f2f2f2; text-align: center; }
        .text-center { text-align: center; }
        .summary { margin-top: 20px; font-weight: bold; }
    </style>
</head>
<body>
    <h2>รายงานการจอง/ใช้ตั๋ว</h2>';

if ($selectedDate) {
    $html .= '<p>วันที่: ' . date('d/m/Y', strtotime($selectedDate)) . '</p>';
}
if ($selectedBus) {
    $busInfo = isset($busMap[$selectedBus]) ? ($busMap[$selectedBus]['bus_number'] ?? $busMap[$selectedBus]['name'] ?? $selectedBus) : $selectedBus;
    $html .= '<p>รถ: ' . $busInfo . '</p>';
}

$html .= '
    <table>
        <thead>
            <tr>
                <th>ลำดับ</th>
                <th>เวลาบันทึก</th>
                <th>รอบรถ</th>
                <th>ชื่อคนขับ</th>
                <th>หมายเลขรถ</th>
                <th>จำนวน (คน)</th>
            </tr>
        </thead>
        <tbody>';

if (count($filteredTickets) > 0) {
    $counter = 1;
    foreach ($filteredTickets as $ticket) {
        $ticketTime = isset($ticket['timestamp']) ? 
            (is_numeric($ticket['timestamp']) ? $ticket['timestamp'] : strtotime($ticket['timestamp'])) : 
            (isset($ticket['created_at']) ? strtotime($ticket['created_at']) : time());
            
        $dateStr = date("d/m/Y H:i:s", $ticketTime);
        
        $roundTime = isset($ticket['round_time']) ? $ticket['round_time'] : '-';
        
        $busId = isset($ticket['bus_id']) ? $ticket['bus_id'] : '';
        $busItem = isset($busMap[$busId]) ? $busMap[$busId] : [];
        $busName = isset($busItem['bus_number']) ? $busItem['bus_number'] : (isset($busItem['name']) ? $busItem['name'] : $busId);
        
        $driverId = isset($ticket['driver_id']) ? $ticket['driver_id'] : '';
        $driverItem = isset($userMap[$driverId]) ? $userMap[$driverId] : [];
        $driverName = isset($driverItem['name']) ? $driverItem['name'] : (isset($driverItem['email']) ? $driverItem['email'] : $driverId);
        
        if (empty($driverName)) $driverName = '-';
        if (empty($busName)) $busName = '-';
        
        $count = isset($ticket['ticket_count']) ? (int)$ticket['ticket_count'] : 0;
        
        $html .= '<tr>
                    <td class="text-center">' . $counter++ . '</td>
                    <td class="text-center">' . $dateStr . '</td>
                    <td class="text-center">' . htmlspecialchars($roundTime) . '</td>
                    <td>' . htmlspecialchars($driverName) . '</td>
                    <td class="text-center">' . htmlspecialchars($busName) . '</td>
                    <td class="text-center">' . htmlspecialchars((string)$count) . '</td>
                  </tr>';
    }
} else {
    $html .= '<tr><td colspan="6" class="text-center">ไม่พบข้อมูล</td></tr>';
}

$html .= '
        </tbody>
    </table>
    
    <div class="summary">
        รวมจำนวนการรายงานรถตู้: ' . count($filteredTickets) . ' รายการ <br/>
        รวมจำนวนผู้โดยสารทั้งหมด: ' . number_format($totalTicketsCount) . ' คน
    </div>
</body>
</html>';

// Setup DOMPDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'ticket_report_' . date('Ymd_His') . '.pdf';
$dompdf->stream($filename, ["Attachment" => false]);
exit;