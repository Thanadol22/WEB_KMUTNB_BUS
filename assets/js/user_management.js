import { db } from "./firebase-init.js";
import { collection, getDocs, doc, setDoc } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-firestore.js";

// Globals accessible in HTML
window.openUserModal = openUserModal;
window.closeUserModal = closeUserModal;
window.editUser = editUser;
window.deleteUser = deleteUser;

window.openDriverModal = openDriverModal;
window.closeDriverModal = closeDriverModal;
window.editDriver = (uid) => openDriverModal(uid);
window.deleteDriver = deleteDriver;
window.togglePasswordVisibility = togglePasswordVisibility;

let allUsers = [];
let allLicenses = {}; // driver_id -> license data
let currentRoleFilter = 'all';
let currentSearchQuery = '';

console.log("Loading consolidated user_management.js script...");

// Filter & Search Implementation
const searchInput = document.getElementById('searchInput');
const filterContainer = document.getElementById('filter-container');

if (searchInput) {
    searchInput.addEventListener('input', (e) => {
        currentSearchQuery = e.target.value.toLowerCase();
        applyFilters();
    });
}

const userRoleSelect = document.getElementById('userRole');
if (userRoleSelect) {
    userRoleSelect.addEventListener('change', (e) => {
        if (e.target.value === 'driver') {
            closeUserModal();
            openDriverModal(null);
        }
    });
}

if (filterContainer) {
    filterContainer.addEventListener('click', (e) => {
        const btn = e.target.closest('.filter-btn');
        if (!btn) return;
        
        const roleActiveClasses = {
            all: ['bg-gradient-to-r', 'from-indigo-600', 'to-indigo-500', 'text-white', 'border-indigo-500', 'shadow-[0_0_15px_rgba(79,70,229,0.4)]', 'scale-105'],
            user: ['bg-gradient-to-r', 'from-orange-500', 'to-amber-500', 'text-white', 'border-orange-500', 'shadow-[0_0_15px_rgba(249,115,22,0.4)]', 'scale-105'],
            admin: ['bg-gradient-to-r', 'from-blue-600', 'to-cyan-500', 'text-white', 'border-blue-500', 'shadow-[0_0_15px_rgba(37,99,235,0.4)]', 'scale-105'],
            driver: ['bg-gradient-to-r', 'from-emerald-600', 'to-green-500', 'text-white', 'border-emerald-500', 'shadow-[0_0_15px_rgba(16,185,129,0.4)]', 'scale-105']
        };

        const inactiveClasses = ['bg-transparent', 'text-gray-500', 'border-transparent', 'hover:bg-gray-100'];
        const commonActiveClasses = ['active-filter'];

        // Update active class
        const allBtns = filterContainer.querySelectorAll('.filter-btn');
        allBtns.forEach(b => {
            const role = b.dataset.role;
            const activeClasses = roleActiveClasses[role] || [];
            
            b.classList.remove(...activeClasses, ...commonActiveClasses, 'scale-105');
            b.classList.add(...inactiveClasses);
        });
        
        // Apply active to selected button
        const activeRole = btn.dataset.role;
        const activeClasses = roleActiveClasses[activeRole] || [];
        btn.classList.remove(...inactiveClasses);
        btn.classList.add(...activeClasses, ...commonActiveClasses);
        
        currentRoleFilter = btn.dataset.role;
        applyFilters();
    });
}

function applyFilters() {
    let filteredUsers = allUsers;
    
    // Dynamic placeholder and primary button text
    const sInput = document.getElementById('searchInput');
    const addBtn = document.querySelector('button[onclick="openUserModal()"]');
    
    if (currentRoleFilter === 'driver') {
        if (sInput) sInput.placeholder = "ค้นหาชื่อ, เบอร์โทร...";
    } else {
        if (sInput) sInput.placeholder = "ค้นหาชื่อ, รหัสผู้ใช้...";
    }

    if (addBtn) {
        addBtn.classList.remove('hidden');
        addBtn.innerHTML = `<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg> เพิ่มผู้ใช้ใหม่`;
    }

    // Filter by role
    if (currentRoleFilter !== 'all') {
        if (currentRoleFilter === 'user') {
            filteredUsers = filteredUsers.filter(u => u.role === 'user' || u.role === 'student' || u.role === 'teacher');
        } else {
            filteredUsers = filteredUsers.filter(u => u.role === currentRoleFilter);
        }
    }
    
    // Filter by search query
    if (currentSearchQuery.trim() !== '') {
        filteredUsers = filteredUsers.filter(u => {
            const name = (u.name || '').toLowerCase();
            const username = (u.username || '').toLowerCase();
            const phone = (u.phone || '').toLowerCase();
            return name.includes(currentSearchQuery) || 
                   username.includes(currentSearchQuery) || 
                   phone.includes(currentSearchQuery);
        });
    }
    
    renderTable(filteredUsers);
}

