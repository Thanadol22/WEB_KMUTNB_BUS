import { db } from "./firebase-init.js";
import { collection, getDocs, doc, setDoc, query, where } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-firestore.js";

window.openDriverModal = openDriverModal;
window.closeDriverModal = closeDriverModal;
window.editDriver = (uid) => openDriverModal(uid);
window.deleteDriver = deleteDriver;

let allDrivers = [];
let allLicenses = {}; // driver_id -> license data
let currentSearchQuery = '';

console.log("Loading driver_management.js script...");

const searchInput = document.getElementById('searchDriverInput');
if (searchInput) {
    searchInput.addEventListener('input', (e) => {
        currentSearchQuery = e.target.value.toLowerCase();
        applyFilters();
    });
}

// Form submission
const form = document.getElementById('driverForm');
if (form) {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const btn = document.getElementById('saveDriverBtn');
        const originalText = btn.innerHTML;
        btn.innerHTML = `<svg class="animate-spin h-5 w-5 mr-2 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> กำลังบันทึก...`;
        btn.disabled = true;
        document.getElementById('driverFormError').classList.add('hidden');

        const driverId = document.getElementById('driverId').value;
        const action = driverId ? 'update' : 'create';
        
        const userData = {
            role: 'driver',
            name: document.getElementById('driverName').value,
            username: document.getElementById('driverUsername').value,
            phone: document.getElementById('driverPhone').value,
            password: document.getElementById('driverPassword').value,
            status: document.getElementById('driverStatus').value,
            gender: document.getElementById('driverGender').value,
            date_of_birth: document.getElementById('driverDob').value,
            profile_image_url: document.getElementById('driverProfileImage').value || document.getElementById('driverProfileImage').dataset.base64 || ''
        };

        if (driverId) userData.uid = driverId;

        try {
            // 1. Save User Data via API
            const res = await fetch(`services/user_api.php?action=${action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(userData)
            });
            const result = await res.json();
            
            if (result.status !== 'success') {
                throw new Error(result.message || 'Error saving user data');
            }

            const finalDriverId = driverId || result.uid;

            // 2. Save License Data directly to Firestore
            const licenseData = {
                driver_id: finalDriverId,
                license_number: document.getElementById('licenseNumber').value,
                license_type: document.getElementById('licenseType').value,
                license_expiry_date: document.getElementById('licenseExpiry').value, // YYYY-MM-DD string
                license_image_url: document.getElementById('licenseImageBase64').value || '',
                updated_at: new Date().toISOString()
            };

            await setDoc(doc(db, "driver_licenses", finalDriverId), licenseData, { merge: true });

            closeDriverModal();
            loadDrivers(); // Refresh
        } catch (error) {
            showError(error.message || "เกิดข้อผิดพลาดในการบันทึกข้อมูล");
            console.error(error);
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });
}

// License Image Upload Handling
const licenseUpload = document.getElementById('licenseImageUpload');
if (licenseUpload) {
    licenseUpload.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                const base64 = event.target.result;
                document.getElementById('licenseImageBase64').value = base64;
                showLicensePreview(base64);
            };
            reader.readAsDataURL(file);
        }
    });
}

document.getElementById('removeLicenseImage').addEventListener('click', function() {
    document.getElementById('licenseImageBase64').value = '';
    document.getElementById('licenseImageUpload').value = '';
    hideLicensePreview();
});

// Profile Image Upload Handling
const profileUpload = document.getElementById('profileImageUpload');
if (profileUpload) {
    profileUpload.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                const base64 = event.target.result;
                document.getElementById('driverProfileImage').value = base64;
                showProfilePreview(base64);
            };
            reader.readAsDataURL(file);
        }
    });
}

document.getElementById('removeProfileImage').addEventListener('click', function() {
    document.getElementById('driverProfileImage').value = '';
    document.getElementById('profileImageUpload').value = '';
    hideProfilePreview();
});

function showProfilePreview(src) {
    document.getElementById('profileUploadPrompt').classList.add('hidden');
    const preview = document.getElementById('profileImagePreview');
    preview.src = src;
    preview.classList.remove('hidden');
    document.getElementById('removeProfileImage').classList.remove('hidden');
}

function hideProfilePreview() {
    document.getElementById('profileUploadPrompt').classList.remove('hidden');
    const preview = document.getElementById('profileImagePreview');
    preview.src = '';
    preview.classList.add('hidden');
    document.getElementById('removeProfileImage').classList.add('hidden');
}

function showLicensePreview(src) {
    document.getElementById('licenseUploadPrompt').classList.add('hidden');
    const preview = document.getElementById('licenseImagePreview');
    preview.src = src;
    preview.classList.remove('hidden');
    document.getElementById('removeLicenseImage').classList.remove('hidden');
}

function hideLicensePreview() {
    document.getElementById('licenseUploadPrompt').classList.remove('hidden');
    const preview = document.getElementById('licenseImagePreview');
    preview.src = '';
    preview.classList.add('hidden');
    document.getElementById('removeLicenseImage').classList.add('hidden');
}

// Start loading immediately
loadDrivers();

async function loadDrivers() {
    const tbody = document.getElementById('driver-table-body');
    
    // Show Loading Skeleton
    tbody.innerHTML = `
        <tr>
            <td colspan="5" class="px-6 py-8">
                <div class="flex flex-col items-center justify-center space-y-4">
                    <div class="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
                    <span class="text-sm text-gray-500 font-medium animate-pulse">กำลังโหลดข้อมูลพนักงานขับรถ...</span>
                </div>
            </td>
        </tr>
    `;

    try {
        // Fetch Drivers
        const q = query(collection(db, "users"), where("role", "==", "driver"));
        const userSnap = await getDocs(q);
        allDrivers = [];
        userSnap.forEach(doc => {
            allDrivers.push({ id: doc.id, ...doc.data() });
        });

        // Fetch Licenses
        const licenseSnap = await getDocs(collection(db, "driver_licenses"));
        allLicenses = {};
        licenseSnap.forEach(doc => {
            const data = doc.data();
            if (data.driver_id) {
                allLicenses[data.driver_id] = data;
            } else {
                 allLicenses[doc.id] = data; // fallback if doc id is driver_id
            }
        });

        applyFilters();
    } catch (e) {
        console.error("Error loading drivers:", e);
        tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-4 text-center text-red-500">เกิดข้อผิดพลาด: ${e.message}</td></tr>`;
    }
}

function applyFilters() {
    let filtered = allDrivers;
    
    if (currentSearchQuery.trim() !== '') {
        filtered = filtered.filter(u => {
            const name = (u.name || '').toLowerCase();
            const phone = (u.phone || '').toLowerCase();
            return name.includes(currentSearchQuery) || phone.includes(currentSearchQuery);
        });
    }
    
    renderTable(filtered);
}

function renderTable(drivers) {
    const tbody = document.getElementById('driver-table-body');
    if (drivers.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">ไม่พบข้อมูลพนักงานขับรถ</td></tr>`;
        return;
    }

    tbody.innerHTML = drivers.map(driver => {
        const license = allLicenses[driver.id] || {};
        
        let statusText = driver.status === 'inactive' ? 
             '<span class="px-2 py-1 bg-red-500/20 text-red-400 text-xs rounded-full border border-red-700 whitespace-nowrap inline-block">ปิดใช้งาน</span>' : 
             '<span class="px-2 py-1 bg-green-500/20 text-green-400 text-xs rounded-full border border-green-700 whitespace-nowrap inline-block">พร้อมบริการ</span>';
        
        const profileImg = driver.profile_image_url ? 
            `<img src="${driver.profile_image_url}" class="w-10 h-10 rounded-full object-cover border border-gray-600">` : 
            `<div class="w-10 h-10 rounded-full bg-gray-700 flex items-center justify-center text-gray-400"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg></div>`;
        
        const licenseThumb = license.license_image_url ?
            `<img src="${license.license_image_url}" class="w-16 h-10 object-cover rounded border border-gray-600 cursor-pointer hover:border-primary transition-colors" onclick="window.open('${license.license_image_url}')" title="ดูรูปใบขับขี่">` :
            `<div class="w-16 h-10 bg-gray-800 rounded border border-gray-700 flex items-center justify-center text-[10px] text-gray-500">ไม่มีรูป</div>`;

        let licenseInfo = `<div class="text-xs text-gray-400">ยังไม่มีข้อมูลใบขับขี่</div>`;
        if (license.license_number) {
            let expiryDisplay = license.license_expiry_date || '-';
            
            // Handle Firebase Timestamp if returned as an object
            if (license.license_expiry_date && typeof license.license_expiry_date === 'object' && (license.license_expiry_date.seconds || license.license_expiry_date._seconds)) {
                const seconds = license.license_expiry_date.seconds || license.license_expiry_date._seconds;
                const date = new Date(seconds * 1000);
                expiryDisplay = date.toLocaleDateString('en-GB', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            } else if (license.license_expiry_date && typeof license.license_expiry_date === 'string') {
                // Try to format ISO string to English format for consistency (A.D. year)
                const date = new Date(license.license_expiry_date);
                if (!isNaN(date)) {
                    expiryDisplay = date.toLocaleDateString('en-GB', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                }
            } else if (license.license_expiry_date) {
                // Fallback for other potential formats
                expiryDisplay = String(license.license_expiry_date);
            }

            licenseInfo = `
                <div class="text-sm text-white">${license.license_number}</div>
                <div class="text-xs text-gray-400">${license.license_type || '-'}</div>
                <div class="text-xs text-gray-500">หมดอายุ: ${expiryDisplay}</div>
            `;
        }

        return `
            <tr class="hover:bg-gray-300/50 transition-colors">
                <td class="px-6 py-4">
                    <div class="flex items-center space-x-3">
                        ${profileImg}
                        <div>
                            <div class="font-medium text-white">${driver.name || '-'}</div>
                            <div class="text-xs text-gray-400">${driver.gender === 'male' ? 'ชาย' : (driver.gender === 'female' ? 'หญิง' : '-')}</div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <div class="text-sm text-gray-300">${driver.phone || '-'}</div>
                    <div class="text-xs text-gray-500">${driver.username || '-'}</div>
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center space-x-3">
                        ${licenseThumb}
                        <div>${licenseInfo}</div>
                    </div>
                </td>
                <td class="px-6 py-4">${statusText}</td>
                <td class="px-6 py-4 text-center">
                    <button onclick="editDriver('${driver.id}')" class="text-primary hover:text-white mr-3 transition-colors p-2 rounded hover:bg-gray-700" title="แก้ไข">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    </button>
                    <button onclick="deleteDriver('${driver.id}')" class="text-red-500 hover:text-red-400 transition-colors p-2 rounded hover:bg-gray-700" title="ลบ">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function openDriverModal(uid = null) {
    const modal = document.getElementById('driverModal');
    const form = document.getElementById('driverForm');
    const modalTitle = document.getElementById('driverModalTitle');
    
    document.getElementById('driverFormError').classList.add('hidden');
    form.reset();
    hideLicensePreview();
    hideProfilePreview();
    document.getElementById('licenseImageBase64').value = '';
    document.getElementById('driverProfileImage').value = '';

    if (uid) {
        modalTitle.innerText = "แก้ไขข้อมูลพนักงานขับรถ";
        document.getElementById('driverId').value = uid;
        
        const driver = allDrivers.find(u => u.id === uid);
        if (driver) {
            document.getElementById('driverName').value = driver.name || '';
            document.getElementById('driverUsername').value = driver.username || '';
            document.getElementById('driverPhone').value = driver.phone || '';
            document.getElementById('driverPassword').value = driver.password || '';
            document.getElementById('driverStatus').value = driver.status || 'active';
            document.getElementById('driverGender').value = driver.gender || 'male';
            document.getElementById('driverDob').value = driver.date_of_birth || '';
            if (driver.profile_image_url) {
                document.getElementById('driverProfileImage').value = driver.profile_image_url;
                showProfilePreview(driver.profile_image_url);
            }
        }

        const license = allLicenses[uid];
        if (license) {
            document.getElementById('licenseNumber').value = license.license_number || '';
            document.getElementById('licenseType').value = license.license_type || '';
            document.getElementById('licenseExpiry').value = license.license_expiry_date || '';
            if (license.license_image_url) {
                document.getElementById('licenseImageBase64').value = license.license_image_url;
                showLicensePreview(license.license_image_url);
            }
        }
    } else {
        modalTitle.innerText = "เพิ่มพนักงานขับรถใหม่";
        document.getElementById('driverId').value = "";
    }

    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        modal.querySelector('.transform').classList.remove('scale-95');
    }, 10);
}

function closeDriverModal() {
    const modal = document.getElementById('driverModal');
    modal.classList.add('opacity-0');
    modal.querySelector('.transform').classList.add('scale-95');
    
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

async function deleteDriver(uid) {
    if (confirm('คุณแน่ใจหรือไม่ว่าต้องการลบพนักงานขับรถนี้?')) {
        try {
            const res = await fetch(`services/user_api.php?action=delete`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ uid })
            });
            const result = await res.json();
            if (result.status === 'success') {
                loadDrivers();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (e) {
            console.error(e);
            alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
        }
    }
}

function showError(msg) {
    const errObj = document.getElementById('driverFormError');
    errObj.innerText = msg;
    errObj.classList.remove('hidden');
}
