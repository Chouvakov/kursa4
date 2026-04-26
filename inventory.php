<?php
session_start();

$host = 'MySQL-8.4';
$dbname = 'cofeshop';
$user = 'root';
$pass = '';

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $db = new PDO($dsn, $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_role = $_SESSION['role'] ?? '';
$allowed_roles = ['менеджер', 'администратор', 'повар'];
if (!in_array($user_role, $allowed_roles)) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Доступ запрещен'];
    header("Location: dashboard.php");
    exit();
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

function formatMoney($amount) {
    return number_format($amount, 2, '.', ' ') . ' ₽';
}


$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;
$search = $_GET['search'] ?? '';
$low_stock = isset($_GET['low_stock']) ? 1 : 0;


if ($action == 'delete' && $user_role == 'администратор') {
    if (!$id || $id == 0) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Не указан ID ингредиента'];
        header("Location: inventory.php");
        exit();
    }
    
    try {
        
        $stmt = $db->prepare("SELECT ingredient_name FROM ingredients WHERE id_ingredient = ?");
        $stmt->execute([$id]);
        $ingredient = $stmt->fetch();
        
        if (!$ingredient) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Ингредиент не найден'];
            header("Location: inventory.php");
            exit();
        }
        
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM dish_ingredients WHERE id_ingredient = ?");
        $stmt->execute([$id]);
        $used_count = $stmt->fetchColumn();
        
       
        $db->beginTransaction();
        
     
        if ($used_count > 0) {
            $stmt = $db->prepare("DELETE FROM dish_ingredients WHERE id_ingredient = ?");
            $stmt->execute([$id]);
        }
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM supply_details WHERE id_ingredient = ?");
        $stmt->execute([$id]);
        $used_in_supplies = $stmt->fetchColumn();
        
        if ($used_in_supplies > 0) {
            $stmt = $db->prepare("DELETE FROM supply_details WHERE id_ingredient = ?");
            $stmt->execute([$id]);
        }
        
        $stmt = $db->prepare("DELETE FROM ingredients WHERE id_ingredient = ?");
        $stmt->execute([$id]);

        $db->commit();
        
        $message = 'Ингредиент "' . htmlspecialchars($ingredient['ingredient_name']) . '" удален';
        if ($used_count > 0 || $used_in_supplies > 0) {
            $details = [];
            if ($used_count > 0) $details[] = "{$used_count} блюд";
            if ($used_in_supplies > 0) $details[] = "{$used_in_supplies} поставок";
            $message .= '. Также удалены связи с ' . implode(', ', $details);
        }
        $_SESSION['flash'] = ['type' => 'success', 'message' => $message];
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Ошибка при удалении: ' . $e->getMessage()];
    }
    header("Location: inventory.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'add':
            if ($user_role == 'администратор' || $user_role == 'менеджер') {
                $ingredient_name = trim($_POST['ingredient_name'] ?? '');
                $unit = trim($_POST['unit'] ?? '');
                $current_quantity = floatval($_POST['current_quantity'] ?? 0);
                $min_quantity = floatval($_POST['min_quantity'] ?? 0);
                $cost_per_unit = floatval($_POST['cost_per_unit'] ?? 0);
                
                if (empty($ingredient_name)) {
                    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Название ингредиента обязательно'];
                } else {
                    try {
                        $stmt = $db->prepare("
                            INSERT INTO ingredients (ingredient_name, unit, current_quantity, min_quantity, cost_per_unit)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$ingredient_name, $unit, $current_quantity, $min_quantity, $cost_per_unit]);
                        
                        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Ингредиент добавлен'];
                        header("Location: inventory.php");
                        exit();
                    } catch (Exception $e) {
                        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Ошибка при добавлении: ' . $e->getMessage()];
                    }
                }
            }
            break;
            
        case 'edit':
            if ($user_role == 'администратор' || $user_role == 'менеджер') {
                $ingredient_name = trim($_POST['ingredient_name'] ?? '');
                $unit = trim($_POST['unit'] ?? '');
                $current_quantity = floatval($_POST['current_quantity'] ?? 0);
                $min_quantity = floatval($_POST['min_quantity'] ?? 0);
                $cost_per_unit = floatval($_POST['cost_per_unit'] ?? 0);
                
                if (empty($ingredient_name)) {
                    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Название ингредиента обязательно'];
                } else {
                    try {
                        $stmt = $db->prepare("
                            UPDATE ingredients 
                            SET ingredient_name = ?, unit = ?, current_quantity = ?, min_quantity = ?, cost_per_unit = ?
                            WHERE id_ingredient = ?
                        ");
                        $stmt->execute([$ingredient_name, $unit, $current_quantity, $min_quantity, $cost_per_unit, $id]);
                        
                        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Ингредиент обновлен'];
                        header("Location: inventory.php");
                        exit();
                    } catch (Exception $e) {
                        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Ошибка при обновлении: ' . $e->getMessage()];
                    }
                }
            }
            break;
            
        // Удаление обрабатывается выше, до блока POST (так как вызывается через GET)
    }
}


