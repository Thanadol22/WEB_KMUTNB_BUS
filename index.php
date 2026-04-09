<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

require_once 'includes/firebase_config.php';
require_once 'services/FirebaseService.php';

// Initialize Service
$firebaseService = new FirebaseService($firebase['db'], $firebase['auth'] ?? null);
$webConfig = getFirebaseWebConfig();

// Theme logic
$theme = $_COOKIE['theme'] ?? 'light'; // Default to light mode
$isDarkMode = ($theme === 'dark');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KMUTNB BUS- Admin</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#ff4009',
                        darkbg: '<?php echo $isDarkMode ? '#121212' : '#f3f4f6'; ?>',
                        cardbg: '<?php echo $isDarkMode ? '#1e1e1e' : '#ffffff'; ?>',
                        accent: '#ffb347',
                    }
                }
            }
        }
    </script>
    <style>
        body { 
            background-color: <?php echo $isDarkMode ? '#121212' : '#f3f4f6'; ?>; 
            color: <?php echo $isDarkMode ? '#ffffff' : '#111827'; ?>; 
            font-family: 'Inter', sans-serif; 
        }
        /* Enhance scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: <?php echo $isDarkMode ? '#121212' : '#f3f4f6'; ?>; }
        ::-webkit-scrollbar-thumb { background: <?php echo $isDarkMode ? '#333' : '#d1d5db'; ?>; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: <?php echo $isDarkMode ? '#555' : '#9ca3af'; ?>; }
        
        /* Global Animations */
        @keyframes fade-in-up {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fade-in-up 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        /* Staggered animation classes for lists/cards */
        .stagger-1 { animation-delay: 100ms; opacity: 0; animation-name: fade-in-up; animation-duration: 0.5s; animation-fill-mode: forwards; }
        .stagger-2 { animation-delay: 200ms; opacity: 0; animation-name: fade-in-up; animation-duration: 0.5s; animation-fill-mode: forwards; }
        .stagger-3 { animation-delay: 300ms; opacity: 0; animation-name: fade-in-up; animation-duration: 0.5s; animation-fill-mode: forwards; }
        .stagger-4 { animation-delay: 400ms; opacity: 0; animation-name: fade-in-up; animation-duration: 0.5s; animation-fill-mode: forwards; }
        .stagger-5 { animation-delay: 500ms; opacity: 0; animation-name: fade-in-up; animation-duration: 0.5s; animation-fill-mode: forwards; }

        /* Generic effects for cards and interactive elements */
        .bg-cardbg, .card-hover-effect {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s cubic-bezier(0.4, 0, 0.2, 1), border-color 0.3s ease;
        }
        .bg-cardbg:hover, .card-hover-effect:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, <?php echo $isDarkMode ? '0.5' : '0.1'; ?>), 0 8px 10px -6px rgba(0, 0, 0, <?php echo $isDarkMode ? '0.3' : '0.05'; ?>);
        }
        
        /* Global button interactive effect */
        button, a.btn, input[type="submit"] {
            transition: all 0.2s ease-in-out;
        }
        button:not(.no-scale):hover, a.btn:hover, input[type="submit"]:hover {
            transform: translateY(-1px) !important;
            filter: brightness(1.1);
        }
        button:not(.no-scale):active, a.btn:active, input[type="submit"]:active {
            transform: translateY(1px) scale(0.98) !important;
        }
        
        /* Table rows hover effect globally */
        tbody tr {
            transition: background-color 0.2s ease, transform 0.2s ease;
        }
        tbody tr:hover {
            background-color: <?php echo $isDarkMode ? 'rgba(255,255,255,0.02)' : 'rgba(0,0,0,0.02)'; ?> !important;
        }

        <?php if (!$isDarkMode): ?>
        /* Light Theme Overrides */
        .text-white:not(button):not(.bg-primary):not(.bg-red-500):not(.bg-green-500):not(.text-green-800):not(.bg-blue-500):not(.bg-gray-500):not(.text-white-keep) {
            color: #111827 !important;
        }
        button .text-white {
            color: #ffffff !important;
        }
        
        .text-gray-400, .text-gray-300 { color: #6b7280 !important; }
        .bg-black { background-color: #ffffff !important; } /* Sidebar background */
        .bg-darkbg { background-color: #f3f4f6 !important; }
        .bg-cardbg { background-color: #ffffff !important; }
        
        .border-gray-800, .border-gray-700 { border-color: #e5e7eb !important; }
        .border-b-gray-800 { border-color: #e5e7eb !important; }
        
        .bg-gray-800 { background-color: #f3f4f6 !important; color: #111827 !important; }
        .bg-gray-900 { background-color: #e5e7eb !important; color: #111827 !important; }
        
        .hover\:bg-gray-700:hover, .hover\:bg-gray-800:hover, .hover\:bg-gray-900:hover { background-color: #e5e7eb !important; color: #111827 !important; }
        .hover\:border-primary\/50:hover { border-color: rgba(255, 64, 9, 0.5) !important; }
        
        input, select, textarea { 
            color: #111827 !important; 
            background-color: #ffffff !important;
            border-color: #d1d5db !important;
        }
        
        th { background-color: #f9fafb !important; color: #374151 !important; font-weight: 600 !important; border-bottom: 2px solid #e5e7eb !important; }
        td { border-color: #e5e7eb !important; color: #4b5563 !important; }
        
        table { border-color: #e5e7eb !important; }
        thead { background-color: #f9fafb !important; }
        
        .divide-y > :not([hidden]) ~ :not([hidden]) { border-color: #e5e7eb !important; }
        .divide-x > :not([hidden]) ~ :not([hidden]) { border-color: #e5e7eb !important; }
        
        /* specific colors */
        .text-green-400 { color: #16a34a !important; }
        .bg-green-500\/20 { background-color: #dcfce7 !important; color: #15803d !important; }
        
        .text-red-400 { color: #dc2626 !important; }
        .bg-red-500\/20 { background-color: #fee2e2 !important; color: #b91c1c !important; }
        
        .text-yellow-400 { color: #eab308 !important; }
        .bg-yellow-500\/20 { background-color: #fef9c3 !important; color: #a16207 !important; }
        
        .shadow-lg { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.02) !important; }
        <?php endif; ?>
    </style>
    
    <!-- Inject Firebase Config for JS -->
    <script>
        window.firebaseConfig = <?php echo json_encode($webConfig); ?>;
    </script>
</head>
<body class="bg-darkbg text-white font-sans antialiased">
    <!-- Sidebar Overlay (mobile) -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/60 z-40 hidden transition-opacity lg:hidden" onclick="toggleSidebar()"></div>

    <div class="flex h-screen overflow-hidden">
        <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-black border-r border-gray-800 flex flex-col transition-transform duration-300 transform -translate-x-full lg:relative lg:translate-x-0 lg:flex h-full shadow-2xl lg:shadow-none">
            <?php include 'includes/sidebar.php'; ?>
        </aside>
        
        <div class="flex-1 flex flex-col bg-darkbg overflow-hidden relative">
            
            <!-- Mobile Header and Hamburger -->
            <div class="lg:hidden flex items-center justify-between p-4 bg-cardbg border-b border-gray-800 z-10">
                <div class="flex items-center space-x-3">
                    <button onclick="toggleSidebar()" class="text-gray-400 hover:text-white focus:outline-none transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    <span class="font-bold text-primary truncate sm:text-lg text-base">KMUTNB BUS</span>
                </div>
            </div>

            <!-- Theme Toggle Button -->
            <div class="absolute top-3 right-4 lg:top-6 lg:right-6 z-50">
                <button id="theme-toggle" class="no-scale p-2 rounded-full bg-cardbg border <?php echo $isDarkMode ? 'border-gray-700 text-gray-300' : 'border-gray-300 text-gray-700'; ?> hover:text-primary hover:border-primary transition-colors focus:outline-none shadow-sm">
                    <!-- Sun icon for dark mode (to switch to light) -->
                    <svg id="theme-toggle-light-icon" class="w-6 h-6 <?php echo $isDarkMode ? '' : 'hidden'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <!-- Moon icon for light mode (to switch to dark) -->
                    <svg id="theme-toggle-dark-icon" class="w-6 h-6 <?php echo $isDarkMode ? 'hidden' : ''; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                </button>
            </div>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-darkbg p-6">
                <!-- Dynamic Content Routing -->
                <div class="animate-fade-in-up w-full h-full max-w-screen-2xl mx-auto">
                <?php 
                    $page = isset($_GET['page']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['page']) : 'dashboard';
                    $pagePath = "pages/{$page}.php";
                    
                    if (file_exists($pagePath)) {
                        include $pagePath;
                    } else {
                        echo "<div class='text-center mt-20'><h2 class='text-2xl text-red-500'>404 Page Not Found</h2><p class='text-gray-400 mt-2'>The requested page `{$page}` does not exist.</p></div>";
                    }
                ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Init Firebase Global App -->
    <script type="module">
        import { app } from './assets/js/firebase-init.js';
        window.firebaseApp = app; 
    </script>

    <!-- Theme Toggle Script -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            
            if (sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                setTimeout(() => overlay.classList.add('opacity-100'), 10);
            } else {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.remove('opacity-100');
                setTimeout(() => overlay.classList.add('hidden'), 300);
            }
        }

        const themeToggleBtn = document.getElementById('theme-toggle');
        // Note: isDarkMode comes from PHP
        let isDarkMode = <?php echo $isDarkMode ? 'true' : 'false'; ?>;

        themeToggleBtn.addEventListener('click', () => {
            isDarkMode = !isDarkMode;
            // Save theme to cookie
            document.cookie = "theme=" + (isDarkMode ? 'dark' : 'light') + "; path=/; max-age=31536000";
            // Reload immediately to apply server-rendered styles
            window.location.reload();
        });
    </script>
</body>
</html>
