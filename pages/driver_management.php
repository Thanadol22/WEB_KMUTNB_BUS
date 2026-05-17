<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-primary">ระบบจัดการพนักงานขับรถ</h1>
        <p class="text-gray-400 mt-1 sm:mt-2 text-sm sm:text-base">จัดการข้อมูลพนักงานขับรถและข้อมูลใบขับขี่</p>
    </div>
    <button onclick="openDriverModal()" class="w-full sm:w-auto bg-primary hover:bg-accent text-white font-semibold py-2 px-4 rounded-lg flex items-center justify-center transition-colors shadow-lg">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        เพิ่มพนักงานขับรถ
    </button>
</div>

<!-- Filters & Search -->
<div class="bg-cardbg stagger-1 p-4 rounded-xl shadow-lg border border-gray-700 mb-6 flex flex-col md:flex-row gap-4 justify-between items-center">
    <div class="relative w-full md:w-64">
        <input type="text" id="searchDriverInput" placeholder="ค้นหาชื่อ, เบอร์โทร..." class="w-full bg-darkbg border border-gray-700 text-white rounded-lg pl-10 pr-4 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
        <svg class="w-5 h-5 absolute left-3 top-2.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
    </div>
</div>

<!-- Drivers Table -->
<div class="bg-cardbg stagger-2 rounded-xl shadow-lg border border-gray-700 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-400">
            <thead class="text-xs text-gray-400 uppercase bg-gray-800 border-b border-gray-700">
                <tr>
                    <th scope="col" class="px-6 py-4">รูปโปรไฟล์/ชื่อ</th>
                    <th scope="col" class="px-6 py-4">ข้อมูลติดต่อ</th>
                    <th scope="col" class="px-6 py-4">ข้อมูลใบขับขี่</th>
                    <th scope="col" class="px-6 py-4">สถานะ</th>
                    <th scope="col" class="px-6 py-4 text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody id="driver-table-body" class="divide-y divide-gray-700">
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

