<?php
// Bus Schedules Page
date_default_timezone_set("Asia/Bangkok");

// ดึงข้อมูลจาก Firebase (ใช้ detailed_schedules แทน schedules)
$schedules = $firebaseService->getAllDocuments("detailed_schedules");

// เรียงลำดับเอกสารตาม ID (เช่น round_01, round_02)
usort($schedules, function($a, $b) {
    return strcmp($a['id'], $b['id']);
});

$totalRounds = count($schedules);
?>

<div class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-end gap-4">
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-primary">ตารางรถวิ่ง</h1>
        <p class="text-gray-500 mt-1 sm:mt-2 text-sm sm:text-base">จัดการรอบรถ เวลา และป้ายจอดแต่ละรอบ</p>
    </div>
    <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
        <a href="services/export_schedule_pdf.php" target="_blank" class="bg-primary hover:bg-accent text-white font-medium rounded-lg text-sm px-4 py-2.5 transition-all duration-200 shadow-lg shadow-indigo-500/30 hover:shadow-indigo-500/50 flex items-center justify-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            พิมพ์ตารางรอบรถ
        </a>
        <button onclick="addRound()" class="bg-primary hover:bg-orange-600 text-white font-medium rounded-lg text-sm px-4 py-2.5 transition-all duration-200 shadow-lg shadow-primary/30 hover:shadow-orange-500/50 hover:-translate-y-0.5 active:translate-y-0">
            + เพิ่มรอบรถใหม่
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
            <h3 class="text-gray-400 font-medium">จำนวนรอบรถปัจจุบัน</h3>
        </div>
        <div class="text-3xl font-bold text-white"><?php echo number_format($totalRounds); ?> <span class="text-sm font-normal text-gray-500">รอบ</span></div>
    </div>
</div>