function renderHeader($title) {
    global $user_role;
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> - АИС Кафе</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
        <style>
            body {
                background-color: #f8f9fa;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            .navbar-brand {
                font-weight: 600;
            }
            .card {
                border-radius: 10px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                border: none;
                margin-bottom: 20px;
            }
            .card-header {
                border-radius: 10px 10px 0 0 !important;
                font-weight: 500;
            }
            .badge-low {
                background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
                color: white;
            }
            .badge-normal {
                background: linear-gradient(135deg, #20c997 0%, #1baa7a 100%);
                color: white;
            }
            .badge-warning {
                background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
                color: #212529;
            }
            .stat-card {
                border-radius: 10px;
                padding: 15px;
                color: white;
                text-align: center;
                height: 100%;
                margin-bottom: 15px;
            }
            .stat-success {
                background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            }
            .stat-warning {
                background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            }
            .stat-danger {
                background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
            }
            .table-hover tbody tr:hover {
                background-color: rgba(0,123,255,0.05);
            }
            .btn-action {
                padding: 0.25rem 0.5rem;
                font-size: 0.875rem;
            }
        </style>
    </head>
    <body>
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
            <div class="container-fluid">
                <a class="navbar-brand" href="dashboard.php">
                    <i class="bi bi-cup-hot me-2"></i>АИС Кафе
                </a>
                <div class="d-flex">
                    <a href="dashboard.php" class="btn btn-outline-light btn-sm me-2">
                        <i class="bi bi-house-door"></i> Назад
                    </a>
                </div>
            </div>
        </nav>
        
        <div class="container-fluid mt-4">
            <div class="row">
                <div class="col-lg-10 mx-auto">
    <?php
}

function renderFooter() {
    ?>
                </div>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            function confirmDelete(ingredientName) {
                return confirm('Вы уверены, что хотите удалить ингредиент "' + ingredientName + '"?');
            }
            document.addEventListener('DOMContentLoaded', function() {
                const rows = document.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    const current = parseFloat(row.querySelector('td:nth-child(3)')?.textContent.replace(',', '') || 0);
                    const min = parseFloat(row.querySelector('td:nth-child(4)')?.textContent.replace(',', '') || 0);
                    if (current < min) {
                        row.classList.add('table-danger');
                    }
                });
            });
        </script>
    </body>
    </html>
    <?php
}

