<?php
// Bus Schedules Page
date_default_timezone_set("Asia/Bangkok");

// ดึงข้อมูลจาก Firebase
$schedules = $firebaseService->getAllDocuments("schedules");

// จัดโครงสร้าง Matrix (Rounds เป็นแถว, Stops เป็นคอลัมน์)
$matrix = [];
$stopsMap = []; // รหัส stop -> ชื่อป้าย (รักษาลำดับ)

// เพื่อรักษาลำดับของ Stop ที่ถูกสร้างขึ้นมา (เช่น stop_01, stop_02) จะดึงจาก ID
// format id = round_XX_stop_YY
foreach ($schedules as $doc) {
    if (preg_match('/stop_(\d+)/', $doc['id'], $matches)) {
        $stopIndex = (int)$matches[1];
        if (!isset($stopsMap[$stopIndex])) {
            $stopsMap[$stopIndex] = $doc['route_name'] ?? 'ไม่ระบุชื่อ';
        }
    }
}
ksort($stopsMap); // เรียงตาม stop_index

foreach ($schedules as $doc) {
    $round = (int)($doc['round'] ?? 0);
    $stopIndex = 0;
    if (preg_match('/stop_(\d+)/', $doc['id'], $matches)) {
        $stopIndex = (int)$matches[1];
    }
    
    if (!isset($matrix[$round])) {
        $matrix[$round] = [];
    }
    
    $matrix[$round][$stopIndex] = $doc;
}

ksort($matrix); // เรียงรอบตามหมายเลข

$totalRounds = count($matrix);
$totalStops = count($stopsMap);
?>

<div class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-end gap-4">
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-primary">ตารางรถวิ่ง</h1>
        <p class="text-gray-500 mt-1 sm:mt-2 text-sm sm:text-base">จัดการรอบรถ เวลา และจุดจอดของสถานี</p>
    </div>
    <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
        <button onclick="addStop()" class="bg-gray-800 hover:bg-gray-900 text-white font-medium rounded-lg text-sm px-4 py-2.5 transition-all duration-200 border border-gray-700 hover:shadow-md hover:-translate-y-0.5 active:translate-y-0 w-full sm:w-auto">
            + เพิ่มจุดจอด
        </button>
        <button onclick="addRound()" class="bg-primary hover:bg-orange-600 text-white font-medium rounded-lg text-sm px-4 py-2.5 transition-all duration-200 shadow-lg shadow-primary/30 hover:shadow-orange-500/50 hover:-translate-y-0.5 active:translate-y-0">
            + เพิ่มรอบรถ (ด้านล่างสุด)
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <div class="bg-cardbg stagger-1 p-6 rounded-2xl shadow-sm border border-gray-700">
        <div class="flex items-center mb-2">
            <div class="p-3 rounded-lg bg-orange-500/20 text-orange-400 mr-4">
                 <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <h3 class="text-gray-400 font-medium">จำนวนรอบรถทั้งหมด</h3>
        </div>
        <div class="text-3xl font-bold text-white"><?php echo number_format($totalRounds); ?> <span class="text-sm font-normal text-gray-500">รอบ</span></div>
    </div>
    
    <div class="bg-cardbg stagger-2 p-6 rounded-2xl shadow-sm border border-gray-700">
        <div class="flex items-center mb-2">
            <div class="p-3 rounded-lg bg-blue-500/20 text-blue-400 mr-4">
                 <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            </div>
            <h3 class="text-gray-400 font-medium">จำนวนป้ายหยุดรถ</h3>
        </div>
        <div class="text-3xl font-bold text-white"><?php echo number_format($totalStops); ?> <span class="text-sm font-normal text-gray-500">จุดจอด</span></div>
    </div>
</div>

