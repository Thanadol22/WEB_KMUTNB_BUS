// Local API driven User Management
// No Firebase SDK needed on frontend

// Globals accessible in HTML
window.openUserModal = openUserModal;
window.closeUserModal = closeUserModal;

let allUsers = [];
let currentRoleFilter = 'all';
let currentSearchQuery = '';

console.log("Loading user_management.js script...");

// Filter & Search Implementation
const searchInput = document.getElementById('searchInput');
const filterContainer = document.getElementById('filter-container');

if (searchInput) {
    searchInput.addEventListener('input', (e) => {
        currentSearchQuery = e.target.value.toLowerCase();
        applyFilters();
    });
}

if (filterContainer) {
    filterContainer.addEventListener('click', (e) => {
        const btn = e.target.closest('.filter-btn');
        if (!btn) return;
        
        // Update active class
        const allBtns = filterContainer.querySelectorAll('.filter-btn');
        allBtns.forEach(b => {
            b.classList.remove('bg-gray-800', 'text-white', 'border-gray-600', 'active-filter');
            b.classList.add('bg-transparent', 'text-gray-400', 'border-transparent');
        });
        
        btn.classList.remove('bg-transparent', 'text-gray-400', 'border-transparent');
        btn.classList.add('bg-gray-800', 'text-white', 'border-gray-600', 'active-filter');
        
        currentRoleFilter = btn.dataset.role;
        applyFilters();
    });
}

function applyFilters() {
    let filteredUsers = allUsers;
    
    // Filter by role
    if (currentRoleFilter !== 'all') {
        filteredUsers = filteredUsers.filter(u => u.role === currentRoleFilter);
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

// Form submission
const form = document.getElementById('userForm');
if (form) {
    form.addEventListener('submit', async (e) => {
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
                showError(result.message);
            }
        } catch (error) {
            showError("เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์");
            console.error(error);
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });
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
        const res = await fetch('services/user_api.php?action=list');
        
        // Always parse response safely
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
        
        allUsers = json.data.filter(u => u.role !== 'driver');
        applyFilters(); // Apply current filters before rendering
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-4 text-center text-red-500">เกิดข้อผิดพลาด: ${e.message}</td></tr>`;
    }
}

function renderTable(users) {
    const tbody = document.getElementById('user-table-body');
    if (users.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">ไม่พบข้อมูลผู้ใช้งาน</td></tr>`;
        return;
    }

    tbody.innerHTML = users.map(user => {
        let roleText = '';
        if (user.role === 'student') {
            roleText = '<span class="bg-blue-900/50 text-white py-1 px-2 text-xs rounded-full border border-blue-700 whitespace-nowrap inline-block">นักศึกษา</span>';
        } else if (user.role === 'teacher') {
            roleText = '<span class="bg-amber-900/50 text-white py-1 px-2 text-xs rounded-full border border-amber-700 whitespace-nowrap inline-block">อาจารย์</span>';
        } else if (user.role === 'driver') {
            roleText = '<span class="bg-green-900/50 text-white py-1 px-2 text-xs rounded-full border border-green-700 whitespace-nowrap inline-block">พนักงานขับรถ</span>';
        } else {
            roleText = `<span class="bg-purple-900/50 text-white py-1 px-2 text-xs rounded-full border border-purple-700 whitespace-nowrap inline-block">${user.role === 'admin' ? 'ผู้ดูแลระบบ' : (user.role || '-')}</span>`;
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

function openUserModal(uid = null) {
    const modal = document.getElementById('userModal');
    const form = document.getElementById('userForm');
    const modalTitle = document.getElementById('modalTitle');
    
    document.getElementById('formError').classList.add('hidden');
    form.reset();
    
    const pwdInput = document.getElementById('userPassword');
    const pwdHint = document.getElementById('pwdHint');

    if (uid) {
        modalTitle.innerText = "แก้ไขข้อมูลผู้ใช้";
        document.getElementById('userId').value = uid;
        
        const user = allUsers.find(u => u.id === uid);
        if (user) {
            document.getElementById('userRole').value = user.role || 'driver';
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
    // small delay to allow display:block to apply before animating opacity
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



window.editUser = function(uid) {
    openUserModal(uid);
};

window.deleteUser = async function(uid) {
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
};

function showError(msg) {
    const errObj = document.getElementById('formError');
    errObj.innerText = msg;
    errObj.classList.remove('hidden');
}
