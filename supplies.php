<?php
require_once 'db_connection.php';
require_once 'functions.php';
if (!isLoggedIn()) {
    redirect('index.php');
}
$user_role = getUserRole();
$allowed_roles = ['менеджер', 'администратор'];
if (!in_array($user_role, $allowed_roles)) {
    setFlashMessage('error', 'Доступ запрещен');
    redirect('dashboard.php');
}
$action = getGet('action', 'list');
$id = getGet('id', 0);
$status_filter = '';
$supplier_filter = getGet('supplier', '');
$date_from = getGet('date_from', date('Y-m-d', strtotime('-30 days')));
$date_to = getGet('date_to', date('Y-m-d'));

try {
    global $db;
    try {
        $columns = $db->query("SHOW COLUMNS FROM supplies")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        die("Ошибка таблицы supplies: " . $e->getMessage());
    }
    
    $has_received_by = in_array('received_by', $columns);
    $has_total_cost = in_array('total_cost', $columns);
    $has_total_amount = in_array('total_amount', $columns);
    try {
        $details_columns = $db->query("SHOW COLUMNS FROM supply_details")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        die("Ошибка таблицы supply_details: " . $e->getMessage());
    }
        $supply_total_field = 'total_cost';
        $supply_total_field_display = 'total_cost';
    $details_total_field = 'computed_total';
    $employees = [];
    if ($has_received_by) {
        $employees = $db->query("SELECT DISTINCT fio FROM employees ORDER BY fio")->fetchAll();
    }
    
    switch ($action) {
        case 'add':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $supplier_name = getPost('supplier_name');
                $supply_date = getPost('supply_date', date('Y-m-d'));
                $received_by = $has_received_by ? trim(getPost('received_by', '')) : '';
                $total_cost = 0;
                $notes = getPost('notes', '');
                $errors = [];
                if (empty($supplier_name)) $errors[] = 'Название поставщика обязательно';
                if ($has_received_by && empty($received_by)) $errors[] = 'Необходимо выбрать сотрудника';
                
                if (empty($errors)) {
                    $db->beginTransaction();
                    
                    try {
                        if ($has_received_by) {
                            $stmt = $db->prepare("
                                INSERT INTO supplies (supplier_name, supply_date, received_by, total_cost, notes)
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $supplier_name,
                                $supply_date,
                                !empty($received_by) ? $received_by : null,
                                $total_cost,
                                $notes
                            ]);
                        } else {
                            $stmt = $db->prepare("
                                INSERT INTO supplies (supplier_name, supply_date, total_cost, notes)
                                VALUES (?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $supplier_name,
                                $supply_date,
                                $total_cost,
                                $notes
                            ]);
                        }
                        
                        $supply_id = $db->lastInsertId();
                        $ingredient_ids = $_POST['ingredient_id'] ?? [];
                        $quantities = $_POST['quantity'] ?? [];
                        $unit_prices = $_POST['unit_price'] ?? [];
                        
                        for ($i = 0; $i < count($ingredient_ids); $i++) {
                            if ($ingredient_ids[$i] && $quantities[$i] > 0) {
                                $item_total = $quantities[$i] * $unit_prices[$i];
                                $stmt = $db->prepare("
                                    INSERT INTO supply_details (id_supply, id_ingredient, quantity, unit_price)
                                    VALUES (?, ?, ?, ?)
                                ");
                                $stmt->execute([
                                    $supply_id,
                                    $ingredient_ids[$i],
                                    $quantities[$i],
                                    $unit_prices[$i]
                                ]);
                                $stmt = $db->prepare("UPDATE ingredients SET current_quantity = current_quantity + ? WHERE id_ingredient = ?");
                                $stmt->execute([$quantities[$i], $ingredient_ids[$i]]);
                                
                                $total_cost += $item_total;
                            }
                        }
                        
                        $db->commit();
                        setFlashMessage('success', 'Поставка добавлена');
                        redirect('supplies.php?action=view&id=' . $supply_id);
                        
                    } catch (Exception $e) {
                        $db->rollBack();
                        throw $e;
                    }
                } else {
                    setFlashMessage('error', implode('<br>', $errors));
                }
            }
            $ingredients = $db->query("SELECT * FROM ingredients ORDER BY ingredient_name")->fetchAll();
            
            include 'header.php';
            ?>
            <div class="card">
                <div class="card-header">
                    <h5>Добавление новой поставки</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="supplyForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Поставщик *</label>
                                <input type="text" class="form-control" name="supplier_name" required>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Дата поставки</label>
                                <input type="date" class="form-control" name="supply_date" 
                                       value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <?php if ($has_received_by): ?>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Принял *</label>
                                <select class="form-select" name="received_by" required>
                                    <option value="">Выберите сотрудника</option>
                                    <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo htmlspecialchars($emp['fio']); ?>">
                                        <?php echo htmlspecialchars($emp['fio']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Статус</label>
                                <select class="form-select" name="status">
                                    <option value="ожидается">Ожидается</option>
                                    <option value="в пути">В пути</option>
                                    <option value="доставлено">Доставлено</option>
                                    <option value="отменено">Отменено</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Общая сумма (авто)</label>
                                <input type="number" class="form-control" name="total_amount" 
                                       id="total_amount" value="0" readonly step="0.01">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Примечания</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                        
                        <h6>Детали поставки:</h6>
                        <div id="supplyDetails">
                            <div class="supply-detail-row row g-2 mb-2">
                                <div class="col-md-5">
                                    <select class="form-select ingredient-select" name="ingredient_id[]" required>
                                        <option value="">Выберите ингредиент</option>
                                        <?php foreach ($ingredients as $ing): ?>
                                        <option value="<?php echo $ing['id_ingredient']; ?>" 
                                                data-unit="<?php echo $ing['unit']; ?>"
                                                data-current="<?php echo $ing['current_quantity']; ?>"
                                                data-min="<?php echo $ing['min_quantity']; ?>">
                                            <?php echo $ing['ingredient_name']; ?> (<?php echo $ing['unit']; ?>)
                                            - Остаток: <?php echo $ing['current_quantity']; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" class="form-control quantity" 
                                           name="quantity[]" step="0.001" min="0.001" 
                                           placeholder="Кол-во" required>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" class="form-control unit-price" 
                                           name="unit_price[]" step="0.01" min="0" 
                                           placeholder="Цена за ед." required>
                                </div>
                                <div class="col-md-2">
                                    <input type="text" class="form-control total-price" 
                                           placeholder="Итого" readonly>
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-danger remove-detail">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <button type="button" class="btn btn-sm btn-success mt-2" id="addDetail">
                            <i class="bi bi-plus-circle"></i> Добавить позицию
                        </button>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between">
                            <a href="supplies.php" class="btn btn-secondary">Отмена</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Создать поставку
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const detailsContainer = document.getElementById('supplyDetails');
                const totalAmountInput = document.getElementById('total_amount');
                document.getElementById('addDetail').addEventListener('click', function() {
                    const newRow = detailsContainer.firstElementChild.cloneNode(true);
                    newRow.querySelectorAll('input').forEach(input => input.value = '');
                    newRow.querySelector('select').selectedIndex = 0;
                    newRow.querySelector('.total-price').value = '';
                    detailsContainer.appendChild(newRow);
                });
                detailsContainer.addEventListener('click', function(e) {
                    if (e.target.closest('.remove-detail')) {
                        const row = e.target.closest('.supply-detail-row');
                        if (detailsContainer.children.length > 1) {
                            row.remove();
                            updateTotal();
                        }
                    }
                });
                detailsContainer.addEventListener('input', function(e) {
                    if (e.target.classList.contains('quantity') || 
                        e.target.classList.contains('unit-price')) {
                        const row = e.target.closest('.supply-detail-row');
                        calculateRowTotal(row);
                        updateTotal();
                    }
                    
                    if (e.target.classList.contains('ingredient-select') && e.target.value) {
                        const option = e.target.options[e.target.selectedIndex];
                        const unit = option.getAttribute('data-unit');
                        const current = option.getAttribute('data-current');
                        const min = option.getAttribute('data-min');
                        e.target.title = `Остаток: ${current} ${unit}, Минимум: ${min} ${unit}`;
                    }
                });
                
                function calculateRowTotal(row) {
                    const quantity = parseFloat(row.querySelector('.quantity').value) || 0;
                    const unitPrice = parseFloat(row.querySelector('.unit-price').value) || 0;
                    const total = quantity * unitPrice;
                    row.querySelector('.total-price').value = total.toFixed(2);
                }
                
                function updateTotal() {
                    let total = 0;
                    document.querySelectorAll('.total-price').forEach(input => {
                        total += parseFloat(input.value) || 0;
                    });
                    totalAmountInput.value = total.toFixed(2);
                }
                document.querySelectorAll('.supply-detail-row').forEach(row => {
                    row.addEventListener('input', function() {
                        calculateRowTotal(this);
                        updateTotal();
                    });
                });
            });
            </script>
            <?php
            break;
        case 'edit':
            $supply = null;
            if ($id) {
                $stmt = $db->prepare("
                    SELECT s.*, 
                           s.received_by as received_by_name,
                           COALESCE((SELECT SUM(sd.quantity * sd.unit_price) FROM supply_details sd WHERE sd.id_supply = s.id_supply), 0) as computed_total
                    FROM supplies s
                    WHERE s.id_supply = ?
                ");
                $stmt->execute([$id]);
                $supply = $stmt->fetch();
                
                if (!$supply) {
                    setFlashMessage('error', 'Поставка не найдена');
                    redirect('supplies.php');
                }
                if (empty($supply[$supply_total_field_display]) || floatval($supply[$supply_total_field_display]) == 0) {
                    $supply[$supply_total_field_display] = $supply['computed_total'] ?? 0;
            }
            }
            $can_edit = true;
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!$can_edit) {
                    setFlashMessage('error', 'Нельзя редактировать поставку со статусом "' . $supply['status'] . '"');
                    redirect('supplies.php?action=view&id=' . $id);
                }
                
                $supplier_name = getPost('supplier_name');
                $supply_date = getPost('supply_date');
                $received_by = $has_received_by ? trim(getPost('received_by', '')) : '';
                $total_cost = 0;
                $notes = getPost('notes', '');
                
                $errors = [];
                if (empty($supplier_name)) $errors[] = 'Название поставщика обязательно';
                if ($has_received_by && empty($received_by)) $errors[] = 'Необходимо выбрать сотрудника';
                
                if (empty($errors)) {
                    $db->beginTransaction();
                    try {
                        $ingredient_ids = $_POST['ingredient_id'] ?? [];
                        $quantities = $_POST['quantity'] ?? [];
                        $unit_prices = $_POST['unit_price'] ?? [];
                        
                        foreach ($ingredient_ids as $idx => $ing_id) {
                            if ($ing_id && isset($quantities[$idx]) && isset($unit_prices[$idx]) && $quantities[$idx] > 0) {
                                $total_cost += $quantities[$idx] * $unit_prices[$idx];
                            }
                        }
                        if ($has_received_by) {
                            $stmt = $db->prepare("
                                UPDATE supplies 
                                SET supplier_name = ?, supply_date = ?, received_by = ?, 
                                    total_cost = ?, notes = ?
                                WHERE id_supply = ?
                            ");
                            $stmt->execute([
                                $supplier_name,
                                $supply_date,
                                !empty($received_by) ? $received_by : null,
                                $total_cost,
                                $notes,
                                $id
                            ]);
                        } else {
                            $stmt = $db->prepare("
                                UPDATE supplies 
                                SET supplier_name = ?, supply_date = ?, 
                                    total_cost = ?, notes = ?
                                WHERE id_supply = ?
                            ");
                            $stmt->execute([
                                $supplier_name,
                                $supply_date,
                                $total_cost,
                                $notes,
                                $id
                            ]);
                        }
                        if ($status == 'доставлено' && $supply['status'] != 'доставлено') {
                            $stmt = $db->prepare("
                                SELECT sd.id_ingredient, sd.quantity 
                                FROM supply_details sd 
                                WHERE sd.id_supply = ?
                            ");
                            $stmt->execute([$id]);
                            $details = $stmt->fetchAll();
                            foreach ($details as $detail) {
                                $stmt = $db->prepare("
                                    UPDATE ingredients 
                                    SET current_quantity = current_quantity + ? 
                                    WHERE id_ingredient = ?
                                ");
                                $stmt->execute([$detail['quantity'], $detail['id_ingredient']]);
                            }
                        }
                        $stmt = $db->prepare("DELETE FROM supply_details WHERE id_supply = ?");
                        $stmt->execute([$id]);
                        $ingredient_ids = $_POST['ingredient_id'] ?? [];
                        $quantities = $_POST['quantity'] ?? [];
                        $unit_prices = $_POST['unit_price'] ?? [];
                        
                        for ($i = 0; $i < count($ingredient_ids); $i++) {
                            if ($ingredient_ids[$i] && $quantities[$i] > 0) {
                                $item_total = $quantities[$i] * $unit_prices[$i];
                                $stmt = $db->prepare("
                                    INSERT INTO supply_details (id_supply, id_ingredient, quantity, unit_price)
                                    VALUES (?, ?, ?, ?)
                                ");
                                $stmt->execute([
                                    $id,
                                    $ingredient_ids[$i],
                                    $quantities[$i],
                                    $unit_prices[$i]
                                ]);
                            }
                        }
                        
                        $db->commit();
                        setFlashMessage('success', 'Поставка обновлена');
                        redirect('supplies.php?action=view&id=' . $id);
                        
                    } catch (Exception $e) {
                        $db->rollBack();
                        throw $e;
                    }
                } else {
                    setFlashMessage('error', implode('<br>', $errors));
                }
            }
            $stmt = $db->prepare("
                SELECT sd.*, i.ingredient_name, i.unit
                FROM supply_details sd
                LEFT JOIN ingredients i ON sd.id_ingredient = i.id_ingredient
                WHERE sd.id_supply = ?
            ");
            $stmt->execute([$id]);
            $supply_details = $stmt->fetchAll();
            foreach ($supply_details as &$detail) {
                $detail['computed_total'] = $detail['quantity'] * $detail['unit_price'];
            }
            unset($detail);
            $ingredients = $db->query("SELECT * FROM ingredients ORDER BY ingredient_name")->fetchAll();
            
            include 'header.php';
            ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5>Редактирование поставки #<?php echo $supply['id_supply']; ?></h5>
                    <?php if (!$can_edit): ?>
                    <span class="badge bg-warning text-dark">Только просмотр (статус: <?php echo $supply['status']; ?>)</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="POST" id="supplyForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Поставщик *</label>
                                <input type="text" class="form-control" name="supplier_name" 
                                       value="<?php echo htmlspecialchars($supply['supplier_name']); ?>" 
                                       <?php echo !$can_edit ? 'readonly' : 'required'; ?>>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Дата поставки</label>
                                <input type="date" class="form-control" name="supply_date" 
                                       value="<?php echo $supply['supply_date']; ?>"
                                       <?php echo !$can_edit ? 'readonly' : ''; ?>>
                            </div>
                            
                            <?php if ($has_received_by): ?>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Принял *</label>
                                <select class="form-select" name="received_by" <?php echo !$can_edit ? 'disabled' : 'required'; ?>>
                                    <option value="">Выберите сотрудника</option>
                                    <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo htmlspecialchars($emp['fio']); ?>" 
                                            <?php echo ($supply['received_by'] ?? '') == $emp['fio'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['fio']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!$can_edit): ?>
                                <input type="hidden" name="received_by" value="<?php echo htmlspecialchars($supply['received_by'] ?? ''); ?>">
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Статус</label>
                                <select class="form-select" name="status" <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                    <option value="ожидается" <?php echo $supply['status'] == 'ожидается' ? 'selected' : ''; ?>>Ожидается</option>
                                    <option value="в пути" <?php echo $supply['status'] == 'в пути' ? 'selected' : ''; ?>>В пути</option>
                                    <option value="доставлено" <?php echo $supply['status'] == 'доставлено' ? 'selected' : ''; ?>>Доставлено</option>
                                    <option value="отменено" <?php echo $supply['status'] == 'отменено' ? 'selected' : ''; ?>>Отменено</option>
                                </select>
                                <?php if (!$can_edit): ?>
                                <input type="hidden" name="status" value="<?php echo $supply['status']; ?>">
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Общая сумма</label>
                                <input type="number" class="form-control" name="total_amount" 
                                       id="total_amount" value="<?php echo $supply[$supply_total_field_display]; ?>" 
                                       readonly step="0.01">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Примечания</label>
                            <textarea class="form-control" name="notes" rows="2" 
                                      <?php echo !$can_edit ? 'readonly' : ''; ?>><?php echo htmlspecialchars($supply['notes'] ?? ''); ?></textarea>
                        </div>
                        
                      
                        <h6>Детали поставки:</h6>
                        <div id="supplyDetails">
                            <?php if ($supply_details): ?>
                                <?php foreach ($supply_details as $detail): ?>
                                <div class="supply-detail-row row g-2 mb-2">
                                    <div class="col-md-5">
                                        <select class="form-select ingredient-select" name="ingredient_id[]" 
                                                <?php echo !$can_edit ? 'disabled' : 'required'; ?>>
                                            <option value="">Выберите ингредиент</option>
                                            <?php foreach ($ingredients as $ing): ?>
                                            <option value="<?php echo $ing['id_ingredient']; ?>" 
                                                    data-unit="<?php echo $ing['unit']; ?>"
                                                    <?php echo $detail['id_ingredient'] == $ing['id_ingredient'] ? 'selected' : ''; ?>>
                                                <?php echo $ing['ingredient_name']; ?> (<?php echo $ing['unit']; ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (!$can_edit): ?>
                                        <input type="hidden" name="ingredient_id[]" value="<?php echo $detail['id_ingredient']; ?>">
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" class="form-control quantity" 
                                               name="quantity[]" step="0.001" min="0.001" 
                                               value="<?php echo $detail['quantity']; ?>"
                                               <?php echo !$can_edit ? 'readonly' : 'required'; ?>>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" class="form-control unit-price" 
                                               name="unit_price[]" step="0.01" min="0" 
                                               value="<?php echo $detail['unit_price']; ?>"
                                               <?php echo !$can_edit ? 'readonly' : 'required'; ?>>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="text" class="form-control total-price" 
                                               value="<?php echo number_format($detail['quantity'] * $detail['unit_price'], 2); ?>" readonly>
                                    </div>
                                    <?php if ($can_edit): ?>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-danger remove-detail">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="supply-detail-row row g-2 mb-2">
                                    <div class="col-md-5">
                                        <select class="form-select ingredient-select" name="ingredient_id[]" 
                                                <?php echo !$can_edit ? 'disabled' : 'required'; ?>>
                                            <option value="">Выберите ингредиент</option>
                                            <?php foreach ($ingredients as $ing): ?>
                                            <option value="<?php echo $ing['id_ingredient']; ?>" 
                                                    data-unit="<?php echo $ing['unit']; ?>">
                                                <?php echo $ing['ingredient_name']; ?> (<?php echo $ing['unit']; ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" class="form-control quantity" 
                                               name="quantity[]" step="0.001" min="0.001" 
                                               placeholder="Кол-во" 
                                               <?php echo !$can_edit ? 'readonly' : 'required'; ?>>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" class="form-control unit-price" 
                                               name="unit_price[]" step="0.01" min="0" 
                                               placeholder="Цена за ед." 
                                               <?php echo !$can_edit ? 'readonly' : 'required'; ?>>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="text" class="form-control total-price" 
                                               placeholder="Итого" readonly>
                                    </div>
                                    <?php if ($can_edit): ?>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-danger remove-detail">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($can_edit): ?>
                        <button type="button" class="btn btn-sm btn-success mt-2" id="addDetail">
                            <i class="bi bi-plus-circle"></i> Добавить позицию
                        </button>
                        <?php endif; ?>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between">
                            <a href="supplies.php" class="btn btn-secondary">Назад</a>
                            <?php if ($can_edit): ?>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Сохранить изменения
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if ($can_edit): ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const detailsContainer = document.getElementById('supplyDetails');
                const totalAmountInput = document.getElementById('total_amount');
                document.getElementById('addDetail').addEventListener('click', function() {
                    const newRow = detailsContainer.firstElementChild.cloneNode(true);
                    newRow.querySelectorAll('input').forEach(input => {
                        if (!input.readonly) input.value = '';
                    });
                    newRow.querySelector('select').selectedIndex = 0;
                    newRow.querySelector('.total-price').value = '';
                    detailsContainer.appendChild(newRow);
                });
                detailsContainer.addEventListener('click', function(e) {
                    if (e.target.closest('.remove-detail')) {
                        const row = e.target.closest('.supply-detail-row');
                        if (detailsContainer.children.length > 1) {
                            row.remove();
                            updateTotal();
                        }
                    }
                });
                detailsContainer.addEventListener('input', function(e) {
                    if (e.target.classList.contains('quantity') || 
                        e.target.classList.contains('unit-price')) {
                        const row = e.target.closest('.supply-detail-row');
                        calculateRowTotal(row);
                        updateTotal();
                    }
                });
                
                function calculateRowTotal(row) {
                    const quantity = parseFloat(row.querySelector('.quantity').value) || 0;
                    const unitPrice = parseFloat(row.querySelector('.unit-price').value) || 0;
                    const total = quantity * unitPrice;
                    row.querySelector('.total-price').value = total.toFixed(2);
                }
                
                function updateTotal() {
                    let total = 0;
                    document.querySelectorAll('.total-price').forEach(input => {
                        total += parseFloat(input.value) || 0;
                    });
                    totalAmountInput.value = total.toFixed(2);
                }
                document.querySelectorAll('.supply-detail-row').forEach(row => {
                    calculateRowTotal(row);
                    row.addEventListener('input', function() {
                        calculateRowTotal(this);
                        updateTotal();
                    });
                });
                
                updateTotal();
            });
            </script>
            <?php endif; ?>
            <?php
            break;
        case 'view':
            $stmt = $db->prepare("
                SELECT s.*, 
                       s.received_by as received_by_name,
                       COALESCE((SELECT SUM(sd.quantity * sd.unit_price) FROM supply_details sd WHERE sd.id_supply = s.id_supply), 0) as computed_total
                FROM supplies s
                WHERE s.id_supply = ?
            ");
            $stmt->execute([$id]);
            $supply = $stmt->fetch();
            
            if (!$supply) {
                setFlashMessage('error', 'Поставка не найдена');
                redirect('supplies.php');
            }
            if (empty($supply[$supply_total_field_display]) || floatval($supply[$supply_total_field_display]) == 0) {
                $supply[$supply_total_field_display] = $supply['computed_total'] ?? 0;
            }
            $stmt = $db->prepare("
                SELECT sd.*, i.ingredient_name, i.unit, i.current_quantity
                FROM supply_details sd
                LEFT JOIN ingredients i ON sd.id_ingredient = i.id_ingredient
                WHERE sd.id_supply = ?
            ");
            $stmt->execute([$id]);
            $supply_details = $stmt->fetchAll();
            
            include 'header.php';
            ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        Поставка #<?php echo $supply['id_supply']; ?>
                        <span class="badge bg-<?php 
                            echo $supply['status'] == 'доставлено' ? 'success' : 
                                 ($supply['status'] == 'отменено' ? 'danger' : 
                                 ($supply['status'] == 'в пути' ? 'warning' : 'info')); 
                        ?> ms-2">
                            <?php echo $supply['status']; ?>
                        </span>
                    </h5>
                    <div>
                        <?php if (in_array($supply['status'], ['ожидается', 'в пути'])): ?>
                        <a href="supplies.php?action=edit&id=<?php echo $id; ?>" class="btn btn-warning btn-sm">
                            <i class="bi bi-pencil"></i> Редактировать
                        </a>
                        <?php endif; ?>
                        <button class="btn btn-info btn-sm" onclick="printSupply()">
                            <i class="bi bi-printer"></i> Печать
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <p><strong>Поставщик:</strong> <?php echo htmlspecialchars($supply['supplier_name']); ?></p>
                            <p><strong>Дата поставки:</strong> <?php echo formatDate($supply['supply_date']); ?></p>
                            <?php if ($has_received_by && !empty($supply['received_by_name'])): ?>
                            <p><strong>Принял:</strong> <?php echo htmlspecialchars($supply['received_by_name']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <p><strong>Общая сумма:</strong> <?php echo formatMoney($supply[$supply_total_field_display]); ?></p>
                        </div>
                        <div class="col-md-4">
                            <?php if ($supply['notes']): ?>
                            <p><strong>Примечания:</strong><br><?php echo nl2br(htmlspecialchars($supply['notes'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                   
                    <h6>Детали поставки:</h6>
                    <?php if ($supply_details): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Ингредиент</th>
                                        <th>Единица</th>
                                        <th>Количество</th>
                                        <th>Цена за ед.</th>
                                        <th>Сумма</th>
                                        <th>Остаток на складе</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($supply_details as $detail): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($detail['ingredient_name']); ?></td>
                                        <td><?php echo htmlspecialchars($detail['unit']); ?></td>
                                        <td><?php echo number_format($detail['quantity'], 3); ?></td>
                                        <td><?php echo formatMoney($detail['unit_price']); ?></td>
                                        <td><?php echo formatMoney($detail['quantity'] * $detail['unit_price']); ?></td>
                                        <td>
                                            <?php echo number_format($detail['current_quantity'], 3); ?>
                                            <?php if ($supply['status'] == 'доставлено'): ?>
                                            <br>
                                            <small class="text-muted">
                                                Было: <?php echo number_format($detail['current_quantity'] - $detail['quantity'], 3); ?>
                                            </small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-active">
                                        <td colspan="4" class="text-end"><strong>Итого:</strong></td>
                                        <td><strong><?php echo formatMoney($supply[$supply_total_field_display]); ?></strong></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">Нет деталей поставки</div>
                    <?php endif; ?>
                
                
                    <div class="mt-4">
                        <?php if ($supply['status'] == 'ожидается' || $supply['status'] == 'в пути'): ?>
                        <form method="POST" action="supplies.php?action=update_status&id=<?php echo $id; ?>" class="d-inline">
                            <?php if ($supply['status'] == 'ожидается'): ?>
                            <button type="submit" name="status" value="в пути" class="btn btn-warning">
                                <i class="bi bi-truck"></i> Отметить "В пути"
                            </button>
                            <?php endif; ?>
                            <?php if ($supply['status'] == 'в пути'): ?>
                            <button type="submit" name="status" value="доставлено" class="btn btn-success">
                                <i class="bi bi-check-circle"></i> Отметить "Доставлено"
                            </button>
                            <?php endif; ?>
                            <button type="submit" name="status" value="отменено" class="btn btn-danger"
                                    onclick="return confirm('Отменить поставку?')">
                                <i class="bi bi-x-circle"></i> Отменить
                            </button>
                        </form>
                        <?php endif; ?>
                        
                        <a href="supplies.php" class="btn btn-secondary float-end">
                            <i class="bi bi-arrow-left"></i> Назад к списку
                        </a>
                    </div>
                </div>
            </div>
            
            <script>
            function printSupply() {
                window.open('supplies.php?action=print&id=<?php echo $id; ?>', '_blank');
            }
            </script>
            <?php
            break;
            
        case 'update_status':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
                $new_status = getPost('status');
                
                $stmt = $db->prepare("SELECT status FROM supplies WHERE id_supply = ?");
                $stmt->execute([$id]);
                $supply = $stmt->fetch();
                
                if (!$supply) {
                    setFlashMessage('error', 'Поставка не найдена');
                    redirect('supplies.php');
                }
                
                $db->beginTransaction();
                try {
                    if ($new_status == 'доставлено' && $supply['status'] != 'доставлено') {
                        $stmt = $db->prepare("
                            SELECT sd.id_ingredient, sd.quantity 
                            FROM supply_details sd 
                            WHERE sd.id_supply = ?
                        ");
                        $stmt->execute([$id]);
                        $details = $stmt->fetchAll();
                        foreach ($details as $detail) {
                            $stmt = $db->prepare("
                                UPDATE ingredients 
                                SET current_quantity = current_quantity + ? 
                                WHERE id_ingredient = ?
                            ");
                            $stmt->execute([$detail['quantity'], $detail['id_ingredient']]);
                        }
                    }
                    if ($supply['status'] == 'доставлено' && $new_status != 'доставлено') {
                        $stmt = $db->prepare("
                            SELECT sd.id_ingredient, sd.quantity 
                            FROM supply_details sd 
                            WHERE sd.id_supply = ?
                        ");
                        $stmt->execute([$id]);
                        $details = $stmt->fetchAll();
                        foreach ($details as $detail) {
                            $stmt = $db->prepare("
                                UPDATE ingredients 
                                SET current_quantity = current_quantity - ? 
                                WHERE id_ingredient = ?
                            ");
                            $stmt->execute([$detail['quantity'], $detail['id_ingredient']]);
                        }
                    }
                    $stmt = $db->prepare("UPDATE supplies SET status = ? WHERE id_supply = ?");
                    $stmt->execute([$new_status, $id]);
                    
                    $db->commit();
                    setFlashMessage('success', 'Статус поставки обновлен');
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    throw $e;
                }
            }
            redirect('supplies.php?action=view&id=' . $id);
            break;
        case 'delete':
            if ($user_role == 'администратор') {
                $stmt = $db->prepare("SELECT status FROM supplies WHERE id_supply = ?");
                $stmt->execute([$id]);
                $supply = $stmt->fetch();
                
                if ($supply) {
                    if ($supply['status'] == 'доставлено') {
                        setFlashMessage('error', 'Нельзя удалить доставленную поставку');
                    } else {
                        $db->beginTransaction();
                        
                        try {
                            $stmt = $db->prepare("DELETE FROM supply_details WHERE id_supply = ?");
                            $stmt->execute([$id]);
                            $stmt = $db->prepare("DELETE FROM supplies WHERE id_supply = ?");
                            $stmt->execute([$id]);
                            
                            $db->commit();
                            setFlashMessage('success', 'Поставка удалена');
                        } catch (Exception $e) {
                            $db->rollBack();
                            throw $e;
                        }
                    }
                }
            } else {
                setFlashMessage('error', 'Недостаточно прав для удаления');
            }
            redirect('supplies.php');
            break;
        case 'print':
            $stmt = $db->prepare("
                SELECT s.*, 
                       s.received_by as received_by_name,
                       COALESCE((SELECT SUM(sd.quantity * sd.unit_price) FROM supply_details sd WHERE sd.id_supply = s.id_supply), 0) as computed_total
                FROM supplies s
                WHERE s.id_supply = ?
            ");
            $stmt->execute([$id]);
            $supply = $stmt->fetch();
            
            if (!$supply) {
                die('Поставка не найдена');
            }
            if (empty($supply[$supply_total_field_display]) || floatval($supply[$supply_total_field_display]) == 0) {
                $supply[$supply_total_field_display] = $supply['computed_total'] ?? 0;
            }
            
            $stmt = $db->prepare("
                SELECT sd.*, i.ingredient_name, i.unit
                FROM supply_details sd
                LEFT JOIN ingredients i ON sd.id_ingredient = i.id_ingredient
                WHERE sd.id_supply = ?
            ");
            $stmt->execute([$id]);
            $supply_details = $stmt->fetchAll();
            
            header('Content-Type: text/html; charset=utf-8');
            ?>
            <!DOCTYPE html>
            <html lang="ru">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Накладная #<?php echo $supply['id_supply']; ?></title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    .invoice { max-width: 800px; margin: 0 auto; border: 1px solid #000; padding: 20px; }
                    .invoice-header { text-align: center; margin-bottom: 30px; }
                    .invoice-title { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
                    .invoice-info { font-size: 14px; margin-bottom: 5px; }
                    .invoice-details { margin: 20px 0; }
                    .invoice-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                    .invoice-table th, .invoice-table td { border: 1px solid #000; padding: 8px; text-align: center; }
                    .invoice-table th { background-color: #f2f2f2; }
                    .invoice-footer { margin-top: 30px; text-align: center; font-size: 12px; }
                    .status-badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 12px; }
                    .status-delivered { background: #d4edda; color: #155724; }
                    .status-pending { background: #fff3cd; color: #856404; }
                    .status-cancelled { background: #f8d7da; color: #721c24; }
                    @media print {
                        body { margin: 0; }
                        .no-print { display: none; }
                    }
                </style>
            </head>
            <body>
                <div class="invoice">
                    <div class="invoice-header">
                        <div class="invoice-title">Товарная накладная</div>
                        <div class="invoice-info">Накладная #<?php echo $supply['id_supply']; ?></div>
                        <div class="invoice-info">Дата: <?php echo formatDate($supply['supply_date']); ?></div>
                    </div>
                    
                    <div class="invoice-details">
                        <p><strong>Поставщик:</strong> <?php echo htmlspecialchars($supply['supplier_name']); ?></p>
                        <p><strong>Дата поставки:</strong> <?php echo formatDate($supply['supply_date']); ?></p>
                        <?php if ($has_received_by && !empty($supply['received_by_name'])): ?>
                        <p><strong>Принял:</strong> <?php echo htmlspecialchars($supply['received_by_name']); ?></p>
                        <?php endif; ?>
                        <p><strong>Статус:</strong> 
                            <span class="status-badge <?php 
                                echo $supply['status'] == 'доставлено' ? 'status-delivered' : 
                                     ($supply['status'] == 'отменено' ? 'status-cancelled' : 'status-pending'); 
                            ?>">
                                <?php echo $supply['status']; ?>
                            </span>
                        </p>
                        <p><strong>Общая сумма:</strong> <?php echo number_format($supply[$supply_total_field_display], 2); ?> ₽</p>
                        <?php if ($supply['notes']): ?>
                        <p><strong>Примечания:</strong> <?php echo htmlspecialchars($supply['notes']); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th>№</th>
                                <th>Наименование</th>
                                <th>Ед. изм.</th>
                                <th>Количество</th>
                                <th>Цена за ед.</th>
                                <th>Сумма</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            $total_sum = 0;
                            foreach ($supply_details as $detail): 
                                $item_total = $detail['quantity'] * $detail['unit_price'];
                                $total_sum += $item_total;
                            ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><?php echo htmlspecialchars($detail['ingredient_name']); ?></td>
                                <td><?php echo htmlspecialchars($detail['unit']); ?></td>
                                <td><?php echo number_format($detail['quantity'], 3); ?></td>
                                <td><?php echo number_format($detail['unit_price'], 2); ?> ₽</td>
                                <td><?php echo number_format($item_total, 2); ?> ₽</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" style="text-align: right; font-weight: bold;">ИТОГО:</td>
                                <td style="font-weight: bold;"><?php echo number_format($total_sum, 2); ?> ₽</td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <div class="invoice-footer">
                        <p>Накладная сформирована <?php echo date('d.m.Y H:i:s'); ?></p>
                        <p><?php echo SITE_NAME; ?></p>
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
            $page = getGet('page', 1);
            $per_page = 20;
            $offset = ($page - 1) * $per_page;
            
            $where = [];
            $params = [];
            if ($supplier_filter) {
                $where[] = "s.supplier_name LIKE ?";
                $params[] = "%$supplier_filter%";
            }
            
            if ($date_from && $date_to) {
                $where[] = "DATE(s.supply_date) BETWEEN ? AND ?";
                $params[] = $date_from;
                $params[] = $date_to;
            }
            
            $where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";
            $count_sql = "SELECT COUNT(*) FROM supplies s $where_clause";
            $count_stmt = $db->prepare($count_sql);
            $count_stmt->execute($params);
            $total_supplies = $count_stmt->fetchColumn();
            $total_pages = ceil($total_supplies / $per_page);
            $sql = "
                SELECT s.*,
                       (SELECT COUNT(*) FROM supply_details sd WHERE sd.id_supply = s.id_supply) as items_count,
                       COALESCE((SELECT SUM(sd.quantity * sd.unit_price) FROM supply_details sd WHERE sd.id_supply = s.id_supply), 0) as computed_total,
                       s.received_by as received_by_name
                FROM supplies s
                $where_clause
                ORDER BY s.supply_date DESC, s.id_supply DESC
                LIMIT $per_page OFFSET $offset
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $supplies = $stmt->fetchAll();
            foreach ($supplies as &$supply) {
                if (empty($supply[$supply_total_field_display]) || floatval($supply[$supply_total_field_display]) == 0) {
                    $supply[$supply_total_field_display] = $supply['computed_total'] ?? 0;
                }
            }
            unset($supply);
            $stats = [];
            include 'header.php';
            ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Управление поставками</h5>
                    <div>
                        <a href="supplies.php?action=add" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-circle"></i> Новая поставка
                        </a>
                    </div>
                </div>
                <div class="card-body">
                   
                    <form method="GET" class="row g-2 mb-4">
                     
                        
                        <div class="col-md-3">
                            <label class="form-label">Поставщик</label>
                            <input type="text" class="form-control" name="supplier" 
                                   placeholder="Поиск по поставщику..." 
                                   value="<?php echo htmlspecialchars($supplier_filter); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">С</label>
                            <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">По</label>
                            <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-filter"></i> Фильтровать
                            </button>
                        </div>
                        
                        <div class="col-md-1 d-flex align-items-end">
                            <a href="supplies.php" class="btn btn-secondary w-100">Сбросить</a>
                        </div>
                    </form>
                    
                
                    <?php if ($supplies): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Дата</th>
                                        <th>Поставщик</th>
                                        <th>Сумма</th>
                                        <th>Позиций</th>
                                        <?php if ($has_received_by): ?>
                                        <th>Принял</th>
                                        <?php endif; ?>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($supplies as $supply): ?>
                                    <tr>
                                        <td>#<?php echo $supply['id_supply']; ?></td>
                                        <td><?php echo formatDate($supply['supply_date'], 'd.m.Y'); ?></td>
                                        <td><?php echo htmlspecialchars($supply['supplier_name']); ?></td>
                                        <td><?php echo formatMoney($supply[$supply_total_field_display]); ?></td>
                                        <td><?php echo $supply['items_count']; ?></td>
                                        <?php if ($has_received_by): ?>
                                        <td><?php echo htmlspecialchars($supply['received_by_name'] ?? 'Не указан'); ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="supplies.php?action=view&id=<?php echo $supply['id_supply']; ?>" 
                                                   class="btn btn-info" title="Просмотр">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="supplies.php?action=edit&id=<?php echo $supply['id_supply']; ?>" 
                                                   class="btn btn-warning" title="Редактировать">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="supplies.php?action=print&id=<?php echo $supply['id_supply']; ?>" 
                                                   class="btn btn-secondary" title="Печать" target="_blank">
                                                    <i class="bi bi-printer"></i>
                                                </a>
                                                <?php if ($user_role == 'администратор' && $supply['status'] != 'доставлено'): ?>
                                                <button type="button" class="btn btn-danger" 
                                                        onclick="if(confirm('Удалить поставку?')) window.location.href='supplies.php?action=delete&id=<?php echo $supply['id_supply']; ?>'" 
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
                        
                  
                        <?php if ($total_pages > 1): ?>
                        <nav aria-label="Навигация по страницам">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" 
                                       href="supplies.php?page=<?php echo $i; ?>&supplier=<?php echo urlencode($supplier_filter); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-truck" style="font-size: 48px;"></i>
                            <p class="mt-2">Поставки не найдены</p>
                            <a href="supplies.php?action=add" class="btn btn-primary mt-2">
                                <i class="bi bi-plus-circle"></i> Создать первую поставку
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
    }
    
} catch (Exception $e) {
    setFlashMessage('error', 'Ошибка: ' . $e->getMessage());
    redirect('dashboard.php');
}

include 'footer.php';
?>