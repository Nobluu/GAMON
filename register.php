<?php
require_once 'controllers/Auth.php';

$auth = new Auth();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!empty($name) && !empty($email) && !empty($password)) {
        $result = $auth->register($name, $email, $password);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    } else {
        $message = 'Semua kolom harus diisi.';
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - GAMON</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #fff7f3 0%, #fef4f1 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .register-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 3rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(242, 92, 92, 0.1);
            border: 1px solid rgba(242, 92, 92, 0.1);
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo-icon {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }

        .logo-img {
            width: 120px;
            height: 120px;
            object-fit: contain;
            margin-bottom: 1rem;
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .logo-subtitle {
            color: #6b7280;
            font-size: 0.9rem;
        }

        .welcome-text {
            text-align: center;
            margin-bottom: 2rem;
        }

        .welcome-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .welcome-subtitle {
            color: #6b7280;
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .form-input {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid rgba(242, 92, 92, 0.1);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }

        .form-input:focus {
            outline: none;
            border-color: #f25c5c;
            box-shadow: 0 0 0 3px rgba(242, 92, 92, 0.1);
            background: white;
        }

        .btn-primary {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #f25c5c, #ff7b7b);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(242, 92, 92, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(242, 92, 92, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 12px;
            text-align: center;
            font-size: 0.9rem;
        }

        .alert.success {
            background: rgba(34, 197, 94, 0.1);
            color: #16a34a;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }

        .alert.error {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .auth-link {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(242, 92, 92, 0.1);
        }

        .auth-link a {
            color: #f25c5c;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .auth-link a:hover {
            color: #e04d4d;
        }

        @media (max-width: 480px) {
            .register-container {
                padding: 2rem 1.5rem;
                margin: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <img src="logo_gamon.png" alt="GAMON" class="logo-img">
            <div class="logo-subtitle">Aplikasi Pesan Kapsul Waktu</div>
        </div>

        <div class="welcome-text">
            <h1 class="welcome-title">Buat Akun</h1>
            <p class="welcome-subtitle">Bergabung dengan GAMON untuk membuat kapsul waktu Anda</p>
        </div>
        <?php if ($message): ?>
            <div class="alert <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" name="name" class="form-input" required placeholder="Masukkan nama lengkap Anda" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Alamat Email</label>
                <input type="email" name="email" class="form-input" required placeholder="Masukkan email Anda" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Kata Sandi</label>
                <input type="password" name="password" class="form-input" required placeholder="Buat kata sandi yang aman">
            </div>

            <button type="submit" class="btn-primary">
                Buat Akun
            </button>
        </form>

        <div class="auth-link">
            Sudah punya akun? <a href="login.php">Masuk di sini</a>
        </div>
    </div>
</body>
</html>
