<?php
// PHP logic for dashboard counts
$users = $firebaseService->getAllDocuments('users');
$totalUsers = count($users);
$roleCounts = [
    'student' => 0,
    'driver' => 0,
    'admin' => 0
];

foreach ($users as $user) {
    $role = $user['role'] ?? 'unknown';
    if (isset($roleCounts[$role])) {
        $roleCounts[$role]++;
    }
}

// Get active buses
$buses = $firebaseService->getAllDocuments('buses');
$activeBusesCount = 0;
foreach ($buses as $bus) {
    if (isset($bus['is_active']) && $bus['is_active'] === true) {
        $activeBusesCount++;
    } elseif (isset($bus['status']) && $bus['status'] === 'active') {
        $activeBusesCount++;
    } else if (!isset($bus['is_active']) && !isset($bus['status'])) {
        // If there's no explicit status, just counting them might be enough, but let's be safe.
        // Usually, buses collection only has active buses, or they have a status flag.
        $activeBusesCount++; 
    }
}
?>

<div class="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-end gap-4">
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-primary">Dashboard Overview</h1>
        <p class="text-gray-400 mt-1 sm:mt-2 text-sm sm:text-base">ภาพรวมสถาที่และการจัดการระบบ KMUTNB BUS</p>
    </div>
    <div class="text-xs text-black font-medium bg-gray-300/80 px-3 py-1 rounded-full border border-gray-500 shadow-sm self-start sm:self-auto">
        อัปเดตล่าสุด: <?php echo date('H:i:s d/m/Y'); ?>
    </div>
</div>

<!-- Stats Cards Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Total Users -->
    <div class="bg-cardbg stagger-1 p-6 rounded-2xl shadow-lg border border-gray-700 hover:border-primary/50 transition-all group overflow-hidden relative">
        <div class="absolute -right-4 -bottom-4 opacity-10 group-hover:opacity-20 transition-opacity">
            <svg class="w-32 h-32" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
        </div>
        <div class="flex items-center mb-4">
            <div class="p-3 rounded-lg bg-blue-500/20 text-blue-400 mr-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </div>
            <h3 class="text-gray-400 font-medium">สมาชิกทั้งหมด</h3>
        </div>
        <div class="text-3xl font-bold"><?php echo number_format($totalUsers); ?></div>
        <div class="text-sm text-blue-400 mt-2">ลงทะเบียนใช้งานแล้ว</div>
    </div>

    <!-- Students -->
    <div class="bg-cardbg stagger-2 p-6 rounded-2xl shadow-lg border border-gray-700 hover:border-primary/50 transition-all group overflow-hidden relative">
        <div class="absolute -right-4 -bottom-4 opacity-10 group-hover:opacity-20 transition-opacity">
            <svg class="w-32 h-32" fill="currentColor" viewBox="0 0 24 24"><path d="M5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82zM12 3L1 9l11 6 9-4.91V17h2V9L12 3z"/></svg>
        </div>
        <div class="flex items-center mb-4">
            <div class="p-3 rounded-lg bg-indigo-500/20 text-indigo-400 mr-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path></svg>
            </div>
            <h3 class="text-gray-400 font-medium">นักศึกษา</h3>
        </div>
        <div class="text-3xl font-bold"><?php echo number_format($roleCounts['student']); ?></div>
        <div class="text-sm text-indigo-400 mt-2">Active Students</div>
    </div>

    <!-- Drivers -->
    <div class="bg-cardbg stagger-3 p-6 rounded-2xl shadow-lg border border-gray-700 hover:border-primary/50 transition-all group overflow-hidden relative">
        <div class="absolute -right-4 -bottom-4 opacity-10 group-hover:opacity-20 transition-opacity">
            <svg class="w-32 h-32" fill="currentColor" viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/></svg>
        </div>
        <div class="flex items-center mb-4">
            <div class="p-3 rounded-lg bg-orange-500/20 text-orange-400 mr-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"></path></svg>
            </div>
            <h3 class="text-gray-400 font-medium">คนขับรถ</h3>
        </div>
        <div class="text-3xl font-bold"><?php echo number_format($roleCounts['driver']); ?></div>
        <div class="text-sm text-orange-400 mt-2">Active Drivers</div>
    </div>

    <!-- Active Buses -->
    <div class="bg-cardbg stagger-4 p-6 rounded-2xl shadow-lg border border-gray-700 hover:border-primary/50 transition-all group overflow-hidden relative">
        <div class="absolute -right-2 -bottom-2 opacity-10 group-hover:opacity-20 transition-opacity">
            <svg class="w-32 h-32" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
        </div>
        <div class="flex items-center mb-4">
            <div class="p-3 rounded-lg bg-green-500/20 text-green-400 mr-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            </div>
            <h3 class="text-gray-400 font-medium">กำลังให้บริการ</h3>
        </div>
        <div class="text-3xl font-bold" id="active-buses-count"><?php echo number_format($activeBusesCount); ?></div>
        <div class="text-sm text-green-400 mt-2">Live Tracking Active</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Chart Container -->
    <div class="lg:col-span-2 bg-cardbg stagger-5 p-6 rounded-2xl shadow-lg border border-gray-700">
        <h3 class="text-lg font-bold mb-6 text-white flex items-center">
            <svg class="w-5 h-5 mr-2 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path></svg>
            สถิติผู้ใช้งานรายบทบาท
        </h3>
        <div class="h-64 flex items-end space-x-4 px-4 pb-10 border-b border-gray-700">
            <!-- Simple Pure CSS Graph for visualization -->
            <?php 
            $max = max($roleCounts);
            if ($max == 0) $max = 1;
            $items = [
                ['label' => 'นักศึกษา', 'val' => $roleCounts['student'], 'color' => 'bg-indigo-500'],
                ['label' => 'คนขับรถ', 'val' => $roleCounts['driver'], 'color' => 'bg-orange-500'],
                ['label' => 'แอดมิน', 'val' => $roleCounts['admin'], 'color' => 'bg-primary']
            ];
            foreach($items as $item): 
                $h = ($item['val'] / $max) * 100;
            ?>
            <div class="flex-1 flex flex-col justify-end items-center group relative h-full mt-auto">
                <div class="absolute top-0 -mt-10 bg-gray-800 text-xs text-white px-2 py-1 rounded hidden group-hover:block border border-gray-600 z-10">
                    <?php echo $item['val']; ?> คน
                </div>
                <div class="<?php echo $item['color']; ?> w-full rounded-t-lg transition-all duration-700 hover:brightness-110" style="height: <?php echo $h; ?>%; min-height: 4px;"></div>
                <div class="absolute -bottom-8 text-xs text-gray-400 font-medium truncate w-full text-center mt-2">
                    <?php echo $item['label']; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Health Check Area -->
    <div class="bg-cardbg stagger-5 p-6 rounded-2xl shadow-lg border border-gray-700 h-full" style="animation-delay: 600ms;">
        <h3 class="text-lg font-bold mb-6 text-white flex items-center">
            <svg class="w-5 h-5 mr-2 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            System Status
        </h3>
        
        <div class="space-y-4">
            <div class="flex justify-between items-center bg-darkbg/50 p-4 rounded-xl border border-gray-800">
                <span class="text-gray-400 text-sm">REST API Client</span>
                <?php if ($firebase['status'] === 'connected'): ?>
                    <span class="flex items-center text-xs text-green-400 bg-green-400/10 px-2 py-1 rounded-full border border-green-500/20">
                        <span class="w-2 h-2 bg-green-400 rounded-full mr-2 animate-pulse"></span> Connected
                    </span>
                <?php else: ?>
                    <span class="text-red-400 text-xs">Error</span>
                <?php endif; ?>
            </div>

            <div class="flex justify-between items-center bg-darkbg/50 p-4 rounded-xl border border-gray-800">
                <span class="text-gray-400 text-sm">JavaScript SDK</span>
                <span id="js-status-badge" class="flex items-center text-xs text-yellow-500 bg-yellow-500/10 px-2 py-1 rounded-full border border-yellow-500/20">
                    <span class="w-2 h-2 bg-yellow-400 rounded-full mr-2"></span> Checking...
                </span>
            </div>

            <div class="flex justify-between items-center bg-darkbg/50 p-4 rounded-xl border border-gray-800">
                <span class="text-gray-400 text-sm">Last Sync</span>
                <span class="text-xs text-gray-500"><?php echo date('H:i'); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Firebase JS Integration -->
