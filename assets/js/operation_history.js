import { app, db } from "./firebase-init.js";
import {
    collection, onSnapshot, query as fsQuery, orderBy, where, getDocs
} from "https://www.gstatic.com/firebasejs/9.23.0/firebase-firestore.js";

// ─── State ────────────────────────────────────────────────────────────────────
let allLogs = [];          // raw Firestore docs for current date
let scheduleStops = {};    // { roundId: [ { order, name, scheduleTime }, ... ] }
let driversMap = {};       // map of driver_id -> user
let busesMap = {};         // map of bus_id -> bus details
let unsubscribeHistory = null;

// ─── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
    await loadUsersAndBuses();
    await loadDetailedSchedules();
    bindFilterEvents();
    startHistoryListener();
    updateDateDisplay();
});

// ─── Load Support Data ────────────────────────────────────────────────────────
async function loadUsersAndBuses() {
    try {
        const usersSnap = await getDocs(fsQuery(collection(db, "users"), where("role", "==", "driver")));
        usersSnap.forEach(doc => {
            driversMap[doc.id] = doc.data();
        });
        const busesSnap = await getDocs(collection(db, "buses"));
        busesSnap.forEach(doc => {
            busesMap[doc.id] = doc.data();
        });
        console.log("[History] Base data loaded.", Object.keys(driversMap).length, "drivers", Object.keys(busesMap).length, "buses");
    } catch (e) {
        console.error("[History] Failed to load base data:", e);
    }
}

// ─── Load stop definitions from Firestore ─────────────────────────────────────
async function loadDetailedSchedules() {
    try {
        const snap = await getDocs(collection(db, "detailed_schedules"));
        snap.forEach(doc => {
            const d = doc.data();
            const roundId = doc.id;
            if (d.stops && Array.isArray(d.stops)) {
                const sorted = [...d.stops].sort((a, b) => (a.order || 0) - (b.order || 0));
                scheduleStops[roundId] = sorted.map(s => ({
                    order: s.order,
                    name: s.name || '-',
                    scheduleTime: s.time || ''
                }));
            } else {
                scheduleStops[roundId] = [];
            }
        });
        console.log("[History] Schedules loaded:", Object.keys(scheduleStops).length, "rounds");
    } catch (e) {
        console.error("[History] Failed to load schedules:", e);
    }
}

// ─── Real-time Firestore Listener ─────────────────────────────────────────────
function startHistoryListener() {
    const dateVal = getSelectedDate();

    // Unsubscribe previous listener if any
    if (unsubscribeHistory) {
        unsubscribeHistory();
    }

    setLoadingState(true);

    // Query only by timestamp order — filter by date client-side
    // (avoids needing a composite index on date + timestamp)
    const q = fsQuery(
        collection(db, "operation_history"),
        orderBy("timestamp", "asc")
    );

    unsubscribeHistory = onSnapshot(q,
        (snapshot) => {
            allLogs = [];
            snapshot.forEach(doc => {
                const d = doc.data();
                // Client-side date filter
                
                const actualDate = d.actualTime ? d.actualTime.split(' ')[0] : '';
                if (actualDate === dateVal) {

                    allLogs.push({ _id: doc.id, ...d });
                }
            });
            setLoadingState(false);
            renderHistoryTable();
            updateSummaryStats();
        },
        (err) => {
            console.error("[History] Snapshot error:", err);
            setLoadingState(false);
            showError("ไม่สามารถโหลดข้อมูลได้: " + err.message);
        }
    );
}

