<?php
require_once 'functions.php';
if (!isset($page_title)) {
    $page_title = 'Информационная система для управления кафе';
}
function canAccessOrders($role) {
    return in_array($role, ['официант', 'администратор', 'менеджер']);
}

function canAccessTables($role) {
    return in_array($role, ['официант', 'администратор', 'менеджер']);
}

function canAccessClients($role) {
    return in_array($role, ['официант', 'администратор', 'менеджер']);
}

function canAccessMenu($role) {
    return in_array($role, ['администратор', 'менеджер']);
}

function canAccessInventory($role) {
    return in_array($role, ['повар', 'администратор', 'менеджер']);
}

function canAccessSupplies($role) {
    return in_array($role, ['администратор', 'менеджер']);
}

function canAccessEmployees($role) {
    return in_array($role, ['администратор', 'менеджер']);
}

function canAccessSchedule($role) {
    return in_array($role, ['администратор', 'менеджер']);
}

function canAccessFeedback($role) {
    return in_array($role, ['администратор', 'менеджер', 'официант']);
}

function canAccessReports($role) {
    return in_array($role, ['администратор', 'менеджер']);
}

$user_role = getUserRole();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }
        .navbar-brand {
            font-weight: bold;
            font-size: 1.2rem;
        }
        .sidebar {
            min-height: calc(100vh - 56px);
            background: #fff;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 56px;
        }
        .sidebar .nav-link {
            color: #333;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 5px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateX(5px);
        }
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: bold;
        }
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        .card {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: none;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 15px 20px;
        }
        .stat-card {
            border-radius: 12px;
            padding: 20px;
            color: white;
            text-align: center;
            height: 100%;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .table th {
            background-color: #f1f3f9;
            font-weight: 600;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #68418f 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo ($user_role === 'повар') ? 'chef_orders.php' : 'dashboard.php'; ?>">
                <i class="bi bi-cup-hot-fill me-2"></i> <?php echo SITE_NAME; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo ($user_role === 'повар') ? 'chef_orders.php' : 'dashboard.php'; ?>">
                            <i class="bi bi-speedometer2"></i> Главная
                        </a>
                    </li>
                </ul>
                
                <div class="navbar-nav">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-2"></i>
                            <div class="d-flex flex-column">
                                <span><?php echo getShortName(getUserName()); ?></span>
                                <small class="text-light"><?php echo getUserRole(); ?></small>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#">
                                <i class="bi bi-person me-2"></i> Профиль
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i> Выйти
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2 col-lg-2 d-none d-md-block sidebar">
                <div class="pt-4">
                    <ul class="nav flex-column">
                        <?php if (canAccessOrders($user_role)): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>" 
                               href="orders.php">
                                <i class="bi bi-cart"></i> Заказы
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ($user_role === 'повар'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'chef_orders.php' ? 'active' : ''; ?>" 
                               href="chef_orders.php">
                                <i class="bi bi-egg-fried"></i> Кухня
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (canAccessTables($user_role)): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'tables.php' ? 'active' : ''; ?>" 
                               href="tables.php">
                                <i class="bi bi-table"></i> Столики
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (canAccessClients($user_role)): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'clients.php' ? 'active' : ''; ?>" 
                               href="clients.php">
                                <i class="bi bi-people"></i> Клиенты
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (canAccessMenu($user_role)): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'menu.php' ? 'active' : ''; ?>" 
                               href="menu.php">
                                <i class="bi bi-menu-button-wide"></i> Меню
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (canAccessInventory($user_role)): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : ''; ?>" 
                               href="inventory.php">
                                <i class="bi bi-box-seam"></i> Инвентарь
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (canAccessSupplies($user_role)): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'supplies.php' ? 'active' : ''; ?>" 
                               href="supplies.php">
                                <i class="bi bi-truck"></i> Поставки
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (canAccessEmployees($user_role)): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'employees.php' ? 'active' : ''; ?>" 
                               href="employees.php">
                                <i class="bi bi-person-badge"></i> Сотрудники
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (canAccessSchedule($user_role)): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'schedule.php' ? 'active' : ''; ?>" 
                               href="schedule.php">
                                <i class="bi bi-calendar-week"></i> Расписание
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (canAccessFeedback($user_role)): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'feedback.php' ? 'active' : ''; ?>" 
                               href="feedback.php">
                                <i class="bi bi-chat-left-text"></i> Отзывы
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (canAccessReports($user_role)): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" 
                               href="reports.php">
                                <i class="bi bi-graph-up"></i> Отчеты
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <div class="col-md-10 col-lg-10 pt-4">
                <?php echo showFlashMessage(); ?>