<div class="flex items-center">
    <button class="md:hidden text-gray-400 hover:text-white focus:outline-none">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
    </button>
</div>

<div class="flex items-center space-x-4">
    <div class="relative">
        <button class="text-gray-400 hover:text-white transition-colors focus:outline-none relative">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
            <span class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full"></span>
        </button>
    </div>
    
    <div class="flex items-center space-x-3 border-l border-gray-700 pl-4 ml-4">
        <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-white font-bold">
            <?php 
            $adminName = $_SESSION['admin_user']['name'] ?? 'Admin';
            echo mb_substr($adminName, 0, 1, 'UTF-8'); 
            ?>
        </div>
        <div class="hidden md:block">
            <span class="text-sm font-medium text-white block"><?php echo htmlspecialchars($adminName); ?></span>
            <span class="text-xs text-gray-400 block"><?php echo htmlspecialchars($_SESSION['admin_user']['username'] ?? 'admin'); ?></span>
        </div>
    </div>
</div>
