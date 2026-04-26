<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connection.php';
require_once 'functions.php';
if (isLoggedIn()) {
    $user_role = getUserRole();
    $current_page = basename($_SERVER['PHP_SELF']);
    if ($user_role === 'повар') {
        if ($current_page !== 'chef_orders.php' && $current_page !== 'inventory.php') {
            redirect('chef_orders.php');
        }
    } else {
        if ($current_page !== 'dashboard.php') {
        redirect('dashboard.php');
    }
}
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = getPost('login');
    $password = $_POST['password'] ?? '';
    if (empty($login) || empty($password)) {
        $error = 'Пожалуйста, заполните все поля';
    } else {
        global $db;
        try {
            $stmt = $db->prepare("SELECT id_employee, fio, login, password_hash, position FROM employees WHERE login = ? AND is_active = 1");
            $stmt->execute([$login]);
            $user = $stmt->fetch();
            if ($user && $password === $user['password_hash']) {
                $_SESSION['user_id'] = $user['id_employee'];
                $_SESSION['user_name'] = $user['fio'];
                $_SESSION['role'] = $user['position'];
                $stmt = $db->prepare("UPDATE employees SET last_login = NOW() WHERE id_employee = ?");
                $stmt->execute([$user['id_employee']]);
                if ($user['position'] === 'повар') {
                    redirect('chef_orders.php');
                } else {
                redirect('dashboard.php');
                }
            } else {
                $error = 'Неверный логин или пароль';
            }
        } catch (Exception $e) {
            $error = 'Ошибка при авторизации: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в систему - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .login-body {
            padding: 30px;
        }
        
        .logo-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <div class="logo-icon">
                <i class="bi bi-cup-hot"></i>
            </div>
            <h1><?php echo SITE_NAME; ?></h1>
        </div>
        
        <div class="login-body">
            <h3 class="text-center mb-4">Вход в систему</h3>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="login" class="form-label">Логин</label>
                    <input type="text" class="form-control" id="login" name="login" required autofocus>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Пароль</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-box-arrow-in-right"></i> Войти
                    </button>
                </div>
            </form>
            
            <div class="mt-4 text-center">
                <p class="text-muted">Тестовые данные:</p>
                <p><strong>admin</strong> / admin123 (Администратор)</p>
                <p><strong>waiter1</strong> / admin123 (Официант)</p>
                <p><strong>manager</strong> / admin123 (Менеджер)</p>
                <p><strong>cook1</strong> / admin123 (Повар)</p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>