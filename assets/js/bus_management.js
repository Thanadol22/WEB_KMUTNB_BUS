import { app, db, rtdb } from "./firebase-init.js";
import { collection, onSnapshot, getDocs, query, where } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-firestore.js";
import { ref, query as dbQuery, limitToLast, onValue } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-database.js";

const container = document.getElementById('bus-management-container');
const busForm = document.getElementById('bus-form');
const busModal = document.getElementById('bus-modal');
const driverSelect = document.getElementById('driver_id');

let busesState = {};
let driversMap = {};

document.addEventListener('DOMContentLoaded', async () => {
    await loadDriversData();
    startBusManagement();
    setupFormListener();
});

async function loadDriversData() {
    try {
        const usersSnap = await getDocs(query(collection(db, "users"), where("role", "==", "driver")));
        driverSelect.innerHTML = '<option value="">-- เลือกคนขับ --</option>';
        usersSnap.forEach(doc => {
            const data = doc.data();
            driversMap[doc.id] = data;
            
            const option = document.createElement('option');
            option.value = doc.id;
            option.textContent = data.name || doc.id;
            driverSelect.appendChild(option);
        });
    } catch (e) {
        console.error("Failed to load driver data:", e);
    }
}

function setupFormListener() {
    busForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const busId = document.getElementById('bus_id_hidden').value;
        const submitBtn = busForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;

        const data = {
            id: busId,
            license_plate: document.getElementById('license_plate').value,
            driver_id: document.getElementById('driver_id').value,
            status: document.getElementById('status').value,
            is_active: document.getElementById('is_active').checked
        };

        submitBtn.disabled = true;
        submitBtn.textContent = 'กำลังบันทึก...';

        try {
            const res = await fetch('services/bus_api.php?action=update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await res.json();
            if (result.status === 'success') {
                closeBusModal();
            } else {
                alert('เกิดข้อผิดพลาด: ' + result.message);
            }
        } catch (err) {
            console.error(err);
            alert('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
}

window.openEditModal = (busId) => {
    const bus = busesState[busId];
    if (!bus) return;

    document.getElementById('bus_id_hidden').value = busId;
    document.getElementById('license_plate').value = bus.plateNumber;
    document.getElementById('driver_id').value = bus.driverId || '';
    document.getElementById('status').value = (bus.status === 'กำลังให้บริการ' || bus.status === 'พร้อมให้บริการ' || bus.status === 'active') ? 'active' : bus.status;
    document.getElementById('is_active').checked = bus.isActive;

    busModal.classList.remove('hidden');
};

window.closeBusModal = () => {
    busModal.classList.add('hidden');
    busForm.reset();
};

function startBusManagement() {
    const busQuery = collection(db, "buses");

    onSnapshot(busQuery, (snapshot) => {
        if (snapshot.empty) {
            container.innerHTML = `
                <div class="col-span-full text-center py-8 text-gray-500">
                    ไม่มีข้อมูลรถในระบบ
                </div>
            `;
            return;
        }

        // Initialize or update buses
        snapshot.forEach((doc) => {
            const data = doc.data();
            const busId = doc.id;
            const rtdbBusId = data.bus_id || busId;
            
            if (!busesState[busId]) {
                busesState[busId] = {
                    id: busId,
                    rtdbBusId: rtdbBusId,
                    rtdbAttached: false,
                    batteryPercent: null,
                    batteryVoltage: null,
                };
            }
            
            let driverName = "ไม่ระบุชื่อคนขับ";
            if (data.driver_id && driversMap[data.driver_id]) {
                driverName = driversMap[data.driver_id].name || driverName;
            } else if (data.driver_name) {
                driverName = data.driver_name;
            }

            busesState[busId] = {
                ...busesState[busId],
                plateNumber: data.license_plate || data.bus_number || data.name || "ไม่ระบุทะเบียน",
                driverId: data.driver_id || "-",
                driverName: driverName,
                status: data.status || "unknown",
                isActive: data.is_active || false,
                capacity: data.capacity || "-",
            };

            // Attach RTDB listener for battery
            if (!busesState[busId].rtdbAttached && rtdbBusId) {
                busesState[busId].rtdbAttached = true;
                // Assuming battery data is sent along with tracking data
                const trackingRef = dbQuery(ref(rtdb, "tracking/" + rtdbBusId), limitToLast(1));
                
                onValue(trackingRef, (rtSnapshot) => {
                    if (rtSnapshot.exists()) {
                        rtSnapshot.forEach((childSnap) => {
                            const rtData = childSnap.val();
                            if (rtData.battery_percent !== undefined) {
                                busesState[busId].batteryPercent = rtData.battery_percent;
                            }
                            if (rtData.battery_voltage !== undefined) {
                                busesState[busId].batteryVoltage = rtData.battery_voltage;
                            }
                        });
                    } else {
                        // Attempt to see if there's a direct monitoring node as alternative
                        const monitorRef = ref(rtdb, "monitoring/" + rtdbBusId);
                        onValue(monitorRef, (monSnap) => {
                            if (monSnap.exists()) {
                                const mData = monSnap.val();
                                if(mData.battery_percent !== undefined) busesState[busId].batteryPercent = mData.battery_percent;
                                if(mData.battery_voltage !== undefined) busesState[busId].batteryVoltage = mData.battery_voltage;
                                renderBuses();
                            }
                        });
                    }
                    renderBuses();
                });
            }
        });
        
        renderBuses();
    }, (error) => {
        console.error("Error fetching buses: ", error);
        container.innerHTML = `
            <div class="col-span-full text-red-400 p-4 bg-red-900/20 rounded-lg border border-red-800 text-sm">
                ข้อผิดพลาดจากฐานข้อมูล: <br>${error.message}
            </div>
        `;
    });
}

function renderBuses() {
    container.innerHTML = '';
    
    if (Object.keys(busesState).length === 0) {
        container.innerHTML = `
            <div class="col-span-full text-center py-8 text-gray-500">
                ไม่มีข้อมูลรถในระบบ
            </div>
        `;
        return;
    }

    for (const [id, bus] of Object.entries(busesState)) {
        let statusBadge = '';
        if (bus.status === 'active' || bus.status === 'กำลังให้บริการ' || bus.status === 'พร้อมให้บริการ' || bus.isActive) {
            statusBadge = '<span class="px-2 py-1 bg-green-500/20 text-green-400 text-xs rounded-full border border-green-700">กำลังวิ่ง</span>';
        } else if (bus.status === 'maintenance' || bus.status === 'ซ่อมบำรุง') {
            statusBadge = '<span class="px-2 py-1 bg-red-500/20 text-red-400 text-xs rounded-full border border-red-700">ซ่อมบำรุง</span>';
        } else if (bus.status === 'inactive' || bus.status === 'หยุดให้บริการ') {
            statusBadge = '<span class="px-2 py-1 bg-gray-500/20 text-gray-400 text-xs rounded-full border border-gray-700">หยุดให้บริการ</span>';
        } else {
            statusBadge = '<span class="px-2 py-1 bg-gray-500/20 text-gray-400 text-xs rounded-full border border-gray-700">ไม่ทราบสถานะ</span>';
        }

        let iconTextColor = 'text-white';
        if (bus.status === 'maintenance' || bus.status === 'ซ่อมบำรุง') {
            iconTextColor = 'text-red-400';
        }

        let batteryColor = 'text-gray-400';
        let batteryIcon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path>';
        
        if (bus.batteryPercent !== null) {
            if (bus.batteryPercent > 60) {
                batteryColor = 'text-green-500';
            } else if (bus.batteryPercent > 20) {
                batteryColor = 'text-yellow-500';
            } else {
                batteryColor = 'text-red-500';
            }

            // Simple battery level icon
            if (bus.batteryPercent > 80) {
                batteryIcon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 10V14C20 15.6569 18.6569 17 17 17H7C5.34315 17 4 15.6569 4 14V10C4 8.34315 5.34315 7 7 7H17C18.6569 7 20 8.34315 20 10ZM12 9V15M16 9V15M8 9V15"></path><path d="M22 11V13C22 13.5523 21.5523 14 21 14H20V10H21C21.5523 10 22 10.4477 22 11Z" fill="currentColor"></path>';
            } else if (bus.batteryPercent > 40) {
                batteryIcon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 10V14C20 15.6569 18.6569 17 17 17H7C5.34315 17 4 15.6569 4 14V10C4 8.34315 5.34315 7 7 7H17C18.6569 7 20 8.34315 20 10ZM12 9V15M8 9V15"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 10V14"></path><path d="M22 11V13C22 13.5523 21.5523 14 21 14H20V10H21C21.5523 10 22 10.4477 22 11Z" fill="currentColor"></path>';
            } else if (bus.batteryPercent > 10) {
                batteryIcon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 10V14C20 15.6569 18.6569 17 17 17H7C5.34315 17 4 15.6569 4 14V10C4 8.34315 5.34315 7 7 7H17C18.6569 7 20 8.34315 20 10ZM8 9V15"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 10V14"></path><path d="M22 11V13C22 13.5523 21.5523 14 21 14H20V10H21C21.5523 10 22 10.4477 22 11Z" fill="currentColor"></path>';
            } else {
                batteryIcon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 10V14C20 15.6569 18.6569 17 17 17H7C5.34315 17 4 15.6569 4 14V10C4 8.34315 5.34315 7 7 7H17C18.6569 7 20 8.34315 20 10Z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 10V14"></path><path d="M22 11V13C22 13.5523 21.5523 14 21 14H20V10H21C21.5523 10 22 10.4477 22 11Z" fill="currentColor"></path>';
            }
        }

        const batteryDisplay = bus.batteryPercent !== null 
            ? `
            <div class="relative w-16 h-16 shrink-0">
                <svg class="w-full h-full -rotate-90" viewBox="0 0 36 36">
                    <circle cx="18" cy="18" r="16" fill="none" class="stroke-gray-100" stroke-width="3"></circle>
                    <circle cx="18" cy="18" r="16" fill="none" class="${batteryColor.replace('text-', 'stroke-')} transition-all duration-700" 
                        stroke-width="3" stroke-dasharray="${bus.batteryPercent}, 100" stroke-linecap="round"></circle>
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center -space-y-0.5">
                    <svg class="w-4 h-4 ${batteryColor}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        ${batteryIcon}
                    </svg>
                    <span class="text-[11px] font-black text-gray-800">${bus.batteryPercent}%</span>
                </div>
            </div>`
            : `
            <div class="w-16 h-16 rounded-full border border-dashed border-gray-200 flex items-center justify-center text-[10px] text-gray-400 text-center font-medium">
                รอข้อมูล
            </div>`;

        const card = `
            <div class="bg-white p-5 rounded-3xl shadow-sm border border-gray-100 hover:shadow-md transition-all duration-300">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 bg-primary rounded-2xl flex items-center justify-center text-white shadow-sm">
                            <svg class="w-6 h-6  ${iconTextColor}" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M4 16c0 .88.39 1.67 1 2.22v1.28c0 .83.67 1.5 1.5 1.5S8 20.33 8 19.5V19h8v.5c0 .82.67 1.5 1.5 1.5.82 0 1.5-.68 1.5-1.5v-1.28c.61-.55 1-1.34 1-2.22V6c0-3.5-3.58-4-8-4s-8 .5-8 4v10zm3.5 1c-.83 0-1.5-.67-1.5-1.5S6.67 14 7.5 14s1.5.67 1.5 1.5S8.33 17 7.5 17zm9 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm1.5-6H6V6h12v5z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-gray-900 leading-tight">${bus.plateNumber}</h3>
                            <div class="flex items-center space-x-2 mt-1">
                                ${statusBadge}
                                <span class="text-[10px] text-gray-400 font-medium tracking-tight">ID: ${bus.rtdbBusId}</span>
                            </div>
                        </div>
                    </div>
                    ${batteryDisplay}
                </div>
                
                <div class="space-y-3 mb-6 bg-gray-50/50 p-4 rounded-2xl">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2 text-gray-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            <span class="text-xs font-semibold">พนักงานขับรถ</span>
                        </div>
                        <span class="text-sm font-bold text-gray-800">${bus.driverName}</span>
                    </div>
                    <div class="flex items-center justify-between border-t border-gray-200/50 pt-3">
                        <div class="flex items-center space-x-2 text-gray-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            <span class="text-xs font-semibold">แรงดันไฟฟ้า</span>
                        </div>
                        <span class="text-sm font-bold text-gray-800 font-mono">${bus.batteryVoltage ? bus.batteryVoltage + ' V' : '-'}</span>
                    </div>
                </div>

                <button onclick="openEditModal('${id}')" class="w-full bg-primary hover:bg-orange-600 text-white py-3.5 rounded-2xl transition-all duration-200 flex items-center justify-center space-x-2 font-bold shadow-sm active:scale-[0.98]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    <span class="text-sm">แก้ไขข้อมูลรถ</span>
                </button>
            </div>
        `;
        container.innerHTML += card;
    }
}
