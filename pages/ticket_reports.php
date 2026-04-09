<?php
// Ticket Reporting & Summary Page
date_default_timezone_set("Asia/Bangkok");

// ดึงข้อมูลจาก Firebase
$tickets = $firebaseService->getAllDocuments("ticket_reports");
$users = $firebaseService->getAllDocuments("users");
$buses = $firebaseService->getAllDocuments("buses");

// สร้าง Map สำหรับค้นหาชื่อผู้ใช้และข้อมูลรถ
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

// เรียงลำดับข้อมูลตั๋วจากใหม่ไปเก่า (ตาม timestamp หรือ created_at)
usort($tickets, function($a, $b) {
    $timeA = isset($a['timestamp']) ? (is_numeric($a['timestamp']) ? $a['timestamp'] : strtotime($a['timestamp'])) : (isset($a['created_at']) ? strtotime($a['created_at']) : 0);
    $timeB = isset($b['timestamp']) ? (is_numeric($b['timestamp']) ? $b['timestamp'] : strtotime($b['timestamp'])) : (isset($b['created_at']) ? strtotime($b['created_at']) : 0);
    return $timeB - $timeA;
});

// กรองตามวันที่ถ้ามีการเลือก
$selectedDate = isset($_GET['date']) ? $_GET['date'] : '';
$selectedBus = isset($_GET['bus']) ? $_GET['bus'] : '';

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

// สรุปข้อมูล
$totalReports = count($filteredTickets);
$todayReports = 0;
$today = date("Y-m-d");

$totalTicketsCount = 0; // ยอดรวมตั๋วจริง (นับจากฟิลด์ ticket_count)
$todayTicketsCount = 0; // ยอดรวมตั๋วจริงของวันนี้

foreach ($tickets as $ticket) {
    $ticketTime = isset($ticket['timestamp']) ? 
        (is_numeric($ticket['timestamp']) ? $ticket['timestamp'] : strtotime($ticket['timestamp'])) : 
        (isset($ticket['created_at']) ? strtotime($ticket['created_at']) : 0);
        
    $count = isset($ticket['ticket_count']) ? (int)$ticket['ticket_count'] : 0;
    
    // คำนวณยอดรวมทั้งหมด (เฉพาะในรายการที่ filter มา)
    if (empty($selectedDate) || date("Y-m-d", $ticketTime) === $selectedDate) {
        $totalTicketsCount += $count;
    }
        
    if (date("Y-m-d", $ticketTime) === $today) {
        $todayReports++;
        $todayTicketsCount += $count;
    }
}
?>

