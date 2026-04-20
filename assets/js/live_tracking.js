import { app, db, rtdb } from "./firebase-init.js";
import { collection, onSnapshot, query as fsQuery, getDocs, where } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-firestore.js";
import { ref, query as dbQuery, limitToLast, onValue, onChildAdded, onChildRemoved } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-database.js";

// Define stops and their sequence based on the schedule image
const BUS_STOPS_SEQUENCE = [
    { id: 'dorm', name: 'หอพักฯ' },
    { id: 'front', name: 'หน้า ม.' },
    { id: 'admin', name: 'บริหารฯ' },
    { id: 'industry', name: 'อุตฯ' },
    { id: 'building', name: 'อาคาร' },
    { id: 'tech', name: 'เทคโนฯ' },
    { id: 'eng', name: 'วิศวะฯ' }
];

// ─── ETA Constants ────────────────────────────────────────────────────────────
const FALLBACK_SPEED_KMH = 20;     // ความเร็วเฉลี่ยภายในมหาวิทยาลัย (km/h)
const ROAD_FACTOR = 1.3;           // ตัวคูณชดเชยเส้นทางจริง vs เส้นตรง
const AT_STOP_THRESHOLD_M = 80;    // ระยะ (เมตร) ที่ถือว่าถึงป้ายแล้ว
const MOVING_THRESHOLD_KMH = 3;    // ความเร็วขั้นต่ำที่ถือว่ารถกำลังเคลื่อนที่

// Reference coordinates for distance calculation from Firestore 'locations'
let busStopCoordinates = [];

// KMUTNB Center Approx coordinates
const KMUTNB_CENTER = [14.163687, 101.3628841];
let map;
let markers = {};
let driversMap = {};
let busesState = {};
let predefinedSchedules = [];

// Check if document is already loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', async () => {
        initMap();
        await loadDriversData();
        await loadBusStops();
        await loadSchedules();
        startLiveTracking();
    });
} else {
    // Top-level await is not supported, so use IIFE or just call an async setup
    (async () => {
        initMap();
        await loadDriversData();
        await loadBusStops();
        await loadSchedules();
        startLiveTracking();
    })();
}

async function loadSchedules() {
    try {
        const querySnapshot = await getDocs(collection(db, "schedules"));
        querySnapshot.forEach((doc) => {
            predefinedSchedules.push(doc.data());
        });
        
        // Sort by round, then by start_time
        predefinedSchedules.sort((a, b) => {
            if (a.round === b.round) {
                return a.start_time.localeCompare(b.start_time);
            }
            return a.round - b.round;
        });
        console.log("Schedules loaded:", predefinedSchedules.length);
    } catch (e) {
        console.error("Error loading schedules:", e);
    }
}

async function loadDriversData() {
    try {
        console.log("Fetching driver data...");
        const res = await fetch('services/user_api.php?action=list');
        const json = await res.json();
        if (json.status === 'success' && json.data) {
            json.data.forEach(user => {
                if (user.role === 'driver') {
                    // Update object mapping for drivers
                    driversMap[user.id] = {
                        name: user.name || 'ไม่มีชื่อ',
                        phone: user.phone || '-'
                    };
                }
            });
            console.log("Drivers loaded:", driversMap);
        }
    } catch (e) {
        console.error("Failed to load drivers:", e);
    }
}

async function loadBusStops() {
    try {
        const querySnapshot = await getDocs(collection(db, "locations"));
        const stopIcon = L.divIcon({
            className: 'custom-div-icon',
            html: `<div class="bg-primary border-2 border-white rounded-full w-4 h-4 shadow-lg flex items-center justify-center"></div>`,
            iconSize: [20, 20],
            iconAnchor: [10, 10]
        });

        querySnapshot.forEach((doc) => {
            const data = doc.data();
            if (data.lat && data.lng) {
                const lat = parseFloat(data.lat);
                const lng = parseFloat(data.lng);
                const name = data.name || 'จุดจอดรถ';
                
                L.marker([lat, lng], {icon: stopIcon})
                  .addTo(map)
                  .bindPopup(`<b class="text-gray-800">${name}</b><br>จุดรับ-ส่ง`);
                  
                busStopCoordinates.push({
                    id: doc.id,
                    name: name,
                    lat: lat,
                    lng: lng
                });
            }
        });
        console.log("Bus stops loaded from Real DB");
    } catch (e) {
        console.error("Error loading bus stops:", e);
    }
}