<script type="module">
    import { app, db, rtdb } from './assets/js/firebase-init.js';
    import { collection, onSnapshot, query as fsQuery } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-firestore.js";
    import { ref, query as dbQuery, limitToLast, onValue } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-database.js";

    if (app) {
        const badge = document.getElementById('js-status-badge');
        if(badge) {
            badge.innerHTML = `<span class="w-2 h-2 bg-green-400 rounded-full mr-2 animate-pulse"></span> Running`;
            badge.className = "flex items-center text-xs text-green-400 bg-green-400/10 px-2 py-1 rounded-full border border-green-500/20";
        }

        // --- Real-time Active Buses Count connecting to RTDB + Firestore ---
        const activeBusesCountEl = document.getElementById('active-buses-count');
        const busQuery = fsQuery(collection(db, "buses"));
        let busesStateCount = {};

        onSnapshot(busQuery, (snapshot) => {
            snapshot.forEach((doc) => {
                const data = doc.data();
                const busId = doc.id;
                const rtdbBusId = data.bus_id || busId;
                const status = data.status || 'unknown';
                
                if (!busesStateCount[busId]) {
                    busesStateCount[busId] = { isMuted: false, rtdbAttached: false, hasRtdbData: false, fsStatus: status };
                } else {
                    busesStateCount[busId].fsStatus = status;
                }

                // Attach RTDB listener since a bus is "active" if it has live locations
                if (!busesStateCount[busId].rtdbAttached && rtdbBusId) {
                    busesStateCount[busId].rtdbAttached = true;
                    // Observe any change in tracking data for this bus
                    const trackingRef = dbQuery(ref(rtdb, "tracking/" + rtdbBusId), limitToLast(1));
                    
                    onValue(trackingRef, (rtSnapshot) => {
                        const exists = rtSnapshot.exists();
                        // If tracking data arrived, it has rtdbData
                        busesStateCount[busId].hasRtdbData = exists;
                        updateActiveCount();
                    });
                }
            });
            updateActiveCount();
        });

        function updateActiveCount() {
            if (!activeBusesCountEl) return;
            let total = 0;
            for (let b of Object.values(busesStateCount)) {
                const isActiveStatus = (b.fsStatus === 'กำลังให้บริการ' || b.fsStatus === 'active' || b.fsStatus === 'running');
                // The bus is "active" if it has tracking locations actively sending, OR its status is explicitly marked active
                if (b.hasRtdbData || isActiveStatus) {
                    total++;
                }
            }
            activeBusesCountEl.innerText = total.toString();
        }
    }
</script>