<!-- Schedule Matrix Table -->
<div class="bg-cardbg stagger-3 rounded-2xl shadow-sm border border-gray-700 mb-8 overflow-hidden">
    <div class="p-6 border-b border-gray-700 flex justify-between items-center">
        <h2 class="text-xl font-bold text-white flex items-center">
            <svg class="w-6 h-6 mr-2 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
            ตารางเวลาเดินรถ
        </h2>
        <span class="text-xs text-gray-400 bg-gray-800 border border-gray-700 px-3 py-1 rounded-full">บันทึกอัตโนมัติเมื่อกดแก้ไขเวลา</span>
    </div>

    <!-- Scrollable container -->
    <div class="overflow-x-auto w-full">
        <table class="min-w-full text-left border-collapse">
            <!-- Table Header: Stops -->
            <thead class="bg-gray-800 border-b border-gray-700">
                <tr>
                    <!-- Pinned Column for Round Number -->
                    <th class="px-4 py-4 font-semibold text-gray-400 text-center w-24 border-r border-gray-700 z-10 sticky left-0 bg-gray-800">
                        รอบ
                    </th>
                    <!-- Dynamic Stop Columns -->
                    <?php foreach ($stopsMap as $stopIdx => $stopName): ?>
                        <th class="px-4 py-4 min-w-[200px] border-r border-gray-700 align-top group">
                            <div class="flex flex-col h-full justify-between">
                                <div class="font-medium text-white mb-2 truncate" title="<?php echo htmlspecialchars($stopName); ?>">
                                    <?php echo htmlspecialchars($stopName); ?>
                                </div>
                                <div class="flex space-x-2 mt-auto">
                                    <button onclick="editStopName('<?php echo htmlspecialchars($stopName, ENT_QUOTES); ?>')" class="text-blue-400 hover:text-white bg-blue-500/10 hover:bg-blue-500 text-xs px-2 py-1 rounded transition border border-blue-500/20">แก้ไข</button>
                                    <button onclick="deleteStopName('<?php echo htmlspecialchars($stopName, ENT_QUOTES); ?>')" class="text-red-400 hover:text-white bg-red-500/10 hover:bg-red-500 text-xs px-2 py-1 rounded transition border border-red-500/20">ลบ</button>
                                </div>
                            </div>
                        </th>
                    <?php endforeach; ?>
                    <th class="px-4 py-3 bg-gray-800 w-24 text-center text-gray-400 font-semibold align-top text-sm">
                        จัดการรอบ
                    </th>
                </tr>
            </thead>
            
            <!-- Table Body: Rounds -->
            <tbody class="divide-y divide-gray-700">
                <?php if (empty($matrix)): ?>
                    <tr>
                        <td colspan="<?php echo count($stopsMap) + 2; ?>" class="px-6 py-12 text-center text-gray-500">
                            ไม่พบข้อมูลตารางเดินรถ กรุณากด "เพิ่มรอบรถ" ด้านบน
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($matrix as $roundNum => $stopsData): ?>
                        <tr class="hover:bg-gray-800/50 transition-colors">
                            <!-- Pinned Round Cell -->
                            <td class="px-4 py-4 text-center font-bold text-gray-300 border-r border-gray-700 sticky left-0 bg-cardbg">
                                #<?php echo str_pad($roundNum, 2, '0', STR_PAD_LEFT); ?>
                            </td>
                            
                            <!-- Stop Input Cells -->
                            <?php foreach ($stopsMap as $stopIdx => $stopName): ?>
                                <td class="px-4 py-3 border-r border-gray-700">
                                    <?php 
                                    // หาว่ามี document ของ cell นี้ไหม
                                    $cellDoc = $stopsData[$stopIdx] ?? null;
                                    
                                    if ($cellDoc): 
                                        $id = $cellDoc['id'];
                                        $time = $cellDoc['start_time'] ?? '';
                                    ?>
                                        <div class="relative w-full">
                                            <input type="time" 
                                                value="<?php echo htmlspecialchars($time); ?>" 
                                                onchange="updateTime(this, '<?php echo $id; ?>')"
                                                class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary focus:border-primary block p-2 transition-all">
                                            <span class="absolute top-2 right-2 text-green-500 hidden transition-opacity" id="check-<?php echo $id; ?>">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-sm text-gray-400 italic">ไม่มีข้อมูล</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            
                            <!-- Delete Round Cell -->
                            <td class="px-4 py-3 text-center bg-gray-50/50">
                                <button onclick="deleteRound(<?php echo $roundNum; ?>)" title="ลบรอบที่ <?php echo $roundNum; ?>" class="text-red-500 bg-red-100 hover:bg-red-500 hover:text-white p-2 rounded-lg transition-all duration-200">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal สำหรับเพิ่มจุดจอด -->
<div id="addStopModal" class="hidden fixed inset-0 z-50 bg-black/60 flex items-center justify-center backdrop-blur-sm p-4 overflow-y-auto">
    <div class="bg-cardbg rounded-2xl shadow-xl w-full max-w-md relative block my-auto border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center bg-gray-50 dark:bg-gray-800 rounded-t-2xl">
            <h3 class="text-xl font-bold text-gray-800 flex items-center">
                <svg class="w-6 h-6 text-primary mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                เพิ่มจุดจอด/สถานีใหม่
            </h3>
            <button onclick="closeAddStopModal()" class="text-gray-400 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-full p-2 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">ชื่อสถานี/จุดจอด <span class="text-red-500">*</span></label>
                <input type="text" id="newStopName" class="w-full bg-white border border-gray-300 text-gray-900 rounded-lg focus:ring-primary focus:border-primary block p-2.5 shadow-sm" placeholder="เช่น หอพักนักศึกษา, คณะวิศวกรรมศาสตร์">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">ละติจูด (Latitude)</label>
                    <input type="number" step="any" id="newStopLat" class="w-full bg-white border border-gray-300 text-gray-900 rounded-lg focus:ring-primary focus:border-primary block p-2.5 shadow-sm" placeholder="เช่น 13.8188">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">ลองจิจูด (Longitude)</label>
                    <input type="number" step="any" id="newStopLng" class="w-full bg-white border border-gray-300 text-gray-900 rounded-lg focus:ring-primary focus:border-primary block p-2.5 shadow-sm" placeholder="เช่น 100.5140">
                </div>
            </div>
            <p class="text-xs text-gray-500">* พิกัดสามารถเว้นว่างไว้ก่อนและค่อยมาเติมทีหลังได้</p>
            
            <div class="mt-6 flex space-x-3 justify-end pt-4 border-t border-gray-200">
                <button onclick="closeAddStopModal()" class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 hover:shadow-sm rounded-lg transition-all duration-200">
                    ยกเลิก
                </button>
                <button onclick="submitNewStop()" class="px-5 py-2.5 text-sm font-medium text-white bg-primary hover:bg-orange-600 rounded-lg transition-all duration-200 flex items-center shadow-md hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    เพิ่มจุดจอด
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// --- Modal Actions ---
function addStop() {
    document.getElementById('newStopName').value = '';
    document.getElementById('newStopLat').value = '';
    document.getElementById('newStopLng').value = '';
    document.getElementById('addStopModal').classList.remove('hidden');
}