function initMap() {
    console.log("Initializing map...");
    // Initialize map
    map = L.map('map', {
        center: KMUTNB_CENTER,
        zoom: 17,
        zoomControl: true,
        attributionControl: false
    });

    // Use OpenStreetMap tile layer (Free, no API key required)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
    }).addTo(map);

    // Custom Map Attribution (move to bottom left)
    L.control.attribution({position: 'bottomleft'}).addAttribution('&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors').addTo(map);

    // Provide center function globally
    window.centerMap = () => {
        map.setView(KMUTNB_CENTER, 17);
    };
}

function startLiveTracking() {
    const busQuery = fsQuery(collection(db, "buses")); // Assume buses collection contains live location data

    console.log("Starting Firebase real-time sync for 'buses'...");

    // Remove loading indicator
    document.getElementById('bus-loading').classList.add('hidden');

    onSnapshot(busQuery, (snapshot) => {
        if (snapshot.empty) {
            document.getElementById('bus-list-container').innerHTML = `
                <div class="text-center py-8 text-gray-500">
                    <svg class="w-12 h-12 mx-auto text-gray-700 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    ไม่มีรถให้บริการในขณะนี้
                </div>
            `;
            return;
        }

        snapshot.forEach((doc) => {
            const data = doc.data();
            const busId = doc.id;
            const rtdbBusId = data.bus_id || busId;
            
            if (!busesState[busId]) {
                busesState[busId] = {
                    position: { lat: null, lng: null },
                    rtdbAttached: false
                };
            }
            
            const lat = data.lat || null;
            const lng = data.lng || null;
            const status = data.status || 'unknown'; // active, maintenance, pause
            
            // Map the driver id to name using the preloaded driversMap
            let driverName = 'ไม่ระบุชื่อคนขับ';
            let driverPhone = '-';
            if (data.driver_id && driversMap[data.driver_id]) {
                driverName = driversMap[data.driver_id].name;
                driverPhone = driversMap[data.driver_id].phone;
            } else if (data.driver_name) {
                driverName = data.driver_name;
            }

            const plateNumber = data.license_plate || data.plate_number || "ไม่ระบุทะเบียน";
            let nextStop = data.next_stop || "กำลังคำนวณ...";
            let eta = data.eta || "กำลังคำนวณ...";

            busesState[busId].metadata = {
                rtdbBusId: rtdbBusId,
                driverName: driverName,
                driverPhone: driverPhone,
                plateNumber: plateNumber,
                status: status,
                nextStop: nextStop,
                eta: eta,
                speed: 0
            };

            // Attach RTDB listener for actual live location
            if (!busesState[busId].rtdbAttached && rtdbBusId) {
                busesState[busId].rtdbAttached = true;
                const trackingRef = dbQuery(ref(rtdb, "tracking/" + rtdbBusId), limitToLast(1));
                
                onValue(trackingRef, (rtSnapshot) => {
                    if (!rtSnapshot.exists()) {
                        busesState[busId].hasRtdbData = false;
                        
                        // Remove marker from map
                        if (markers[busId]) {
                            map.removeLayer(markers[busId]);
                            delete markers[busId];
                        }
                        
                        updateLiveMapAndList();
                        return;
                    }
                    
                    rtSnapshot.forEach((childSnap) => {
                        const rtData = childSnap.val();
                        if (rtData.lat && rtData.lon) {
                            busesState[busId].position = {
                                lat: parseFloat(rtData.lat),
                                lng: parseFloat(rtData.lon)
                            };
                            
                            // Get speed if available to calculate ETA
                            if (rtData.speed) {
                                busesState[busId].metadata.speed = parseFloat(rtData.speed);
                            }
                            
                            busesState[busId].hasRtdbData = true;
                            
                            // Calculate ETA and next stop based on current pos
                            calculateEta(busId);
                            
                            updateLiveMapAndList();
                        }
                    });
                });
            }
        });
        
        updateLiveMapAndList();

    }, (error) => {
        console.error("Error fetching live tracking: ", error);
        document.getElementById('bus-list-container').innerHTML = `
            <div class="text-red-400 p-4 bg-red-900/20 rounded-lg border border-red-800 text-sm">
                ข้อผิดพลาดจากฐานข้อมูล: <br>${error.message}
            </div>
        `;
    });
}