// ─── Render Table ─────────────────────────────────────────────────────────────
function renderHistoryTable() {
    const container = document.getElementById('history-table-container');
    if (!container) return;

    const filterStop = getFilterStop();
    const filterStatus = getFilterStatus();

    // Group logs by roundId
    // pivotMap: { roundId: { busId, roundNum, stops: { stopOrder: logEntry } } }
    const pivotMap = {};
    allLogs.forEach(log => {
        const roundId = log.round_id || log.roundId || 'unknown';
        
        let driverName = log.driverName || log.driver_name || '-';
        if (log.driver_id && driversMap[log.driver_id]) {
            driverName = driversMap[log.driver_id].name;
        } else if (log.driverId && driversMap[log.driverId]) {
            driverName = driversMap[log.driverId].name;
        }

        let plateNumber = log.plateNumber || log.license_plate || log.plate_number || '-';
        let busDisplayId = log.bus_id || log.busId || '-';
        
        const busRefId = log.bus_id || log.busId;
        if (busRefId && busesMap[busRefId]) {
            plateNumber = busesMap[busRefId].license_plate || busesMap[busRefId].plate_number || plateNumber;
            // Use bus number if available, else keep the ID limit
            if (busesMap[busRefId].bus_number || busesMap[busRefId].number) {
                 busDisplayId = "รถคันที่ " + (busesMap[busRefId].bus_number || busesMap[busRefId].number);
            }
        }

        if (!pivotMap[roundId]) {
            pivotMap[roundId] = {
                busId: busDisplayId,
                plateNumber: plateNumber,
                driverName: driverName,
                roundNum: parseInt(roundId.replace(/\D/g, '')) || 0,
                roundId: roundId,
                stopData: {}  // { stopName: log }
            };
        }
        
        let stopName = log.currentStop || log.stop_name || log.stopName;
        
        // Always try to map the stop name via scheduleTime if available.
        // This ensures mismatching names (e.g. "หอพักชาย" vs "หอพักนักศึกษา") are mapped properly.
        if (log.scheduleTime) {
            const stopsForRound = scheduleStops[roundId] || [];
            const matchedStop = stopsForRound.find(s => s.scheduleTime === log.scheduleTime);
            if (matchedStop) {
                stopName = matchedStop.name;
            }
        }

        // Even without schedule time, we can try to match by name fuzzily or let exact match happen
        if (stopName && stopName !== "กำลังเดินทาง (ไม่อยู่ที่ป้าย)") {
            // Find if there's any similar named stop in schedule to normalize name
            const stopsForRound = scheduleStops[roundId] || [];
            if (!stopsForRound.find(s => s.name === stopName) && !log.scheduleTime) {
                 const fuzzyMatch = stopsForRound.find(s => stopName.includes(s.name) || s.name.includes(stopName));
                 if (fuzzyMatch) stopName = fuzzyMatch.name;
            }
            pivotMap[roundId].stopData[stopName] = log;
        }

        // Update bus info with latest entry per round
        if (busDisplayId && busDisplayId !== '-') pivotMap[roundId].busId = busDisplayId;
        if (plateNumber && plateNumber !== '-') pivotMap[roundId].plateNumber = plateNumber;
        if (driverName && driverName !== '-') pivotMap[roundId].driverName = driverName;
    });

    // Get all unique round IDs from both scheduleStops and pivotMap
    const allRoundIds = new Set([
        ...Object.keys(scheduleStops),
        ...Object.keys(pivotMap)
    ]);

    // Sort by round number
    const sortedRoundIds = [...allRoundIds].sort((a, b) => {
        const ra = scheduleStops[a]?.[0]?.order ?? 99;
        const rb = scheduleStops[b]?.[0]?.order ?? 99;
        // sort by round number encoded in ID like "round_01"
        const numA = parseInt(a.replace(/\D/g, '')) || 0;
        const numB = parseInt(b.replace(/\D/g, '')) || 0;
        return numA - numB;
    });

    // Filter rows: if filterStop selected, only show rounds that have that stop
    let filteredRoundIds = sortedRoundIds;
    if (filterStop) {
        filteredRoundIds = sortedRoundIds.filter(rid => {
            const stops = scheduleStops[rid] || [];
            return stops.some(s => s.name === filterStop);
        });
    }
    if (filterStatus) {
        filteredRoundIds = filteredRoundIds.filter(rid => {
            const data = pivotMap[rid];
            if (!data) return false;
            return Object.values(data.stopData).some(s => s.status === filterStatus);
        });
    }

    if (filteredRoundIds.length === 0) {
        container.innerHTML = `
            <div class="text-center py-16 text-gray-500">
                <svg class="w-14 h-14 mx-auto mb-4 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <p class="text-lg font-medium">ไม่พบข้อมูลประวัติ</p>
                <p class="text-sm mt-1">สำหรับวันที่เลือกและตัวกรองนี้</p>
            </div>
        `;
        return;
    }

    // Build a unified stop list (union of all stops across all displayed rounds)
    // We'll use schedule-defined stops for column headers
    // Find all unique stop names across displayed rounds (from scheduleStops)
    // For each round we use that round's own schedule stops as columns.

    // Approach: build per-round rows (each row shows all stops of that round)
    // Table is dynamic per-round, not a global pivot.

    let html = '';
    filteredRoundIds.forEach(roundId => {
        const stops = scheduleStops[roundId] || [];
        const roundData = pivotMap[roundId] || null;
        const roundNum = parseInt(roundId.replace(/\D/g, '')) || '?';

        // Determine overall round status
        let hasLate = false, hasEarly = false;
        let arrivedCount = 0;
        if (roundData) {
            Object.values(roundData.stopData).forEach(s => {
                if (s.status === 'LATE') hasLate = true;
                if (s.status === 'EARLY') hasEarly = true;
                arrivedCount++;
            });
        }

        const roundStatusBadge = roundData
            ? (hasLate
                ? `<span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-yellow-900/40 text-yellow-400 border border-yellow-700/50">มีล่าช้า</span>`
                : `<span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-green-900/40 text-green-400 border border-green-700/50">ตรงเวลา</span>`)
            : `<span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-gray-800 text-gray-400 border border-gray-600">รอข้อมูล</span>`;

        const busDisplay = roundData ? `${roundData.busId} (${roundData.plateNumber})` : '-';
        const driverDisplay = roundData ? roundData.driverName : '-';
        const progressText = stops.length > 0 ? `${arrivedCount}/${stops.length} ป้าย` : '-';

        html += `
        <div class="mb-6 bg-cardbg rounded-2xl border border-gray-700 overflow-hidden shadow-sm">
            <!-- Round Header -->
            <div class="px-5 py-3 bg-cardbg border-b border-gray-700 flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-primary/20 flex items-center justify-center">
                        <span class="text-primary font-bold text-sm">${roundNum}</span>
                    </div>
                    <div>
                        <span class="text-white font-semibold text-sm">รอบที่ ${roundNum}</span>
                        <span class="text-gray-400 text-xs ml-2">${roundId}</span>
                    </div>
                </div>
                ${roundStatusBadge}
                <div class="ml-auto flex items-center gap-4 text-xs text-gray-400">
                    <span class="flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M4 16c0 .88.39 1.67 1 2.22v1.28c0 .83.67 1.5 1.5 1.5S8 20.33 8 19.5V19h8v.5c0 .82.67 1.5 1.5 1.5.82 0 1.5-.68 1.5-1.5v-1.28c.61-.55 1-1.34 1-2.22V6c0-3.5-3.58-4-8-4s-8 .5-8 4v10zm3.5 1c-.83 0-1.5-.67-1.5-1.5S6.67 14 7.5 14s1.5.67 1.5 1.5S8.33 17 7.5 17zm9 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm1.5-6H6V6h12v5z"/></svg>
                        ${busDisplay}
                    </span>
                    <span class="flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        ${driverDisplay}
                    </span>
                    <span class="flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        ${progressText}
                    </span>
                </div>
            </div>

            <!-- Stops Timeline Row -->
            <div class="overflow-x-auto">
                <div class="flex min-w-max p-4 gap-2 items-stretch">
                    ${stops.length === 0
                ? `<div class="text-gray-400 text-sm px-4 py-6">ไม่มีข้อมูลป้ายในตารางเวลา</div>`
                : stops.map((stop, idx) => {
                    const stopLog = roundData?.stopData?.[stop.name] || null;
                    return buildStopCell(stop, stopLog, idx, stops.length);
                }).join('')
            }
                </div>
            </div>
        </div>
        `;
    });

    container.innerHTML = html;
}

