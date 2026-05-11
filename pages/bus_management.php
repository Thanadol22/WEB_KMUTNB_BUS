<?php
date_default_timezone_set("Asia/Bangkok");
?>

<div class="mb-6">
    <h1 class="text-2xl sm:text-3xl font-bold text-primary">จัดการรถและตรวจสอบสถานะแบตเตอรี่</h1>
    <p class="text-gray-400 mt-2">ดูรายละเอียดรถทั้งหมด และสถานะแบตเตอรี่แบบเรียลไทม์จากระบบเซ็นเซอร์</p>
</div>

<!-- Container for buses -->
<div id="bus-management-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <!-- Loading skeleton -->
    <div class="bg-cardbg p-6 rounded-2xl shadow-lg border border-gray-700 animate-pulse">
        <div class="h-6 bg-gray-700 rounded w-1/2 mb-4"></div>
        <div class="space-y-3">
            <div class="h-4 bg-gray-700 rounded w-3/4"></div>
            <div class="h-4 bg-gray-700 rounded w-full"></div>
            <div class="h-4 bg-gray-700 rounded w-5/6"></div>
        </div>
    </div>
</div>

<!-- Edit Bus Modal -->
<div id="bus-modal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-black/60" onclick="closeBusModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="inline-block align-bottom bg-cardbg rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-700">
            <div class="bg-gray-800 px-6 py-4 border-b border-gray-700 flex justify-between items-center">
                <h3 class="text-xl font-bold text-white">แก้ไขข้อมูลรถ</h3>
                <button onclick="closeBusModal()" class="text-gray-400 hover:text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <form id="bus-form" class="p-6 space-y-4">
                <input type="hidden" id="bus_id_hidden">
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">ทะเบียนรถ</label>
                    <input type="text" id="license_plate" class="w-full bg-darkbg border border-gray-700 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-primary transition-all">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">พนักงานขับรถ</label>
                    <select id="driver_id" class="w-full bg-darkbg border border-gray-700 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-primary transition-all">
                        <option value="">-- เลือกคนขับ --</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">สถานะ</label>
                    <select id="status" class="w-full bg-darkbg border border-gray-700 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-primary transition-all">
                        <option value="active">พร้อมให้บริการ</option>
                        <option value="maintenance">ซ่อมบำรุง</option>
                        <option value="inactive">หยุดให้บริการ</option>
                    </select>
                </div>
                <div class="flex items-center space-x-2 pt-2">
                    <input type="checkbox" id="is_active" class="w-4 h-4 text-primary bg-darkbg border-gray-700 rounded focus:ring-primary">
                    <label for="is_active" class="text-sm text-gray-400 font-medium">เปิดใช้งาน (is_active)</label>
                </div>
                <div class="pt-4 flex space-x-3">
                    <button type="button" onclick="closeBusModal()" class="flex-1 bg-white hover:bg-red-50 text-red-600 border-2 border-red-600 font-bold py-3 rounded-xl transition-all">ยกเลิก</button>
                    <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-green-900/20">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Load JS Module -->
<script type="module" src="assets/js/bus_management.js?v=<?php echo time(); ?>"></script>