function updateLiveMapAndList() {
    const container = document.getElementById('bus-list-container');
    if (!container) return;
    
    // Check if empty
    if (Object.keys(busesState).length === 0) return;
    
    let renderedCount = 0;
    container.innerHTML = ''; // Re-render the list purely from state

    for (const [id, bus] of Object.entries(busesState)) {
        if (!bus.hasRtdbData) continue; // Only show buses actively sending RTDB locations

        const meta = bus.metadata;
        const pos = bus.position;

        // Ensure marker limits if lat/lng missing
        if (pos.lat === null || pos.lng === null) continue;

        updateBusMarker(id, pos.lat, pos.lng, meta.plateNumber, meta.status, meta.nextStop, meta.eta);

        container.innerHTML += createBusCardHtml(id, meta.driverName, meta.driverPhone, meta.plateNumber, meta.status, meta.nextStop, meta.eta);
        renderedCount++;
    }

    if (renderedCount === 0) {
        container.innerHTML = `
            <div class="text-center py-8 text-gray-500">
                <svg class="w-12 h-12 mx-auto text-gray-700 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                ไม่มีรถให้บริการในขณะนี้
            </div>
        `;
    }
}

function updateBusMarker(id, lat, lng, plate, status, nextStop, eta) {


    if (!lat || !lng) return;

    const isActive = status === 'active' || status === 'พร้อมให้บริการ';
    
    // Bus Icon
    const busIconColor = isActive ? 'bg-green-500' : 'bg-gray-500';
    const iconBg = 'bg-white shadow-sm';
    const iconTextColor = isActive ? 'text-green-500' : 'text-gray-400';
    const borderColor = isActive ? 'border-green-500' : 'border-gray-300';
    
    const busIconHtml = `
        <div class="relative flex flex-col items-center">
            <div class="w-12 h-12 rounded-full ${iconBg} border-2 ${borderColor} shadow-md flex items-center justify-center relative z-10 bg-white">
                <svg class="w-6 h-6 border-b ${iconTextColor}" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M4 16c0 .88.39 1.67 1 2.22v1.28c0 .83.67 1.5 1.5 1.5S8 20.33 8 19.5V19h8v.5c0 .82.67 1.5 1.5 1.5.82 0 1.5-.68 1.5-1.5v-1.28c.61-.55 1-1.34 1-2.22V6c0-3.5-3.58-4-8-4s-8 .5-8 4v10zm3.5 1c-.83 0-1.5-.67-1.5-1.5S6.67 14 7.5 14s1.5.67 1.5 1.5S8.33 17 7.5 17zm9 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm1.5-6H6V6h12v5z" />
                </svg>
            </div>
            <div class="w-3 h-3 ${busIconColor} transform rotate-45 -translate-y-2 border-r-2 border-b-2 border-white shadow-sm relative z-0"></div>
        </div>
    `;

    const icon = L.divIcon({
        className: 'bg-transparent',
        html: busIconHtml,
        iconSize: [48, 60],
        iconAnchor: [24, 60],
        popupAnchor: [0, -60]
    });

    const popupHtml = `
        <div class="text-gray-800" style="min-width:180px">
            <b class="text-base text-primary">${plate}</b><br>
            <span class="text-xs text-gray-500">สถานะ: ${status}</span><br>
            <hr class="my-1 border-gray-200">
            <div style="margin-top:4px">
                <span class="text-xs text-gray-500">สถานีถัดไป:</span><br>
                <b style="font-size:14px">${nextStop}</b>
            </div>
            <div style="margin-top:4px">
                <span class="text-xs text-gray-500">เวลาถึงโดยประมาณ:</span><br>
                <b style="color:#f97316;font-size:13px">${eta}</b>
            </div>
        </div>
    `;

    if (markers[id]) {
        // Move existing marker
        markers[id].setLatLng([lat, lng]);
        markers[id].setIcon(icon);
        markers[id].setPopupContent(popupHtml);
    } else {
        // Create new marker
        markers[id] = L.marker([lat, lng], {icon: icon})
            .addTo(map)
            .bindPopup(popupHtml);
    }
}