<!-- Schedule Table -->
<div class="bg-cardbg stagger-3 rounded-2xl shadow-sm border border-gray-700 mb-8 overflow-hidden">
    <div class="p-6 border-b border-gray-700 flex justify-between items-center">
        <h2 class="text-xl font-bold text-white flex items-center">
            <svg class="w-6 h-6 mr-2 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
            เวลาตามรอบและป้ายจอด
        </h2>
        <span class="text-xs text-gray-400 bg-gray-800 border border-gray-700 px-3 py-1 rounded-full">บันทึกอัตโนมัติเมื่อแก้ไขข้อมูล</span>
    </div>

    <div class="overflow-x-auto w-full">
        <table class="min-w-full text-left border-collapse">
            <thead class="bg-gray-800 border-b border-gray-700">
                <tr>
                    <th class="px-4 py-4 font-semibold text-gray-400 w-24 border-r border-gray-700 text-center">รอบ</th>
                    <th class="px-4 py-4 font-semibold text-gray-400 border-r border-gray-700 w-48 text-center">เวลาเริ่ม - สิ้นสุด</th>
                    <th class="px-4 py-4 font-semibold text-gray-400 border-r border-gray-700 min-w-[400px]">รายละเอียดป้ายจอด</th>
                    <th class="px-4 py-3 text-center text-gray-400 font-semibold w-24">จัดการ</th>
                </tr>
            </thead>
            
            <tbody class="divide-y divide-gray-700">
                <?php if (empty($schedules)): ?>
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-gray-500 bg-gray-800/20">ไม่พบข้อมูลตารางเดินรถ กรุณากด "เพิ่มรอบรถใหม่" ด้านบน</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($schedules as $doc): ?>
                        <tr class="hover:bg-gray-800/30 transition-colors">
                            <td class="px-4 py-6 font-bold text-gray-300 border-r border-gray-700 align-top text-center">
                                <span class="bg-white  border border-primary text-primary px-3 py-1.5 rounded-lg text-sm"><?php echo htmlspecialchars($doc['id'] ?? ''); ?></span>
                            </td>

                            <td class="px-4 py-6 border-r border-gray-700 align-top">
                                <div class="flex flex-col gap-3">
                                    <div class="flex flex-col">
                                        <label class="text-xs text-gray-500 mb-1">เวลาที่เริ่มออกรถ</label>
                                        <input type="time" 
                                            value="<?php echo htmlspecialchars($doc['start_time'] ?? ''); ?>" 
                                            onchange="updateScheduleField(this, '<?php echo $doc['id']; ?>', 'start_time')"
                                            class="w-full bg-gray-800 border border-gray-600 text-gray-200 text-sm rounded-lg focus:ring-primary focus:border-primary block p-2 transition-all hover:bg-gray-700">
                                    </div>
                                    <div class="flex items-center justify-center">
                                        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
                                    </div>
                                    <div class="flex flex-col">
                                        <label class="text-xs text-gray-500 mb-1">เวลาสิ้นสุด (ถึงป้ายสุดท้าย)</label>
                                        <input type="time" 
                                            value="<?php echo htmlspecialchars($doc['end_time'] ?? ''); ?>" 
                                            onchange="updateScheduleField(this, '<?php echo $doc['id']; ?>', 'end_time')"
                                            class="w-full bg-gray-800 border border-gray-600 text-gray-200 text-sm rounded-lg focus:ring-primary focus:border-primary block p-2 transition-all hover:bg-gray-700">
                                    </div>
                                </div>
                            </td>

                            <td class="px-4 py-6 border-r border-gray-700 align-top">
                                <div class="flex flex-col gap-3 relative">
                                    <?php 
                                        $stops = $doc['stops'] ?? [];
                                        // Sort stops by order
                                        usort($stops, function($a, $b) {
                                            return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
                                        });
                                    ?>
                                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-3">
                                        <?php foreach($stops as $idx => $stop): ?>
                                        <div class="flex flex-col gap-2 bg-gray-300/50 p-3 rounded-xl border border-gray-700/50 hover:bg-gray-800 hover:border-gray-600 transition-all group">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <span class="flex items-center justify-center bg-white border border-primary  w-6 h-6 rounded-full text-xs text-primary font-bold shrink-0"><?php echo ($idx+1); ?></span>
                                                    <div class="relative">
                                                        <input type="time" title="เวลาถึงป้าย"
                                                            value="<?php echo htmlspecialchars($stop['time'] ?? ''); ?>" 
                                                            onchange="updateStopField('<?php echo $doc['id']; ?>', <?php echo $idx; ?>, 'time', this.value)"
                                                            class="w-32 bg-gray-800 border border-gray-600 text-primary font-medium text-sm rounded-md focus:ring-primary focus:border-primary block p-1.5 transition-all outline-none">
                                                    </div>
                                                </div>
                                                <button onclick="removeStop('<?php echo $doc['id']; ?>', <?php echo $idx; ?>)" class="text-gray-500 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity p-1" title="ลบป้ายนี้">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                </button>
                                            </div>
                                            
                                            <div class="flex flex-col gap-1.5">
                                                <input type="text" title="ชื่อป้ายจอด"
                                                    value="<?php echo htmlspecialchars($stop['name'] ?? ''); ?>" 
                                                    onchange="updateStopField('<?php echo $doc['id']; ?>', <?php echo $idx; ?>, 'name', this.value)"
                                                    class="w-full bg-gray-800 border border-gray-600 text-gray-200 text-xs rounded-md focus:ring-primary focus:border-primary block p-2 transition-all outline-none placeholder-gray-500"
                                                    placeholder="ชื่อป้ายจอด">
                                                    
                                                <div class="flex gap-1.5">
                                                    <input type="number" step="any" title="ละติจูด (Latitude)"
                                                        value="<?php echo htmlspecialchars($stop['lat'] ?? ''); ?>" 
                                                        onchange="updateStopField('<?php echo $doc['id']; ?>', <?php echo $idx; ?>, 'lat', this.value)"
                                                        class="w-1/2 bg-gray-800 border border-gray-600 text-gray-400 text-xs rounded-md focus:ring-primary focus:border-primary block p-1.5 transition-all outline-none placeholder-gray-600 text-center"
                                                        placeholder="Lat">
                                                    <input type="number" step="any" title="ลองจิจูด (Longitude)"
                                                        value="<?php echo htmlspecialchars($stop['lng'] ?? ''); ?>" 
                                                        onchange="updateStopField('<?php echo $doc['id']; ?>', <?php echo $idx; ?>, 'lng', this.value)"
                                                        class="w-1/2 bg-gray-800 border border-gray-600 text-gray-400 text-xs rounded-md focus:ring-primary focus:border-primary block p-1.5 transition-all outline-none placeholder-gray-600 text-center"
                                                        placeholder="Lng">
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <button onclick="addStop('<?php echo $doc['id']; ?>')" class="mt-3 flex items-center justify-center gap-2 text-sm text-primary hover:text-white border-2 border-primary/30 hover:border-primary hover:bg-primary/20 border-dashed rounded-lg py-2.5 w-full transition-all">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                        เพิ่มป้ายจอดใหม่
                                    </button>
                                </div>
                            </td>
                            
                            <td class="px-4 py-6 text-center align-top">
                                <button onclick="deleteRound('<?php echo htmlspecialchars($doc['id'], ENT_QUOTES); ?>')" title="ลบรอบนี้" class="text-red-400 bg-red-400/10 border border-red-400/20 hover:bg-red-500 hover:text-white p-2.5 rounded-xl transition-all duration-200 shadow-sm w-full flex justify-center mt-1">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// --- Update Stop Field (Auto-save) ---