// User Form submission
const userForm = document.getElementById('userForm');
if (userForm) {
    userForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const btn = document.getElementById('saveUserBtn');
        const originalText = btn.innerHTML;
        btn.innerHTML = `<svg class="animate-spin h-5 w-5 mr-2 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> กำลังบันทึก...`;
        btn.disabled = true;

        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());
        const uid = document.getElementById('userId').value;
        const action = uid ? 'update' : 'create';
        
        if (uid) data.uid = uid;

        try {
            const res = await fetch(`services/user_api.php?action=${action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await res.json();
            
            if (result.status === 'success') {
                closeUserModal();
                loadUsers(); // Refresh
            } else {
                showUserError(result.message);
            }
        } catch (error) {
            showUserError("เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์");
            console.error(error);
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });
}

// Driver Form submission
const driverForm = document.getElementById('driverForm');
if (driverForm) {
    driverForm.addEventListener('submit', async (e) => {
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
            profile_image_url: document.getElementById('driverProfileImage').value || ''
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
            loadUsers(); // Refresh
        } catch (error) {
            showDriverError(error.message || "เกิดข้อผิดพลาดในการบันทึกข้อมูล");
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

const removeLicenseBtn = document.getElementById('removeLicenseImage');
if (removeLicenseBtn) {
    removeLicenseBtn.addEventListener('click', function() {
        document.getElementById('licenseImageBase64').value = '';
        document.getElementById('licenseImageUpload').value = '';
        hideLicensePreview();
    });
}

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

const removeProfileBtn = document.getElementById('removeProfileImage');
if (removeProfileBtn) {
    removeProfileBtn.addEventListener('click', function() {
        document.getElementById('driverProfileImage').value = '';
        document.getElementById('profileImageUpload').value = '';
        hideProfilePreview();
    });
}

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
loadUsers();

async function loadUsers() {
    const tbody = document.getElementById('user-table-body');
    
    // Show Loading Skeleton
    tbody.innerHTML = `
        <tr>
            <td colspan="6" class="px-6 py-8">
                <div class="flex flex-col items-center justify-center space-y-4">
                    <div class="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
                    <span class="text-sm text-gray-500 font-medium animate-pulse">กำลังโหลดข้อมูลผู้ใช้งาน...</span>
                </div>
            </td>
        </tr>
    `;

    try {
        // 1. Fetch all users from API
        const res = await fetch('services/user_api.php?action=list');
        
        let textResult = '';
        try {
           textResult = await res.text();
        } catch(err) {
           throw new Error("Could not read response: " + err.message);
        }

        let json = null;
        try {
            json = JSON.parse(textResult);
        } catch(parseErr) {
            console.error("Invalid JSON:", textResult);
            throw new Error("Server returned invalid data format. Check console.");
        }
        
        if (json.status !== 'success') throw new Error(json.message);
        
        allUsers = json.data;

        // 2. Fetch driver licenses from Firestore
        const licenseSnap = await getDocs(collection(db, "driver_licenses"));
        allLicenses = {};
        licenseSnap.forEach(doc => {
            const data = doc.data();
            if (data.driver_id) {
                allLicenses[data.driver_id] = data;
            } else {
                 allLicenses[doc.id] = data;
            }
        });
        
        applyFilters(); // Apply current filters before rendering
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-4 text-center text-red-500">เกิดข้อผิดพลาด: ${e.message}</td></tr>`;
    }
}

