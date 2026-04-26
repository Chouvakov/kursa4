<?php
session_start();
require_once 'db_connection.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

if (!checkRole('менеджер') && !checkRole('администратор') && !checkRole('повар')) {
    setFlashMessage('error', 'Доступ запрещен');
    redirect('dashboard.php');
}

$action = getGet('action', 'list');
$id = getGet('id', 0);
$category_id = getGet('category_id', '');

try {
    global $db;
    
    switch ($action) {
        case 'add_dish':
        case 'edit_dish':
            $dish = null;
            if ($action == 'edit_dish' && $id) {
                $stmt = $db->prepare("
                    SELECT d.*, dc.category_name 
                    FROM dishes d
                    LEFT JOIN dish_categories dc ON d.id_category = dc.id_category
                    WHERE d.id_dish = ?
                ");
                $stmt->execute([$id]);
                $dish = $stmt->fetch();
                
                if (!$dish) {
                    setFlashMessage('error', 'Блюдо не найдено');
                    redirect('menu.php');
                }
                
                $stmt = $db->prepare("
                    SELECT di.*, i.ingredient_name, i.unit 
                    FROM dish_ingredients di
                    LEFT JOIN ingredients i ON di.id_ingredient = i.id_ingredient
                    WHERE di.id_dish = ?
                ");
                $stmt->execute([$id]);
                $ingredients = $stmt->fetchAll();
            }
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $dish_name = getPost('dish_name');
                $id_category = getPost('id_category');
                $description = getPost('description');
                $price = getPost('price');
                $cost_price = getPost('cost_price');
                $preparation_time = getPost('preparation_time');
                $is_available = getPost('is_available', 0);
                
                if ($action == 'add_dish') {
                    $stmt = $db->prepare("
                        INSERT INTO dishes (dish_name, id_category, description, price, 
                                           cost_price, preparation_time, is_available)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$dish_name, $id_category, $description, $price, 
                                   $cost_price, $preparation_time, $is_available]);
                    
                    $dish_id = $db->lastInsertId();
                    $message = 'Блюдо добавлено';
                } else {
                    $stmt = $db->prepare("
                        UPDATE dishes 
                        SET dish_name = ?, id_category = ?, description = ?, price = ?,
                            cost_price = ?, preparation_time = ?, is_available = ?
                        WHERE id_dish = ?
                    ");
                    $stmt->execute([$dish_name, $id_category, $description, $price, 
                                   $cost_price, $preparation_time, $is_available, $id]);
                    
                    $dish_id = $id;
                    $message = 'Блюдо обновлено';
                }
                
                $ingredient_ids = $_POST['ingredient_id'] ?? [];
                $quantities = $_POST['quantity'] ?? [];
                
                $stmt = $db->prepare("DELETE FROM dish_ingredients WHERE id_dish = ?");
                $stmt->execute([$dish_id]);
                
                for ($i = 0; $i < count($ingredient_ids); $i++) {
                    if ($ingredient_ids[$i] && $quantities[$i] > 0) {
                        $stmt = $db->prepare("
                            INSERT INTO dish_ingredients (id_dish, id_ingredient, quantity_needed)
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([$dish_id, $ingredient_ids[$i], $quantities[$i]]);
                    }
                }
                
                setFlashMessage('success', $message);
                redirect('menu.php?action=edit_dish&id=' . $dish_id);
            }
            
            $categories = $db->query("SELECT * FROM dish_categories ORDER BY category_name")->fetchAll();
            $all_ingredients = $db->query("SELECT * FROM ingredients ORDER BY ingredient_name")->fetchAll();
            
            include 'header.php';
            ?>
            <div class="card">
                <div class="card-header">
                    <h5><?php echo $action == 'add_dish' ? 'Добавление блюда' : 'Редактирование блюда'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="dishForm">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Название блюда *</label>
                                    <input type="text" class="form-control" name="dish_name" 
                                           value="<?php echo $dish['dish_name'] ?? ''; ?>" required>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Категория</label>
                                        <select class="form-select" name="id_category">
                                            <option value="">Без категории</option>
                                            <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo $cat['id_category']; ?>" 
                                                    <?php echo ($dish['id_category'] ?? '') == $cat['id_category'] ? 'selected' : ''; ?>>
                                                <?php echo $cat['category_name']; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Цена (₽) *</label>
                                        <input type="number" class="form-control" name="price" 
                                               value="<?php echo $dish['price'] ?? ''; ?>" step="0.01" min="0" required>
                                    </div>
                                    
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Себестоимость (₽)</label>
                                        <input type="number" class="form-control" name="cost_price" 
                                               value="<?php echo $dish['cost_price'] ?? ''; ?>" step="0.01" min="0">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Время приготовления (мин)</label>
                                        <input type="number" class="form-control" name="preparation_time" 
                                               value="<?php echo $dish['preparation_time'] ?? ''; ?>" min="1">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Доступность</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_available" value="1" 
                                                   id="is_available" <?php echo ($dish['is_available'] ?? 1) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="is_available">
                                                Доступно для заказа
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Описание</label>
                                    <textarea class="form-control" name="description" rows="3"><?php echo $dish['description'] ?? ''; ?></textarea>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <h6>Ингредиенты:</h6>
                                <div id="ingredientsContainer">
                                    <?php if (!empty($ingredients)): ?>
                                        <?php foreach ($ingredients as $index => $ing): ?>
                                        <div class="ingredient-row mb-2">
                                            <div class="input-group">
                                                <select class="form-select form-select-sm" name="ingredient_id[]" required>
                                                    <option value="">Выберите ингредиент</option>
                                                    <?php foreach ($all_ingredients as $ingredient): ?>
                                                    <option value="<?php echo $ingredient['id_ingredient']; ?>" 
                                                            <?php echo $ing['id_ingredient'] == $ingredient['id_ingredient'] ? 'selected' : ''; ?>>
                                                        <?php echo $ingredient['ingredient_name']; ?> (<?php echo $ingredient['unit']; ?>)
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="number" class="form-control form-control-sm" 
                                                       name="quantity[]" value="<?php echo $ing['quantity_needed']; ?>" 
                                                       step="0.001" min="0.001" placeholder="Кол-во" required>
                                                <button type="button" class="btn btn-sm btn-danger remove-ingredient">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                    <div class="ingredient-row mb-2">
                                        <div class="input-group">
                                            <select class="form-select form-select-sm" name="ingredient_id[]">
                                                <option value="">Выберите ингредиент</option>
                                                <?php foreach ($all_ingredients as $ingredient): ?>
                                                <option value="<?php echo $ingredient['id_ingredient']; ?>">
                                                    <?php echo $ingredient['ingredient_name']; ?> (<?php echo $ingredient['unit']; ?>)
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="number" class="form-control form-control-sm" 
                                                   name="quantity[]" step="0.001" min="0.001" placeholder="Кол-во">
                                            <button type="button" class="btn btn-sm btn-danger remove-ingredient">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <button type="button" class="btn btn-sm btn-success mt-2" id="addIngredient">
                                    <i class="bi bi-plus-circle me-1"></i> Добавить ингредиент
                                </button>
                                
                                <hr>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle me-1"></i> Сохранить
                                    </button>
                                    <a href="menu.php" class="btn btn-secondary">Отмена</a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <script>
            document.getElementById('addIngredient').addEventListener('click', function() {
                const container = document.getElementById('ingredientsContainer');
                const row = document.createElement('div');
                row.className = 'ingredient-row mb-2';
                row.innerHTML = `
                    <div class="input-group">
                        <select class="form-select form-select-sm" name="ingredient_id[]">
                            <option value="">Выберите ингредиент</option>
                            <?php foreach ($all_ingredients as $ingredient): ?>
                            <option value="<?php echo $ingredient['id_ingredient']; ?>">
                                <?php echo $ingredient['ingredient_name']; ?> (<?php echo $ingredient['unit']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" class="form-control form-control-sm" 
                               name="quantity[]" step="0.001" min="0.001" placeholder="Кол-во">
                        <button type="button" class="btn btn-sm btn-danger remove-ingredient">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                `;
                container.appendChild(row);
            });
            
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-ingredient')) {
                    e.target.closest('.ingredient-row').remove();
                }
            });
            </script>
            <?php
            break;
            
        case 'delete_dish':
            if (checkRole('администратор') || checkRole('менеджер')) {
                $stmt = $db->prepare("SELECT dish_name, price FROM dishes WHERE id_dish = ?");
                $stmt->execute([$id]);
                $dish_info = $stmt->fetch();
                
                if (!$dish_info) {
                    setFlashMessage('error', 'Блюдо не найдено');
                    redirect('menu.php');
                    break;
                }
                $stmt = $db->prepare("
                    SELECT COUNT(*) as active_count
                    FROM order_items oi
                    INNER JOIN orders o ON oi.id_order = o.id_order
                    WHERE oi.id_dish = ? 
                    AND o.status NOT IN ('оплачен', 'отменен')
                ");
                $stmt->execute([$id]);
                $active_result = $stmt->fetch();
                $stmt = $db->prepare("SELECT COUNT(*) as total_count FROM order_items WHERE id_dish = ?");
                $stmt->execute([$id]);
                $total_result = $stmt->fetch();
                
                if ($active_result['active_count'] > 0) {
                    setFlashMessage('error', 'Нельзя удалить блюдо: оно используется в ' . $active_result['active_count'] . ' активных заказах. Сначала завершите эти заказы.');
                } else if ($total_result['total_count'] > 0) {
                    try {
                        $columns = $db->query("SHOW COLUMNS FROM order_items LIKE 'dish_name'")->fetch();
                        if (!$columns) {
                            $db->exec("ALTER TABLE order_items ADD COLUMN dish_name VARCHAR(255) NULL AFTER id_dish");
                        }
                        $id_dish_info = $db->query("SHOW COLUMNS FROM order_items WHERE Field = 'id_dish'")->fetch();
                        if ($id_dish_info && strpos($id_dish_info['Null'], 'NO') !== false) {
                            $db->exec("ALTER TABLE order_items MODIFY COLUMN id_dish INT NULL");
                        }
                        $db->beginTransaction();
                        $stmt = $db->prepare("
                            UPDATE order_items 
                            SET dish_name = ?, id_dish = NULL 
                            WHERE id_dish = ?
                        ");
                        $stmt->execute([$dish_info['dish_name'], $id]);
                        $stmt = $db->prepare("DELETE FROM dish_ingredients WHERE id_dish = ?");
                        $stmt->execute([$id]);
                        $stmt = $db->prepare("DELETE FROM dishes WHERE id_dish = ?");
                        $stmt->execute([$id]);
                        if ($db->inTransaction()) {
                            $db->commit();
                        }
                        setFlashMessage('success', 'Блюдо удалено. История заказов сохранена (было использовано в ' . $total_result['total_count'] . ' завершенных заказах).');
                    } catch (Exception $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        setFlashMessage('error', 'Ошибка при удалении блюда: ' . $e->getMessage());
                    }
                } else {
                    try {
                        $db->beginTransaction();
                        $stmt = $db->prepare("DELETE FROM dish_ingredients WHERE id_dish = ?");
                        $stmt->execute([$id]);
                        $stmt = $db->prepare("DELETE FROM dishes WHERE id_dish = ?");
                        $stmt->execute([$id]);
                        if ($db->inTransaction()) {
                            $db->commit();
                        }
                        setFlashMessage('success', 'Блюдо удалено');
                    } catch (Exception $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        setFlashMessage('error', 'Ошибка при удалении блюда: ' . $e->getMessage());
                    }
                }
            } else {
                setFlashMessage('error', 'Недостаточно прав для удаления блюда');
            }
            redirect('menu.php');
            break;
            
        case 'categories':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $category_name = getPost('category_name');
                $description = getPost('description');
                
                if (isset($_POST['edit_id'])) {
                    $stmt = $db->prepare("
                        UPDATE dish_categories 
                        SET category_name = ?, description = ? 
                        WHERE id_category = ?
                    ");
                    $stmt->execute([$category_name, $description, getPost('edit_id')]);
                    $message = 'Категория обновлена';
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO dish_categories (category_name, description) 
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$category_name, $description]);
                    $message = 'Категория добавлена';
                }
                
                setFlashMessage('success', $message);
                redirect('menu.php?action=categories');
            }
            
            if (isset($_GET['delete_category'])) {
                $cat_id = getGet('delete_category');
                $stmt = $db->prepare("SELECT COUNT(*) FROM dishes WHERE id_category = ?");
                $stmt->execute([$cat_id]);
                $count = $stmt->fetchColumn();
                
                if ($count == 0) {
                    $stmt = $db->prepare("DELETE FROM dish_categories WHERE id_category = ?");
                    $stmt->execute([$cat_id]);
                    setFlashMessage('success', 'Категория удалена');
                } else {
                    setFlashMessage('error', 'Нельзя удалить категорию, в которой есть блюда');
                }
                redirect('menu.php?action=categories');
            }
            
            $categories = $db->query("SELECT * FROM dish_categories ORDER BY category_name")->fetchAll();
            $edit_category = null;
            
            if (isset($_GET['edit_category'])) {
                $stmt = $db->prepare("SELECT * FROM dish_categories WHERE id_category = ?");
                $stmt->execute([getGet('edit_category')]);
                $edit_category = $stmt->fetch();
            }
            
            include 'header.php';
            ?>
            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><?php echo $edit_category ? 'Редактирование' : 'Добавление'; ?> категории</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <?php if ($edit_category): ?>
                                <input type="hidden" name="edit_id" value="<?php echo $edit_category['id_category']; ?>">
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label class="form-label">Название категории *</label>
                                    <input type="text" class="form-control" name="category_name" 
                                           value="<?php echo $edit_category['category_name'] ?? ''; ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Описание</label>
                                    <textarea class="form-control" name="description" rows="3"><?php echo $edit_category['description'] ?? ''; ?></textarea>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <?php echo $edit_category ? 'Обновить' : 'Добавить'; ?>
                                    </button>
                                    <?php if ($edit_category): ?>
                                    <a href="menu.php?action=categories" class="btn btn-secondary">Отмена</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Все категории</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($categories): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Название</th>
                                                <th>Описание</th>
                                                <th>Действия</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($categories as $cat): 
                                                $stmt = $db->prepare("SELECT COUNT(*) FROM dishes WHERE id_category = ?");
                                                $stmt->execute([$cat['id_category']]);
                                                $dishes_count = $stmt->fetchColumn();
                                            ?>
                                            <tr>
                                                <td><?php echo $cat['category_name']; ?></td>
                                                <td><?php echo $cat['description'] ?: '—'; ?></td>
                                                <td>
                                                    <div class="menu-actions">
                                                        <a href="menu.php?action=categories&edit_category=<?php echo $cat['id_category']; ?>" 
                                                           class="btn btn-warning btn-sm me-1">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <?php if ($dishes_count == 0): ?>
                                                        <button type="button" class="btn btn-danger btn-sm" 
                                                                onclick="if(confirm('Удалить категорию?')) window.location.href='menu.php?action=categories&delete_category=<?php echo $cat['id_category']; ?>'">
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
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-tags" style="font-size: 3rem;"></i>
                                    <p class="mt-3">Категории не найдены</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            break;
            
        default:
            $where = '';
            $params = [];
            
            if ($category_id !== '') {
                $where = "WHERE d.id_category = ?";
                $params[] = $category_id;
            }
            
            $sql = "
                SELECT d.*, dc.category_name,
                       (SELECT COUNT(*) FROM dish_ingredients di WHERE di.id_dish = d.id_dish) as ingredients_count
                FROM dishes d
                LEFT JOIN dish_categories dc ON d.id_category = dc.id_category
                $where
                ORDER BY dc.category_name, d.dish_name
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $dishes = $stmt->fetchAll();
            
            $categories = $db->query("SELECT * FROM dish_categories ORDER BY category_name")->fetchAll();
            
            include 'header.php';
            ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Меню</h5>
                    <div>
                        <a href="menu.php?action=categories" class="btn btn-outline-primary btn-sm me-2">
                            <i class="bi bi-tags me-1"></i> Категории
                        </a>
                        <a href="menu.php?action=add_dish" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-circle me-1"></i> Добавить блюдо
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <select class="form-select" onchange="window.location.href='menu.php?category_id=' + this.value">
                                <option value="">Все категории</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id_category']; ?>" 
                                        <?php echo $category_id == $cat['id_category'] ? 'selected' : ''; ?>>
                                    <?php echo $cat['category_name']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <?php foreach ($dishes as $dish): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card dish-card">
                                <?php if ($dish['image_url']): ?>
                                <img src="<?php echo $dish['image_url']; ?>" class="card-img-top" alt="<?php echo $dish['dish_name']; ?>">
                                <?php endif; ?>
                                
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <?php echo $dish['dish_name']; ?>
                                        <?php if (!$dish['is_available']): ?>
                                        <span class="badge bg-danger float-end">Не доступно</span>
                                        <?php endif; ?>
                                    </h6>
                                    
                                    <p class="card-text">
                                        <small class="text-muted"><?php echo $dish['category_name'] ?: 'Без категории'; ?></small>
                                        <br>
                                        <?php if ($dish['description']): ?>
                                        <?php echo mb_strimwidth($dish['description'], 0, 80, '...'); ?>
                                        <?php endif; ?>
                                    </p>
                                    
                                    <div class="mb-2">
                                        <span class="dish-price"><?php echo formatMoney($dish['price']); ?></span>
                                        <?php if ($dish['cost_price']): ?>
                                        <br><small class="text-muted">Себестоимость: <?php echo formatMoney($dish['cost_price']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($dish['preparation_time']): ?>
                                    <p class="mb-2"><small><i class="bi bi-clock me-1"></i><?php echo $dish['preparation_time']; ?> мин</small></p>
                                    <?php endif; ?>
                                    
                                    <p class="mb-2"><small><i class="bi bi-box me-1"></i>Ингредиентов: <?php echo $dish['ingredients_count']; ?></small></p>
                                </div>
                                
                                <div class="card-footer">
                                    <div class="menu-actions d-grid gap-2">
                                        <a href="menu.php?action=edit_dish&id=<?php echo $dish['id_dish']; ?>" 
                                           class="btn btn-warning">
                                            <i class="bi bi-pencil me-1"></i> Редактировать
                                        </a>
                                        <?php if (checkRole('администратор') || checkRole('менеджер')): ?>
                                        <button type="button" class="btn btn-danger"
                                                onclick="if(confirm('Удалить блюдо?')) window.location.href='menu.php?action=delete_dish&id=<?php echo $dish['id_dish']; ?>'">
                                            <i class="bi bi-trash me-1"></i> Удалить
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (empty($dishes)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-egg-fried" style="font-size: 3rem;"></i>
                        <p class="mt-3">Блюда не найдены</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
    }
    
} catch (Exception $e) {
    setFlashMessage('error', 'Ошибка: ' . $e->getMessage());
    redirect('menu.php');
}

include 'footer.php';
?>