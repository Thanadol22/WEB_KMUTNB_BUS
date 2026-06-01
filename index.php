<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('HTTP/1.1 401 Unauthorized');
        exit();
    }
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

// Handle AJAX dynamic page routing (SPA Mode)
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    $page = isset($_GET['page']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['page']) : 'dashboard';
    $pagePath = "pages/{$page}.php";
    
    header('Content-Type: text/html; charset=UTF-8');
    
    if (file_exists($pagePath)) {
        include $pagePath;
    } else {
        echo "<div class='text-center mt-20'><h2 class='text-2xl text-red-500'>404 Page Not Found</h2><p class='text-gray-400 mt-2'>The requested page `" . htmlspecialchars($page) . "` does not exist.</p></div>";
    }
    exit();
}
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
        
        #main-page-content {
            transition: opacity 0.2s cubic-bezier(0.4, 0, 0.2, 1), transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        <?php endif; ?>
    </style>
    
    <!-- Inject Firebase Config for JS -->
    <script>
        window.firebaseConfig = <?php echo json_encode($webConfig); ?>;
    </script>
</head>
<body class="bg-darkbg text-white font-sans antialiased">
    
    <!-- Sleek Top-Bar Progress Indicator -->
    <div id="top-loading-bar" class="fixed top-0 left-0 right-0 h-[3px] bg-gradient-to-r from-primary via-accent to-primary z-[99999] transition-all duration-300 ease-out opacity-0 pointer-events-none" style="width: 0%;"></div>
    
    <!-- Global Page Loader Overlay -->
    <div id="global-page-loader" class="fixed inset-0 z-[9999] flex items-center justify-center bg-darkbg transition-opacity duration-300">
        <div class="text-center animate-fade-in-up">
            <div class="relative w-20 h-20 mx-auto mb-6">
                <!-- Outer ring -->
                <div class="absolute inset-0 border-4 border-gray-700/30 rounded-full"></div>
                <!-- Spinning ring -->
                <div class="absolute inset-0 border-4 border-primary rounded-full border-t-transparent animate-spin"></div>
                <!-- Center Icon -->
                <svg class="absolute inset-0 w-8 h-8 m-auto text-primary animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-100 mb-2 tracking-wide">กำลังเตรียมข้อมูล...</h2>
            <p class="text-sm text-gray-400">กรุณารอสักครู่ ระบบกำลังประมวลผล</p>
        </div>
    </div>
    
    <script>
        // --- Intercept DOMContentLoaded for AJAX SPA Compatibility ---
        (function() {
            const originalAddEventListener = document.addEventListener;
            document.addEventListener = function(type, listener, options) {
                if (type === 'DOMContentLoaded') {
                    if (document.readyState === 'interactive' || document.readyState === 'complete') {
                        setTimeout(listener, 10);
                        return;
                    }
                }
                return originalAddEventListener.call(this, type, listener, options);
            };
        })();

        // Handle global page loader transitions
        window.addEventListener('load', function() {
            const loader = document.getElementById('global-page-loader');
            if (loader) {
                loader.style.opacity = '0';
                setTimeout(() => {
                    loader.style.display = 'none';
                }, 300);
            }
        });

        // Fallback timeout for initial loader
        setTimeout(() => {
            const loader = document.getElementById('global-page-loader');
            if (loader && loader.style.display !== 'none') {
                loader.style.opacity = '0';
                setTimeout(() => { loader.style.display = 'none'; }, 300);
            }
        }, 8000);

        // --- Dynamic SPA Navigation Logic ---
        async function loadPageDynamic(url, pushToHistory = true) {
            const contentArea = document.getElementById('main-page-content');
            const loader = document.getElementById('global-page-loader');
            
            // Extract page name
            let pageKey = 'dashboard';
            try {
                const urlObj = new URL(url, window.location.origin);
                pageKey = urlObj.searchParams.get('page') || 'dashboard';
            } catch (e) {
                // If parsing fails, extract using regex
                const match = url.match(/[?&]page=([^&]+)/);
                if (match) pageKey = match[1];
            }
            
            // 1. Show Global Page Loader Overlay immediately
            if (loader) {
                loader.style.display = 'flex';
                void loader.offsetWidth;
                loader.style.opacity = '1';
            }
            
            // 2. Animate content area out smoothly
            if (contentArea) {
                contentArea.style.opacity = '0';
                contentArea.style.transform = 'translateY(10px)';
            }
            
            try {
                // Fetch dynamic HTML page
                const response = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (!response.ok) throw new Error('Dynamic request returned non-OK response');
                
                const html = await response.text();
                
                // 3. Swap Content
                if (contentArea) {
                    contentArea.innerHTML = html;
                }
                
                // 4. Update History State if requested
                if (pushToHistory) {
                    history.pushState({ page: pageKey, url: url }, '', url);
                }
                
                // 5. Update Active Sidebar Link styles
                updateActiveSidebarLink(pageKey);
                
                // 6. Dynamically parse and execute script tags
                executeScriptsInContent(contentArea);
                
                // 7. Hide Global Page Loader Overlay
                if (loader) {
                    // Small timeout to guarantee visual transition smoothness
                    setTimeout(() => {
                        loader.style.opacity = '0';
                        setTimeout(() => { loader.style.display = 'none'; }, 300);
                    }, 100);
                }
                
                // 8. Animate content area back in
                if (contentArea) {
                    setTimeout(() => {
                        contentArea.style.opacity = '1';
                        contentArea.style.transform = 'translateY(0)';
                    }, 150);
                }
                
                // Scroll page back to top
                const mainEl = document.querySelector('main');
                if (mainEl) mainEl.scrollTop = 0;
                
                // 9. Auto-close mobile sidebar if open
                const sidebar = document.getElementById('sidebar');
                if (sidebar && !sidebar.classList.contains('-translate-x-full')) {
                    toggleSidebar();
                }
                
            } catch (error) {
                console.warn('Dynamic navigation failed, falling back to full load:', error);
                // Fallback to standard page load
                window.location.href = url;
            }
        }

        // Helper to extract and run scripts dynamically in global scope
        function executeScriptsInContent(container) {
            if (!container) return;
            const scripts = container.querySelectorAll('script');
            scripts.forEach(oldScript => {
                const newScript = document.createElement('script');
                Array.from(oldScript.attributes).forEach(attr => {
                    newScript.setAttribute(attr.name, attr.value);
                });
                if (oldScript.src) {
                    newScript.src = oldScript.src;
                } else {
                    newScript.text = oldScript.textContent;
                }
                // Append script to trigger loading/execution
                document.body.appendChild(newScript);
            });
        }

        // Helper to update active link styling in Sidebar
        function updateActiveSidebarLink(activePage) {
            const sidebar = document.getElementById('sidebar');
            if (!sidebar) return;
            
            const links = sidebar.querySelectorAll('a[href*="page="]');
            links.forEach(link => {
                let page = null;
                try {
                    const url = new URL(link.href, window.location.origin);
                    page = url.searchParams.get('page');
                } catch(e) {
                    const match = link.href.match(/[?&]page=([^&]+)/);
                    if (match) page = match[1];
                }
                
                const isActive = (page === activePage);
                
                // Reset classes
                link.className = "flex items-center px-4 py-3 rounded-lg transition-all duration-300 group hover:pl-5";
                
                if (isActive) {
                    link.className += " bg-primary text-white";
                } else {
                    link.className += " text-gray-400 hover:bg-gray-800 hover:text-white";
                }
            });
        }

        // Intercept global link clicks
        document.addEventListener('click', function(e) {
            // Find closest anchor tag
            const anchor = e.target.closest('a');
            if (!anchor) return;
            
            const href = anchor.getAttribute('href');
            if (!href) return;
            
            // Check if it is a system navigation link (e.g. ?page=... or index.php?page=...)
            const isSystemNav = href.startsWith('?page=') || href.startsWith('index.php?page=');
            
            // Check standard browser modifier keys (Ctrl/Cmd click to open in new tab)
            const isModifier = e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1;
            const isTargetBlank = anchor.target === '_blank';
            
            if (isSystemNav && !isModifier && !isTargetBlank) {
                e.preventDefault();
                loadPageDynamic(href);
            }
        });

        // Handle browser Back/Forward navigation
        window.addEventListener('popstate', function(e) {
            loadPageDynamic(window.location.search || 'index.php', false);
        });
    </script>

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
                <div id="main-page-content" class="animate-fade-in-up w-full h-full max-w-screen-2xl mx-auto">
                <?php 
                    $page = isset($_GET['page']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['page']) : 'dashboard';
                    $pagePath = "pages/{$page}.php";
                    
                    if (file_exists($pagePath)) {
                        include $pagePath;
                    } else {
                        echo "<div class='text-center mt-20'><h2 class='text-2xl text-red-500'>404 Page Not Found</h2><p class='text-gray-400 mt-2'>The requested page `" . htmlspecialchars($page) . "` does not exist.</p></div>";
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
