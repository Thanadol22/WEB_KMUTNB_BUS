<div class="py-1 px-6 flex items-center justify-center border-b border-gray-800 overflow-hidden">
    <img src="assets/images/logo.png" alt="KMUTNB BUS Logo" class="h-60 max-w-full w-auto object-cover -my-20">
</div>

<nav class="flex-1 mt-4 px-4 space-y-2">
    <?php 
    $currentPage = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
    
    $navItems = [
        ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>'],
        ['id' => 'user_management', 'label' => 'จัดการผู้ใช้งาน', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>'],
        ['id' => 'live_tracking', 'label' => 'ระบบติดตามรถ', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>'],
        ['id' => 'operation_history', 'label' => 'ประวัติการเดินรถ', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>'],
        ['id' => 'bus_schedules', 'label' => 'ตารางรถวิ่ง', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>'],
        ['id' => 'ticket_reports', 'label' => 'รายงานตั๋ว', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>'],
        ['id' => 'issue_reports', 'label' => 'การแจ้งปัญหา', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>']
    ];

    foreach ($navItems as $item):
        $isActive = $currentPage === $item['id'];
        $activeClass = $isActive ? 'bg-primary text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white';
    ?>
    <a href="?page=<?php echo $item['id']; ?>" class="flex items-center px-4 py-3 rounded-lg transition-all duration-300 group <?php echo $activeClass; ?> hover:pl-5">
        <svg class="w-5 h-5 mr-3 transition-transform duration-300 group-hover:scale-110 group-hover:text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <?php echo $item['icon']; ?>
        </svg>
        <?php echo $item['label']; ?>
    </a>
    <?php endforeach; ?>
</nav>

<div class="p-4 border-t border-gray-800">
    <div class="flex items-center space-x-3 mb-4 px-4">
        <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-bold text-lg">
            <?php 
            $adminName = $_SESSION['admin_user']['name'] ?? 'Admin';
            echo mb_substr($adminName, 0, 1, 'UTF-8'); 
            ?>
        </div>
        <div>
            <span class="text-sm font-medium text-white block"><?php echo htmlspecialchars($adminName); ?></span>
            <span class="text-xs text-gray-400 block"><?php echo htmlspecialchars($_SESSION['admin_user']['username'] ?? 'admin'); ?></span>
        </div>
    </div>
    <a href="logout.php" class="flex items-center px-4 py-3 text-red-400 hover:bg-red-500/10 hover:text-red-300 rounded-lg transition-colors w-full">
        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
        ออกจากระบบ
    </a>
</div>
