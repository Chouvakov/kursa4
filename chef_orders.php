<?php
require_once 'db_connection.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$user_role = getUserRole();
if ($user_role !== 'повар') {
    setFlashMessage('error', 'Доступ запрещен');
    redirect('dashboard.php');
}

$user_id = getUserId();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_item_status'])) {
    $item_id = getPost('item_id');
    $new_status = getPost('update_item_status'); 
    
    global $db;
    $stmt = $db->prepare("
        UPDATE order_items 
        SET status = ?, id_chef = ? 
        WHERE id_order_item = ?
    ");
    $stmt->execute([$new_status, $user_id, $item_id]);
    
    setFlashMessage('success', 'Статус позиции обновлен');
    redirect('chef_orders.php');
}
global $db;
$stmt = $db->query("
    SELECT DISTINCT
        o.id_order,
        o.order_datetime,
        o.status as order_status,
        t.table_number,
        e.fio as waiter_name,
        COUNT(DISTINCT oi.id_order_item) as total_items,
        SUM(CASE WHEN oi.status = 'готово' THEN 1 ELSE 0 END) as ready_items,
        SUM(CASE WHEN oi.status IN ('ожидает', 'готовится') THEN 1 ELSE 0 END) as pending_items
    FROM orders o
    LEFT JOIN tables t ON o.id_table = t.id_table
    LEFT JOIN employees e ON o.id_employee = e.id_employee
    LEFT JOIN order_items oi ON o.id_order = oi.id_order
    WHERE o.status NOT IN ('оплачен', 'отменен')
        AND oi.status IN ('ожидает', 'готовится', 'готово')
    GROUP BY o.id_order, o.order_datetime, o.status, t.table_number, e.fio
    ORDER BY 
        CASE 
            WHEN SUM(CASE WHEN oi.status = 'ожидает' THEN 1 ELSE 0 END) > 0 THEN 1
            WHEN SUM(CASE WHEN oi.status = 'готовится' THEN 1 ELSE 0 END) > 0 THEN 2
            ELSE 3
        END,
        o.order_datetime ASC
");
$orders = $stmt->fetchAll();

foreach ($orders as &$order) {
    $stmt = $db->prepare("
        SELECT oi.*, 
               COALESCE(oi.dish_name, d.dish_name) as dish_name,
               d.id_category,
               dc.category_name
        FROM order_items oi
        LEFT JOIN dishes d ON oi.id_dish = d.id_dish
        LEFT JOIN dish_categories dc ON d.id_category = dc.id_category
        WHERE oi.id_order = ?
        ORDER BY 
            CASE oi.status
                WHEN 'ожидает' THEN 1
                WHEN 'готовится' THEN 2
                WHEN 'готово' THEN 3
                ELSE 4
            END,
            oi.id_order_item ASC
    ");
    $stmt->execute([$order['id_order']]);
    $order['items'] = $stmt->fetchAll();
}
unset($order);

include 'header.php';
?>
<style>
    .order-card {
        border-left: 4px solid #007bff;
        margin-bottom: 20px;
        transition: all 0.3s;
    }
    .order-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transform: translateY(-2px);
    }
    .order-card.urgent {
        border-left-color: #dc3545;
    }
    .order-card.in-progress {
        border-left-color: #ffc107;
    }
    .order-card.completed {
        border-left-color: #28a745;
    }
    .item-card {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 10px;
        background: #fff;
    }
    .item-card.waiting {
        border-left: 4px solid #6c757d;
        background: #f8f9fa;
    }
    .item-card.cooking {
        border-left: 4px solid #ffc107;
        background: #fff3cd;
    }
    .item-card.ready {
        border-left: 4px solid #28a745;
        background: #d4edda;
    }
    .status-badge {
        font-size: 0.85rem;
        padding: 5px 10px;
    }
    .btn-action {
        min-width: 120px;
    }
    .order-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px;
        border-radius: 8px 8px 0 0;
    }
    .stats-box {
        background: #f8f9fa;
        padding: 10px;
        border-radius: 5px;
        text-align: center;
    }
</style>

