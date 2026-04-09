<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-primary">ระบบจัดการผู้ใช้งาน</h1>
        <p class="text-gray-400 mt-1 sm:mt-2 text-sm sm:text-base">จัดการข้อมูลนักศึกษา และพนักงานขับรถ</p>
    </div>
    <button onclick="openUserModal()" class="w-full sm:w-auto bg-primary hover:bg-accent text-white font-semibold py-2 px-4 rounded-lg flex items-center justify-center transition-colors shadow-lg">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        เพิ่มผู้ใช้ใหม่
    </button>
</div>

<!-- Filters & Search -->
<div class="bg-cardbg stagger-1 p-4 rounded-xl shadow-lg border border-gray-700 mb-6 flex flex-col md:flex-row gap-4 justify-between items-center">
    <div class="flex space-x-2" id="filter-container">
        <button data-role="all" class="filter-btn px-4 py-2 rounded-lg bg-gray-800 text-white border border-gray-600 hover:bg-gray-700 active-filter">ทั้งหมด</button>
        <button data-role="student" class="filter-btn px-4 py-2 rounded-lg bg-transparent text-gray-400 border border-transparent hover:bg-gray-800">นักศึกษา</button>
        <button data-role="driver" class="filter-btn px-4 py-2 rounded-lg bg-transparent text-gray-400 border border-transparent hover:bg-gray-800">พนักงานขับรถ</button>
    </div>
    <div class="relative w-full md:w-64">
        <input type="text" id="searchInput" placeholder="ค้นหาชื่อ, รหัสนักศึกษา..." class="w-full bg-darkbg border border-gray-700 text-white rounded-lg pl-10 pr-4 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
        <svg class="w-5 h-5 absolute left-3 top-2.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
    </div>
</div>

<!-- Users Table -->
<div class="bg-cardbg stagger-2 rounded-xl shadow-lg border border-gray-700 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-400">
            <thead class="text-xs text-gray-400 uppercase bg-gray-800 border-b border-gray-700">
                <tr>
                    <th scope="col" class="px-6 py-4">ชื่อ - นามสกุล</th>
                    <th scope="col" class="px-6 py-4">ชื่อผู้ใช้งาน</th>
                    <th scope="col" class="px-6 py-4">บทบาท</th>
                    <th scope="col" class="px-6 py-4">เบอร์โทรศัพท์</th>
                    <th scope="col" class="px-6 py-4">สถานะ</th>
                    <th scope="col" class="px-6 py-4 text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody id="user-table-body" class="divide-y divide-gray-700">
                <!-- Loading State placeholder -->
                <tr>
                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                        <svg class="animate-spin h-6 w-6 mx-auto mb-2 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        กำลังโหลดข้อมูล...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- User Modal (Add/Edit) -->
<div id="userModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center opacity-0 transition-opacity duration-300">
    <div class="bg-cardbg border border-gray-700 rounded-xl shadow-2xl w-full max-w-md p-6 transform scale-95 transition-transform duration-300">
        <div class="flex justify-between items-center mb-5">
            <h3 id="modalTitle" class="text-xl font-bold text-white">เพิ่มผู้ใช้ใหม่</h3>
            <button onclick="closeUserModal()" class="text-gray-400 hover:text-white focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        
        <form id="userForm" class="space-y-4">
            <input type="hidden" id="userId" name="userId">
            
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">บทบาท <span class="text-red-500">*</span></label>
                <select id="userRole" name="role" class="w-full bg-darkbg border border-gray-700 text-white rounded-lg px-4 py-2 focus:border-primary focus:ring-1 focus:ring-primary outline-none" required>
                    <option value="driver">พนักงานขับรถ (Driver)</option>
                    <option value="student">นักศึกษา (Student)</option>
                    <option value="admin">ผู้ดูแลระบบ (Admin)</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">ชื่อ - นามสกุล <span class="text-red-500">*</span></label>
                <input type="text" id="userName" name="name" required class="w-full bg-darkbg border border-gray-700 text-white rounded-lg px-4 py-2 focus:border-primary focus:ring-1 focus:ring-primary outline-none" placeholder="เช่น สมทบ งามวาจา">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">ชื่อผู้ใช้งาน (Username) <span class="text-red-500">*</span></label>
                <input type="text" id="userUsername" name="username" required class="w-full bg-darkbg border border-gray-700 text-white rounded-lg px-4 py-2 focus:border-primary focus:ring-1 focus:ring-primary outline-none" placeholder="เช่น 111111">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">เบอร์โทรศัพท์</label>
                <input type="tel" id="userPhone" name="phone" class="w-full bg-darkbg border border-gray-700 text-white rounded-lg px-4 py-2 focus:border-primary focus:ring-1 focus:ring-primary outline-none" placeholder="เช่น 0860542759">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">รหัสผ่าน <span class="text-red-500">*</span> <span class="text-xs text-gray-500" id="pwdHint"></span></label>
                <input type="text" id="userPassword" name="password" required class="w-full bg-darkbg border border-gray-700 text-white rounded-lg px-4 py-2 focus:border-primary focus:ring-1 focus:ring-primary outline-none" placeholder="เช่น 111111">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">สถานะ</label>
                <select id="userStatus" name="status" class="w-full bg-darkbg border border-gray-700 text-white rounded-lg px-4 py-2 focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                    <option value="active">ใช้งาน (Active)</option>
                    <option value="inactive">ปิดใช้งาน (Inactive)</option>
                </select>
            </div>

            <div id="formError" class="text-red-400 text-sm hidden"></div>

            <div class="flex justify-end space-x-3 pt-4 border-t border-gray-700 mt-6">
                <button type="button" onclick="closeUserModal()" class="px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-700 transition-colors">ยกเลิก</button>
                <button type="submit" id="saveUserBtn" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-accent transition-colors flex items-center">
                    บันทึกข้อมูล
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Load Page Specific JS -->
<script src="assets/js/user_management.js?v=<?php echo time(); ?>"></script>