function closeAddStopModal() {
    document.getElementById('addStopModal').classList.add('hidden');
}

async function submitNewStop() {
    const name = document.getElementById('newStopName').value.trim();
    const lat = document.getElementById('newStopLat').value.trim();
    const lng = document.getElementById('newStopLng').value.trim();

    if (!name) {
        alert("กรุณาระบุชื่อจุดจอด");
        return;
    }
    
    try {
        const res = await fetch('services/schedule_api.php?action=add_stop', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ 
                stop_name: name,
                lat: lat,
                lng: lng
            })
        });
        const data = await res.json();
        if (data.success) {
            closeAddStopModal();
            location.reload();
        } else {
            alert('ไม่สามารถเพิ่มจุดจอดได้: ' + data.message);
        }
    } catch(e) {
        alert('เชื่อมต่อเซิร์ฟเวอร์ไม่ได้');
    }
}

// --- Update Time (Auto-save) ---
async function updateTime(inputEl, id) {
    const newTime = inputEl.value;
    inputEl.classList.add('opacity-50');
    
    try {
        const res = await fetch('services/schedule_api.php?action=update_time', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id: id, time: newTime })
        });
        const data = await res.json();
        
        inputEl.classList.remove('opacity-50');
        
        if (data.success) {
            // Flash green checkmark
            const check = document.getElementById('check-' + id);
            if (check) {
                check.classList.remove('hidden');
                setTimeout(() => check.classList.add('hidden'), 2000);
            }
        } else {
            alert('บันทึกเวลาไม่สำเร็จ: ' + data.message);
        }
    } catch(err) {
        inputEl.classList.remove('opacity-50');
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
        console.error(err);
    }
}

// --- Stop CRUD ---
async function editStopName(oldName) {
    const newName = prompt(`เปลี่ยนชื่อจุดจอด "${oldName}" เป็น:`, oldName);
    if (!newName || newName === oldName) return;
    
    try {
        const res = await fetch('services/schedule_api.php?action=update_stop_name', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ old_name: oldName, new_name: newName })
        });
        const data = await res.json();
        if (data.success) location.reload();
        else alert('ไม่สามารถแก้ไขจุดจอดได้: ' + data.message);
    } catch(e) {
        alert('เชื่อมต่อเซิร์ฟเวอร์ไม่ได้');
    }
}

async function deleteStopName(stopName) {
    if (!confirm(`ยืนยันการลบจุดจอด "${stopName}" จากทุกรอบ ใช่หรือไม่?\n\n* ข้อมูลเวลาของป้ายนี้ในทุกรอบจะหายไปอย่างถาวร!`)) return;
    
    try {
        const res = await fetch('services/schedule_api.php?action=delete_stop', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ stop_name: stopName })
        });
        const data = await res.json();
        if (data.success) location.reload();
        else alert('ไม่สามารถลบจุดจอดได้: ' + data.message);
    } catch(e) {
        alert('เชื่อมต่อเซิร์ฟเวอร์ไม่ได้');
    }
}

// --- Round CRUD ---
async function addRound() {
    if (!confirm("ระบบจะเพิ่มรอบรถใหม่ต่อจากรอบล่าสุด โดยคัดลอกจุดจอดจากรอบล่าสุด (เวลาจะว่างเปล่า) ยืนยันหรือไม่?")) return;
    
    try {
        const res = await fetch('services/schedule_api.php?action=add_round', {
            method: 'POST'
        });
        const data = await res.json();
        if (data.success) location.reload();
        else alert('ไม่สามารถเพิ่มรอบได้: ' + data.message);
    } catch(e) {
        alert('เชื่อมต่อเซิร์ฟเวอร์ไม่ได้');
    }
}

async function deleteRound(roundNum) {
    if (!confirm(`ยืนยันการลบรอบรถที่ #${roundNum} ใช่หรือไม่?\n\n* ข้อมูลเวลาของรอบนี้จะหายไปอย่างถาวร!`)) return;
    
    try {
        const res = await fetch('services/schedule_api.php?action=delete_round', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ round: roundNum })
        });
        const data = await res.json();
        if (data.success) location.reload();
        else alert('ไม่สามารถลบรอบได้: ' + data.message);
    } catch(e) {
        alert('เชื่อมต่อเซิร์ฟเวอร์ไม่ได้');
    }
}
</script>