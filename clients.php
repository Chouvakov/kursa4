<?php
require_once 'db_connection.php';
require_once 'functions.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
$user_role = $_SESSION['role'] ?? '';
$allowed_roles = ['менеджер', 'администратор', 'официант'];
if (!in_array($user_role, $allowed_roles)) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Доступ запрещен'];
    header("Location: dashboard.php");
    exit();
}
$action = isset($_GET['action']) ? trim($_GET['action']) : 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$page_title = 'Клиенты';

try {
    global $db;
    
    switch ($action) {
        case 'add':
        case 'edit':
            $client = null;
            if ($action == 'edit' && $id) {
                $stmt = $db->prepare("SELECT * FROM clients WHERE id_client = ?");
                $stmt->execute([$id]);
                $client = $stmt->fetch();
                
                if (!$client) {
                    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Клиент не найден'];
                    header("Location: clients.php");
                    exit();
                }
            }
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $fio = isset($_POST['fio']) ? trim($_POST['fio']) : '';
                $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
                $email = isset($_POST['email']) ? trim($_POST['email']) : '';
                $errors = [];
                if (empty($fio)) $errors[] = 'ФИО обязательно';
                if (empty($phone)) $errors[] = 'Телефон обязателен';
                
                if (empty($errors)) {
                    if ($action == 'add') {
                        $stmt = $db->prepare("
                            INSERT INTO clients (fio, phone, email) 
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([$fio, $phone, $email]);
                        $message = 'Клиент успешно добавлен';
                    } else {
                        $stmt = $db->prepare("
                            UPDATE clients 
                            SET fio = ?, phone = ?, email = ? 
                            WHERE id_client = ?
                        ");
                        $stmt->execute([$fio, $phone, $email, $id]);
                        $message = 'Данные клиента обновлены';
                    }
                    
                    $_SESSION['flash'] = ['type' => 'success', 'message' => $message];
                    header("Location: clients.php");
                    exit();
                } else {
                    $_SESSION['flash'] = ['type' => 'error', 'message' => implode('<br>', $errors)];
                }
            }
            include 'header.php';
            ?>
            <div class="card">
                <div class="card-header">
                    <h5><?php echo $action == 'add' ? 'Добавление клиента' : 'Редактирование клиента'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="fio" class="form-label">ФИО *</label>
                            <input type="text" class="form-control" id="fio" name="fio" 
                                   value="<?php echo htmlspecialchars($client['fio'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Телефон *</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($client['phone'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($client['email'] ?? ''); ?>">
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="clients.php" class="btn btn-secondary">Отмена</a>
                            <button type="submit" class="btn btn-primary">
                                <?php echo $action == 'add' ? 'Добавить' : 'Сохранить'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php
            include 'footer.php';
            break;
            
        case 'view':
            $stmt = $db->prepare("SELECT * FROM clients WHERE id_client = ?");
            $stmt->execute([$id]);
            $client = $stmt->fetch();
            
            if (!$client) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'Клиент не найден'];
                header("Location: clients.php");
                exit();
            }
            
            // Получаем статистику клиента
            $stats_stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_orders,
                    SUM(total_amount) as total_spent,
                    MAX(order_datetime) as last_visit
                FROM orders 
                WHERE id_client = ? AND status = 'оплачен'
            ");
            $stats_stmt->execute([$id]);
            $stats = $stats_stmt->fetch();
            $orders_stmt = $db->prepare("
                SELECT o.*, t.table_number, e.fio as waiter
                FROM orders o
                LEFT JOIN tables t ON o.id_table = t.id_table
                LEFT JOIN employees e ON o.id_employee = e.id_employee
                WHERE o.id_client = ?
                ORDER BY o.order_datetime DESC
                LIMIT 10
            ");
            $orders_stmt->execute([$id]);
            $orders = $orders_stmt->fetchAll();
            $feedback_stmt = $db->prepare("
                SELECT f.*, o.id_order
                FROM feedback f
                LEFT JOIN orders o ON f.id_order = o.id_order
                WHERE f.id_client = ?
                ORDER BY f.feedback_date DESC
            ");
            $feedback_stmt->execute([$id]);
            $feedbacks = $feedback_stmt->fetchAll();
            
            // Подключаем header
            include 'header.php';
            ?>
            <div class="row">
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>Информация о клиенте</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>ФИО:</strong> <?php echo htmlspecialchars($client['fio']); ?></p>
                            <p><strong>Телефон:</strong> <?php echo htmlspecialchars($client['phone']); ?></p>
                            <p><strong>Email:</strong> <?php echo $client['email'] ? htmlspecialchars($client['email']) : 'не указан'; ?></p>
                            <p><strong>Дата регистрации:</strong> <?php echo date('d.m.Y', strtotime($client['registration_date'])); ?></p>
                            <p><strong>Количество посещений:</strong> <?php echo $client['visit_count']; ?></p>
                            <p><strong>Общая сумма покупок:</strong> <?php echo number_format($stats['total_spent'] ?? 0, 2); ?> ₽</p>
                            <p><strong>Всего заказов:</strong> <?php echo $stats['total_orders'] ?? 0; ?></p>
                            <p><strong>Последнее посещение:</strong> <?php echo $stats['last_visit'] ? date('d.m.Y H:i', strtotime($stats['last_visit'])) : '—'; ?></p>
                        </div>
                        <div class="card-footer">
                            <a href="clients.php?action=edit&id=<?php echo $id; ?>" class="btn btn-warning btn-sm">Редактировать</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>История заказов</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($orders): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>№</th>
                                                <th>Дата</th>
                                                <th>Стол</th>
                                                <th>Сумма</th>
                                                <th>Статус</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($orders as $order): 
                                                $status_class = getOrderStatusClass($order['status']);
                                                $status_text = getOrderStatusName($order['status']);
                                            ?>
                                            <tr>
                                                <td><a href="orders.php?action=view&id=<?php echo $order['id_order']; ?>">#<?php echo $order['id_order']; ?></a></td>
                                                <td><?php echo date('d.m.Y H:i', strtotime($order['order_datetime'])); ?></td>
                                                <td><?php echo $order['table_number']; ?></td>
                                                <td><?php echo number_format($order['total_amount'], 2); ?> ₽</td>
                                                <td>
                                                    <span class="badge <?php echo $status_class; ?>">
                                                        <?php echo $status_text; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">Нет заказов</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h5>Отзывы</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($feedbacks): ?>
                                <?php foreach ($feedbacks as $fb): ?>
                                <div class="border-bottom pb-3 mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span class="text-warning">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi bi-star<?php echo $i <= $fb['rating'] ? '-fill' : ''; ?>"></i>
                                            <?php endfor; ?>
                                        </span>
                                        <small class="text-muted"><?php echo date('d.m.Y', strtotime($fb['feedback_date'])); ?></small>
                                    </div>
                                    <p class="mt-2"><?php echo nl2br(htmlspecialchars($fb['comment'])); ?></p>
                                    <?php if ($fb['manager_response']): ?>
                                        <div class="alert alert-info mt-2">
                                            <strong>Ответ менеджера (<?php echo date('d.m.Y', strtotime($fb['response_date'])); ?>):</strong>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($fb['manager_response'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">Отзывов пока нет</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            include 'footer.php';
            break;
            
        case 'delete':
            // Удаление клиента
            if (in_array($user_role, ['администратор', 'менеджер'])) {
                $stmt = $db->prepare("DELETE FROM clients WHERE id_client = ?");
                $stmt->execute([$id]);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Клиент удален'];
            } else {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'Недостаточно прав'];
            }
            header("Location: clients.php");
            exit();
            
        default:
            // Список клиентов
            $where = '';
            $params = [];
            
            if ($search) {
                $where = "WHERE fio LIKE ? OR phone LIKE ? OR email LIKE ?";
                $search_term = "%$search%";
                $params = [$search_term, $search_term, $search_term];
            }
            
            // Общее количество клиентов
            $count_stmt = $db->prepare("SELECT COUNT(*) FROM clients $where");
            $count_stmt->execute($params);
            $total_clients = $count_stmt->fetchColumn();
            $total_pages = ceil($total_clients / $per_page);
            
            // Получение списка клиентов
            $stmt = $db->prepare("
                SELECT * FROM clients 
                $where 
                ORDER BY fio 
                LIMIT $per_page OFFSET $offset
            ");
            $stmt->execute($params);
            $clients = $stmt->fetchAll();
            include 'header.php';
            ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Клиенты</h5>
                    <div>
                        <a href="clients.php?action=add" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-circle"></i> Добавить
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Поиск -->
                    <form method="GET" class="row g-2 mb-3">
                        <div class="col-md-8">
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Поиск по ФИО, телефону или email..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-outline-primary w-100">
                                <i class="bi bi-search"></i> Найти
                            </button>
                        </div>
                        <div class="col-md-2">
                            <a href="clients.php" class="btn btn-outline-secondary w-100">Сбросить</a>
                        </div>
                        <input type="hidden" name="action" value="list">
                    </form>
                    
                    <!-- Таблица клиентов -->
                    <?php if ($clients): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ФИО</th>
                                        <th>Телефон</th>
                                        <th>Email</th>
                                        <th>Посещений</th>
                                        <th>Всего потрачено</th>
                                        <th>Дата регистрации</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clients as $client): ?>
                                    <tr>
                                        <td>
                                            <a href="clients.php?action=view&id=<?php echo $client['id_client']; ?>">
                                                <?php echo htmlspecialchars($client['fio']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($client['phone']); ?></td>
                                        <td><?php echo $client['email'] ? htmlspecialchars($client['email']) : '-'; ?></td>
                                        <td><?php echo $client['visit_count']; ?></td>
                                        <td><?php echo number_format($client['total_spent'], 2); ?> ₽</td>
                                        <td><?php echo date('d.m.Y', strtotime($client['registration_date'])); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="clients.php?action=view&id=<?php echo $client['id_client']; ?>" 
                                                   class="btn btn-info" title="Просмотр">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="clients.php?action=edit&id=<?php echo $client['id_client']; ?>" 
                                                   class="btn btn-warning" title="Редактировать">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php if (in_array($user_role, ['администратор', 'менеджер'])): ?>
                                                <button type="button" class="btn btn-danger" 
                                                        onclick="if(confirm('Вы уверены, что хотите удалить этого клиента?')) window.location.href='clients.php?action=delete&id=<?php echo $client['id_client']; ?>'" 
                                                        title="Удалить">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                <?php endif; ?>
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
                                    <a class="page-link" href="clients.php?action=list&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-people" style="font-size: 48px;"></i>
                            <p class="mt-2">Клиенты не найдены</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            include 'footer.php';
    }
    
} catch (Exception $e) {

    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Ошибка - Клиенты</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    </head>
    <body>
        <div class="container mt-4">
            <div class="alert alert-danger">
                <h4>Ошибка в модуле клиентов</h4>
                <p><?php echo htmlspecialchars($e->getMessage()); ?></p>
                <a href="dashboard.php" class="btn btn-primary">На главную</a>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>