// Calculate distance in meters between two lat/lng points using Haversine formula
function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371e3; // metres
    const φ1 = lat1 * Math.PI/180;
    const φ2 = lat2 * Math.PI/180;
    const Δφ = (lat2-lat1) * Math.PI/180;
    const Δλ = (lon2-lon1) * Math.PI/180;

    const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
            Math.cos(φ1) * Math.cos(φ2) *
            Math.sin(Δλ/2) * Math.sin(Δλ/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));

    return R * c;
}

// ─── Spatial-based ETA Calculation ─────────────────────────────────────────────
// สูตร: ETA = เวลาปัจจุบัน + (ระยะทางตามเส้นทาง ÷ ความเร็วเฉลี่ย)
function calculateEta(busId) {
    const bus = busesState[busId];
    if (!bus || !bus.hasRtdbData) {
        return;
    }

    // ── ขั้นตอนที่ 1: สร้าง ordered stop list จาก BUS_STOPS_SEQUENCE + พิกัดจาก DB ──
    const orderedStops = buildOrderedStops();
    if (orderedStops.length === 0) {
        bus.metadata.nextStop = 'รอข้อมูลป้ายรถ';
        bus.metadata.eta = '-';
        return;
    }

    const currentPos = bus.position;
    const speedKmh = bus.metadata.speed || 0;

    // ── ขั้นตอนที่ 2: หาป้ายที่ใกล้รถที่สุด (nearest stop) ด้วย Haversine ──
    let nearestIdx = 0;
    let nearestDist = Infinity;
    for (let i = 0; i < orderedStops.length; i++) {
        const d = calculateDistance(currentPos.lat, currentPos.lng, orderedStops[i].lat, orderedStops[i].lng);
        if (d < nearestDist) {
            nearestDist = d;
            nearestIdx = i;
        }
    }

    // ── ขั้นตอนที่ 3: กำหนดป้ายถัดไป ──
    let nextStopIdx;
    if (nearestDist <= AT_STOP_THRESHOLD_M) {
        // รถอยู่ที่ป้ายแล้ว → ป้ายถัดไปคือตัวถัดไปในเส้นทาง
        nextStopIdx = nearestIdx + 1;
    } else {
        // รถอยู่ระหว่างทาง → ป้ายถัดไปคือป้ายที่ใกล้ที่สุดข้างหน้า
        // ตรวจสอบว่ารถผ่านป้ายใกล้ที่สุดไปแล้วหรือยัง
        if (nearestIdx + 1 < orderedStops.length) {
            const distToNext = calculateDistance(currentPos.lat, currentPos.lng, orderedStops[nearestIdx + 1].lat, orderedStops[nearestIdx + 1].lng);
            const distNearestToNext = calculateDistance(orderedStops[nearestIdx].lat, orderedStops[nearestIdx].lng, orderedStops[nearestIdx + 1].lat, orderedStops[nearestIdx + 1].lng);
            // ถ้ารถอยู่ใกล้ป้ายถัดไปมากกว่าระยะระหว่างป้ายปัจจุบันกับถัดไป → ยังไม่ผ่าน
            if (distToNext < distNearestToNext) {
                nextStopIdx = nearestIdx + 1;
            } else {
                nextStopIdx = nearestIdx;
            }
        } else {
            nextStopIdx = nearestIdx;
        }
    }

    // ตรวจสอบว่าเลยป้ายสุดท้ายหรือยัง
    if (nextStopIdx >= orderedStops.length) {
        bus.metadata.nextStop = 'ครบรอบแล้ว';
        bus.metadata.eta = '-';
        return;
    }

    const nextStop = orderedStops[nextStopIdx];
    bus.metadata.nextStop = nextStop.name;

    // ── ขั้นตอนที่ 4: คำนวณระยะทางตามเส้นทาง (route distance) ──
    // ระยะจาก bus → ป้าย nearest → ... → ป้าย nextStop
    let routeDistanceM = 0;

    if (nextStopIdx === nearestIdx) {
        // รถมุ่งไปป้ายเดียวกับที่ใกล้ที่สุด
        routeDistanceM = nearestDist;
    } else {
        // ระยะจากรถถึงป้าย nearest
        routeDistanceM = nearestDist;
        // รวมระยะ segment ระหว่างป้าย
        for (let i = nearestIdx; i < nextStopIdx; i++) {
            routeDistanceM += calculateDistance(
                orderedStops[i].lat, orderedStops[i].lng,
                orderedStops[i + 1].lat, orderedStops[i + 1].lng
            );
        }
    }

    // คูณ road factor เพราะเส้นทางจริงไม่ใช่เส้นตรง
    routeDistanceM *= ROAD_FACTOR;

    // ── ขั้นตอนที่ 5: คำนวณ ETA ด้วยสูตร ──
    // ETA = เวลาปัจจุบัน + (ระยะทาง ÷ ความเร็วเฉลี่ย)
    if (routeDistanceM <= AT_STOP_THRESHOLD_M) {
        bus.metadata.eta = 'กำลังถึงป้าย';
        return;
    }

    // ใช้ speed จาก GPS ถ้ากำลังเคลื่อนที่, ถ้าไม่ใช้ fallback
    const avgSpeedKmh = (speedKmh > MOVING_THRESHOLD_KMH) ? speedKmh : FALLBACK_SPEED_KMH;
    const avgSpeedMs = avgSpeedKmh * (1000 / 3600); // แปลงเป็น m/s
    const etaSeconds = routeDistanceM / avgSpeedMs;

    // คำนวณเวลาถึงจริง (absolute)
    const now = new Date();
    const arrivalTime = new Date(now.getTime() + etaSeconds * 1000);
    const arrivalHH = arrivalTime.getHours().toString().padStart(2, '0');
    const arrivalMM = arrivalTime.getMinutes().toString().padStart(2, '0');

    // ── ขั้นตอนที่ 6: แสดงผล ETA ──
    if (etaSeconds < 60) {
        bus.metadata.eta = `< 1 นาที (ถึง ~${arrivalHH}:${arrivalMM} น.)`;
    } else {
        const etaMinutes = Math.ceil(etaSeconds / 60);
        bus.metadata.eta = `~${etaMinutes} นาที (ถึง ~${arrivalHH}:${arrivalMM} น.)`;
    }

    // แสดง source ของความเร็วใน console สำหรับ debug
    if (speedKmh <= MOVING_THRESHOLD_KMH) {
        console.log(`[ETA] ${busId}: ใช้ fallback speed ${FALLBACK_SPEED_KMH} km/h (รถหยุด/ช้ามาก)`);
    }
}