<div class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-end gap-4">
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-primary">รายงานการจอง/ใช้ตั๋ว</h1>
        <p class="text-gray-400 mt-1 sm:mt-2 text-sm sm:text-base">ตรวจสอบและติดตามประวัติการใช้งานตั๋วโดยสารของแต่ละรอบรถ</p>
    </div>
    <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
        <select id="ticketBus" class="bg-gray-800 border border-gray-700 text-white text-sm rounded-lg focus:ring-primary focus:border-primary block p-2.5 w-full sm:w-auto" onchange="updateTicketFilters()">
            <option value="">รถทุกคัน</option>
            <?php foreach ($buses as $busOption): ?>
                <?php 
                $bId = $busOption['bus_id'] ?? $busOption['id'] ?? '';
                $bName = $busOption['bus_number'] ?? $busOption['name'] ?? $bId;
                if(empty($bId)) continue;
                ?>
                <option value="<?php echo htmlspecialchars($bId); ?>" <?php if ($selectedBus === $bId) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($bName); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <input type="date" id="ticketDate" class="bg-gray-800 border border-gray-700 text-white text-sm rounded-lg focus:ring-primary focus:border-primary block p-2.5 w-full sm:w-auto" value="<?php echo htmlspecialchars($selectedDate); ?>" onchange="updateTicketFilters()" />
        
        <a href="#" onclick="window.location.href='?page=ticket_reports'" class="bg-gray-500 hover:bg-gray-600 text-white font-medium rounded-lg text-sm px-4 py-2.5 transition-colors flex items-center justify-center w-full sm:w-auto">
            ล้างตัวกรอง
        </a>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-cardbg stagger-1 p-6 rounded-2xl shadow-lg border border-gray-700 relative overflow-hidden">
        <div class="absolute -right-4 -bottom-4 opacity-10">
            <svg class="w-32 h-32" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
        </div>
        <div class="flex items-center mb-2">
            <div class="p-3 rounded-lg bg-blue-500/20 text-blue-400 mr-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </div>
            <h3 class="text-gray-400 font-medium">รอบรถที่รายงานวันนี้</h3>
        </div>
        <div class="text-3xl font-bold text-white"><?php echo number_format($todayReports); ?> <span class="text-sm font-normal text-gray-500">รอบ</span></div>
    </div>
    
    <div class="bg-cardbg stagger-2 p-6 rounded-2xl shadow-lg border border-gray-700 relative overflow-hidden">
        <div class="absolute -right-4 -bottom-4 opacity-10">
             <svg class="w-32 h-32" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
        </div>
        <div class="flex items-center mb-2">
            <div class="p-3 rounded-lg bg-indigo-500/20 text-indigo-400 mr-4">
                 <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path></svg>
            </div>
            <h3 class="text-gray-400 font-medium">ยอดตั๋ววันนี้</h3>
        </div>
        <div class="text-3xl font-bold text-white"><?php echo number_format($todayTicketsCount); ?> <span class="text-sm font-normal text-gray-500">ใบ</span></div>
    </div>

    <div class="bg-cardbg stagger-3 p-6 rounded-2xl shadow-lg border border-gray-700 relative overflow-hidden">
        <div class="absolute -right-4 -bottom-4 opacity-10">
             <svg class="w-32 h-32" fill="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
        <div class="flex items-center mb-2">
            <div class="p-3 rounded-lg bg-green-500/20 text-green-400 mr-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </div>
            <h3 class="text-gray-400 font-medium">รวมรอบการรายงาน<?php echo empty($selectedDate) ? 'ทั้งหมด' : 'ที่เลือก'; ?></h3>
        </div>
        <div class="text-3xl font-bold text-white"><?php echo number_format($totalReports); ?> <span class="text-sm font-normal text-gray-500">รอบ</span></div>
    </div>

    <div class="bg-cardbg stagger-4 p-6 rounded-2xl shadow-lg border border-gray-700 relative overflow-hidden">
        <div class="absolute -right-4 -bottom-4 opacity-10">
             <svg class="w-32 h-32" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
        </div>
        <div class="flex items-center mb-2">
            <div class="p-3 rounded-lg bg-orange-500/20 text-orange-400 mr-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </div>
            <h3 class="text-gray-400 font-medium">รวมยอดตั๋ว<?php echo empty($selectedDate) ? 'ทั้งหมด' : 'ที่เลือก'; ?></h3>
        </div>
        <div class="text-3xl font-bold text-white"><?php echo number_format($totalTicketsCount); ?> <span class="text-sm font-normal text-gray-500">ใบ</span></div>
    </div>
</div>

