<?php
// Issue Reporting Page
date_default_timezone_set("Asia/Bangkok");

// ดึงข้อมูลจาก Firebase
$issues = $firebaseService->getAllDocuments("issue_reports");
$users = $firebaseService->getAllDocuments("users");

// สร้าง Map สำหรับค้นหาชื่อผู้ใช้
$userMap = [];
foreach ($users as $user) {
    if (isset($user['id'])) {
        $userMap[$user['id']] = $user;
    }
}

// เรียงลำดับข้อมูลจากใหม่ไปเก่า
usort($issues, function($a, $b) {
    $timeA = isset($a['timestamp']) ? (is_numeric($a['timestamp']) ? $a['timestamp'] : strtotime($a['timestamp'])) : 0;
    $timeB = isset($b['timestamp']) ? (is_numeric($b['timestamp']) ? $b['timestamp'] : strtotime($b['timestamp'])) : 0;
    return $timeB - $timeA;
});

// กรองสถานะ
$selectedStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filteredIssues = array_filter($issues, function($issue) use ($selectedStatus) {
    if (empty($selectedStatus)) return true;
    $status = $issue['status'] ?? 'pending';
    return strtolower($status) === strtolower($selectedStatus);
});

// สรุปข้อมูล
$totalIssues = count($issues);
$pendingIssues = 0;
$resolvedIssues = 0;

foreach ($issues as $issue) {
    $status = strtolower($issue['status'] ?? 'pending');
    if ($status === 'pending') {
        $pendingIssues++;
    } elseif ($status === 'resolved' || $status === 'completed' || $status === 'done') {
        $resolvedIssues++;
    }
}
?>

<div class="mb-8 flex justify-between items-end">
    <div>
        <h1 class="text-3xl font-bold text-primary">รายงานปัญหา</h1>
        <p class="text-gray-400 mt-2">ตรวจสอบและติดตามการแจ้งปัญหาการใช้งานต่างๆ จากผู้ใช้</p>
    </div>
    <div class="flex space-x-3">
        <select id="statusFilter" class="bg-gray-800 border border-gray-700 text-white text-sm rounded-lg focus:ring-primary focus:border-primary block p-2.5" onchange="updateIssueFilters()">
            <option value="">ทุกสถานะ</option>
            <option value="pending" <?php echo $selectedStatus === 'pending' ? 'selected' : ''; ?>>รอดำเนินการ</option>
            <option value="resolved" <?php echo $selectedStatus === 'resolved' ? 'selected' : ''; ?>>แก้ไขแล้ว</option>
        </select>
        
        <a href="?page=issue_reports" class="bg-primary hover:bg-accent text-white font-medium rounded-lg text-sm px-5 py-2.5 transition-colors flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            รีเฟรชข้อมูล
        </a>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-cardbg p-6 rounded-2xl shadow-lg border border-gray-700 relative overflow-hidden">
        <div class="absolute -right-4 -bottom-4 opacity-10">
            <svg class="w-32 h-32" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        </div>
        <div class="flex items-center mb-2">
            <div class="p-3 rounded-lg bg-red-500/20 text-red-400 mr-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <h3 class="text-gray-400 font-medium">รอดำเนินการ</h3>
        </div>
        <div class="text-3xl font-bold text-white"><?php echo number_format($pendingIssues); ?> <span class="text-sm font-normal text-gray-500">รายการ</span></div>
    </div>
    
    <div class="bg-cardbg p-6 rounded-2xl shadow-lg border border-gray-700 relative overflow-hidden">
        <div class="absolute -right-4 -bottom-4 opacity-10">
             <svg class="w-32 h-32" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
        <div class="flex items-center mb-2">
            <div class="p-3 rounded-lg bg-green-500/20 text-green-400 mr-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <h3 class="text-gray-400 font-medium">แก้ไขแล้ว</h3>
        </div>
        <div class="text-3xl font-bold text-white"><?php echo number_format($resolvedIssues); ?> <span class="text-sm font-normal text-gray-500">รายการ</span></div>
    </div>

    <div class="bg-cardbg p-6 rounded-2xl shadow-lg border border-gray-700 relative overflow-hidden">
        <div class="absolute -right-4 -bottom-4 opacity-10">
             <svg class="w-32 h-32" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16v12H4z"></path></svg>
        </div>
        <div class="flex items-center mb-2">
            <div class="p-3 rounded-lg bg-blue-500/20 text-blue-400 mr-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
            </div>
            <h3 class="text-gray-400 font-medium">รวมรายงานปัญหาทั้งหมด</h3>
        </div>
        <div class="text-3xl font-bold text-white"><?php echo number_format($totalIssues); ?> <span class="text-sm font-normal text-gray-500">รายการ</span></div>
    </div>