// ─── Build Ordered Stops with Coordinates ─────────────────────────────────────
// จับคู่ BUS_STOPS_SEQUENCE กับพิกัด GPS จาก busStopCoordinates (Firestore locations)
function buildOrderedStops() {
    if (busStopCoordinates.length === 0) return [];

    const result = [];
    for (const seqStop of BUS_STOPS_SEQUENCE) {
        // หาพิกัดจาก Firestore locations โดย fuzzy match ชื่อ
        const matched = busStopCoordinates.find(coord =>
            coord.name === seqStop.name ||
            coord.name.includes(seqStop.name) ||
            seqStop.name.includes(coord.name) ||
            coord.id === seqStop.id
        );
        if (matched) {
            result.push({
                id: seqStop.id,
                name: seqStop.name,
                lat: matched.lat,
                lng: matched.lng
            });
        }
    }

    // ถ้า match ไม่ได้เลย ใช้ busStopCoordinates ตามลำดับเดิม
    if (result.length === 0) {
        return busStopCoordinates.map(c => ({ id: c.id, name: c.name, lat: c.lat, lng: c.lng }));
    }

    return result;
}

function createBusCardHtml(id, driverName, driverPhone, plate, status, nextStop, eta) {
    const isActive = status === 'active' || status === 'พร้อมให้บริการ' || status === 'กำลังให้บริการ';
    
    const borderColor = isActive ? 'border-green-500' : 'border-gray-600';
    const badgeColor = isActive ? 'bg-green-100 text-green-700' : 'bg-gray-700 text-gray-300';
    const iconBg = isActive ? 'bg-green-50' : 'bg-gray-50';
    const iconColor = isActive ? 'text-green-600' : 'text-gray-400';
    const printStatus = isActive ? 'กำลังให้บริการ' : status;
    const printEta = eta || '-';
    const displayId = busesState[id]?.metadata?.rtdbBusId || id;
    
    return `
        <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border-2 ${borderColor} hover:shadow-lg transition-all cursor-pointer mb-4 relative overflow-hidden" onclick="focusBusOnMap('${id}')">
            <div class="flex items-center space-x-4">
                <!-- Circular Icon Left -->
                <div class="w-14 h-14 rounded-full ${iconBg} border border-gray-100 dark:border-gray-700 shadow-sm flex items-center justify-center shrink-0">
                    <svg class="w-7 h-7 ${iconColor}" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M4 16c0 .88.39 1.67 1 2.22v1.28c0 .83.67 1.5 1.5 1.5S8 20.33 8 19.5V19h8v.5c0 .82.67 1.5 1.5 1.5.82 0 1.5-.68 1.5-1.5v-1.28c.61-.55 1-1.34 1-2.22V6c0-3.5-3.58-4-8-4s-8 .5-8 4v10zm3.5 1c-.83 0-1.5-.67-1.5-1.5S6.67 14 7.5 14s1.5.67 1.5 1.5S8.33 17 7.5 17zm9 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm1.5-6H6V6h12v5z" />
                    </svg>
                </div>
                
                <!-- Content Right -->
                <div class="flex-1 min-w-0">
                    <!-- Top Row -->
                    <div class="flex items-start justify-between">
                        <div class="flex flex-col">
                            <h4 class="text-gray-900 dark:text-white font-bold text-lg leading-tight truncate">${displayId}</h4>
                            <div class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">${plate}</div>
                        </div>
                        <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold tracking-wide ${badgeColor} whitespace-nowrap ml-2">
                            ${printStatus}
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Middle Row with ETA & Next Stop -->
            <div class="mt-4 bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3 flex items-center justify-between text-sm">
                <div class="flex flex-col">
                    <span class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">เวลาถึงโดยประมาณ</span>
                    <span class="text-orange-500 font-bold text-sm leading-tight">${printEta}</span>
                </div>
                <div class="flex flex-col items-end text-right">
                    <span class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">มุ่งหน้าสถานี</span>
                    <span class="text-gray-800 dark:text-gray-200 font-bold leading-none truncate max-w-[140px]">${nextStop}</span>
                </div>
            </div>
            
            <!-- Bottom Driver Info -->
            <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
                <div class="flex items-center space-x-3 min-w-0">
                    <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center text-gray-500 dark:text-gray-300 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </div>
                    <div class="truncate">
                        <p class="text-[10px] text-gray-500 dark:text-gray-400 uppercase tracking-wider">พนักงานขับรถ</p>
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200 truncate">${driverName}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">${driverPhone}</p>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Attach focus globally so inline HTML onclick works
window.focusBusOnMap = (id) => {
    if (markers[id]) {
        const pos = markers[id].getLatLng();
        map.setView(pos, 18);
        markers[id].openPopup();
    }
};