function renderTable(users) {
    const tbody = document.getElementById('user-table-body');
    const thead = document.getElementById('user-table-header');
    
    if (users.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">ไม่พบข้อมูลผู้ใช้งาน</td></tr>`;
        return;
    }

    if (currentRoleFilter === 'driver') {
        // Change table header to Driver specific columns
        thead.innerHTML = `
            <tr>
                <th scope="col" class="px-6 py-4">รูปโปรไฟล์/ชื่อ</th>
                <th scope="col" class="px-6 py-4">ข้อมูลติดต่อ</th>
                <th scope="col" class="px-6 py-4">ข้อมูลใบขับขี่</th>
                <th scope="col" class="px-6 py-4">สถานะ</th>
                <th scope="col" class="px-6 py-4 text-center">จัดการ</th>
            </tr>
        `;
        
        tbody.innerHTML = users.map(driver => {
            const license = allLicenses[driver.id] || {};
            
            let statusText = driver.status === 'inactive' ? 
                 '<span class="text-red-400">ปิดใช้งาน</span>' : 
                 '<span class="text-green-400">ใช้งานแล้ว</span>';
            
            const profileImg = driver.profile_image_url ? 
                `<img src="${driver.profile_image_url}" class="w-10 h-10 rounded-full object-cover border border-gray-600">` : 
                `<div class="w-10 h-10 rounded-full bg-gray-700 flex items-center justify-center text-gray-400"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg></div>`;
            
            const licenseThumb = license.license_image_url ?
                `<img src="${license.license_image_url}" class="w-16 h-10 object-cover rounded border border-gray-600 cursor-pointer hover:border-primary transition-colors" onclick="window.open('${license.license_image_url}')" title="ดูรูปใบขับขี่">` :
                `<div class="w-16 h-10 bg-gray-800 rounded border border-gray-700 flex items-center justify-center text-[10px] text-gray-500">ไม่มีรูป</div>`;

            let licenseInfo = `<div class="text-xs text-gray-400">ยังไม่มีข้อมูลใบขับขี่</div>`;
            if (license.license_number) {
                let expiryDisplay = license.license_expiry_date || '-';
                
                if (license.license_expiry_date && typeof license.license_expiry_date === 'object' && (license.license_expiry_date.seconds || license.license_expiry_date._seconds)) {
                    const seconds = license.license_expiry_date.seconds || license.license_expiry_date._seconds;
                    const date = new Date(seconds * 1000);
                    expiryDisplay = date.toLocaleDateString('en-GB', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                } else if (license.license_expiry_date && typeof license.license_expiry_date === 'string') {
                    const date = new Date(license.license_expiry_date);
                    if (!isNaN(date)) {
                        expiryDisplay = date.toLocaleDateString('en-GB', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        });
                    }
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
    } else {
        // Standard headers
        thead.innerHTML = `
            <tr>
                <th scope="col" class="px-6 py-4">ชื่อ - นามสกุล</th>
                <th scope="col" class="px-6 py-4">ชื่อผู้ใช้งาน</th>
                <th scope="col" class="px-6 py-4">บทบาท</th>
                <th scope="col" class="px-6 py-4">สถานะ</th>
                <th scope="col" class="px-6 py-4 text-center">จัดการ</th>
            </tr>
        `;
        
        tbody.innerHTML = users.map(user => {
            let roleText = '';
            if (user.role === 'user' || user.role === 'student' || user.role === 'teacher') {
                roleText = '<span class="bg-gradient-to-r from-orange-500 to-amber-500 text-white py-1 px-3 text-xs font-bold rounded-full whitespace-nowrap inline-flex items-center shadow-[0_3px_10px_rgba(249,115,22,0.4)] transition-all duration-300 hover:scale-105 hover:shadow-[0_4px_16px_rgba(249,115,22,0.6)]"><span class="w-1.5 h-1.5 rounded-full bg-white mr-1.5 animate-pulse"></span>ผู้ใช้งาน</span>';
            } else if (user.role === 'driver') {
                roleText = '<span class="bg-gradient-to-r from-emerald-600 to-green-500 text-white py-1 px-3 text-xs font-bold rounded-full whitespace-nowrap inline-flex items-center shadow-[0_3px_10px_rgba(16,185,129,0.4)] transition-all duration-300 hover:scale-105 hover:shadow-[0_4px_16px_rgba(16,185,129,0.6)]"><span class="w-1.5 h-1.5 rounded-full bg-white mr-1.5 animate-pulse"></span>พนักงานขับรถ</span>';
            } else if (user.role === 'admin') {
                roleText = '<span class="bg-gradient-to-r from-blue-600 to-cyan-500 text-white py-1 px-3 text-xs font-bold rounded-full whitespace-nowrap inline-flex items-center shadow-[0_3px_10px_rgba(37,99,235,0.4)] transition-all duration-300 hover:scale-105 hover:shadow-[0_4px_16px_rgba(37,99,235,0.6)]"><span class="w-1.5 h-1.5 rounded-full bg-white mr-1.5 animate-pulse"></span>ผู้ดูแลระบบ</span>';
            } else {
                roleText = `<span class="bg-gray-800 text-gray-300 py-1 px-2.5 text-xs font-medium rounded-full border border-gray-700 whitespace-nowrap inline-block">${user.role || '-'}</span>`;
            }
                
            let statusText = user.status === 'inactive' ? 
                 '<span class="text-red-400">ปิดใช้งาน</span>' : 
                 '<span class="text-green-400">ใช้งานแล้ว</span>';
            
            return `
                <tr class="hover:bg-gray-300/50 transition-colors">
                    <td class="px-6 py-4 font-medium text-white">${user.name || '-'}</td>
                    <td class="px-6 py-4">${user.username || '-'}</td>
                    <td class="px-6 py-4">${roleText}</td>
                    <td class="px-6 py-4">${statusText}</td>
                    <td class="px-6 py-4 text-center">
                        <button onclick="editUser('${user.id}')" class="text-primary hover:text-white mr-3 transition-colors p-2 rounded hover:bg-gray-700" title="แก้ไข">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        </button>
                        <button onclick="deleteUser('${user.id}')" class="text-red-500 hover:text-red-400 transition-colors p-2 rounded hover:bg-gray-700" title="ลบ">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    }
}

function openUserModal(uid = null) {
    if (!uid && currentRoleFilter === 'driver') {
        openDriverModal(null);
        return;
    }

    const modal = document.getElementById('userModal');
    const form = document.getElementById('userForm');
    const modalTitle = document.getElementById('modalTitle');
    
    document.getElementById('formError').classList.add('hidden');
    form.reset();

    if (uid) {
        modalTitle.innerText = "แก้ไขข้อมูลผู้ใช้";
        document.getElementById('userId').value = uid;
        
        const user = allUsers.find(u => u.id === uid);
        if (user) {
            let roleVal = user.role || 'user';
            if (roleVal === 'student' || roleVal === 'teacher') {
                roleVal = 'user';
            }
            document.getElementById('userRole').value = roleVal;
            document.getElementById('userName').value = user.name || '';
            document.getElementById('userUsername').value = user.username || '';
            document.getElementById('userPassword').value = user.password || '';
            document.getElementById('userStatus').value = user.status || 'active';
        }
    } else {
        modalTitle.innerText = "เพิ่มผู้ใช้ใหม่";
        document.getElementById('userId').value = "";
    }
    
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        modal.querySelector('.transform').classList.remove('scale-95');
    }, 10);
}

function closeUserModal() {
    const modal = document.getElementById('userModal');
    modal.classList.add('opacity-0');
    modal.querySelector('.transform').classList.add('scale-95');
    
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
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
        
        const driver = allUsers.find(u => u.id === uid);
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

function editUser(uid) {
    const user = allUsers.find(u => u.id === uid);
    if (user && user.role === 'driver') {
        openDriverModal(uid);
    } else {
        openUserModal(uid);
    }
}

async function deleteUser(uid) {
    const user = allUsers.find(u => u.id === uid);
    if (user && user.role === 'driver') {
        deleteDriver(uid);
        return;
    }

    if (confirm('คุณแน่ใจหรือไม่ว่าต้องการลบผู้ใช้งานนี้?')) {
        try {
            const res = await fetch(`services/user_api.php?action=delete`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ uid })
            });
            const result = await res.json();
            if (result.status === 'success') {
                loadUsers();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (e) {
            console.error(e);
            alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
        }
    }
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
                loadUsers();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (e) {
            console.error(e);
            alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
        }
    }
}

function showUserError(msg) {
    const errObj = document.getElementById('formError');
    errObj.innerText = msg;
    errObj.classList.remove('hidden');
}

function showDriverError(msg) {
    const errObj = document.getElementById('driverFormError');
    errObj.innerText = msg;
    errObj.classList.remove('hidden');
}

function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = `
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
            </svg>
        `;
    } else {
        input.type = 'password';
        btn.innerHTML = `
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
        `;
    }
}
