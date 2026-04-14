<?php
// Only basic PHP - no server-side data fetching needed anymore
// All data is loaded real-time via Firebase JS SDK (operation_history.js)
date_default_timezone_set("Asia/Bangkok");
$today = date("Y-m-d");
?>

<!-- ─── Page Header ──────────────────────────────────────────────────────────── -->
<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-end gap-4">
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-primary">ประวัติการเดินรถ</h1>
        <p class="text-gray-400 mt-1 sm:mt-2 text-sm sm:text-base" id="history-date-display">กำลังโหลด...</p>
    </div>
    <div class="flex items-center">
        <span class="flex items-center text-xs sm:text-sm text-green-400 bg-green-300/30 px-3 py-1.5 rounded-full border border-green-700">
            <span class="w-2 h-2 bg-green-400 rounded-full mr-2 animate-pulse"></span>
            Real-time Active
        </span>
    </div>
</div>

<!-- ─── Summary Stats ──────────────────────────────────────────────────────── -->
<div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
    <div class="bg-cardbg rounded-xl border border-gray-700 p-4 text-center">
        <div class="text-2xl font-bold text-primary" id="stat-rounds">-</div>
        <div class="text-xs text-gray-400 mt-1">รอบที่ทำงาน</div>
    </div>
    <div class="bg-cardbg rounded-xl border border-gray-700 p-4 text-center">
        <div class="text-2xl font-bold text-white" id="stat-total-arrivals">-</div>
        <div class="text-xs text-gray-400 mt-1">ป้ายที่ผ่าน (รวม)</div>
    </div>
    <div class="bg-cardbg rounded-xl border border-green-800/40 p-4 text-center bg-green-900/10">
        <div class="text-2xl font-bold text-green-400" id="stat-on-time">-</div>
        <div class="text-xs text-gray-400 mt-1">ตรงเวลา</div>
    </div>
    <div class="bg-cardbg rounded-xl border border-yellow-800/40 p-4 text-center bg-yellow-900/10">
        <div class="text-2xl font-bold text-yellow-400" id="stat-late">-</div>
        <div class="text-xs text-gray-400 mt-1">ล่าช้า</div>
    </div>
    <div class="bg-cardbg rounded-xl border border-blue-800/40 p-4 text-center bg-blue-900/10">
        <div class="text-2xl font-bold text-blue-400" id="stat-early">-</div>
        <div class="text-xs text-gray-400 mt-1">ก่อนกำหนด</div>
    </div>
</div>

<!-- ─── Filters ───────────────────────────────────────────────────────────── -->
<div class="bg-cardbg rounded-xl border border-gray-700 p-4 mb-6">
    <div class="flex flex-wrap gap-3 items-center">
        <!-- Date Picker -->
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <input type="date" id="historyDate"
                class="bg-gray-800 border border-gray-700 text-white text-sm rounded-lg focus:ring-primary focus:border-primary block p-2.5"
                value="<?php echo $today; ?>" />
        </div>

        <!-- Stop Filter -->
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <select id="stopFilter" class="bg-gray-800 border border-gray-700 text-white text-sm rounded-lg focus:ring-primary focus:border-primary block p-2.5">
                <option value="">ทุกป้ายจอด</option>
                <!-- populated by JS from scheduleStops -->
            </select>
        </div>

        <!-- Status Filter -->
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
            </svg>
            <select id="statusFilter" class="bg-gray-800 border border-gray-700 text-white text-sm rounded-lg focus:ring-primary focus:border-primary block p-2.5">
                <option value="">ทุกสถานะ</option>
                <option value="ON_TIME">ตรงเวลา</option>
                <option value="LATE">ล่าช้า</option>
                <option value="EARLY">ก่อนกำหนด</option>
            </select>
        </div>

        <div class="ml-auto text-xs text-gray-500 flex items-center gap-1.5">
            <span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse"></span>
            อัปเดตอัตโนมัติ (ไม่ต้องรีเฟรช)
        </div>
    </div>
</div>

<!-- ─── Legend ────────────────────────────────────────────────────────────── -->
<div class="flex flex-wrap gap-3 mb-4 text-xs">
    <div class="flex items-center gap-1.5 text-green-400">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        ตรงเวลา (±5 นาที)
    </div>
    <div class="flex items-center gap-1.5 text-yellow-400">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        ล่าช้า (> 5 นาที)
    </div>
    <div class="flex items-center gap-1.5 text-blue-400">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        ก่อนกำหนด (> 5 นาทีก่อน)
    </div>
    <div class="flex items-center gap-1.5 text-gray-500">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        รอข้อมูล (ยังไม่ถึงป้าย)
    </div>
</div>

<!-- ─── Loading State ─────────────────────────────────────────────────────── -->
<div id="history-loading" class="text-center py-12">
    <svg class="animate-spin h-10 w-10 mx-auto text-primary mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
    <p class="text-gray-400 text-sm">กำลังโหลดข้อมูลแบบ Real-time...</p>
</div>

<!-- ─── Main Content: Per-round Cards with Stop Timeline ─────────────────── -->
<div id="history-table-container">
    <!-- Populated by operation_history.js -->
</div>

<!-- ─── Load JS Module ────────────────────────────────────────────────────── -->
<script type="module" src="assets/js/operation_history.js?v=<?php echo time(); ?>"></script>
