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
$selectedStatus = isset($_GET['status']) ? $_GET['status'] : '';

// Get data
$issues = $firebaseService->getAllDocuments("issue_reports");
$users = $firebaseService->getAllDocuments("users");

$userMap = [];
foreach ($users as $user) {
    if (isset($user['id'])) {
        $userMap[$user['id']] = $user;
    }
}

// Filter data
$filteredIssues = array_filter($issues, function($issue) use ($selectedStatus) {
    if (!empty($selectedStatus)) {
        $status = isset($issue['status']) ? $issue['status'] : 'pending';
        if ($status !== $selectedStatus) {
            return false;
        }
    }
    return true;
});

// Sort by date (new to old)
usort($filteredIssues, function($a, $b) {
    $timeA = isset($a['timestamp']) ? (is_numeric($a['timestamp']) ? $a['timestamp'] : strtotime(str_replace(' at ', ' ', $a['timestamp']))) : (isset($a['created_at']) ? strtotime(str_replace(' at ', ' ', $a['created_at'])) : 0);
    $timeB = isset($b['timestamp']) ? (is_numeric($b['timestamp']) ? $b['timestamp'] : strtotime(str_replace(' at ', ' ', $b['timestamp']))) : (isset($b['created_at']) ? strtotime(str_replace(' at ', ' ', $b['created_at'])) : 0);
    return $timeB - $timeA;
});

$pendingCount = 0;
$resolvedCount = 0;

foreach ($filteredIssues as $issue) {
    $status = isset($issue['status']) ? $issue['status'] : 'pending';
    if ($status === 'resolved') {
        $resolvedCount++;
    } else {
        $pendingCount++;
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
        body { font-family: "Noto Sans Thai", sans-serif; font-size: 14px; }
        h2 { text-align: center; color: #333; font-weight: 700; }
        p { text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background-color: #f2f2f2; text-align: left; }
        .text-center { text-align: center; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 10px; color: #fff; }
        .bg-red { background-color: #ef4444; }
        .bg-green { background-color: #10b981; }
        .summary { margin-top: 20px; font-weight: bold; }
    </style>
</head>
<body>
    <h2>รายงานการแจ้งปัญหา</h2>';

if ($selectedStatus) {
    $statusText = $selectedStatus === 'resolved' ? 'แก้ไขแล้ว' : 'รอดำเนินการ';
    $html .= '<p>สถานะ: ' . $statusText . '</p>';
}

$html .= '
    <table>
        <thead>
            <tr>
                <th>วันที่/เวลา</th>
                <th>ผู้แจ้ง</th>
                <th>หัวข้อ/รายละเอียด</th>
                <th>สถานะ</th>
            </tr>
        </thead>
        <tbody>';

if (count($filteredIssues) > 0) {
    foreach ($filteredIssues as $issue) {
        $timestamp = isset($issue['timestamp']) ? 
                    (is_numeric($issue['timestamp']) ? $issue['timestamp'] : strtotime(str_replace(' at ', ' ', $issue['timestamp']))) : 
                    (isset($issue['created_at']) ? strtotime(str_replace(' at ', ' ', $issue['created_at'])) : time());
        $dateStr = date("d/m/Y H:i", $timestamp);
        
        $userId = isset($issue['student_id']) ? $issue['student_id'] : '';
        $userItem = isset($userMap[$userId]) ? $userMap[$userId] : [];
        $userName = isset($userItem['name']) ? $userItem['name'] : (isset($userItem['email']) ? $userItem['email'] : 'ไม่ระบุตัวตน');
        
        $title = isset($issue['topic']) ? $issue['topic'] : 'ไม่ระบุหัวข้อ';
        $description = isset($issue['description']) ? $issue['description'] : '';
        
        $status = isset($issue['status']) ? $issue['status'] : 'pending';
        $statusBadge = $status === 'resolved' ? '<span class="badge bg-green">แก้ไขแล้ว</span>' : '<span class="badge bg-red">รอดำเนินการ</span>';
        
        $html .= '<tr>
                    <td>' . $dateStr . '</td>
                    <td>' . htmlspecialchars($userName) . '</td>
                    <td>
                        <strong>' . htmlspecialchars($title) . '</strong><br/>
                        <span style="color: #666;">' . htmlspecialchars($description) . '</span>
                    </td>
                    <td class="text-center">' . $statusBadge . '</td>
                  </tr>';
    }
} else {
    $html .= '<tr><td colspan="4" class="text-center">ไม่พบข้อมูล</td></tr>';
}

$html .= '
        </tbody>
    </table>
    
    <div class="summary">
        รวมจำนวนการแจ้งปัญหาทั้งหมด: ' . count($filteredIssues) . ' รายการ <br/>
        - รอดำเนินการ: ' . $pendingCount . ' รายการ <br/>
        - แก้ไขแล้ว: ' . $resolvedCount . ' รายการ
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

$filename = 'issue_report_' . date('Ymd_His') . '.pdf';
$dompdf->stream($filename, ["Attachment" => false]);
exit;