try {
    switch ($action) {
        case 'add':
            if ($user_role == 'администратор' || $user_role == 'менеджер') {
                renderHeader('Добавление ингредиента');
                echo showFlashMessage();
                ?>
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Добавление нового ингредиента</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Название ингредиента *</label>
                                    <input type="text" class="form-control" name="ingredient_name" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Единица измерения</label>
                                    <select class="form-select" name="unit" required>
                                        <option value="кг">кг</option>
                                        <option value="г">г</option>
                                        <option value="л">л</option>
                                        <option value="мл">мл</option>
                                        <option value="шт">шт</option>
                                        <option value="уп">уп</option>
                                        <option value="пак">пак</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Стоимость за единицу (₽)</label>
                                    <input type="number" class="form-control" name="cost_per_unit" step="0.01" min="0" value="0">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Текущее количество</label>
                                    <input type="number" class="form-control" name="current_quantity" step="0.001" min="0" value="0">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Минимальное количество</label>
                                    <input type="number" class="form-control" name="min_quantity" step="0.001" min="0" value="0">
                                    <small class="text-muted">При достижении этого уровня появится предупреждение</small>
                                </div>
                                <div class="col-md-4 mb-3 d-flex align-items-end">
                                    <div class="w-100">
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="bi bi-check-circle me-2"></i>Добавить
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <a href="inventory.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>Отмена
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                <?php
                renderFooter();
            }
            break;
            
        case 'edit':
            if ($user_role == 'администратор' || $user_role == 'менеджер') {
                $stmt = $db->prepare("SELECT * FROM ingredients WHERE id_ingredient = ?");
                $stmt->execute([$id]);
                $ingredient = $stmt->fetch();
                
                if (!$ingredient) {
                    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Ингредиент не найден'];
                    header("Location: inventory.php");
                    exit();
                }
                
                renderHeader('Редактирование ингредиента');
                echo showFlashMessage();
                ?>
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Редактирование ингредиента</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Название ингредиента *</label>
                                    <input type="text" class="form-control" name="ingredient_name" 
                                           value="<?php echo htmlspecialchars($ingredient['ingredient_name']); ?>" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Единица измерения</label>
                                    <select class="form-select" name="unit" required>
                                        <option value="кг" <?php echo $ingredient['unit'] == 'кг' ? 'selected' : ''; ?>>кг</option>
                                        <option value="г" <?php echo $ingredient['unit'] == 'г' ? 'selected' : ''; ?>>г</option>
                                        <option value="л" <?php echo $ingredient['unit'] == 'л' ? 'selected' : ''; ?>>л</option>
                                        <option value="мл" <?php echo $ingredient['unit'] == 'мл' ? 'selected' : ''; ?>>мл</option>
                                        <option value="шт" <?php echo $ingredient['unit'] == 'шт' ? 'selected' : ''; ?>>шт</option>
                                        <option value="уп" <?php echo $ingredient['unit'] == 'уп' ? 'selected' : ''; ?>>уп</option>
                                        <option value="пак" <?php echo $ingredient['unit'] == 'пак' ? 'selected' : ''; ?>>пак</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Стоимость за единицу (₽)</label>
                                    <input type="number" class="form-control" name="cost_per_unit" step="0.01" min="0" 
                                           value="<?php echo htmlspecialchars($ingredient['cost_per_unit']); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Текущее количество</label>
                                    <input type="number" class="form-control" name="current_quantity" step="0.001" min="0" 
                                           value="<?php echo htmlspecialchars($ingredient['current_quantity']); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Минимальное количество</label>
                                    <input type="number" class="form-control" name="min_quantity" step="0.001" min="0" 
                                           value="<?php echo htmlspecialchars($ingredient['min_quantity']); ?>">
                                    <small class="text-muted">При достижении этого уровня появится предупреждение</small>
                                </div>
                                <div class="col-md-4 mb-3 d-flex align-items-end">
                                    <div class="w-100">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="bi bi-save me-2"></i>Сохранить изменения
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <a href="inventory.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>Отмена
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                <?php
                renderFooter();
            }
            break;
            
        default: // list
            $total_ingredients = $db->query("SELECT COUNT(*) FROM ingredients")->fetchColumn();
            $low_stock_count = $db->query("
                SELECT COUNT(*) 
                FROM ingredients 
                WHERE current_quantity < min_quantity
            ")->fetchColumn();
            $total_value = $db->query("
                SELECT SUM(current_quantity * cost_per_unit) 
                FROM ingredients
            ")->fetchColumn();
            
            $where = [];
            $params = [];
            
            if ($low_stock) {
                $where[] = "i.current_quantity < i.min_quantity";
            }
            
            if ($search) {
                $where[] = "i.ingredient_name LIKE ?";
                $params[] = "%$search%";
            }
            
            $where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";
            
            $sql = "
                SELECT i.*,
                       (SELECT COUNT(*) FROM dish_ingredients di WHERE di.id_ingredient = i.id_ingredient) as used_in_dishes
                FROM ingredients i
                $where_clause
                ORDER BY 
                    CASE WHEN i.current_quantity < i.min_quantity THEN 0 ELSE 1 END,
                    i.ingredient_name
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $ingredients = $stmt->fetchAll();
            
            renderHeader('Управление инвентарем');
            echo showFlashMessage();
            ?>
            
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stat-card stat-success">
                        <h6><i class="bi bi-box-seam me-2"></i>Всего ингредиентов</h6>
                        <h2 class="mt-2"><?php echo $total_ingredients; ?></h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card stat-warning">
                        <h6><i class="bi bi-exclamation-triangle me-2"></i>Низкий запас</h6>
                        <h2 class="mt-2"><?php echo $low_stock_count; ?></h2>
                        <?php if ($low_stock_count > 0): ?>
                        <a href="inventory.php?low_stock=1" class="btn btn-light btn-sm mt-2">Показать</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card stat-danger">
                        <h6><i class="bi bi-cash-stack me-2"></i>Общая стоимость</h6>
                        <h4 class="mt-2"><?php echo formatMoney($total_value ?? 0); ?></h4>
                    </div>
                </div>
            </div>
            
            <!-- Поиск и фильтры -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <form method="GET" class="row g-2">
                                <div class="col">
                                    <input type="text" class="form-control" name="search" 
                                           placeholder="Поиск по названию..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i> Найти
                                    </button>
                                </div>
                                <?php if ($search || $low_stock): ?>
                                <div class="col-auto">
                                    <a href="inventory.php" class="btn btn-secondary">Сбросить</a>
                                </div>
                                <?php endif; ?>
                            </form>
                        </div>
                        <div class="col-md-6 d-flex justify-content-end">
                            <div class="btn-group">
                                <?php if ($user_role == 'администратор' || $user_role == 'менеджер'): ?>
                                <a href="inventory.php?action=add" class="btn btn-success">
                                    <i class="bi bi-plus-circle me-2"></i>Добавить ингредиент
                                </a>
                                <?php endif; ?>
                                <a href="dashboard.php" class="btn btn-outline-primary">
                                    <i class="bi bi-arrow-left me-2"></i>На главную
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="lowStockCheck" 
                                   onclick="window.location.href='inventory.php?low_stock=' + (this.checked ? '1' : '0')"
                                   <?php echo $low_stock ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="lowStockCheck">
                                Показать только с низким запасом
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Таблица ингредиентов -->
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-list-ul me-2"></i>Список ингредиентов
                        <?php if ($low_stock): ?>
                        <span class="badge bg-warning ms-2">Низкий запас</span>
                        <?php endif; ?>
                    </h5>
                    <small>Всего: <?php echo count($ingredients); ?></small>
                </div>
                <div class="card-body p-0">
                    <?php if ($ingredients): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Название</th>
                                        <th>Единица</th>
                                        <th>Текущее количество</th>
                                        <th>Минимум</th>
                                        <th>Статус</th>
                                        <th>Стоимость/ед.</th>
                                        <th>Используется в</th>
                                        <?php if ($user_role == 'администратор' || $user_role == 'менеджер'): ?>
                                        <th>Действия</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ingredients as $ing): 
                                        $is_low = $ing['current_quantity'] < $ing['min_quantity'];
                                        $status_class = $is_low ? 'badge-low' : 'badge-normal';
                                        $status_text = $is_low ? 'Низкий запас' : 'В норме';
                                    ?>
                                    <tr class="<?php echo $is_low ? 'table-danger' : ''; ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($ing['ingredient_name']); ?></strong>
                                            <?php if ($is_low): ?>
                                            <i class="bi bi-exclamation-triangle text-danger ms-1"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($ing['unit']); ?></td>
                                        <td class="fw-bold"><?php echo number_format($ing['current_quantity'], 3); ?></td>
                                        <td><?php echo number_format($ing['min_quantity'], 3); ?></td>
                                        <td>
                                            <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                        </td>
                                        <td><?php echo formatMoney($ing['cost_per_unit']); ?></td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo $ing['used_in_dishes']; ?> блюд</span>
                                        </td>
                                        <?php if ($user_role == 'администратор' || $user_role == 'менеджер'): ?>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="inventory.php?action=edit&id=<?php echo $ing['id_ingredient']; ?>" 
                                                   class="btn btn-warning btn-action" title="Редактировать">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php if ($user_role == 'администратор'): ?>
                                                <button onclick="if(confirmDelete('<?php echo addslashes($ing['ingredient_name']); ?>')) 
                                                    window.location.href='inventory.php?action=delete&id=<?php echo $ing['id_ingredient']; ?>'" 
                                                    class="btn btn-danger btn-action" title="Удалить"
                                                    <?php if ($ing['used_in_dishes'] > 0): ?>
                                                    data-bs-toggle="tooltip" data-bs-placement="top" 
                                                    title="Внимание: ингредиент используется в <?php echo $ing['used_in_dishes']; ?> блюдах"
                                                    <?php endif; ?>>
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-box-seam" style="font-size: 64px;"></i>
                            <h5 class="mt-3">Ингредиенты не найдены</h5>
                            <?php if ($low_stock || $search): ?>
                            <p class="mb-0">Попробуйте изменить критерии поиска</p>
                            <a href="inventory.php" class="btn btn-primary mt-3">
                                <i class="bi bi-arrow-clockwise me-2"></i>Сбросить фильтры
                            </a>
                            <?php else: ?>
                            <p class="mb-0">База данных ингредиентов пуста</p>
                            <?php if ($user_role == 'администратор' || $user_role == 'менеджер'): ?>
                            <a href="inventory.php?action=add" class="btn btn-success mt-3">
                                <i class="bi bi-plus-circle me-2"></i>Добавить первый ингредиент
                            </a>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            renderFooter();
            break;
    }
} catch (Exception $e) {
    renderHeader('Ошибка');
    echo '<div class="alert alert-danger">Ошибка: ' . htmlspecialchars($e->getMessage()) . '</div>';
    renderFooter();
}
?>