<?php
require_once 'db_connection.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

if (!checkRole('официант') && !checkRole('менеджер') && !checkRole('администратор')) {
    setFlashMessage('error', 'Доступ запрещен');
    redirect('dashboard.php');
}

$action = getGet('action', 'list');
$id = getGet('id', 0);
$status = getGet('status', '');
$date_from = getGet('date_from', date('Y-m-d'));
$date_to = getGet('date_to', date('Y-m-d'));

try {
    global $db;
    $user_role = getUserRole();
    $user_id = getUserId();
    
    switch ($action) {
        case 'new':
            $table_id = getGet('table_id', 0);
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $table_id = getPost('table_id');
                $client_id_raw = getPost('client_id', '');
                $client_id = (empty($client_id_raw) || $client_id_raw === '') ? null : (int)$client_id_raw;
                $notes = getPost('notes', '');
                $payment_method = 'наличные';
                $stmt = $db->prepare("SELECT status FROM tables WHERE id_table = ?");
                $stmt->execute([$table_id]);
                $table = $stmt->fetch();
                
                if (!$table || ($table['status'] !== 'свободен' && $table['status'] !== 'забронирован')) {
                    setFlashMessage('error', 'Столик недоступен');
                    redirect('orders.php?action=new');
                }
                
                try {
                    $stmt = $db->prepare("CALL create_new_order(?, ?, ?, ?, ?, @order_id)");
                    $stmt->execute([$table_id, $client_id, $user_id, $notes, $payment_method]);
                    $result = $db->query("SELECT @order_id as order_id")->fetch();
                    $order_id = $result['order_id'];
                } catch (Exception $e) {
                    $stmt = $db->prepare("INSERT INTO orders (id_table, id_client, id_employee, notes, payment_method, status, order_datetime) VALUES (?, ?, ?, ?, ?, 'принят', NOW())");
                    $stmt->execute([$table_id, $client_id, $user_id, $notes, $payment_method]);
                    $order_id = $db->lastInsertId();
                    $stmt = $db->prepare("UPDATE tables SET status = 'занят' WHERE id_table = ?");
                    $stmt->execute([$table_id]);
                }
                
                setFlashMessage('success', 'Заказ #' . $order_id . ' создан');
                redirect('orders.php?action=edit&id=' . $order_id);
            }
            $stmt = $db->query("SELECT * FROM tables WHERE status IN ('свободен', 'забронирован') ORDER BY table_number");
            $tables = $stmt->fetchAll();
            $stmt = $db->query("SELECT id_client, fio, phone FROM clients ORDER BY fio");
            $clients = $stmt->fetchAll();
            
            include 'header.php';
            ?>
            <div class="card">
                <div class="card-header">
                    <h5>Новый заказ</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Столик *</label>
                                <select class="form-select" name="table_id" required>
                                    <option value="">Выберите столик</option>
                                    <?php foreach ($tables as $table): 
                                        $status_text = $table['status'] == 'забронирован' ? ' (забронирован)' : '';
                                    ?>
                                    <option value="<?php echo $table['id_table']; ?>" 
                                            <?php echo $table['id_table'] == $table_id ? 'selected' : ''; ?>>
                                        Столик <?php echo $table['table_number']; ?> 
                                        (<?php echo $table['capacity']; ?> чел.)<?php echo $status_text; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Клиент (опционально)</label>
                                <select class="form-select" name="client_id">
                                    <option value="">Выберите клиента</option>
                                    <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client['id_client']; ?>">
                                        <?php echo $client['fio']; ?> (<?php echo $client['phone']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Примечания</label>
                                <textarea class="form-control" name="notes" rows="2"></textarea>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="orders.php" class="btn btn-secondary">Отмена</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Создать заказ
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php
            break;
            
        case 'edit':
        case 'view':
            $stmt = $db->prepare("
                SELECT o.*, t.table_number, t.capacity, 
                       c.fio as client_name, c.phone as client_phone,
                       e.fio as waiter_name
                FROM orders o
                LEFT JOIN tables t ON o.id_table = t.id_table
                LEFT JOIN clients c ON o.id_client = c.id_client
                LEFT JOIN employees e ON o.id_employee = e.id_employee
                WHERE o.id_order = ?
            ");
            $stmt->execute([$id]);
            $order = $stmt->fetch();
            
            if (!$order) {
                setFlashMessage('error', 'Заказ не найден');
                redirect('orders.php');
            }
            $stmt = $db->prepare("
                SELECT oi.*, 
                       COALESCE(oi.dish_name, d.dish_name) as dish_name,
                       d.price as menu_price,
                       ec.fio as chef_name
                FROM order_items oi
                LEFT JOIN dishes d ON oi.id_dish = d.id_dish
                LEFT JOIN employees ec ON oi.id_chef = ec.id_employee
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
            $stmt->execute([$id]);
            $order_items = $stmt->fetchAll();
            $all_ready = true;
            $items_count = count($order_items);
            $ready_count = 0;
            foreach ($order_items as $item) {
                if ($item['status'] === 'готово') {
                    $ready_count++;
                } else {
                    $all_ready = false;
                }
            }
            $stmt = $db->query("
                SELECT d.*, dc.category_name 
                FROM dishes d
                LEFT JOIN dish_categories dc ON d.id_category = dc.id_category
                WHERE d.is_available = 1
                ORDER BY dc.category_name, d.dish_name
            ");
            $dishes = $stmt->fetchAll();
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
                $dish_id = getPost('dish_id');
                $quantity = getPost('quantity', 1);
                
                $stmt = $db->prepare("SELECT dish_name, price FROM dishes WHERE id_dish = ?");
                $stmt->execute([$dish_id]);
                $dish = $stmt->fetch();
                if ($dish) {
                    $columns = $db->query("SHOW COLUMNS FROM order_items LIKE 'dish_name'")->fetch();
                    if ($columns) {
                        $stmt = $db->prepare("
                            INSERT INTO order_items (id_order, id_dish, dish_name, quantity, unit_price)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$id, $dish_id, $dish['dish_name'], $quantity, $dish['price']]);
                    } else {
                    $stmt = $db->prepare("
                        INSERT INTO order_items (id_order, id_dish, quantity, unit_price)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$id, $dish_id, $quantity, $dish['price']]);
                    }
                    $stmt = $db->prepare("
                        UPDATE orders 
                        SET total_amount = (
                            SELECT SUM(quantity * unit_price) 
                            FROM order_items 
                            WHERE id_order = ?
                        ) 
                        WHERE id_order = ?
                    ");
                    $stmt->execute([$id, $id]);
                    
                    setFlashMessage('success', 'Позиция добавлена в заказ');
                    redirect('orders.php?action=edit&id=' . $id);
                }
            }
            if (isset($_GET['delete_item'])) {
                $item_id = getGet('delete_item');
                $stmt = $db->prepare("DELETE FROM order_items WHERE id_order_item = ?");
                $stmt->execute([$item_id]);
                $stmt = $db->prepare("
                    UPDATE orders 
                    SET total_amount = (
                        SELECT COALESCE(SUM(quantity * unit_price), 0) 
                        FROM order_items 
                        WHERE id_order = ?
                    ) 
                    WHERE id_order = ?
                ");
                $stmt->execute([$id, $id]);
                
                setFlashMessage('success', 'Позиция удалена из заказа');
                redirect('orders.php?action=edit&id=' . $id);
            }
            if (isset($_POST['cancel_order'])) {
                $stmt = $db->prepare("UPDATE orders SET status = 'отменен' WHERE id_order = ?");
                $stmt->execute([$id]);
                $stmt = $db->prepare("UPDATE tables SET status = 'свободен' WHERE id_table = ?");
                $stmt->execute([$order['id_table']]);
                
                setFlashMessage('success', 'Заказ отменен');
                redirect('orders.php?action=edit&id=' . $id);
            }
            if (isset($_POST['pay_order'])) {
                $payment_method = getPost('payment_method', 'наличные');
                $stmt = $db->prepare("
                    SELECT COUNT(*) as total, 
                           SUM(CASE WHEN status = 'готово' THEN 1 ELSE 0 END) as ready
                    FROM order_items 
                    WHERE id_order = ?
                ");
                $stmt->execute([$id]);
                $items_check = $stmt->fetch();
                if ($items_check['total'] > 0 && $items_check['ready'] < $items_check['total']) {
                    setFlashMessage('error', 'Нельзя оплатить заказ: не все блюда готовы. Повар должен завершить приготовление всех позиций.');
                    redirect('orders.php?action=edit&id=' . $id);
                }
                $stmt = $db->prepare("UPDATE orders SET status = 'оплачен', payment_method = ? WHERE id_order = ?");
                $stmt->execute([$payment_method, $id]);
                $stmt = $db->prepare("UPDATE tables SET status = 'свободен' WHERE id_table = ?");
                $stmt->execute([$order['id_table']]);
                if ($order['id_client']) {
                    $stmt = $db->prepare("
                        UPDATE clients 
                        SET visit_count = visit_count + 1,
                            total_spent = total_spent + ?
                        WHERE id_client = ?
                    ");
                    $stmt->execute([$order['total_amount'], $order['id_client']]);
                }
                
                setFlashMessage('success', 'Заказ отмечен как оплаченный');
                redirect('orders.php?action=edit&id=' . $id);
            }
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
                $new_status = getPost('status');
                $payment_method = getPost('payment_method');
                $allowed_payment_methods = ['наличные', 'карта', 'онлайн'];
                if ($payment_method && !in_array($payment_method, $allowed_payment_methods)) {
                    $payment_method = 'наличные';
                }
                if ($new_status === 'оплачен' && empty($payment_method)) {
                    setFlashMessage('error', 'Для оплаченного заказа необходимо указать способ оплаты');
                    redirect('orders.php?action=edit&id=' . $id);
                }
                if ($new_status === 'оплачен' && in_array($user_role, ['официант', 'менеджер'])) {
                    $stmt = $db->prepare("
                        SELECT COUNT(*) as total, 
                               SUM(CASE WHEN status = 'готово' THEN 1 ELSE 0 END) as ready
                        FROM order_items 
                        WHERE id_order = ?
                    ");
                    $stmt->execute([$id]);
                    $items_check = $stmt->fetch();
                    if ($items_check['total'] > 0 && $items_check['ready'] < $items_check['total']) {
                        setFlashMessage('error', 'Нельзя оплатить заказ: не все блюда готовы. Повар должен завершить приготовление всех позиций.');
                        redirect('orders.php?action=edit&id=' . $id);
                    }
                }
                
                $stmt = $db->prepare("
                    UPDATE orders 
                    SET status = ?, payment_method = ? 
                    WHERE id_order = ?
                ");
                $stmt->execute([$new_status, $payment_method, $id]);
                if ($new_status === 'оплачен' || $new_status === 'отменен') {
                    $stmt = $db->prepare("UPDATE tables SET status = 'свободен' WHERE id_table = ?");
                    $stmt->execute([$order['id_table']]);
                    if ($new_status === 'оплачен' && $order['id_client']) {
                        $stmt = $db->prepare("
                            UPDATE clients 
                            SET visit_count = visit_count + 1,
                                total_spent = total_spent + ?
                            WHERE id_client = ?
                        ");
                        $stmt->execute([$order['total_amount'], $order['id_client']]);
                    }
                }
                
                setFlashMessage('success', 'Статус заказа обновлен');
                redirect('orders.php?action=edit&id=' . $id);
            }
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_chef'])) {
                $item_id = getPost('item_id');
                $chef_status = getPost('chef_status');
                
                if ($user_role === 'повар') {
                    $stmt = $db->prepare("
                        UPDATE order_items 
                        SET status = ?, id_chef = ? 
                        WHERE id_order_item = ?
                    ");
                    $stmt->execute([$chef_status, $user_id, $item_id]);
                    
                    setFlashMessage('success', 'Статус позиции обновлен');
                    redirect('orders.php?action=edit&id=' . $id);
                }
            }
            
            include 'header.php';
            ?>
            <div class="row">
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                Заказ #<?php echo $order['id_order']; ?>
                                <span class="badge <?php echo getOrderStatusClass($order['status']); ?> ms-2">
                                    <?php echo getOrderStatusName($order['status']); ?>
                                </span>
                            </h5>
                            <div>
                                <span class="text-muted me-3">
                                    <?php echo formatDate($order['order_datetime']); ?>
                                </span>
                                <a href="orders.php?action=print&id=<?php echo $id; ?>" 
                                   class="btn btn-sm btn-outline-secondary" target="_blank">
                                    <i class="bi bi-printer"></i> Печать
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (!$all_ready && $items_count > 0 && in_array($user_role, ['официант', 'менеджер'])): ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle"></i> 
                                <strong>Внимание!</strong> Не все блюда готовы (готово: <?php echo $ready_count; ?>/<?php echo $items_count; ?>). 
                                Заказ можно оплатить только после того, как повар завершит приготовление всех позиций.
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php endif; ?>
                            
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <p><strong>Столик:</strong> <?php echo $order['table_number']; ?></p>
                                    <p><strong>Вместимость:</strong> <?php echo $order['capacity']; ?> чел.</p>
                                </div>
                                <div class="col-md-4">
                                    <p><strong>Официант:</strong> <?php echo $order['waiter_name']; ?></p>
                                    <p><strong>Клиент:</strong> 
                                        <?php echo $order['client_name'] ?: 'Не указан'; ?>
                                        <?php if ($order['client_phone']): ?>
                                            <br><small><?php echo $order['client_phone']; ?></small>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="col-md-4">
                                    <p><strong>Общая сумма:</strong> <?php echo formatMoney($order['total_amount']); ?></p>
                                    <p><strong>Способ оплаты:</strong> 
                                        <span class="badge bg-info">
                                            <?php echo $order['payment_method'] ?: 'не выбран'; ?>
                                        </span>
                                    </p>
                                    <?php if ($items_count > 0): ?>
                                    <p><strong>Готовность:</strong> 
                                        <span class="badge <?php echo $all_ready ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                            <?php echo $ready_count; ?>/<?php echo $items_count; ?>
                                        </span>
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <h6>Состав заказа:</h6>
                            <?php if ($order_items): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Блюдо</th>
                                                <th>Кол-во</th>
                                                <th>Цена</th>
                                                <th>Сумма</th>
                                                <th>Статус</th>
                                                <?php if ($user_role === 'повар'): ?>
                                                <th>Повар</th>
                                                <?php endif; ?>
                                                <th>Действия</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($order_items as $item): 
                                                $item_total = $item['quantity'] * $item['unit_price'];
                                            ?>
                                            <tr>
                                                <td><?php echo $item['dish_name']; ?></td>
                                                <td><?php echo $item['quantity']; ?></td>
                                                <td><?php echo formatMoney($item['unit_price']); ?></td>
                                                <td><?php echo formatMoney($item_total); ?></td>
                                                <td>
                                                    <span class="badge <?php 
                                                        if ($item['status'] == 'ожидает') echo 'bg-secondary';
                                                        elseif ($item['status'] == 'готовится') echo 'bg-warning text-dark';
                                                        elseif ($item['status'] == 'готово') echo 'bg-success';
                                                        else echo 'bg-info';
                                                    ?>">
                                                        <?php echo $item['status']; ?>
                                                    </span>
                                                    <?php if ($user_role === 'повар'): ?>
                                                    <div class="mt-1">
                                                        <?php if ($item['status'] == 'ожидает'): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="item_id" value="<?php echo $item['id_order_item']; ?>">
                                                            <input type="hidden" name="chef_status" value="готовится">
                                                            <button type="submit" name="assign_chef" class="btn btn-sm btn-warning">
                                                                Начать готовить
                                                            </button>
                                                        </form>
                                                        <?php elseif ($item['status'] == 'готовится'): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="item_id" value="<?php echo $item['id_order_item']; ?>">
                                                            <input type="hidden" name="chef_status" value="готово">
                                                            <button type="submit" name="assign_chef" class="btn btn-sm btn-success">
                                                                Готово
                                                            </button>
                                                        </form>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php endif; ?>
                                                </td>
                                                <?php if ($user_role === 'повар'): ?>
                                                <td><?php echo $item['chef_name'] ?: '—'; ?></td>
                                                <?php endif; ?>
                                                <td>
                                                    <?php if ($order['status'] !== 'оплачен' && $order['status'] !== 'отменен' && $user_role !== 'повар'): ?>
                                                    <button class="btn btn-sm btn-danger"
                                                            onclick="if(confirm('Удалить позицию?')) window.location.href='orders.php?action=edit&id=<?php echo $id; ?>&delete_item=<?php echo $item['id_order_item']; ?>'">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-active">
                                                <td colspan="3" class="text-end"><strong>Итого:</strong></td>
                                                <td><strong><?php echo formatMoney($order['total_amount']); ?></strong></td>
                                                <td colspan="<?php echo ($user_role === 'повар') ? 3 : 2; ?>"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">Нет позиций в заказе</div>
                            <?php endif; ?>
                            

                            <?php if ($order['status'] !== 'оплачен' && $order['status'] !== 'отменен' && $user_role !== 'повар'): ?>
                            <hr>
                            <h6>Добавить позицию:</h6>
                            <form method="POST" class="row g-2">
                                <div class="col-md-5">
                                    <select class="form-select form-select-sm" name="dish_id" required>
                                        <option value="">Выберите блюдо</option>
                                        <?php foreach ($dishes as $dish): ?>
                                        <option value="<?php echo $dish['id_dish']; ?>">
                                            [<?php echo $dish['category_name']; ?>] <?php echo $dish['dish_name']; ?> - <?php echo formatMoney($dish['price']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" class="form-control form-control-sm" name="quantity" 
                                           value="1" min="1" max="10" required>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" name="add_item" class="btn btn-sm btn-primary">
                                        <i class="bi bi-plus-circle"></i> Добавить
                                    </button>
                                </div>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">Управление заказом</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($order['status'] !== 'оплачен' && $order['status'] !== 'отменен'): ?>
                            <?php if (in_array($user_role, ['официант', 'менеджер'])): ?>
                            <form method="POST" id="payForm" class="mb-3">
                                <div class="mb-3">
                                    <label class="form-label">Способ оплаты *</label>
                                    <select class="form-select" name="payment_method" required <?php echo !$all_ready && $items_count > 0 ? 'disabled' : ''; ?>>
                                        <option value="наличные" <?php echo $order['payment_method'] == 'наличные' ? 'selected' : ''; ?>>Наличные</option>
                                        <option value="карта" <?php echo $order['payment_method'] == 'карта' ? 'selected' : ''; ?>>Карта</option>
                                        <option value="онлайн" <?php echo $order['payment_method'] == 'онлайн' ? 'selected' : ''; ?>>Онлайн</option>
                                    </select>
                                </div>
                                <button type="submit" name="pay_order" class="btn btn-success w-100" 
                                        <?php echo !$all_ready && $items_count > 0 ? 'disabled title="Не все блюда готовы"' : ''; ?>>
                                    <i class="bi bi-cash"></i> Отметить как оплаченный
                                </button>
                                <?php if (!$all_ready && $items_count > 0): ?>
                                <small class="text-danger d-block mt-2">
                                    <i class="bi bi-info-circle"></i> Не все блюда готовы
                                </small>
                                <?php endif; ?>
                            </form>
                            <?php endif; ?>
                    
                            <form method="POST" id="cancelForm">
                                <button type="submit" name="cancel_order" class="btn btn-danger w-100"
                                        onclick="return confirm('Вы уверены, что хотите отменить заказ?')">
                                    <i class="bi bi-x-circle"></i> Отменить заказ
                                </button>
                            </form>
                            <?php elseif ($order['status'] === 'оплачен'): ?>
                            <div class="alert alert-success">
                                <h6><i class="bi bi-check-circle"></i> Заказ оплачен</h6>
                                <p class="mb-1">Способ оплаты: <strong><?php echo $order['payment_method']; ?></strong></p>
                                <p class="mb-0">Дата: <?php echo formatDate($order['order_datetime']); ?></p>
                            </div>
                            <?php elseif ($order['status'] === 'отменен'): ?>
                            <div class="alert alert-danger">
                                <h6><i class="bi bi-x-circle"></i> Заказ отменен</h6>
                                <p class="mb-0">Дата: <?php echo formatDate($order['order_datetime']); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Стандартная форма обновления статуса (для других статусов) -->
                            <?php if ($order['status'] !== 'оплачен' && $order['status'] !== 'отменен'): ?>
                            <hr>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Статус заказа</label>
                                    <select class="form-select" name="status">
                                        <?php 
                                        $statuses = ['принят', 'готовится', 'готов', 'подан', 'оплачен', 'отменен'];
                                        foreach ($statuses as $s): 
                                        ?>
                                        <option value="<?php echo $s; ?>" 
                                                <?php echo $order['status'] == $s ? 'selected' : ''; ?>>
                                            <?php echo getOrderStatusName($s); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Способ оплаты</label>
                                    <select class="form-select" name="payment_method">
                                        <option value="наличные" <?php echo $order['payment_method'] == 'наличные' ? 'selected' : ''; ?>>Наличные</option>
                                        <option value="карта" <?php echo $order['payment_method'] == 'карта' ? 'selected' : ''; ?>>Карта</option>
                                        <option value="онлайн" <?php echo $order['payment_method'] == 'онлайн' ? 'selected' : ''; ?>>Онлайн</option>
                                    </select>
                                </div>
                                
                                <button type="submit" name="update_status" class="btn btn-primary w-100">
                                    Обновить статус
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Быстрые действия -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">Быстрые действия</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="tables.php" class="btn btn-outline-info">
                                    <i class="bi bi-table"></i> Управление столиками
                                </a>
                                <a href="clients.php?action=view&id=<?php echo $order['id_client']; ?>" 
                                   class="btn btn-outline-info <?php echo !$order['id_client'] ? 'disabled' : ''; ?>">
                                    <i class="bi bi-person"></i> Карточка клиента
                                </a>
                                <a href="orders.php?action=new" class="btn btn-outline-success">
                                    <i class="bi bi-plus-circle"></i> Новый заказ
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Примечания -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Примечания</h6>
                        </div>
                        <div class="card-body">
                            <p><?php echo nl2br($order['notes'] ?: 'Нет примечаний'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
            document.getElementById('payForm')?.addEventListener('submit', function(e) {
                if (!confirm('Отметить заказ как оплаченный?')) {
                    e.preventDefault();
                }
            });
            document.getElementById('cancelForm')?.addEventListener('submit', function(e) {
                if (!confirm('Вы уверены, что хотите отменить заказ?')) {
                    e.preventDefault();
                }
            });
            </script>
            <?php
            break;
        case 'print':
            $stmt = $db->prepare("
                SELECT o.*, t.table_number, c.fio as client_name,
                       e.fio as waiter_name
                FROM orders o
                LEFT JOIN tables t ON o.id_table = t.id_table
                LEFT JOIN clients c ON o.id_client = c.id_client
                LEFT JOIN employees e ON o.id_employee = e.id_employee
                WHERE o.id_order = ?
            ");
            $stmt->execute([$id]);
            $order = $stmt->fetch();
            
            if (!$order) {
                die('Заказ не найден');
            }
            $stmt = $db->prepare("
                SELECT oi.*, COALESCE(oi.dish_name, d.dish_name) as dish_name
                FROM order_items oi
                LEFT JOIN dishes d ON oi.id_dish = d.id_dish
                WHERE oi.id_order = ?
                ORDER BY oi.id_order_item ASC
            ");
            $stmt->execute([$id]);
            $order_items = $stmt->fetchAll();
            
            header('Content-Type: text/html; charset=utf-8');
            ?>
            <!DOCTYPE html>
            <html lang="ru">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Чек #<?php echo $order['id_order']; ?></title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    .check { max-width: 300px; margin: 0 auto; border: 1px solid #000; padding: 15px; }
                    .check-header { text-align: center; margin-bottom: 20px; }
                    .check-title { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
                    .check-info { font-size: 12px; margin-bottom: 5px; }
                    .check-items { margin: 15px 0; }
                    .item-row { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 12px; }
                    .item-name { flex: 2; }
                    .item-qty { text-align: center; flex: 1; }
                    .item-price { text-align: right; flex: 1; }
                    .item-total { text-align: right; flex: 1; }
                    .check-total { border-top: 1px dashed #000; padding-top: 10px; margin-top: 10px; font-weight: bold; }
                    .check-footer { text-align: center; margin-top: 20px; font-size: 11px; }
                    @media print {
                        body { margin: 0; }
                        .no-print { display: none; }
                    }
                </style>
            </head>
            <body>
                <div class="check">
                    <div class="check-header">
                        <div class="check-title">Информационная система для управления кафе</div>
                        <div class="check-info">Кассовый чек</div>
                        <div class="check-info">Чек #<?php echo $order['id_order']; ?></div>
                        <div class="check-info"><?php echo formatDate($order['order_datetime'], 'd.m.Y H:i:s'); ?></div>
                    </div>
                    
                    <div class="check-info">
                        <div>Столик: <?php echo $order['table_number']; ?></div>
                        <div>Официант: <?php echo $order['waiter_name']; ?></div>
                        <?php if ($order['client_name']): ?>
                        <div>Клиент: <?php echo $order['client_name']; ?></div>
                        <?php endif; ?>
                        <div>Способ оплаты: <?php echo $order['payment_method']; ?></div>
                    </div>
                    
                    <div class="check-items">
                        <?php 
                        $total_sum = 0;
                        foreach ($order_items as $item): 
                            $item_total = $item['quantity'] * $item['unit_price'];
                            $total_sum += $item_total;
                        ?>
                        <div class="item-row">
                            <div class="item-name"><?php echo $item['dish_name']; ?></div>
                            <div class="item-qty"><?php echo $item['quantity']; ?>x</div>
                            <div class="item-price"><?php echo number_format($item['unit_price'], 2); ?></div>
                            <div class="item-total"><?php echo number_format($item_total, 2); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="check-total">
                        <div class="item-row">
                            <div class="item-name">ИТОГО:</div>
                            <div class="item-total"><?php echo number_format($total_sum, 2); ?> ₽</div>
                        </div>
                    </div>
                    
                    <div class="check-footer">
                        <div>Спасибо за заказ!</div>
                        <div>Ждем вас снова!</div>
                    </div>
                </div>
                
                <div class="no-print" style="text-align: center; margin-top: 20px;">
                    <button onclick="window.print()" class="btn btn-primary">Печать</button>
                    <button onclick="window.close()" class="btn btn-secondary">Закрыть</button>
                </div>
                
                <script>
                    window.onload = function() {
                        setTimeout(function() {
                            window.print();
                        }, 500);
                    };
                </script>
            </body>
            </html>
            <?php
            exit();            
        default:
            $where = [];
            $params = [];
            
            if ($status) {
                $where[] = "o.status = ?";
                $params[] = $status;
            }
            
            if ($date_from && $date_to) {
                $where[] = "DATE(o.order_datetime) BETWEEN ? AND ?";
                $params[] = $date_from;
                $params[] = $date_to;
            }
            if ($user_role === 'повар') {
                $where[] = "oi.id_chef = ? OR oi.id_chef IS NULL";
                $params[] = $user_id;
                $join = "LEFT JOIN order_items oi ON o.id_order = oi.id_order";
            } else if ($user_role === 'официант') {
                $where[] = "o.id_employee = ?";
                $params[] = $user_id;
                $join = "";
            } else {
                $join = "";
            }
            
            $where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";
            $sql = "SELECT COUNT(DISTINCT o.id_order) FROM orders o $join $where_clause";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $total_orders = $stmt->fetchColumn();
            $page = getGet('page', 1);
            $per_page = 20;
            $total_pages = ceil($total_orders / $per_page);
            $offset = ($page - 1) * $per_page;
            $sql = "
                SELECT o.*, t.table_number, c.fio as client_name, 
                       e.fio as waiter_name, COUNT(oi.id_order_item) as items_count
                FROM orders o
                LEFT JOIN tables t ON o.id_table = t.id_table
                LEFT JOIN clients c ON o.id_client = c.id_client
                LEFT JOIN employees e ON o.id_employee = e.id_employee
                LEFT JOIN order_items oi ON o.id_order = oi.id_order
                $where_clause
                GROUP BY o.id_order
                ORDER BY 
                    CASE o.status
                        WHEN 'готовится' THEN 1
                        WHEN 'принят' THEN 2
                        WHEN 'готов' THEN 3
                        WHEN 'подан' THEN 4
                        WHEN 'оплачен' THEN 5
                        WHEN 'отменен' THEN 6
                        ELSE 7
                    END,
                    o.order_datetime DESC
                LIMIT $per_page OFFSET $offset
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $orders = $stmt->fetchAll();
            
            include 'header.php';
            ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Заказы</h5>
                    <?php if ($user_role !== 'повар'): ?>
                    <a href="orders.php?action=new" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-circle"></i> Новый заказ
                    </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <!-- Фильтры -->
                    <form method="GET" class="row g-2 mb-4">
                        <div class="col-md-3">
                            <label class="form-label">Статус</label>
                            <select class="form-select" name="status">
                                <option value="">Все статусы</option>
                                <?php 
                                $statuses = ['принят', 'готовится', 'готов', 'подан', 'оплачен', 'отменен'];
                                foreach ($statuses as $s): 
                                ?>
                                <option value="<?php echo $s; ?>" <?php echo $status == $s ? 'selected' : ''; ?>>
                                    <?php echo getOrderStatusName($s); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">С</label>
                            <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">По</label>
                            <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                        </div>
                        
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-filter"></i> Фильтровать
                            </button>
                        </div>
                        
                        <input type="hidden" name="action" value="list">
                    </form>
                    
                    <!-- Статистика -->
                    <?php if ($user_role !== 'повар'): ?>
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stat-card bg-primary text-white rounded p-3 text-center">
                                <h6>Сегодня</h6>
                                <h5>
                                    <?php 
                                    $stmt = $db->query("SELECT COUNT(*) FROM orders WHERE DATE(order_datetime) = CURDATE()");
                                    echo $stmt->fetchColumn();
                                    ?>
                                </h5>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-warning text-white rounded p-3 text-center">
                                <h6>Активные</h6>
                                <h5>
                                    <?php 
                                    $stmt = $db->query("SELECT COUNT(*) FROM orders WHERE status IN ('принят', 'готовится', 'готов', 'подан')");
                                    echo $stmt->fetchColumn();
                                    ?>
                                </h5>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-success text-white rounded p-3 text-center">
                                <h6>Выручка сегодня</h6>
                                <h5>
                                    <?php 
                                    $stmt = $db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(order_datetime) = CURDATE() AND status = 'оплачен'");
                                    echo formatMoney($stmt->fetchColumn());
                                    ?>
                                </h5>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-info text-white rounded p-3 text-center">
                                <h6>Средний чек</h6>
                                <h5>
                                    <?php 
                                    $stmt = $db->query("SELECT COALESCE(AVG(total_amount), 0) FROM orders WHERE DATE(order_datetime) = CURDATE() AND status = 'оплачен'");
                                    echo formatMoney($stmt->fetchColumn());
                                    ?>
                                </h5>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Таблица заказов -->
                    <?php if ($orders): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>№</th>
                                        <th>Дата/Время</th>
                                        <th>Стол</th>
                                        <?php if ($user_role !== 'повар'): ?>
                                        <th>Клиент</th>
                                        <?php endif; ?>
                                        <th>Сумма</th>
                                        <th>Статус</th>
                                        <th>Официант</th>
                                        <th>Позиций</th>
                                        <th>Оплата</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>#<?php echo $order['id_order']; ?></td>
                                        <td><?php echo formatDate($order['order_datetime'], 'd.m.Y H:i'); ?></td>
                                        <td><?php echo $order['table_number']; ?></td>
                                        <?php if ($user_role !== 'повар'): ?>
                                        <td><?php echo $order['client_name'] ?: '—'; ?></td>
                                        <?php endif; ?>
                                        <td><?php echo formatMoney($order['total_amount']); ?></td>
                                        <td>
                                            <span class="badge <?php echo getOrderStatusClass($order['status']); ?>">
                                                <?php echo getOrderStatusName($order['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $order['waiter_name']; ?></td>
                                        <td><?php echo $order['items_count']; ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo $order['payment_method'] ?: 'наличные'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="orders.php?action=view&id=<?php echo $order['id_order']; ?>" 
                                                   class="btn btn-info" title="Просмотр">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="orders.php?action=edit&id=<?php echo $order['id_order']; ?>" 
                                                   class="btn btn-warning" title="Редактировать">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="orders.php?action=print&id=<?php echo $order['id_order']; ?>" 
                                                   class="btn btn-secondary" title="Печать" target="_blank">
                                                    <i class="bi bi-printer"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Пагинация -->
                        <?php if ($total_pages > 1): ?>
                        <nav aria-label="Навигация по страницам">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" 
                                       href="orders.php?action=list&page=<?php echo $i; ?>&status=<?php echo $status; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-cart" style="font-size: 48px;"></i>
                            <p class="mt-2">Заказы не найдены</p>
                            <?php if ($user_role !== 'повар'): ?>
                            <a href="orders.php?action=new" class="btn btn-primary mt-2">
                                <i class="bi bi-plus-circle"></i> Создать первый заказ
                            </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
    }
    
} catch (Exception $e) {
    setFlashMessage('error', 'Ошибка: ' . $e->getMessage());
    redirect('orders.php');
}

include 'footer.php';
?>