<!-- Driver Modal (Add/Edit) -->
<div id="driverModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center opacity-0 transition-opacity duration-300">
    <div class="bg-cardbg border border-gray-700 rounded-xl shadow-2xl w-full max-w-2xl p-6 transform scale-95 transition-transform duration-300 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-5">
            <h3 id="driverModalTitle" class="text-xl font-bold text-white">เพิ่มพนักงานขับรถ</h3>
            <button onclick="closeDriverModal()" class="text-gray-400 hover:text-white focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        
        <form id="driverForm" class="space-y-6">
            <input type="hidden" id="driverId" name="driverId">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- ข้อมูลส่วนตัว -->
                <div class="space-y-4">
                    <h4 class="text-lg font-semibold text-primary border-b border-gray-700 pb-2">ข้อมูลส่วนตัว</h4>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">ชื่อ - นามสกุล <span class="text-red-500">*</span></label>
                        <input type="text" id="driverName" required class="w-full bg-darkbg border border-gray-700 text-white rounded-lg px-4 py-2 focus:border-primary focus:ring-1 focus:ring-primary outline-none" placeholder="เช่น สมทบ งามวาจา">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">ชื่อผู้ใช้งาน (อีเมล หรือ Username) <span class="text-red-500">*</span></label>
                        <input type="text" id="driverUsername" required class="w-full bg-darkbg border border-gray-700 text-white rounded-lg px-4 py-2 focus:border-primary focus:ring-1 focus:ring-primary outline-none" placeholder="เช่น driver@gmail.com">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">เบอร์โทรศัพท์</label>
                        <input type="tel" id="driverPhone" class="w-full bg-darkbg border border-gray-700 text-white rounded-lg px-4 py-2 focus:border-primary focus:ring-1 focus:ring-primary outline-none" placeholder="เช่น 0860542759">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">รหัสผ่าน <span class="text-red-500">*</span> <span class="text-xs text-gray-500" id="driverPwdHint"></span></label>
                        <input type="text" id="driverPassword" required class="w-full bg-darkbg border border-gray-700 text-white rounded-lg px-4 py-2 focus:border-primary focus:ring-1 focus:ring-primary outline-none" placeholder="เช่น 111111">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">สถานะ</label>
                        <select id="driverStatus" class="w-full bg-darkbg border border-gray-700 text-white rounded-lg px-4 py-2 focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                            <option value="active">ใช้งาน (Active)</option>
                            <option value="inactive">ปิดใช้งาน (Inactive)</option>
                        </select>
                    </div>

                    <div class="flex gap-4">
                         <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-400 mb-1">เพศ</label>
                            <select id="driverGender" class="w-full bg-darkbg border border-gray-700 text-white rounded-lg px-4 py-2 focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                                <option value="male">ชาย</option>
                                <option value="female">หญิง</option>
                                <option value="other">อื่นๆ</option>
                            </select>
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-400 mb-1">วันเกิด</label>
                            <input type="date" id="driverDob" class="w-full bg-darkbg border border-gray-700 text-white rounded-lg px-4 py-2 focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">รูปโปรไฟล์</label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-700 border-dashed rounded-lg bg-darkbg hover:border-primary transition-colors cursor-pointer relative" id="profileImageContainer">
                            <div class="space-y-1 text-center" id="profileUploadPrompt">
                                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="flex text-sm text-gray-400 justify-center">
                                    <label for="profileImageUpload" class="relative cursor-pointer rounded-md font-medium text-primary hover:text-accent focus-within:outline-none">
                                        <span>อัปโหลดรูปภาพโปรไฟล์</span>
                                        <input id="profileImageUpload" name="profileImageUpload" type="file" class="sr-only" accept="image/*">
                                    </label>
                                </div>
                                <p class="text-xs text-gray-500">PNG, JPG up to 2MB</p>
                            </div>
                            <img id="profileImagePreview" src="" class="hidden max-h-48 rounded-full w-32 h-32 object-cover mx-auto" alt="Profile preview">
                            <button type="button" id="removeProfileImage" class="hidden absolute top-2 right-2 bg-red-500 text-white rounded-full p-1 hover:bg-red-600 focus:outline-none">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                        <input type="hidden" id="driverProfileImage">
                    </div>
                </div>

                <!-- ข้อมูลใบขับขี่ -->
                <div class="space-y-4">
                    <h4 class="text-lg font-semibold text-primary border-b border-gray-700 pb-2">ข้อมูลใบขับขี่</h4>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">เลขที่ใบอนุญาต</label>
                        <input type="text" id="licenseNumber" class="w-full bg-darkbg border border-gray-700 text-white rounded-lg px-4 py-2 focus:border-primary focus:ring-1 focus:ring-primary outline-none" placeholder="เช่น 12345678">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">ประเภทใบอนุญาต</label>
                        <input type="text" id="licenseType" class="w-full bg-darkbg border border-gray-700 text-white rounded-lg px-4 py-2 focus:border-primary focus:ring-1 focus:ring-primary outline-none" placeholder="เช่น ส่วนบุคคล ชนิดที่ 3 (บ.3)">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">วันหมดอายุใบอนุญาต</label>
                        <input type="date" id="licenseExpiry" class="w-full bg-darkbg border border-gray-700 text-white rounded-lg px-4 py-2 focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">รูปถ่ายใบขับขี่</label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-700 border-dashed rounded-lg bg-darkbg hover:border-primary transition-colors cursor-pointer relative" id="licenseImageContainer">
                            <div class="space-y-1 text-center" id="licenseUploadPrompt">
                                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="flex text-sm text-gray-400 justify-center">
                                    <label for="licenseImageUpload" class="relative cursor-pointer rounded-md font-medium text-primary hover:text-accent focus-within:outline-none">
                                        <span>อัปโหลดรูปภาพ</span>
                                        <input id="licenseImageUpload" name="licenseImageUpload" type="file" class="sr-only" accept="image/*">
                                    </label>
                                </div>
                                <p class="text-xs text-gray-500">PNG, JPG, GIF up to 2MB</p>
                            </div>
                            <img id="licenseImagePreview" src="" class="hidden max-h-48 rounded-lg object-contain" alt="License preview">
                            <button type="button" id="removeLicenseImage" class="hidden absolute top-2 right-2 bg-red-500 text-white rounded-full p-1 hover:bg-red-600 focus:outline-none">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                        <input type="hidden" id="licenseImageBase64">
                    </div>
                </div>
            </div>

            <div id="driverFormError" class="text-red-400 text-sm hidden"></div>

            <div class="flex justify-end space-x-3 pt-4 border-t border-gray-700 mt-6">
                <button type="button" onclick="closeDriverModal()" class="px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-700 transition-colors">ยกเลิก</button>
                <button type="submit" id="saveDriverBtn" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-accent transition-colors flex items-center">
                    บันทึกข้อมูล
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Load Page Specific JS -->
<script type="module" src="assets/js/driver_management.js?v=<?php echo time(); ?>"></script>