// ─── Build single stop cell ───────────────────────────────────────────────────
function buildStopCell(stop, log, idx, total) {
    const isLast = idx === total - 1;

    let statusIcon = '', statusBg = '', statusText = '', arrivedTime = '', diffText = '';

    if (!log) {
        // Not yet arrived
        statusIcon = `<svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`;
        statusBg = 'bg-gray-800 border-gray-700';
        statusText = '<span class="text-xs text-gray-400">รอข้อมูล</span>';
        arrivedTime = stop.scheduleTime ? `<span class="text-[10px] text-gray-500">ตาราง: ${stop.scheduleTime}</span>` : '';
    } else {
        const status = log.status || 'ON_TIME';
        if (status === 'ON_TIME') {
            statusIcon = `<svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`;
            statusBg = 'bg-green-900/20 border-green-800/30';
            statusText = '<span class="text-xs font-semibold text-green-400">ตรงเวลา</span>';
        } else if (status === 'LATE') {
            statusIcon = `<svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>`;
            statusBg = 'bg-yellow-900/20 border-yellow-800/30';
            statusText = '<span class="text-xs font-semibold text-yellow-400">ล่าช้า</span>';
        } else {
            statusIcon = `<svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>`;
            statusBg = 'bg-blue-900/20 border-blue-800/30';
            statusText = '<span class="text-xs font-semibold text-blue-400">ก่อนเวลา</span>';
        }

        // Time display
        arrivedTime = `
            <div class="text-center mt-1">
                <div class="text-xs text-gray-400">ตาราง: <span class="text-gray-300">${stop.scheduleTime || '-'}</span></div>
                <div class="text-xs text-gray-400">จริง: <span class="font-semibold text-gray-100">${(log.actualTime ? log.actualTime.split(' ')[1].substring(0, 5) : '') || '-'}</span></div>
            </div>
        `;

        // Diff calculation
        if (stop.scheduleTime && (log.actualTime ? log.actualTime.split(' ')[1].substring(0, 5) : '')) {
            const [sh, sm] = stop.scheduleTime.split(':').map(Number);
            const [ah, am] = (log.actualTime ? log.actualTime.split(' ')[1].substring(0, 5) : '').split(':').map(Number);
            const diff = (ah * 60 + am) - (sh * 60 + sm);
            if (diff > 0) {
                diffText = `<span class="text-[10px] text-yellow-400">+${diff} นาที</span>`;
            } else if (diff < 0) {
                diffText = `<span class="text-[10px] text-blue-400">${diff} นาที</span>`;
            }
        }
    }

    // Connector line between stops
    const connector = !isLast
        ? `<div class="flex items-center self-center mx-1 shrink-0">
               <div class="h-0.5 w-6 ${log ? 'bg-primary' : 'bg-gray-600'}"></div>
               <svg class="w-3 h-3 ${log ? 'text-primary' : 'text-gray-600'} -ml-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
           </div>`
        : '';

    return `
        <div class="flex items-stretch">
            <div class="w-36 rounded-xl border ${statusBg} p-3 flex flex-col items-center text-center shrink-0">
                <div class="flex items-center justify-center mb-2">
                    ${statusIcon}
                </div>
                <div class="w-5 h-5 rounded-full bg-primary/20 flex items-center justify-center mb-1">
                    <span class="text-primary text-[9px] font-bold">${stop.order || idx + 1}</span>
                </div>
                <p class="text-xs font-semibold text-black-200 leading-snug mb-1">${stop.name}</p>
                ${statusText}
                ${arrivedTime}
                ${diffText}
            </div>
            ${connector}
        </div>
    `;
}

