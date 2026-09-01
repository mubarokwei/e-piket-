<?php
session_start();
require_once 'config/database.php';

// Redirect jika sudah login
if (isset($_SESSION['user_id'])) {
    redirect('admin/dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $conn = getDB();
    $stmt = $conn->prepare("SELECT id, username, password, nama_lengkap, role FROM users WHERE username = ? AND status = 'aktif'");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role'] = $user['role'];
            
            // Log aktivitas
            $stmt_log = $conn->prepare("INSERT INTO log_aktivitas (user_id, aktivitas, ip_address) VALUES (?, 'Login', ?)");
            $ip = $_SERVER['REMOTE_ADDR'];
            $stmt_log->bind_param("is", $user['id'], $ip);
            $stmt_log->execute();
            
            redirect('admin/dashboard.php');
        } else {
            $error = 'Password salah!';
        }
    } else {
        $error = 'Username tidak ditemukan!';
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - E-Piket</title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.7/dist/gsap.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow: hidden;
        }
        
        .login-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }
        
        .bg-shape {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.3;
        }
        
        .bg-shape-1 {
            width: 400px;
            height: 400px;
            background: #3b82f6;
            top: -100px;
            right: -100px;
        }
        
        .bg-shape-2 {
            width: 300px;
            height: 300px;
            background: #8b5cf6;
            bottom: -50px;
            left: -50px;
        }
        
        .bg-shape-3 {
            width: 200px;
            height: 200px;
            background: #06b6d4;
            top: 50%;
            left: 30%;
        }
        
        .login-container {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 48px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        
        .login-logo {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .login-logo-img {
            width: 96px;
            height: 96px;
            object-fit: contain;
            margin-bottom: 16px;
            filter: drop-shadow(0 10px 30px rgba(59, 130, 246, 0.35));
        }
        
        .login-logo h1 {
            color: white;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .login-logo p {
            color: rgba(255, 255, 255, 0.95);
            font-size: 14px;
        }
        
        .form-floating {
            margin-bottom: 20px;
        }
        
        .form-floating .form-control {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            color: white;
            padding: 16px 16px;
            height: 56px;
        }
        
        .form-floating .form-control:focus {
            background: rgba(255, 255, 255, 0.12);
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
            color: white;
        }
        
        .form-floating label {
            color: rgba(255, 255, 255, 0.85);
        }
        
        .form-floating .form-control:focus ~ label,
        .form-floating .form-control:not(:placeholder-shown) ~ label {
            color: #3b82f6;
        }
        
        .input-group-text {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-right: none;
            border-radius: 12px 0 0 12px;
            color: rgba(255, 255, 255, 0.85);
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 8px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(59, 130, 246, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            padding: 12px 16px;
            margin-bottom: 20px;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 24px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 13px;
        }
        
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
        }
        
        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
        }
    </style>
</head>
<body>
    <!-- Background Shapes -->
    <div class="login-bg">
        <div class="bg-shape bg-shape-1"></div>
        <div class="bg-shape bg-shape-2"></div>
        <div class="bg-shape bg-shape-3"></div>
    </div>
    
    <!-- Particles -->
    <div class="particles" id="particles"></div>
    
    <!-- Login Form -->
    <div class="login-container">
        <div class="login-card" id="loginCard">
        <div class="login-logo">
            <img src="assets/img/logo.png" alt="Logo E-Piket" class="login-logo-img">
            <h1>E-Piket</h1>
            <p>Sistem Manajemen Guru Piket</p>
        </div>
            
            <?php if ($error): ?>
                <div class="alert" id="alertError">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm" autocomplete="on">
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="username" name="username" 
                           placeholder="Username" required autofocus
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    <label for="username"><i class="bi bi-person me-2"></i>Username</label>
                </div>
                
                <div class="form-floating mb-4">
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Password" required>
                    <label for="password"><i class="bi bi-lock me-2"></i>Password</label>
                </div>
                
                <button type="submit" class="btn-login" id="btnLogin">
                    <i class="bi bi-box-arrow-in-right me-2"></i>
                    Masuk
                </button>
            </form>
            
            <div class="login-footer">
                <p>&copy; 2026 E-Piket &middot; SMK PK TGI JAKARTA</p>
            </div>
        </div>
    </div>

    <script>
        // GSAP Animations
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof gsap === 'undefined') return; // CDN GSAP tidak tersedia -> lewati animasi
            // Animate login card (hanya posisi - TANPA opacity, agar kartu
            // tidak pernah transparan walau tween terputus)
            gsap.from('#loginCard', {
                duration: 1,
                y: 50,
                ease: 'power3.out'
            });
            
            // Animate background shapes
            gsap.to('.bg-shape-1', {
                duration: 8,
                x: 50,
                y: 30,
                repeat: -1,
                yoyo: true,
                ease: 'sine.inOut'
            });
            
            gsap.to('.bg-shape-2', {
                duration: 10,
                x: -30,
                y: -50,
                repeat: -1,
                yoyo: true,
                ease: 'sine.inOut'
            });
            
            gsap.to('.bg-shape-3', {
                duration: 6,
                x: 40,
                y: -30,
                repeat: -1,
                yoyo: true,
                ease: 'sine.inOut'
            });
            
            // Create particles
            const particlesContainer = document.getElementById('particles');
            for (let i = 0; i < 50; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                particlesContainer.appendChild(particle);
                
                gsap.to(particle, {
                    duration: 3 + Math.random() * 4,
                    y: -100 + Math.random() * 200,
                    x: -50 + Math.random() * 100,
                    opacity: 0,
                    repeat: -1,
                    delay: Math.random() * 3,
                    ease: 'power1.out'
                });
            }
            
            // Animate form elements (hanya posisi - TANPA opacity)
            gsap.from('.form-floating', {
                duration: 0.6,
                x: -30,
                stagger: 0.15,
                delay: 0.5,
                ease: 'power2.out'
            });
            
            // Animasi tombol hanya menggeser posisi (y), TANPA mengubah opacity.
            // Ini memastikan tombol login TIDAK PERNAH transparan walau animasi
            // terputus (mis. GSAP error/terblokir).
            gsap.from('.btn-login', {
                duration: 0.6,
                y: 20,
                delay: 0.6,
                ease: 'power2.out'
            });
        });
        
        // Pengaman ekstra: paksa tombol login selalu terlihat setelah 1.5 detik,
        // apa pun yang terjadi dengan animasi/GSAP.
        setTimeout(function() {
            const btn = document.getElementById('btnLogin');
            if (btn) {
                btn.style.opacity = '1';
                btn.style.visibility = 'visible';
            }
        }, 1500);
        
        // Button loading state - dipasang di event submit (setelah validasi html5),
        // bukan di click, agar browser (terutama Safari) tetap mengirim form saat
        // tombol disable. Ada timeout pengaman agar tombol tidak terkunci jika
        // server tidak merespons.
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('btnLogin');
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';
            btn.disabled = true;
            setTimeout(function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-box-arrow-in-right me-2"></i>Masuk';
            }, 8000);
        });
    </script>
</body>
</html>