</div>

<div class="bg-cardbg rounded-2xl shadow-sm border border-gray-700 overflow-hidden mb-8">
    <div class="p-6 border-b border-gray-700 flex justify-between items-center">
        <h2 class="text-xl font-bold text-white flex items-center">
            <svg class="w-6 h-6 mr-2 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            ข้อมูลรายการแจ้งปัญหา
        </h2>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-gray-300">
            <thead class="bg-gray-800 text-xs uppercase text-gray-400 border-b border-gray-700">
                <tr>
                    <th class="px-6 py-4 font-medium whitespace-nowrap">เวลาที่แจ้ง</th>
                    <th class="px-6 py-4 font-medium whitespace-nowrap">ผู้แจ้ง (Student ID)</th>
                    <th class="px-6 py-4 font-medium min-w-[150px]">หัวข้อปัญหา</th>
                    <th class="px-6 py-4 font-medium min-w-[200px] w-1/3">รายละเอียด</th>
                    <th class="px-6 py-4 font-medium whitespace-nowrap">สถานะ</th>
                    <th class="px-6 py-4 font-medium text-center whitespace-nowrap">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
                <?php if (empty($filteredIssues)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                            ไม่พบข้อมูลการแจ้งปัญหา
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    foreach ($filteredIssues as $issue): 
                        // สกัดข้อมูลเวลา
                        $timeStr = "-";
                        if (isset($issue['timestamp'])) {
                            $ts = is_numeric($issue['timestamp']) ? $issue['timestamp'] : strtotime($issue['timestamp']);
                            $timeStr = date("d/m/Y H:i:s", $ts);
                        }
                        
                        // หาชื่อผู้แจ้ง
                        $studentId = $issue['student_id'] ?? "-";
                        $studentName = "-";
                        if (isset($userMap[$studentId])) {
                            $studentName = $userMap[$studentId]['name'] ?? $studentName;
                        }
                        
                        // สถานะ
                        $status = strtolower($issue['status'] ?? "pending");
                        $statusBadge = "";
                        if ($status === 'pending') {
                            $statusBadge = '<span class="px-3 py-1 bg-orange-500/20 text-orange-400 dark:text-orange-400 text-xs font-bold rounded-full flex items-center w-max border border-orange-500/30"><div class="w-2 h-2 rounded-full bg-orange-500 mr-2 animate-pulse"></div> รอดำเนินการ</span>';
                        } else if ($status === 'resolved' || $status === 'completed') {
                            $statusBadge = '<span class="px-3 py-1 bg-green-500/20 text-green-400 dark:text-green-400 text-xs font-bold rounded-full flex items-center w-max border border-green-500/30"><div class="w-2 h-2 rounded-full bg-green-500 mr-2"></div> แก้ไขแล้ว</span>';
                        } else {
                            $statusBadge = '<span class="px-3 py-1 bg-gray-500/20 text-gray-400 dark:text-gray-300 text-xs font-bold rounded-full border border-gray-500/30">' . htmlspecialchars($status) . '</span>';
                        }
                    ?>
                    <tr class="hover:bg-gray-800 transition-colors group">
                        <td class="px-6 py-4 text-sm text-gray-400 whitespace-nowrap"><?php echo $timeStr; ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-9 h-9 rounded-full bg-gray-700 border border-gray-600 flex items-center justify-center mr-3 text-white font-bold shrink-0">
                                    <?php echo mb_strtoupper(mb_substr($studentName !== "-" ? $studentName : ($studentId !== "-" ? $studentId : "?"), 0, 1, 'UTF-8'), 'UTF-8'); ?>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-sm font-medium text-white"><?php echo htmlspecialchars($studentName !== "-" ? $studentName : "ไม่ทราบชื่อ"); ?></span>
                                    <?php if ($studentId !== "-"): ?>
                                        <span class="text-xs text-gray-400 mt-0.5"><?php echo htmlspecialchars($studentId); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 font-medium text-white break-words"><?php echo htmlspecialchars($issue['topic'] ?? "-"); ?></td>
                        <td class="px-6 py-4 text-sm text-gray-300 break-words">
                            <div class="line-clamp-2 hover:line-clamp-none transition-all duration-300">
                                <?php echo nl2br(htmlspecialchars($issue['description'] ?? "-")); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <?php echo $statusBadge; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center justify-center space-x-2">
                                <?php if ($status === 'pending'): ?>
                                <button onclick="quickResolveIssue('<?php echo htmlspecialchars($issue['id'] ?? ''); ?>')" title="ดำเนินการแก้ไขแล้ว" class="flex items-center px-3 py-1.5 bg-green-500/10 hover:bg-green-500 text-green-500 hover:text-white rounded-lg transition-all duration-200 text-sm font-medium border border-green-500/20 hover:border-green-500 group/btn">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    แก้ไขแล้ว
                                </button>
                                <?php endif; ?>
                                
                                <button onclick="viewIssueDetails('<?php echo htmlspecialchars(json_encode($issue), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($studentName); ?>', '<?php echo $timeStr; ?>')" title="ดูรายละเอียดข้อความ" class="text-blue-400 bg-blue-500/10 hover:bg-blue-500 hover:text-white p-2 rounded-lg transition-all duration-200 border border-blue-500/20 hover:border-blue-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg> 
                                </button>

                                <button onclick="deleteIssue('<?php echo htmlspecialchars($issue['id'] ?? ''); ?>')" title="ลบข้อมูล" class="text-red-400 bg-red-500/10 hover:bg-red-500 hover:text-white p-2 rounded-lg transition-all duration-200 border border-red-500/20 hover:border-red-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal สำหรับแก้ไข/ดูรายละเอียด -->
<div id="issueModal" class="hidden fixed inset-0 z-50 bg-black/60 flex items-center justify-center backdrop-blur-sm p-4 overflow-y-auto">
    <div class="bg-cardbg rounded-2xl shadow-xl border border-gray-700 w-full max-w-lg relative block my-auto left-0 right-0 mx-auto">
        <div class="px-6 py-4 border-b border-gray-700 flex justify-between items-center">
            <h3 class="text-xl font-bold text-white flex items-center">
                <svg class="w-6 h-6 text-primary mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                รายละเอียดปัญหา
            </h3>
            <button onclick="closeIssueModal()" class="text-gray-400 hover:text-white bg-gray-800 hover:bg-gray-700 rounded-full p-2 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        
        <div class="p-6 space-y-4">
            <input type="hidden" id="editIssueId" />
            
            <div>
                <label class="block text-gray-400 text-sm font-medium mb-1">หัวข้อปัญหา</label>
                <div id="modalTopic" class="text-white bg-gray-800/50 p-3 rounded-lg border border-gray-700"></div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-400 text-sm font-medium mb-1">เวลาที่แจ้ง</label>
                    <div id="modalTime" class="text-gray-300 text-sm"></div>
                </div>
                <div>
                    <label class="block text-gray-400 text-sm font-medium mb-1">ผู้แจ้งปัญหา</label>
                    <div id="modalUser" class="text-gray-300 text-sm"></div>
                </div>
            </div>
            
            <div>
                <label class="block text-gray-400 text-sm font-medium mb-1">รายละเอียดเพิ่มเติม</label>
                <div id="modalDesc" class="text-gray-300 bg-gray-800/50 p-3 rounded-lg border border-gray-700 whitespace-pre-wrap text-sm min-h-[100px]"></div>
            </div>
            
            <div class="pt-4 border-t border-gray-700">
                <label class="block text-gray-300 text-sm font-bold mb-2">อัปเดตสถานะ</label>
                <select id="editStatus" class="w-full bg-gray-800 border border-gray-600 text-white rounded-lg focus:ring-primary focus:border-primary block p-2.5">
                    <option value="pending">รอดำเนินการ </option>
                    <option value="resolved">แก้ไขแล้ว </option>
                </select>
            </div>
            
            <div class="mt-6 flex space-x-3 justify-end pt-2">
                <button onclick="closeIssueModal()" class="px-5 py-2.5 text-sm font-medium text-gray-300 bg-gray-800 hover:bg-gray-700 border border-gray-600 rounded-lg transition-colors">
                    ยกเลิก
                </button>
                <button onclick="saveIssueStatus()" class="px-5 py-2.5 text-sm font-medium text-white bg-primary hover:bg-orange-600 rounded-lg transition-colors flex items-center shadow-lg shadow-primary/30">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    บันทึกสถานะ
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Filter actions
function updateIssueFilters() {
    const statusVal = document.getElementById('statusFilter').value;
    let url = '?page=issue_reports';
    if (statusVal) {
        url += '&status=' + encodeURIComponent(statusVal);
    }
    window.location.href = url;
}

// Modal handling
const modal = document.getElementById('issueModal');

function quickResolveIssue(issueId) {
    if(confirm('คุณต้องการทำเครื่องหมายว่าปัญหานี้ได้รับการแก้ไขแล้วใช่หรือไม่?')) {
        fetch('services/issue_api.php?action=update_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: issueId, status: 'resolved' })
        })
        .then(response => response.json())
        .then(data => {
            if(data.status === 'success') {
                window.location.reload();
            } else {
                alert('เกิดข้อผิดพลาด: ' + (data.message || 'ไม่สามารถอัปเดตสถานะได้'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
        });
    }
}

function viewIssueDetails(issueJson, studentName, timeStr) {
    if (!issueJson) return;
    try {
        const issue = JSON.parse(issueJson);
        
        document.getElementById('modalTopic').textContent = issue.topic || '-';
        document.getElementById('modalTime').textContent = timeStr;
        document.getElementById('modalUser').textContent = studentName + ' (' + (issue.student_id ? issue.student_id : 'ไม่ระบุ') + ')';
        document.getElementById('modalDesc').textContent = issue.description || '-';
        
        // Form states
        document.getElementById('editIssueId').value = issue.id;
        document.getElementById('editStatus').value = (issue.status || 'pending').toLowerCase() === 'pending' ? 'pending' : 'resolved';
        
        modal.classList.remove('hidden');
    } catch(e) {
        console.error("Error parsing issue data", e);
    }
}

function closeIssueModal() {
    modal.classList.add('hidden');
}

// Save status via API
function saveIssueStatus() {
    const id = document.getElementById('editIssueId').value;
    const newStatus = document.getElementById('editStatus').value;
    
    if(!id) return;
    
    fetch('services/issue_api.php?action=update_status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, status: newStatus })
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            window.location.reload();
        } else {
            alert('เกิดข้อผิดพลาด: ' + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้');
    });
}

// Delete Issue via API
function deleteIssue(id) {
    if(!id) return;
    if(!confirm('คุณแน่ใจหรือไม่ว่าต้องการลบรายการแจ้งปัญหานี้? การกระทำนี้ไม่สามารถย้อนกลับได้')) {
        return;
    }
    
    fetch('services/issue_api.php?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            window.location.reload();
        } else {
            alert('เกิดข้อผิดพลาด: ' + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้');
    });
}
</script>