async function updateStopField(id, stopIdx, fieldName, newValue) {
    try {
        const res = await fetch('services/schedule_api.php?action=update_stop', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id: id, stop_index: stopIdx, field: fieldName, value: newValue })
        });
        const data = await res.json();
        
        if (!data.success) {
            alert('บันทึกข้อมูลไม่สำเร็จ: ' + data.message);
        }
    } catch(err) {
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
        console.error(err);
    }
}

// --- Add Stop ---
async function addStop(id) {
    try {
        const res = await fetch('services/schedule_api.php?action=add_stop', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id: id })
        });
        const data = await res.json();
        if (data.success) location.reload();
        else alert('ไม่สามารถเพิ่มป้ายจอดได้: ' + data.message);
    } catch(e) {
        alert('เชื่อมต่อเซิร์ฟเวอร์ไม่ได้');
    }
}

// --- Remove Stop ---
async function removeStop(id, stopIdx) {
    if (!confirm('ยืนยันการลบป้ายจอดนี้ใช่หรือไม่?')) return;
    try {
        const res = await fetch('services/schedule_api.php?action=remove_stop', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id: id, stop_index: stopIdx })
        });
        const data = await res.json();
        if (data.success) location.reload();
        else alert('ไม่สามารถลบป้ายจอดได้: ' + data.message);
    } catch(e) {
        alert('เชื่อมต่อเซิร์ฟเวอร์ไม่ได้');
    }
}

// --- Update Field (Auto-save) ---
async function updateScheduleField(inputEl, id, fieldName) {
    const newValue = inputEl.value;
    inputEl.classList.add('opacity-50');
    
    try {
        const res = await fetch('services/schedule_api.php?action=update_field', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id: id, field: fieldName, value: newValue })
        });
        const data = await res.json();
        
        inputEl.classList.remove('opacity-50');
        
        if (!data.success) {
            alert('บันทึกข้อมูลไม่สำเร็จ: ' + data.message);
        }
    } catch(err) {
        inputEl.classList.remove('opacity-50');
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
        console.error(err);
    }
}

// --- Round CRUD ---
async function addRound() {
    if (!confirm("ระบบจะเพิ่มรอบรถใหม่ต่อจากรอบล่าสุด ยืนยันหรือไม่?")) return;
    
    try {
        const res = await fetch('services/schedule_api.php?action=add_round', {
            method: 'POST'
        });
        const data = await res.json();
        if (data.success) location.reload();
        else alert('ไม่สามารถเพิ่มรอบรถได้: ' + data.message);
    } catch(e) {
        alert('เชื่อมต่อเซิร์ฟเวอร์ไม่ได้');
    }
}

async function deleteRound(id) {
    if (!confirm(`ยืนยันการลบ ${id} ใช่หรือไม่?\n\n* ข้อมูลของรอบนี้จะหายไปอย่างถาวร!`)) return;
    
    try {
        const res = await fetch('services/schedule_api.php?action=delete_round', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id: id })
        });
        const data = await res.json();
        if (data.success) location.reload();
        else alert('ไม่สามารถลบรอบรถได้: ' + data.message);
    } catch(e) {
        alert('เชื่อมต่อเซิร์ฟเวอร์ไม่ได้');
    }
}
</script>