<div class="bg-cardbg stagger-5 rounded-2xl shadow-lg border border-gray-700 overflow-hidden mb-8">
    <div class="p-6 border-b border-gray-700 flex justify-between items-center">
        <h2 class="text-xl font-bold text-white flex items-center">
            <svg class="w-6 h-6 mr-2 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
            ตารางข้อมูลตั๋ว
        </h2>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-gray-300">
            <thead class="bg-gray-800/50 text-xs uppercase text-gray-400">
                <tr>
                    <th class="px-6 py-4 font-medium">ลำดับ</th>
                    <th class="px-6 py-4 font-medium">รหัสเอกสาร</th>
                    <th class="px-6 py-4 font-medium">เวลาบันทึก (Timestamp)</th>
                    <th class="px-6 py-4 font-medium">เวลารอบรถ</th>
                    <th class="px-6 py-4 font-medium">ชื่อคนขับ</th>
                    <th class="px-6 py-4 font-medium">หมายเลขรถ</th>
                    <th class="px-6 py-4 font-medium">จำนวนคนใช้ตั๋ว</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700/50">
                <?php if (empty($filteredTickets)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                            ไม่พบข้อมูลการบันทึกรายงานตั๋วในระบบ
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $counter = 1;
                    foreach ($filteredTickets as $ticket): 
                        // สกัดข้อมูลเวลา
                        $ticketTime = "-";
                        if (isset($ticket['timestamp'])) {
                            $ts = is_numeric($ticket['timestamp']) ? $ticket['timestamp'] : strtotime($ticket['timestamp']);
                            $ticketTime = date("d/m/Y H:i:s", $ts);
                        } else if (isset($ticket['created_at'])) {
                            $ticketTime = date("d/m/Y H:i:s", strtotime($ticket['created_at']));
                        }
                        
                        // หาชื่อผู้ขับรถ
                        $driverName = "-";
                        if (isset($ticket['driver_id']) && isset($userMap[$ticket['driver_id']])) {
                            $driverName = $userMap[$ticket['driver_id']]['name'] ?? $ticket['driver_id'];
                        } else if (isset($ticket['driver_id'])) {
                            $driverName = $ticket['driver_id'];
                        }
                        
                        // หาข้อมูลรถ
                        $busName = "-";
                        if (isset($ticket['bus_id']) && isset($busMap[$ticket['bus_id']])) {
                            $busName = $busMap[$ticket['bus_id']]['bus_number'] ?? $busMap[$ticket['bus_id']]['name'] ?? $ticket['bus_id'];
                        } else if (isset($ticket['bus_id'])) {
                            $busName = $ticket['bus_id'];
                        }
                        
                        $roundTime = $ticket['round_time'] ?? "-";
                        $ticketCount = $ticket['ticket_count'] ?? 0;
                    ?>
                    <tr class="hover:bg-gray-800/30 transition-colors">
                        <td class="px-6 py-4"><?php echo $counter++; ?></td>
                        <td class="px-6 py-4 font-mono text-xs text-gray-500"><?php echo htmlspecialchars($ticket['id'] ?? "-"); ?></td>
                        <td class="px-6 py-4 text-sm"><?php echo $ticketTime; ?></td>
                        <td class="px-6 py-4 text-primary font-medium"><?php echo htmlspecialchars($roundTime); ?></td>
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full bg-gray-500 flex items-center justify-center mr-3 text-white font-bold shadow-sm">
                                    <?php echo mb_strtoupper(mb_substr($driverName !== "-" ? $driverName : "?", 0, 1, 'UTF-8'), 'UTF-8'); ?>
                                </div>
                                <?php echo htmlspecialchars($driverName); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <?php if ($busName !== "-"): ?>
                                <span class="bg-gray-100 border border-gray-300 dark:bg-gray-800 dark:border-gray-600 px-2 py-1 rounded text-sm inline-flex items-center text-gray-700 dark:text-gray-300">
                                    <svg class="w-4 h-4 mr-1 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h8M8 11h8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                    <?php echo htmlspecialchars($busName); ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-3 py-1 bg-green-500/20 text-green-400 text-sm font-bold rounded-full border border-green-500/30">
                                <?php echo (int)$ticketCount; ?> ใบ
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function updateTicketFilters() {
    const dateVal = document.getElementById('ticketDate').value;
    const busVal = document.getElementById('ticketBus').value;
    let url = '?page=ticket_reports';
    if (dateVal) {
        url += '&date=' + encodeURIComponent(dateVal);
    }
    if (busVal) {
        url += '&bus=' + encodeURIComponent(busVal);
    }
    window.location.href = url;
}
</script>