<div class="container-fluid">
    <?php echo showFlashMessage(); ?>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-egg-fried"></i> Кухня - Заказы для приготовления</h2>
        <div>
            <span class="badge bg-primary">Всего заказов: <?php echo count($orders); ?></span>
            <span class="badge bg-warning text-dark">
                Ожидают: <?php echo array_sum(array_column($orders, 'pending_items')); ?>
            </span>
        </div>
    </div>
    
    <?php if (empty($orders)): ?>
        <div class="alert alert-info text-center">
            <i class="bi bi-inbox" style="font-size: 48px;"></i>
            <h4 class="mt-3">Нет активных заказов</h4>
            <p>Все заказы обработаны или нет новых заказов для приготовления.</p>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($orders as $order): 
                $has_waiting = $order['pending_items'] > 0;
                $all_ready = $order['ready_items'] == $order['total_items'] && $order['total_items'] > 0;
                $card_class = $has_waiting ? ($order['pending_items'] == $order['total_items'] ? 'urgent' : 'in-progress') : 'completed';
            ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card order-card <?php echo $card_class; ?>">
                    <div class="order-header">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h5 class="mb-1">Заказ #<?php echo $order['id_order']; ?></h5>
                                <small>
                                    <i class="bi bi-clock"></i> <?php echo formatDate($order['order_datetime'], 'H:i'); ?>
                                    <br>
                                    <i class="bi bi-table"></i> Стол <?php echo $order['table_number']; ?>
                                    <br>
                                    <i class="bi bi-person"></i> <?php echo $order['waiter_name']; ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-light text-dark status-badge">
                                    <?php echo $order['ready_items']; ?>/<?php echo $order['total_items']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="stats-box mb-3">
                            <small class="text-muted">Готовность</small>
                            <div class="progress mt-2" style="height: 8px;">
                                <div class="progress-bar <?php echo $all_ready ? 'bg-success' : ($has_waiting ? 'bg-warning' : 'bg-info'); ?>" 
                                     role="progressbar" 
                                     style="width: <?php echo $order['total_items'] > 0 ? ($order['ready_items'] / $order['total_items'] * 100) : 0; ?>%">
                                </div>
                            </div>
                        </div>
                        
                        <?php foreach ($order['items'] as $item): 
                            $item_class = '';
                            if ($item['status'] === 'ожидает') $item_class = 'waiting';
                            elseif ($item['status'] === 'готовится') $item_class = 'cooking';
                            elseif ($item['status'] === 'готово') $item_class = 'ready';
                        ?>
                        <div class="item-card <?php echo $item_class; ?>">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($item['dish_name']); ?></h6>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($item['category_name']); ?> • 
                                        Количество: <?php echo $item['quantity']; ?>
                                    </small>
                                </div>
                                <span class="badge <?php 
                                    echo $item['status'] === 'ожидает' ? 'bg-secondary' : 
                                        ($item['status'] === 'готовится' ? 'bg-warning text-dark' : 'bg-success');
                                ?>">
                                    <?php echo $item['status']; ?>
                                </span>
                            </div>
                            
                            <?php if ($item['status'] !== 'готово'): ?>
                            <form method="POST" class="mt-2">
                                <input type="hidden" name="item_id" value="<?php echo $item['id_order_item']; ?>">
                                <?php if ($item['status'] === 'ожидает'): ?>
                                    <button type="submit" name="update_item_status" value="готовится" 
                                            class="btn btn-warning btn-sm btn-action w-100">
                                        <i class="bi bi-play-circle"></i> Начать готовить
                                    </button>
                                <?php elseif ($item['status'] === 'готовится'): ?>
                                    <button type="submit" name="update_item_status" value="готово" 
                                            class="btn btn-success btn-sm btn-action w-100">
                                        <i class="bi bi-check-circle"></i> Готово
                                    </button>
                                <?php endif; ?>
                            </form>
                            <?php else: ?>
                            <div class="text-center text-success mt-2">
                                <i class="bi bi-check-circle-fill"></i> Готово
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="mt-3 pt-3 border-top">
                            <a href="orders.php?action=view&id=<?php echo $order['id_order']; ?>" 
                               class="btn btn-outline-primary btn-sm w-100">
                                <i class="bi bi-eye"></i> Подробнее
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
setTimeout(function() {
    location.reload();
}, 30000);
</script>

<?php include 'footer.php'; ?>

