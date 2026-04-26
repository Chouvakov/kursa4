<?php
require_once 'db_connection.php';
require_once 'functions.php';
if (!isLoggedIn()) {
    redirect('index.php');
}
if (basename($_SERVER['PHP_SELF']) === 'index.php') {
    redirect('dashboard.php');
}
$user_id = getUserId();
$user_name = getUserName();
$user_role = getUserRole();
if ($user_role === 'повар') {
    redirect('chef_orders.php');
}
global $db;
$orders_today = $db->query("
    SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue 
    FROM orders 
    WHERE DATE(order_datetime) = CURDATE() 
    AND status = 'оплачен'
")->fetch();

$active_orders = $db->query("
    SELECT COUNT(*) as count 
    FROM orders 
    WHERE status IN ('принят', 'готовится', 'готов', 'подан')
")->fetchColumn();

$tables_free = $db->query("
    SELECT COUNT(*) as count 
    FROM tables 
    WHERE status = 'свободен'
")->fetchColumn();

try {
    $table_exists = $db->query("SHOW TABLES LIKE 'supplies'")->fetch();
    if ($table_exists) {
        $columns = $db->query("SHOW COLUMNS FROM supplies")->fetchAll(PDO::FETCH_COLUMN);
        $has_total_cost = in_array('total_cost', $columns);
        $has_total_amount = in_array('total_amount', $columns);
        if ($has_total_cost) {
            $sum_field = 'total_cost';
        } elseif ($has_total_amount) {
            $sum_field = 'total_amount';
        } else {
            $sum_field = 'total_cost';
        }
        $details_table_exists = $db->query("SHOW TABLES LIKE 'supply_details'")->fetch();
        if ($details_table_exists) {
            $sql = "
                SELECT 
                    COUNT(DISTINCT s.id_supply) as count,
                    COALESCE(SUM(sd.quantity * sd.unit_price), 0) as total
                FROM supplies s
                LEFT JOIN supply_details sd ON sd.id_supply = s.id_supply
                WHERE s.supply_date >= CURDATE()
                  AND s.supply_date < (CURDATE() + INTERVAL 1 DAY)
            ";
            $result = $db->query($sql);
            $supplies_today = $result->fetch(PDO::FETCH_ASSOC);
            if (!$supplies_today) {
                $supplies_today = ['count' => 0, 'total' => 0];
            } else {
                $supplies_today['total'] = floatval($supplies_today['total'] ?? 0);
                $supplies_today['count'] = intval($supplies_today['count'] ?? 0);
            }
        } else {
            $allowed_fields = ['total_cost', 'total_amount'];
            if (!in_array($sum_field, $allowed_fields)) {
                $sum_field = 'total_cost';
            }
            $sql = "SELECT COUNT(*) as count, COALESCE(SUM(CAST(`$sum_field` AS DECIMAL(10,2))), 0) as total 
            FROM supplies 
                    WHERE supply_date >= CURDATE()
                      AND supply_date < (CURDATE() + INTERVAL 1 DAY)";
            $result = $db->query($sql);
            $supplies_today = $result->fetch(PDO::FETCH_ASSOC) ?: ['count' => 0, 'total' => 0];
            $supplies_today['total'] = floatval($supplies_today['total'] ?? 0);
            $supplies_today['count'] = intval($supplies_today['count'] ?? 0);
        }
        
        $pending_supplies = 0;
        $has_status_column = false;
        $details_table_exists = $db->query("SHOW TABLES LIKE 'supply_details'")->fetch();
        if ($details_table_exists) {
            $recent_supplies = $db->query("
                SELECT 
                    s.id_supply, 
                    s.supply_date, 
                    s.supplier_name,
                    COALESCE(SUM(sd.quantity * sd.unit_price), 0) as total_sum,
                    s.received_by as received_by_name
                FROM supplies s
                LEFT JOIN supply_details sd ON sd.id_supply = s.id_supply
                GROUP BY s.id_supply, s.supply_date, s.supplier_name, s.received_by
                ORDER BY s.supply_date DESC, s.id_supply DESC
                LIMIT 5
            ")->fetchAll();
        } else {
        $recent_supplies = $db->query("
            SELECT s.id_supply, s.supply_date, s.supplier_name, 
                       CAST(COALESCE(s.total_cost, 0) AS DECIMAL(10,2)) as total_sum,
                       s.received_by as received_by_name
            FROM supplies s
            ORDER BY s.supply_date DESC, s.id_supply DESC
            LIMIT 5
        ")->fetchAll();
        }
        
        foreach ($recent_supplies as &$supply) {
            $supply['total_sum'] = floatval($supply['total_sum'] ?? 0);
            $supply['total_amount'] = $supply['total_sum'];
            if (!isset($supply['received_by_name']) || empty($supply['received_by_name'])) {
                $supply['received_by_name'] = 'Не указан';
            }
        }
        unset($supply);
    } else {
        $supplies_today = ['count' => 0, 'total' => 0];
        $pending_supplies = 0;
        $recent_supplies = [];
        $has_status_column = false;
    }
} catch (Exception $e) {
    $supplies_today = ['count' => 0, 'total' => 0];
    $pending_supplies = 0;
    $recent_supplies = [];
    $has_status_column = false;
}
$low_stock_count = $db->query("
    SELECT COUNT(*) as count 
    FROM ingredients 
    WHERE current_quantity < min_quantity
")->fetchColumn();

$recent_orders = $db->query("
    SELECT o.id_order, o.order_datetime, t.table_number, 
           o.total_amount, o.status, e.fio as waiter
    FROM orders o
    LEFT JOIN tables t ON o.id_table = t.id_table
    LEFT JOIN employees e ON o.id_employee = e.id_employee
    ORDER BY o.order_datetime DESC 
    LIMIT 5
")->fetchAll();

$page_title = 'Панель управления';
include 'header.php';
?>
    <style>
        .stat-card {
            border-radius: 10px;
            padding: 20px;
            color: white;
            text-align: center;
            height: 100%;
        }
        .stat-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .stat-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        .stat-warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
        }
        .stat-info {
            background: linear-gradient(135deg, #17a2b8 0%, #0dcaf0 100%);
        }
        .stat-danger {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
        }
    </style>
    <div class="container-fluid mt-4">
        <?php echo showFlashMessage(); ?>
        <div class="row mb-4">
            <?php if ($user_role !== 'повар'): ?>
            <div class="col-md-3 mb-3">
                <div class="stat-card stat-primary">
                    <h5>Заказов сегодня</h5>
                    <h1 class="display-4"><?php echo $orders_today['count'] ?? 0; ?></h1>
                    <p>Выручка: <strong><?php echo number_format($orders_today['revenue'] ?? 0, 2); ?> ₽</strong></p>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stat-card stat-warning">
                    <h5>Активных заказов</h5>
                    <h1 class="display-4"><?php echo $active_orders; ?></h1>
                    <p>Требуют внимания</p>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stat-card stat-success">
                    <h5>Свободных столиков</h5>
                    <h1 class="display-4"><?php echo $tables_free; ?></h1>
                    <a href="tables.php" class="btn btn-light btn-sm mt-2">Посмотреть</a>
                </div>
            </div>
            
    
            <?php if (in_array($user_role, ['менеджер', 'администратор'])): ?>
            <div class="col-md-3 mb-3">
                <div class="stat-card stat-info">
                    <h5>Сегодняшние поставки</h5>
                    <h1 class="display-4"><?php echo $supplies_today['count'] ?? 0; ?></h1>
                    <p>На сумму: <strong><?php echo number_format($supplies_today['total'] ?? 0, 2); ?> ₽</strong></p>
                </div>
            </div>
            <?php endif; ?>
            <?php else: ?>
          
            <div class="col-md-6 mb-3">
                <div class="stat-card stat-warning">
                    <h5>Низкий запас</h5>
                    <h1 class="display-4"><?php echo $low_stock_count; ?></h1>
                    <a href="inventory.php?low_stock=1" class="btn btn-light btn-sm mt-2">Проверить</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
     
        <?php if (in_array($user_role, ['менеджер', 'администратор'])): ?>
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="stat-card stat-warning">
                    <h5>Низкий запас ингредиентов</h5>
                    <h1 class="display-4"><?php echo $low_stock_count; ?></h1>
                    <a href="inventory.php?low_stock=1" class="btn btn-light btn-sm mt-2">Требуют закупки</a>
                </div>
                </div>
            </div>
            <?php endif; ?>
        
      
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Быстрые действия</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if ($user_role !== 'повар'): ?>
                    <div class="col-md-2 mb-2">
                        <a href="orders.php?action=new" class="btn btn-success w-100">
                            <i class="bi bi-plus-circle"></i> Новый заказ
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if ($user_role !== 'повар'): ?>
                    <div class="col-md-2 mb-2">
                        <a href="orders.php" class="btn btn-primary w-100">
                            <i class="bi bi-list"></i> Все заказы
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if ($user_role === 'повар'): ?>
                    <div class="col-md-2 mb-2">
                        <a href="chef_orders.php" class="btn btn-danger w-100">
                            <i class="bi bi-egg-fried"></i> Кухня
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if ($user_role !== 'повар'): ?>
                    <div class="col-md-2 mb-2">
                        <a href="tables.php" class="btn btn-info w-100">
                            <i class="bi bi-table"></i> Столики
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array($user_role, ['менеджер', 'администратор'])): ?>
                    <div class="col-md-2 mb-2">
                        <a href="menu.php" class="btn btn-secondary w-100">
                            <i class="bi bi-menu-button"></i> Меню
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array($user_role, ['повар', 'менеджер', 'администратор'])): ?>
                    <div class="col-md-2 mb-2">
                        <a href="inventory.php" class="btn btn-warning w-100">
                            <i class="bi bi-box-seam"></i> Инвентарь
                        </a>
                    </div>
                    <?php endif; ?>
                 
                    <?php if (in_array($user_role, ['менеджер', 'администратор'])): ?>
                    <div class="col-md-2 mb-2">
                        <a href="supplies.php?action=add" class="btn btn-danger w-100">
                            <i class="bi bi-truck"></i> Новая поставка
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
 
        <?php if ($user_role !== 'повар'): ?>
        <div class="row mt-4">
            <div class="<?php echo in_array($user_role, ['менеджер', 'администратор']) ? 'col-md-6' : 'col-md-12'; ?>">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Последние заказы</h5>
                        <a href="orders.php" class="btn btn-sm btn-outline-primary">Все заказы</a>
                    </div>
                    <div class="card-body">
                        <?php if (count($recent_orders) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>№</th>
                                            <th>Стол</th>
                                            <th>Сумма</th>
                                            <th>Статус</th>
                                            <th>Время</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td>#<?php echo $order['id_order']; ?></td>
                                            <td><?php echo $order['table_number']; ?></td>
                                            <td><?php echo number_format($order['total_amount'], 2); ?> ₽</td>
                                            <td>
                                                <span class="badge <?php echo getOrderStatusClass($order['status']); ?>">
                                                    <?php echo getOrderStatusName($order['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatDate($order['order_datetime'], 'H:i'); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-inbox" style="font-size: 48px;"></i>
                                <p class="mt-2">Нет заказов</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
           
            <?php if (in_array($user_role, ['менеджер', 'администратор'])): ?>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Последние поставки</h5>
                        <a href="supplies.php" class="btn btn-sm btn-outline-primary">Все поставки</a>
                    </div>
                    <div class="card-body">
                        <?php if (count($recent_supplies) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>№</th>
                                            <th>Поставщик</th>
                                            <th>Сумма</th>
                                            <th>Принял</th>
                                            <th>Дата</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_supplies as $supply): ?>
                                        <tr>
                                            <td>#<?php echo $supply['id_supply']; ?></td>
                                            <td><?php echo htmlspecialchars($supply['supplier_name']); ?></td>
                                            <td><?php echo number_format($supply['total_amount'], 2); ?> ₽</td>
                                            <td><?php echo htmlspecialchars($supply['received_by_name'] ?? 'Не указан'); ?></td>
                                            <td><?php echo formatDate($supply['supply_date'], 'd.m.Y'); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-truck" style="font-size: 48px;"></i>
                                <p class="mt-2">Нет поставок</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
<?php
include 'footer.php';
?>