// ─── Summary Stats ─────────────────────────────────────────────────────────────
function updateSummaryStats() {
    const totalEl = document.getElementById('stat-total-arrivals');
    const onTimeEl = document.getElementById('stat-on-time');
    const lateEl = document.getElementById('stat-late');
    const earlyEl = document.getElementById('stat-early');
    const roundsEl = document.getElementById('stat-rounds');

    let total = 0, onTime = 0, late = 0, early = 0;
    const rounds = new Set();

    allLogs.forEach(log => {
        total++;
        rounds.add(log.round_id || log.roundId); // Fixed: proper round key
        if (log.status === 'ON_TIME') onTime++;
        else if (log.status === 'LATE') late++;
        else if (log.status === 'EARLY') early++;
    });

    if (totalEl) totalEl.textContent = total;
    if (onTimeEl) onTimeEl.textContent = onTime;
    if (lateEl) lateEl.textContent = late;
    if (earlyEl) earlyEl.textContent = early;
    if (roundsEl) roundsEl.textContent = rounds.size;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function getSelectedDate() {
    const el = document.getElementById('historyDate');
    if (el && el.value) return el.value;
    return new Date().toISOString().split('T')[0];
}

function getFilterStop() {
    const el = document.getElementById('stopFilter');
    return el ? el.value : '';
}

function getFilterStatus() {
    const el = document.getElementById('statusFilter');
    return el ? el.value : '';
}

function setLoadingState(loading) {
    const el = document.getElementById('history-loading');
    if (!el) return;
    el.classList.toggle('hidden', !loading);
}

function showError(msg) {
    const container = document.getElementById('history-table-container');
    if (container) {
        container.innerHTML = `
            <div class="text-red-400 p-4 bg-red-900/20 rounded-lg border border-red-800 text-sm">
                <strong>เกิดข้อผิดพลาด:</strong> ${msg}
            </div>
        `;
    }
}

function updateDateDisplay() {
    const el = document.getElementById('history-date-display');
    if (!el) return;
    const dateVal = getSelectedDate();
    const d = new Date(dateVal + 'T00:00:00');
    const opts = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    el.textContent = d.toLocaleDateString('th-TH', opts);
}

function bindFilterEvents() {
    const dateEl = document.getElementById('historyDate');
    const stopEl = document.getElementById('stopFilter');
    const statusEl = document.getElementById('statusFilter');

    if (dateEl) {
        dateEl.addEventListener('change', () => {
            updateDateDisplay();
            startHistoryListener(); // Re-listen with new date
        });
    }
    if (stopEl) stopEl.addEventListener('change', () => renderHistoryTable());
    if (statusEl) statusEl.addEventListener('change', () => renderHistoryTable());
}

// ─── Populate stop filter from schedules ──────────────────────────────────────
// Make stop list available globally for PHP to (or do it here in JS)
window.populateStopFilter = function (stopNames) {
    const el = document.getElementById('stopFilter');
    if (!el) return;
    stopNames.forEach(name => {
        const opt = document.createElement('option');
        opt.value = name;
        opt.textContent = name;
        el.appendChild(opt);
    });
};

// Auto-populate stop filter from scheduleStops after load
setTimeout(() => {
    const allStopNames = new Set();
    Object.values(scheduleStops).forEach(stops => {
        stops.forEach(s => { if (s.name) allStopNames.add(s.name); });
    });
    const el = document.getElementById('stopFilter');
    if (el && allStopNames.size > 0) {
        // Clear existing options except first
        while (el.options.length > 1) el.remove(1);
        [...allStopNames].sort().forEach(name => {
            const opt = document.createElement('option');
            opt.value = name;
            opt.textContent = name;
            el.appendChild(opt);
        });
    }
}, 3000); // Wait for schedules to load
