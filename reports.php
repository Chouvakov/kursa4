<?php
require_once 'db_connection.php';
require_once 'functions.php';
if (!isLoggedIn()) {
    redirect('index.php');
}
$user_role = getUserRole();
if ($user_role !== 'администратор' && $user_role !== 'менеджер') {
    setFlashMessage('error', 'Доступ запрещен');
    redirect('dashboard.php');
}
$report_type = getGet('type', 'sales');
$date_from = getGet('date_from', date('Y-m-01'));
$date_to = getGet('date_to', date('Y-m-d'));
$category_id = getGet('category_id', '');
$supplier_filter = getGet('supplier', '');
$status_filter = getGet('status', '');

try {
    global $db;
    $categories = $db->query("SELECT * FROM dish_categories ORDER BY category_name")->fetchAll();
    $suppliers = $db->query("SELECT DISTINCT supplier_name FROM supplies ORDER BY supplier_name")->fetchAll();
    $report_title = '';
    $report_data = [];
    switch ($report_type) {
        case 'sales':
            $report_title = 'Отчет по продажам';
            $stmt = $db->prepare("
                SELECT 
                    COUNT(DISTINCT id_order) as total_orders,
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as avg_order
                FROM orders 
                WHERE status = 'оплачен' 
                AND DATE(order_datetime) BETWEEN ? AND ?
            ");
            $stmt->execute([$date_from, $date_to]);
            $report_data['summary'] = $stmt->fetch();
            $stmt = $db->prepare("
                SELECT 
                    DATE(order_datetime) as date,
                    COUNT(*) as orders_count,
                    SUM(total_amount) as daily_revenue
                FROM orders 
                WHERE status = 'оплачен' 
                AND DATE(order_datetime) BETWEEN ? AND ?
                GROUP BY DATE(order_datetime)
                ORDER BY date
            ");
            $stmt->execute([$date_from, $date_to]);
            $report_data['daily'] = $stmt->fetchAll();
            break;
            
        case 'popular':
            $report_title = 'Популярные блюда';
            
            $sql = "
                SELECT 
                    d.dish_name,
                    dc.category_name,
                    COUNT(oi.id_order_item) as times_ordered,
                    SUM(oi.quantity) as total_quantity
                FROM order_items oi
                INNER JOIN dishes d ON oi.id_dish = d.id_dish
                LEFT JOIN dish_categories dc ON d.id_category = dc.id_category
                INNER JOIN orders o ON oi.id_order = o.id_order
                WHERE o.status = 'оплачен'
                AND DATE(o.order_datetime) BETWEEN ? AND ?
            ";
            
            $params = [$date_from, $date_to];
            if ($category_id) {
                $sql .= " AND d.id_category = ?";
                $params[] = $category_id;
            }
            
            $sql .= " GROUP BY d.id_dish ORDER BY times_ordered DESC LIMIT 20";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $report_data['dishes'] = $stmt->fetchAll();
            break;
            
        case 'inventory':
            $report_title = 'Отчет по инвентарю';
            
            $stmt = $db->prepare("
                SELECT 
                    ingredient_name,
                    unit,
                    current_quantity,
                    min_quantity
                FROM ingredients
                ORDER BY ingredient_name
            ");
            $stmt->execute();
            $report_data['inventory'] = $stmt->fetchAll();
            break;
        case 'supplies':
            $report_title = 'Отчет по поставкам';
            $stmt = $db->prepare("
                SELECT 
                    COUNT(DISTINCT s.id_supply) as total_supplies,
                    COUNT(sd.id) as total_items,
                    SUM(sd.total_price) as total_cost,
                    AVG(s.total_amount) as avg_supply
                FROM supplies s
                LEFT JOIN supply_details sd ON s.id_supply = sd.id_supply
                WHERE DATE(s.supply_date) BETWEEN ? AND ?
            ");
            $stmt->execute([$date_from, $date_to]);
            $report_data['summary'] = $stmt->fetch();
            $sql = "
                SELECT 
                    supplier_name,
                    COUNT(*) as supplies_count,
                    SUM(total_amount) as total_cost,
                    AVG(total_amount) as avg_cost
                FROM supplies 
                WHERE DATE(supply_date) BETWEEN ? AND ?
            ";
            
            $params = [$date_from, $date_to];
            if ($supplier_filter) {
                $sql .= " AND supplier_name LIKE ?";
                $params[] = "%$supplier_filter%";
            }
            
            $sql .= " GROUP BY supplier_name ORDER BY total_cost DESC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $report_data['suppliers'] = $stmt->fetchAll();
            $stmt = $db->prepare("
                SELECT 
                    DATE_FORMAT(supply_date, '%Y-%m') as month,
                    COUNT(*) as supplies_count,
                    SUM(total_amount) as monthly_total
                FROM supplies 
                WHERE DATE(supply_date) BETWEEN ? AND ?
                GROUP BY DATE_FORMAT(supply_date, '%Y-%m')
                ORDER BY month
            ");
            $stmt->execute([$date_from, $date_to]);
            $report_data['monthly'] = $stmt->fetchAll();
            $stmt = $db->prepare("
                SELECT 
                    status,
                    COUNT(*) as count,
                    SUM(total_amount) as total_amount
                FROM supplies 
                WHERE DATE(supply_date) BETWEEN ? AND ?
                GROUP BY status
                ORDER BY count DESC
            ");
            $stmt->execute([$date_from, $date_to]);
            $report_data['statuses'] = $stmt->fetchAll();
            break;
        case 'ingredient_usage':
            $report_title = 'Расход ингредиентов';
            
            $stmt = $db->prepare("
                SELECT 
                    i.ingredient_name,
                    i.unit,
                    SUM(oi.quantity * di.quantity_needed) as total_used,
                    i.current_quantity,
                    i.min_quantity
                FROM order_items oi
                INNER JOIN orders o ON oi.id_order = o.id_order
                INNER JOIN dish_ingredients di ON oi.id_dish = di.id_dish
                INNER JOIN ingredients i ON di.id_ingredient = i.id_ingredient
                WHERE o.status = 'оплачен'
                AND DATE(o.order_datetime) BETWEEN ? AND ?
                GROUP BY i.id_ingredient
                ORDER BY total_used DESC
            ");
            $stmt->execute([$date_from, $date_to]);
            $report_data['usage'] = $stmt->fetchAll();
            break;
    }
    
} catch (Exception $e) {
    setFlashMessage('error', 'Ошибка: ' . $e->getMessage());
}
$page_title = 'Отчеты';
include 'header.php';
?>

<div class="row">
    <div class="col-md-3">
        <div class="card">
            <div class="card-header">
                <h5>Параметры отчета</h5>
            </div>
            <div class="card-body">
                <form method="GET">
                    <div class="mb-3">
                        <label class="form-label">Тип отчета</label>
                        <select class="form-select" name="type" onchange="this.form.submit()">
                            <option value="sales" <?php echo $report_type == 'sales' ? 'selected' : ''; ?>>Продажи</option>
                            <option value="popular" <?php echo $report_type == 'popular' ? 'selected' : ''; ?>>Популярные блюда</option>
                            <option value="inventory" <?php echo $report_type == 'inventory' ? 'selected' : ''; ?>>Инвентарь</option>
                            <option value="supplies" <?php echo $report_type == 'supplies' ? 'selected' : ''; ?>>Поставки</option>
                            <option value="ingredient_usage" <?php echo $report_type == 'ingredient_usage' ? 'selected' : ''; ?>>Расход ингредиентов</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Период с</label>
                        <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">по</label>
                        <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                    </div>
                    
                    <?php if ($report_type == 'popular'): ?>
                    <div class="mb-3">
                        <label class="form-label">Категория</label>
                        <select class="form-select" name="category_id">
                            <option value="">Все категории</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id_category']; ?>" <?php echo $category_id == $cat['id_category'] ? 'selected' : ''; ?>>
                                <?php echo $cat['category_name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($report_type == 'supplies'): ?>
                    <div class="mb-3">
                        <label class="form-label">Поставщик</label>
                        <select class="form-select" name="supplier">
                            <option value="">Все поставщики</option>
                            <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo $supplier['supplier_name']; ?>" <?php echo $supplier_filter == $supplier['supplier_name'] ? 'selected' : ''; ?>>
                                <?php echo $supplier['supplier_name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Статус</label>
                        <select class="form-select" name="status">
                            <option value="">Все статусы</option>
                            <option value="ожидается" <?php echo $status_filter == 'ожидается' ? 'selected' : ''; ?>>Ожидается</option>
                            <option value="в пути" <?php echo $status_filter == 'в пути' ? 'selected' : ''; ?>>В пути</option>
                            <option value="доставлено" <?php echo $status_filter == 'доставлено' ? 'selected' : ''; ?>>Доставлено</option>
                            <option value="отменено" <?php echo $status_filter == 'отменено' ? 'selected' : ''; ?>>Отменено</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-filter"></i> Сформировать
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-9">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?php echo $report_title; ?></h5>
                <button onclick="window.print()" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-printer me-2"></i>Печать
                </button>
            </div>
            <div class="card-body" id="report-content">
                <p class="text-muted">
                    Период: <?php echo formatDate($date_from); ?> - <?php echo formatDate($date_to); ?>
                </p>
                
                <?php if ($report_type == 'sales'): ?>
                    <?php if (!empty($report_data['summary'])): ?>
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h6>Заказов</h6>
                                    <h3><?php echo $report_data['summary']['total_orders']; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h6>Выручка</h6>
                                    <h3><?php echo formatMoney($report_data['summary']['total_revenue']); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h6>Средний чек</h6>
                                    <h3><?php echo formatMoney($report_data['summary']['avg_order']); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($report_data['daily'])): ?>
                    <h6>Продажи по дням:</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Дата</th>
                                    <th>Заказов</th>
                                    <th>Выручка</th>
                                    <th>Средний чек</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data['daily'] as $day): ?>
                                <tr>
                                    <td><?php echo formatDate($day['date']); ?></td>
                                    <td><?php echo $day['orders_count']; ?></td>
                                    <td><?php echo formatMoney($day['daily_revenue']); ?></td>
                                    <td><?php echo formatMoney($day['daily_revenue'] / $day['orders_count']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                <?php elseif ($report_type == 'popular'): ?>
                    <?php if (!empty($report_data['dishes'])): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Блюдо</th>
                                    <th>Категория</th>
                                    <th>Раз продано</th>
                                    <th>Количество</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data['dishes'] as $dish): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dish['dish_name']); ?></td>
                                    <td><?php echo htmlspecialchars($dish['category_name'] ?? '—'); ?></td>
                                    <td><?php echo $dish['times_ordered']; ?></td>
                                    <td><?php echo $dish['total_quantity']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">Нет данных за выбранный период</div>
                    <?php endif; ?>
                    
                <?php elseif ($report_type == 'inventory'): ?>
                    <?php if (!empty($report_data['inventory'])): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Ингредиент</th>
                                    <th>Единица</th>
                                    <th>Текущий запас</th>
                                    <th>Минимум</th>
                                    <th>Статус</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data['inventory'] as $item): 
                                    $percentage = $item['min_quantity'] > 0 ? ($item['current_quantity'] / $item['min_quantity']) * 100 : 100;
                                    $status_class = $item['current_quantity'] < $item['min_quantity'] ? 'danger' : 'success';
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['ingredient_name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                    <td><?php echo number_format($item['current_quantity'], 3); ?></td>
                                    <td><?php echo number_format($item['min_quantity'], 3); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $status_class; ?>">
                                            <?php echo number_format($percentage, 0); ?>%
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">Нет данных</div>
                    <?php endif; ?>
                    
                <?php elseif ($report_type == 'supplies'): ?>
                    <?php if (!empty($report_data['summary'])): ?>
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h6>Поставок</h6>
                                    <h3><?php echo $report_data['summary']['total_supplies']; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h6>Позиций</h6>
                                    <h3><?php echo $report_data['summary']['total_items']; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h6>Общая стоимость</h6>
                                    <h3><?php echo formatMoney($report_data['summary']['total_cost']); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h6>Средняя поставка</h6>
                                    <h3><?php echo formatMoney($report_data['summary']['avg_supply']); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($report_data['statuses'])): ?>
                    <h6>Распределение по статусам:</h6>
                    <div class="row mb-4">
                        <?php foreach ($report_data['statuses'] as $status): 
                            $status_class = [
                                'ожидается' => 'info',
                                'в пути' => 'warning',
                                'доставлено' => 'success',
                                'отменено' => 'danger'
                            ][$status['status']] ?? 'secondary';
                        ?>
                        <div class="col-md-3 mb-2">
                            <div class="card border-<?php echo $status_class; ?>">
                                <div class="card-body p-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-0"><?php echo getSupplyStatusName($status['status']); ?></h6>
                                            <small><?php echo $status['count']; ?> поставок</small>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold"><?php echo formatMoney($status['total_amount']); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($report_data['suppliers'])): ?>
                    <h6>Поставщики:</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Поставщик</th>
                                    <th>Количество поставок</th>
                                    <th>Общая стоимость</th>
                                    <th>Средний чек</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data['suppliers'] as $supplier): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($supplier['supplier_name']); ?></td>
                                    <td><?php echo $supplier['supplies_count']; ?></td>
                                    <td><?php echo formatMoney($supplier['total_cost']); ?></td>
                                    <td><?php echo formatMoney($supplier['avg_cost']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($report_data['monthly'])): ?>
                    <h6>Динамика по месяцам:</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Месяц</th>
                                    <th>Поставок</th>
                                    <th>Сумма</th>
                                    <th>Средняя</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data['monthly'] as $month): ?>
                                <tr>
                                    <td><?php echo date('F Y', strtotime($month['month'] . '-01')); ?></td>
                                    <td><?php echo $month['supplies_count']; ?></td>
                                    <td><?php echo formatMoney($month['monthly_total']); ?></td>
                                    <td><?php echo formatMoney($month['monthly_total'] / $month['supplies_count']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                <?php elseif ($report_type == 'ingredient_usage'): ?>
                    <?php if (!empty($report_data['usage'])): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Ингредиент</th>
                                    <th>Единица</th>
                                    <th>Расход за период</th>
                                    <th>Текущий остаток</th>
                                    <th>Минимум</th>
                                    <th>Статус</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data['usage'] as $usage): 
                                    $percentage = $usage['min_quantity'] > 0 ? ($usage['current_quantity'] / $usage['min_quantity']) * 100 : 100;
                                    $status_class = $usage['current_quantity'] < $usage['min_quantity'] ? 'danger' : 
                                                  ($percentage < 150 ? 'warning' : 'success');
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($usage['ingredient_name']); ?></td>
                                    <td><?php echo htmlspecialchars($usage['unit']); ?></td>
                                    <td><?php echo number_format($usage['total_used'], 3); ?> <?php echo htmlspecialchars($usage['unit']); ?></td>
                                    <td><?php echo number_format($usage['current_quantity'], 3); ?> <?php echo htmlspecialchars($usage['unit']); ?></td>
                                    <td><?php echo number_format($usage['min_quantity'], 3); ?> <?php echo htmlspecialchars($usage['unit']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $status_class; ?>">
                                            <?php echo number_format($percentage, 0); ?>%
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">Нет данных за выбранный период</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    @media print {
    
        .navbar,
        .sidebar,
        .col-md-3, 
        .card-header .btn,
        .btn,
        .form-control,
        .form-select,
        nav,
        footer,
        .alert {
            display: none !important;
        }
        
        .col-md-9 {
            width: 100% !important;
            max-width: 100% !important;
        }
        
        body {
            padding: 10px;
            margin: 0;
            font-size: 12px;
        }
        
        .container-fluid {
            padding: 0;
            margin: 0;
        }
        
        .row {
            margin: 0;
        }
        
        .card {
            margin: 0;
            padding: 0;
            border: none;
            box-shadow: none;
            page-break-inside: avoid;
        }
        
        .card-header {
            background: #fff !important;
            color: #000 !important;
            border-bottom: 2px solid #000;
            padding: 10px;
            margin-bottom: 10px;
        }
        
        .card-body {
            margin: 0;
            padding: 10px;
            border: none;
            box-shadow: none;
        }
        
        .table {
            font-size: 11px;
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 6px;
            border: 1px solid #000;
            text-align: left;
        }
        
        .table thead th {
            background-color: #f0f0f0 !important;
            font-weight: bold;
        }
        
        .table-responsive {
            overflow: visible;
        }
        

        .card.bg-primary,
        .card.bg-success,
        .card.bg-info {
            border: 1px solid #000;
            page-break-inside: avoid;
            background: #fff !important;
            color: #000 !important;
        }
        
        .card.bg-primary .card-body,
        .card.bg-success .card-body,
        .card.bg-info .card-body {
            background: #fff !important;
            color: #000 !important;
        }
        
        .table-responsive {
            page-break-inside: auto;
        }
        
        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        
        thead {
            display: table-header-group;
        }
        
        tfoot {
            display: table-footer-group;
        }
        
        h5 {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        h6 {
            font-size: 14px;
            font-weight: bold;
        }
        
        .badge {
            border: 1px solid #000;
            padding: 2px 6px;
        }
    }
    
    @media screen {
        .card-header .btn {
            margin-left: auto;
        }
    }
</style>

<?php include 'footer.php'; ?>