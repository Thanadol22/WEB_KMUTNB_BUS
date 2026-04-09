<?php
session_start();
require_once 'includes/firebase_config.php';
require_once 'services/FirebaseService.php';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: index.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $firebaseService = new FirebaseService($firebase['db']);
    $users = $firebaseService->getAllDocuments('users');
    
    $isAuthenticated = false;
    foreach ($users as $user) {
        if (isset($user['username']) && $user['username'] === $username && 
            isset($user['password']) && $user['password'] === $password) {
            
            if (isset($user['role']) && $user['role'] === 'admin') {
                $isAuthenticated = true;
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user'] = $user;
                header("Location: index.php");
                exit();
            } else {
                $error = 'ไม่มีสิทธิ์เข้าถึง (อนุญาตเฉพาะ Admin)';
            }
            break;
        }
    }
    
    if (!$isAuthenticated && empty($error)) {
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - KMUTNB BUS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: { colors: { primary: '#ff4009', darkbg: '#121212', cardbg: '#1e1e1e' } }
            }
        }
    </script>
    <style>
        @keyframes fade-in-up {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fade-in-up 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        button[type="submit"] {
            transition: all 0.2s ease-in-out;
        }
        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 64, 9, 0.4);
        }
        button[type="submit"]:active {
            transform: translateY(1px);
        }
    </style>
</head>
<body class="bg-cover bg-center bg-no-repeat text-white font-sans flex items-center justify-center h-screen relative" style="background-image: url('assets/images/loadscreen.png');">
    <!-- Overlay filter to darken the background -->
    <div class="absolute inset-0 bg-black/60 z-0"></div>

    <!-- Added backdrop-blur to make the login box stand out on top of the image -->
    <div class="animate-fade-in-up bg-cardbg/80 backdrop-blur-md p-8 rounded-xl shadow-2xl border border-gray-700/50 w-full max-w-sm z-10">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-primary">KMUTNB BUS ADMIN</h1>
            <p class="text-gray-400 mt-2">กรุณาเข้าสู่ระบบเพื่อจัดการข้อมูล</p>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-red-900/50 border border-red-500 text-red-200 px-4 py-3 rounded-lg mb-6 text-sm">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-4">
                <label class="block text-gray-400 text-sm mb-2" for="username">ชื่อผู้ใช้งาน</label>
                <input class="w-full bg-darkbg border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-primary" type="text" id="username" name="username" required>
            </div>
            <div class="mb-6">
                <label class="block text-gray-400 text-sm mb-2" for="password">รหัสผ่าน</label>
                <input class="w-full bg-darkbg border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-primary" type="password" id="password" name="password" required>
            </div>
            <button class="w-full bg-primary hover:bg-orange-500 text-white font-bold py-2 px-4 rounded-lg transition-colors" type="submit">
                เข้าสู่ระบบ
            </button>
        </form>
    </div>
</body>
</html>
