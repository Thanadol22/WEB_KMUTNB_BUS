<div class="mb-6 flex justify-between items-end">
    <div>
        <h1 class="text-3xl font-bold text-primary">ระบบติดตามรถ </h1>
        <p class="text-gray-400 mt-2">แสดงตำแหน่งรถทุกคันแบบเรียลไทม์ และตรวจสอบเวลาที่รถถึงแต่ละจุดรับ-ส่ง</p>
    </div>
    <div class="flex items-center space-x-3">
        <span class="flex items-center text-sm text-green-400 bg-green-300/30 px-3 py-1.5 rounded-full border border-green-700">
            <span class="w-2 h-2 bg-green-400 rounded-full mr-2 animate-pulse"></span>
            Real-time Active
        </span>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6 h-[calc(100vh-200px)] min-h-[600px]">
    <!-- Map Container -->
    <div class="lg:col-span-3 bg-cardbg rounded-xl shadow-lg border border-gray-700 overflow-hidden relative flex flex-col">
        <div class="bg-gray-800 p-3 border-b border-gray-700 flex justify-between items-center z-10 w-full">
            <h3 class="text-white font-semibold flex items-center">
                <svg class="w-5 h-5 text-primary mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                แผนที่มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี
            </h3>
            <button onclick="centerMap()" class="text-xs bg-gray-700 hover:bg-gray-600 text-white px-3 py-1.5 rounded transition-colors">
                กลับสู่จุดศูนย์กลาง
            </button>
        </div>
        <div id="map" class="flex-1 w-full bg-gray-900 focus:outline-none z-0"></div>
    </div>

    <!-- Sidebar Bus Info -->
    <div class="bg-cardbg rounded-xl shadow-lg border border-gray-700 flex flex-col overflow-hidden">
        <div class="bg-gray-800 p-4 border-b border-gray-700">
            <h3 class="text-white font-semibold mb-1">สถานะรถประจำทาง</h3>
            <p class="text-xs text-gray-400">อัปเดตข้อมูลแบบเรียลไทม์จากคนขับ</p>
        </div>
        <div class="flex-1 overflow-y-auto p-4 space-y-4" id="bus-list-container">
            <!-- Loading State -->
            <div class="text-center py-10 text-gray-500" id="bus-loading">
                <svg class="animate-spin h-8 w-8 mx-auto text-primary mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <span>กำลังโหลดข้อมูลรถ...</span>
            </div>
            <!-- Dynamic Content will go here -->
        </div>
    </div>
</div>

<!-- Include Leaflet CSS/JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<!-- Load Page Specific JS -->
<script type="module" src="assets/js/live_tracking.js?v=<?php echo time(); ?>"></script>
