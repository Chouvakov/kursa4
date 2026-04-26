<?php
require_once 'db_connection.php';
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}
function getUserName() {
    return $_SESSION['user_name'] ?? 'Гость';
}
function getUserRole() {
    return $_SESSION['role'] ?? null;
}
function checkRole($role) {
    if (!isLoggedIn()) return false;
    return getUserRole() === $role;
}
function checkAccess($required_role = null) {
    if (!isLoggedIn()) {
        redirect('index.php');
        exit();
    }
    if ($required_role && getUserRole() !== $required_role) {
        setFlashMessage('error', 'Недостаточно прав для доступа к этой странице');
        redirect('dashboard.php');
        exit();
    }
}
function redirect($url) {
    if (headers_sent()) {
        echo "<script>window.location.href = '$url';</script>";
        echo "<noscript><meta http-equiv='refresh' content='0;url=$url'></noscript>";
        exit();
    } else {
        header("Location: " . $url);
        exit();
    }
}
function formatDate($date, $format = 'd.m.Y H:i') {
    if (empty($date) || $date == '0000-00-00 00:00:00') return '';
    return date($format, strtotime($date));
}
function formatMoney($amount) {
    return number_format($amount, 2, '.', ' ') . ' ₽';
}
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}
function showFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $alert_class = [
            'success' => 'alert-success',
            'error'   => 'alert-danger',
            'warning' => 'alert-warning',
            'info'    => 'alert-info'
        ][$flash['type']] ?? 'alert-info';
        return '<div class="alert ' . $alert_class . ' alert-dismissible fade show" role="alert">
                  ' . htmlspecialchars($flash['message']) . '
                  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>';
    }
    return '';
}
function getPost($key, $default = '') {
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}
function getGet($key, $default = '') {
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}
function getShortName($fullName) {
    $parts = explode(' ', $fullName);
    if (count($parts) >= 2) {
        return $parts[0] . ' ' . mb_substr($parts[1], 0, 1) . '.';
    }
    return $fullName;
}
function getOrderStatusName($status) {
    $statuses = [
        'принят'    => 'Принят',
        'готовится' => 'Готовится',
        'готов'     => 'Готов',
        'подан'     => 'Подан',
        'оплачен'   => 'Оплачен',
        'отменен'   => 'Отменен'
    ];
    return $statuses[$status] ?? $status;
}
function getOrderStatusClass($status) {
    $classes = [
        'принят'    => 'bg-primary',
        'готовится' => 'bg-warning text-dark',
        'готов'     => 'bg-info text-dark',
        'подан'     => 'bg-secondary',
        'оплачен'   => 'bg-success',
        'отменен'   => 'bg-danger'
    ];
    return $classes[$status] ?? 'bg-secondary';
}
function getTableStatusClass($status) {
    $classes = [
        'свободен' => 'bg-success',
        'занят'    => 'bg-danger',
        'забронирован' => 'bg-warning text-dark',
        'недоступен' => 'bg-secondary'
    ];
    return $classes[$status] ?? 'bg-secondary';
}
function getTableStatusName($status) {
    $statuses = [
        'свободен' => 'Свободен',
        'занят'    => 'Занят',
        'забронирован' => 'Забронирован',
        'недоступен' => 'Недоступен'
    ];
    return $statuses[$status] ?? $status;
}
function getSupplyStatusName($status) {
    $statuses = [
        'ожидается' => 'Ожидается',
        'в пути'    => 'В пути',
        'доставлено' => 'Доставлено',
        'отменено'  => 'Отменено'
    ];
    return $statuses[$status] ?? $status;
}
function getSupplyStatusClass($status) {
    $classes = [
        'ожидается' => 'bg-info',
        'в пути'    => 'bg-warning text-dark',
        'доставлено' => 'bg-success',
        'отменено'  => 'bg-danger'
    ];
    return $classes[$status] ?? 'bg-secondary';
}
function getPaymentMethodName($method) {
    $methods = [
        'наличные' => 'Наличные',
        'карта'    => 'Банковская карта',
        'онлайн'   => 'Онлайн-оплата'
    ];
    return $methods[$method] ?? $method;
}
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
function escape($value) {
    global $pdo;
    if ($pdo) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
function generatePassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
}
function getDashboardStats() {
    global $pdo;
    $stats = [];
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total_sales FROM orders WHERE DATE(order_date) = CURDATE() AND status = 'оплачен'");
        $stmt->execute();
        $stats['total_sales'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_sales'];
        $stmt = $pdo->prepare("SELECT COUNT(*) as orders_today FROM orders WHERE DATE(order_date) = CURDATE()");
        $stmt->execute();
        $stats['orders_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['orders_today'];
        $stmt = $pdo->prepare("SELECT COUNT(*) as active_tables FROM tables WHERE status = 'занят'");
        $stmt->execute();
        $stats['active_tables'] = $stmt->fetch(PDO::FETCH_ASSOC)['active_tables'];
        $stmt = $pdo->prepare("SELECT COALESCE(AVG(total_amount), 0) as avg_check FROM orders WHERE DATE(order_date) = CURDATE() AND status = 'оплачен'");
        $stmt->execute();
        $stats['avg_check'] = $stmt->fetch(PDO::FETCH_ASSOC)['avg_check'];
        $stmt = $pdo->prepare("SELECT COUNT(*) as supplies_today, COALESCE(SUM(total_cost), 0) as supplies_total FROM supplies WHERE DATE(supply_date) = CURDATE()");
        $stmt->execute();
        $supply_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['supplies_today'] = $supply_stats['supplies_today'];
        $stats['supplies_total'] = $supply_stats['supplies_total'];
        $stats['pending_supplies'] = 0;
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_ingredients, COUNT(CASE WHEN current_quantity < min_quantity THEN 1 END) as low_stock_count FROM ingredients");
        $stmt->execute();
        $inventory_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_ingredients'] = $inventory_stats['total_ingredients'];
        $stats['low_stock_count'] = $inventory_stats['low_stock_count'];
    } catch (PDOException $e) {
        error_log("Ошибка получения статистики: " . $e->getMessage());
        $stats = [
            'total_sales' => 0,
            'orders_today' => 0,
            'active_tables' => 0,
            'avg_check' => 0,
            'supplies_today' => 0,
            'supplies_total' => 0,
            'pending_supplies' => 0,
            'total_ingredients' => 0,
            'low_stock_count' => 0
        ];
    }
    return $stats;
}
function formatNumber($number, $decimals = 0) {
    return number_format($number, $decimals, '.', ' ');
}
function canEditSupply($supply_status) {
    return true;
}
function getSupplyTotal($supply_id) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT COALESCE(SUM(quantity * unit_price), 0) as total FROM supply_details WHERE id_supply = ?");
        $stmt->execute([$supply_id]);
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Ошибка получения суммы поставки: " . $e->getMessage());
        return 0;
    }
}
function updateSupplyTotal($supply_id) {
    global $db;
    try {
        $total = getSupplyTotal($supply_id);
        $stmt = $db->prepare("UPDATE supplies SET total_cost = ? WHERE id_supply = ?");
        $stmt->execute([$total, $supply_id]);
        return true;
    } catch (Exception $e) {
        error_log("Ошибка обновления суммы поставки: " . $e->getMessage());
        return false;
    }
}
function getSuppliersList() {
    global $db;
    try {
        $stmt = $db->query("SELECT DISTINCT supplier_name FROM supplies ORDER BY supplier_name");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Ошибка получения списка поставщиков: " . $e->getMessage());
        return [];
    }
}
?>
