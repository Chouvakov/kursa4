<?php
require_once 'db_connection.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

if (!checkRole('администратор') && !checkRole('менеджер')) {
    setFlashMessage('error', 'Доступ запрещен');
    redirect('dashboard.php');
}

$action = getGet('action', 'list');
$id = getGet('id', 0);
$search = getGet('search', '');
$position_filter = getGet('position', '');
$page = getGet('page', 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

try {
    global $db;
    
    switch ($action) {
        case 'add':
            if (!checkRole('администратор')) {
                setFlashMessage('error', 'Только администратор может добавлять сотрудников');
                redirect('employees.php');
            }
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $fio = getPost('fio');
                $phone = getPost('phone');
                $email = getPost('email');
                $login = getPost('login');
                $position = getPost('position');
                $salary = getPost('salary', 0);
                $hire_date = getPost('hire_date');
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                $password = getPost('password');
                $confirm_password = getPost('confirm_password');
                
                $errors = [];
                
                if (empty($fio)) $errors[] = 'ФИО обязательно';
                if (empty($login)) $errors[] = 'Логин обязателен';
                if (empty($position)) $errors[] = 'Должность обязательна';
                if (empty($password)) $errors[] = 'Пароль обязателен';
                $check_stmt = $db->prepare("SELECT id_employee FROM employees WHERE login = ?");
                $check_stmt->execute([$login]);
                if ($check_stmt->fetch()) {
                    $errors[] = 'Такой логин уже используется';
                }
                
                if ($password != $confirm_password) {
                    $errors[] = 'Пароли не совпадают';
                }
                if ($position == 'администратор' && !checkRole('администратор')) {
                    $errors[] = 'Только администраторы могут создавать администраторов';
                }
                
                if (empty($errors)) {
                    $stmt = $db->prepare("
                        INSERT INTO employees (fio, phone, email, login, password_hash, position, salary, hire_date, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$fio, $phone, $email, $login, $password, $position, $salary, $hire_date, $is_active]);
                    
                    setFlashMessage('success', 'Сотрудник добавлен');
                    redirect('employees.php');
                } else {
                    setFlashMessage('error', implode('<br>', $errors));
                }
            }
            
            include 'header.php';
            ?>
            <div class="card">
                <div class="card-header">
                    <h5>Добавление сотрудника</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="fio" class="form-label">ФИО *</label>
                                <input type="text" class="form-control" id="fio" name="fio" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="position" class="form-label">Должность *</label>
                                <select class="form-select" id="position" name="position" required>
                                    <option value="">Выберите должность</option>
                                    <option value="официант">Официант</option>
                                    <option value="повар">Повар</option>
                                    <option value="менеджер">Менеджер</option>
                                    <?php if (checkRole('администратор')): ?>
                                    <option value="администратор">Администратор</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="phone" class="form-label">Телефон</label>
                                <input type="tel" class="form-control" id="phone" name="phone">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="login" class="form-label">Логин *</label>
                                <input type="text" class="form-control" id="login" name="login" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Пароль *</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Подтверждение пароля *</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="salary" class="form-label">Зарплата</label>
                                <input type="number" class="form-control" id="salary" name="salary" value="0" step="0.01" min="0">
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label for="hire_date" class="form-label">Дата приема</label>
                                <input type="date" class="form-control" id="hire_date" name="hire_date" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Статус</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" checked>
                                    <label class="form-check-label" for="is_active">
                                        Активен
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="employees.php" class="btn btn-secondary">Отмена</a>
                            <button type="submit" class="btn btn-primary">
                                Добавить
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php
            break;
            
        case 'edit':
            $employee = null;
            if ($id) {
                $stmt = $db->prepare("SELECT * FROM employees WHERE id_employee = ?");
                $stmt->execute([$id]);
                $employee = $stmt->fetch();
                
                if (!$employee) {
                    setFlashMessage('error', 'Сотрудник не найден');
                    redirect('employees.php');
                }
            }
            
            $can_edit_auth = checkRole('администратор');
            $can_edit_position = true;
            if ($employee['position'] == 'администратор' && !checkRole('администратор')) {
                $can_edit_position = false;
                setFlashMessage('error', 'Только администраторы могут редактировать администраторов');
                redirect('employees.php');
            }
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_edit_position) {
                $fio = getPost('fio');
                $phone = getPost('phone');
                $email = getPost('email');
                $login = getPost('login');
                $position = getPost('position');
                $salary = getPost('salary', 0);
                $hire_date = getPost('hire_date');
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                $password = $can_edit_auth ? getPost('password') : '';
                $confirm_password = $can_edit_auth ? getPost('confirm_password') : '';
                
                $errors = [];
                
                if (empty($fio)) $errors[] = 'ФИО обязательно';
                if (empty($position)) $errors[] = 'Должность обязательна';
                if ($can_edit_auth && empty($login)) {
                    $errors[] = 'Логин обязателен';
                }
                if ($can_edit_auth) {
                    $check_stmt = $db->prepare("SELECT id_employee FROM employees WHERE login = ? AND id_employee != ?");
                    $check_stmt->execute([$login, $id]);
                    if ($check_stmt->fetch()) {
                        $errors[] = 'Такой логин уже используется';
                    }
                }
                if ($position == 'администратор' && !checkRole('администратор')) {
                    $errors[] = 'Только администраторы могут назначать администраторов';
                }
                
                if ($can_edit_auth && !empty($password) && $password != $confirm_password) {
                    $errors[] = 'Пароли не совпадают';
                }
                
                if (empty($errors)) {
                    if ($can_edit_auth && !empty($password)) {
                        $stmt = $db->prepare("
                            UPDATE employees 
                            SET fio = ?, phone = ?, email = ?, login = ?, 
                                password_hash = ?, position = ?, salary = ?, 
                                hire_date = ?, is_active = ?
                            WHERE id_employee = ?
                        ");
                        $stmt->execute([$fio, $phone, $email, $login, $password, 
                                       $position, $salary, $hire_date, $is_active, $id]);
                    } elseif ($can_edit_auth && empty($password)) {
                        $stmt = $db->prepare("
                            UPDATE employees 
                            SET fio = ?, phone = ?, email = ?, login = ?, 
                                position = ?, salary = ?, hire_date = ?, 
                                is_active = ?
                            WHERE id_employee = ?
                        ");
                        $stmt->execute([$fio, $phone, $email, $login, $position, 
                                       $salary, $hire_date, $is_active, $id]);
                    } else {
                        $stmt = $db->prepare("
                            UPDATE employees 
                            SET fio = ?, phone = ?, email = ?, 
                                position = ?, salary = ?, hire_date = ?, 
                                is_active = ?
                            WHERE id_employee = ?
                        ");
                        $stmt->execute([$fio, $phone, $email, $position, 
                                       $salary, $hire_date, $is_active, $id]);
                    }
                    
                    setFlashMessage('success', 'Данные сотрудника обновлены');
                    redirect('employees.php');
                } else {
                    setFlashMessage('error', implode('<br>', $errors));
                }
            }
            
            include 'header.php';
            ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5>Редактирование сотрудника</h5>
                    <?php if (!$can_edit_auth && $action == 'edit'): ?>
                    <span class="badge bg-warning text-dark">Режим ограниченного редактирования</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="fio" class="form-label">ФИО *</label>
                                <input type="text" class="form-control" id="fio" name="fio" 
                                       value="<?php echo htmlspecialchars($employee['fio'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="position" class="form-label">Должность *</label>
                                <select class="form-select" id="position" name="position" required
                                        <?php echo ($employee['position'] == 'администратор' && !checkRole('администратор')) ? 'disabled' : ''; ?>>
                                    <option value="">Выберите должность</option>
                                    <option value="официант" <?php echo ($employee['position'] ?? '') == 'официант' ? 'selected' : ''; ?>>Официант</option>
                                    <option value="повар" <?php echo ($employee['position'] ?? '') == 'повар' ? 'selected' : ''; ?>>Повар</option>
                                    <option value="менеджер" <?php echo ($employee['position'] ?? '') == 'менеджер' ? 'selected' : ''; ?>>Менеджер</option>
                                    <?php if (checkRole('администратор')): ?>
                                    <option value="администратор" <?php echo ($employee['position'] ?? '') == 'администратор' ? 'selected' : ''; ?>>Администратор</option>
                                    <?php endif; ?>
                                </select>
                                <?php if ($employee['position'] == 'администратор' && !checkRole('администратор')): ?>
                                <input type="hidden" name="position" value="администратор">
                                <small class="text-muted">Только администратор может изменять должность администратора</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="phone" class="form-label">Телефон</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($employee['email'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="login" class="form-label">
                                    Логин <?php echo $can_edit_auth ? '*' : ''; ?>
                                </label>
                                <input type="text" class="form-control" id="login" name="login" 
                                       value="<?php echo htmlspecialchars($employee['login'] ?? ''); ?>" 
                                       <?php echo $can_edit_auth ? 'required' : 'readonly'; ?>>
                                <?php if (!$can_edit_auth): ?>
                                <small class="text-muted">Только администратор может менять логин</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($can_edit_auth): ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Новый пароль</label>
                                <input type="password" class="form-control" id="password" name="password">
                                <small class="text-muted">Оставьте пустым, чтобы не менять</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Подтверждение пароля</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Только администратор может менять пароль и логин сотрудника
                        </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="salary" class="form-label">Зарплата</label>
                                <input type="number" class="form-control" id="salary" name="salary" 
                                       value="<?php echo $employee['salary'] ?? '0'; ?>" step="0.01" min="0">
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label for="hire_date" class="form-label">Дата приема</label>
                                <input type="date" class="form-control" id="hire_date" name="hire_date" 
                                       value="<?php echo $employee['hire_date'] ?? date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Статус</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" 
                                           id="is_active" <?php echo ($employee['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_active">
                                        Активен
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <div class="alert alert-info">
                                    <small>
                                        <strong>Последний вход:</strong><br>
                                        <?php echo $employee['last_login'] ? formatDate($employee['last_login']) : 'Никогда'; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="employees.php" class="btn btn-secondary">Отмена</a>
                            <?php if ($can_edit_position): ?>
                            <button type="submit" class="btn btn-primary">
                                Сохранить
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn btn-secondary" disabled>
                                Редактирование запрещено
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            <?php
            break;
            
        case 'view':
            $stmt = $db->prepare("
                SELECT e.*,
                       (SELECT COUNT(*) FROM orders WHERE id_employee = e.id_employee) as total_orders,
                       (SELECT COUNT(*) FROM orders WHERE id_employee = e.id_employee AND status = 'оплачен') as successful_orders,
                       (SELECT COUNT(*) FROM employee_schedule WHERE id_employee = e.id_employee) as scheduled_shifts
                FROM employees e
                WHERE e.id_employee = ?
            ");
            $stmt->execute([$id]);
            $employee = $stmt->fetch();
            
            if (!$employee) {
                setFlashMessage('error', 'Сотрудник не найден');
                redirect('employees.php');
            }
            if ($employee['position'] == 'администратор' && !checkRole('администратор')) {
                setFlashMessage('error', 'Только администраторы могут просматривать информацию об администраторах');
                redirect('employees.php');
            }
            $schedule_stmt = $db->prepare("
                SELECT es.*, s.shift_name, s.start_time, s.end_time, s.work_date
                FROM employee_schedule es
                LEFT JOIN shifts s ON es.id_shift = s.id_shift
                WHERE es.id_employee = ? AND s.work_date >= CURDATE()
                ORDER BY s.work_date, s.start_time
                LIMIT 5
            ");
            $schedule_stmt->execute([$id]);
            $schedules = $schedule_stmt->fetchAll();
            $orders_stmt = $db->prepare("
                SELECT o.*, t.table_number, c.fio as client_name
                FROM orders o
                LEFT JOIN tables t ON o.id_table = t.id_table
                LEFT JOIN clients c ON o.id_client = c.id_client
                WHERE o.id_employee = ?
                ORDER BY o.order_datetime DESC
                LIMIT 10
            ");
            $orders_stmt->execute([$id]);
            $orders = $orders_stmt->fetchAll();
            
            include 'header.php';
            ?>
            <div class="row">
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>Информация о сотруднике</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>ФИО:</strong> <?php echo htmlspecialchars($employee['fio']); ?></p>
                            <p><strong>Должность:</strong> 
                                <span class="badge bg-primary"><?php echo htmlspecialchars($employee['position']); ?></span>
                            </p>
                            <p><strong>Телефон:</strong> <?php echo $employee['phone'] ? htmlspecialchars($employee['phone']) : 'не указан'; ?></p>
                            <p><strong>Email:</strong> <?php echo $employee['email'] ? htmlspecialchars($employee['email']) : 'не указан'; ?></p>
                            <p><strong>Логин:</strong> <?php echo htmlspecialchars($employee['login']); ?></p>
                            <p><strong>Зарплата:</strong> <?php echo formatMoney($employee['salary']); ?></p>
                            <p><strong>Дата приема:</strong> <?php echo formatDate($employee['hire_date']); ?></p>
                            <p><strong>Статус:</strong> 
                                <span class="badge <?php echo $employee['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $employee['is_active'] ? 'Активен' : 'Неактивен'; ?>
                                </span>
                            </p>
                            <p><strong>Последний вход:</strong> <?php echo $employee['last_login'] ? formatDate($employee['last_login']) : 'Никогда'; ?></p>
                        </div>
                        <div class="card-footer">
                            <?php 
                            $can_edit = true;
                            if ($employee['position'] == 'администратор' && !checkRole('администратор')) {
                                $can_edit = false;
                            }
                            ?>
                            <?php if ($can_edit && (checkRole('администратор') || checkRole('менеджер'))): ?>
                            <a href="employees.php?action=edit&id=<?php echo $id; ?>" class="btn btn-warning btn-sm">Редактировать</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>Статистика</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="stat-card bg-primary text-white rounded p-3 text-center">
                                        <h6>Всего заказов</h6>
                                        <h3><?php echo $employee['total_orders']; ?></h3>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stat-card bg-success text-white rounded p-3 text-center">
                                        <h6>Успешных заказов</h6>
                                        <h3><?php echo $employee['successful_orders']; ?></h3>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stat-card bg-info text-white rounded p-3 text-center">
                                        <h6>Запланировано смен</h6>
                                        <h3><?php echo $employee['scheduled_shifts']; ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($employee['position'] == 'официант' || $employee['position'] == 'администратор' || $employee['position'] == 'менеджер'): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>Ближайшие смены</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($schedules): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Дата</th>
                                                <th>Смена</th>
                                                <th>Начало</th>
                                                <th>Конец</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($schedules as $schedule): ?>
                                            <tr>
                                                <td><?php echo formatDate($schedule['work_date'], 'd.m.Y'); ?></td>
                                                <td><?php echo htmlspecialchars($schedule['shift_name']); ?></td>
                                                <td><?php echo substr($schedule['start_time'], 0, 5); ?></td>
                                                <td><?php echo substr($schedule['end_time'], 0, 5); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">Нет запланированных смен</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h5>Последние заказы</h5>
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
                                                <th>Клиент</th>
                                                <th>Сумма</th>
                                                <th>Статус</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td><a href="orders.php?action=view&id=<?php echo $order['id_order']; ?>">#<?php echo $order['id_order']; ?></a></td>
                                                <td><?php echo formatDate($order['order_datetime']); ?></td>
                                                <td><?php echo htmlspecialchars($order['table_number']); ?></td>
                                                <td><?php echo $order['client_name'] ? htmlspecialchars($order['client_name']) : '—'; ?></td>
                                                <td><?php echo formatMoney($order['total_amount']); ?></td>
                                                <td>
                                                    <span class="badge <?php echo getOrderStatusClass($order['status']); ?>">
                                                        <?php echo getOrderStatusName($order['status']); ?>
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
                    <?php endif; ?>
                </div>
            </div>
            <?php
            break;
            
        case 'delete':
            if (!checkRole('администратор')) {
                setFlashMessage('error', 'Недостаточно прав для удаления сотрудников');
                redirect('employees.php');
            }
            $current_user_id = getUserId();
            if ($id == $current_user_id) {
                setFlashMessage('error', 'Вы не можете удалить самого себя');
                redirect('employees.php');
            }
            $stmt = $db->prepare("SELECT position FROM employees WHERE id_employee = ?");
            $stmt->execute([$id]);
            $employee = $stmt->fetch();
            
            if (!$employee) {
                setFlashMessage('error', 'Сотрудник не найден');
                redirect('employees.php');
            }
            if ($employee['position'] == 'администратор') {
                $admin_count = $db->query("SELECT COUNT(*) FROM employees WHERE position = 'администратор' AND is_active = 1")->fetchColumn();
                
                if ($admin_count <= 1) {
                    setFlashMessage('error', 'Нельзя удалить последнего активного администратора');
                    redirect('employees.php');
                }
            }
            
           
            try {
                $db->beginTransaction();
                
                
                try {
                    $stmt = $db->prepare("UPDATE orders SET id_employee = NULL WHERE id_employee = ?");
                    $stmt->execute([$id]);
                    $orders_updated = $stmt->rowCount();
                } catch (Exception $e) {
                  
                    $orders_updated = 0;
                }
                
                
                try {
                    $stmt = $db->prepare("DELETE FROM employee_schedule WHERE id_employee = ?");
                    $stmt->execute([$id]);
                    $schedule_deleted = $stmt->rowCount();
                } catch (Exception $e) {
                    $schedule_deleted = 0;
                }
                
                
                try {
                    $stmt = $db->prepare("UPDATE feedback SET id_manager = NULL WHERE id_manager = ?");
                    $stmt->execute([$id]);
                    $feedback_updated = $stmt->rowCount();
                } catch (Exception $e) {
                    $feedback_updated = 0;
                }
                
              
                try {
                    $stmt = $db->prepare("UPDATE order_items SET id_chef = NULL WHERE id_chef = ?");
                    $stmt->execute([$id]);
                    $order_items_updated = $stmt->rowCount();
                } catch (Exception $e) {
                    $order_items_updated = 0;
                }
                
           
                $stmt = $db->prepare("DELETE FROM employees WHERE id_employee = ?");
                $stmt->execute([$id]);
                
                if ($stmt->rowCount() == 0) {
                    throw new Exception("Сотрудник не был удален. Возможно, он уже был удален или произошла ошибка.");
                }
                
                $db->commit();
                
             
                $details = [];
                if ($orders_updated > 0) $details[] = "{$orders_updated} заказов";
                if ($schedule_deleted > 0) $details[] = "{$schedule_deleted} записей расписания";
                if ($feedback_updated > 0) $details[] = "{$feedback_updated} отзывов";
                if ($order_items_updated > 0) $details[] = "{$order_items_updated} позиций заказов";
                
                $message = 'Сотрудник удален из системы';
                if (!empty($details)) {
                    $message .= '. Обработаны связи: ' . implode(', ', $details);
                }
                
                setFlashMessage('success', $message);
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log("Ошибка удаления сотрудника ID {$id}: " . $e->getMessage());
                setFlashMessage('error', 'Ошибка при удалении сотрудника: ' . $e->getMessage());
            }
            
            redirect('employees.php');
            break;
        default:
            $where = [];
            $params = [];
            if (checkRole('менеджер')) {
                $where[] = "position != 'администратор'";
            }
            
            if ($search) {
                $where[] = "(fio LIKE ? OR phone LIKE ? OR email LIKE ? OR login LIKE ?)";
                $search_term = "%$search%";
                $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
            }
            
            if ($position_filter) {
                $where[] = "position = ?";
                $params[] = $position_filter;
            }
            
            $where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";
            $count_sql = "SELECT COUNT(*) FROM employees $where_clause";
            $count_stmt = $db->prepare($count_sql);
            $count_stmt->execute($params);
            $total_employees = $count_stmt->fetchColumn();
            $total_pages = ceil($total_employees / $per_page);
            $sql = "
                SELECT e.*,
                       (SELECT COUNT(*) FROM orders WHERE id_employee = e.id_employee AND status = 'оплачен') as successful_orders,
                       (SELECT SUM(total_amount) FROM orders WHERE id_employee = e.id_employee AND status = 'оплачен') as total_revenue
                FROM employees e
                $where_clause
                ORDER BY 
                    CASE position 
                        WHEN 'администратор' THEN 1
                        WHEN 'менеджер' THEN 2
                        WHEN 'повар' THEN 3
                        WHEN 'официант' THEN 4
                        ELSE 5
                    END,
                    fio
                LIMIT $per_page OFFSET $offset
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $employees = $stmt->fetchAll();
            
            include 'header.php';
            ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Сотрудники</h5>
                    <div>
                        <?php if (checkRole('администратор')): ?>
                        <a href="employees.php?action=add" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-circle"></i> Добавить
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                  
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <form method="GET" class="row g-2">
                                <div class="col-md-4">
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Поиск по ФИО, телефону..." 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select" name="position">
                                        <option value="">Все должности</option>
                                        <option value="официант" <?php echo $position_filter == 'официант' ? 'selected' : ''; ?>>Официанты</option>
                                        <option value="повар" <?php echo $position_filter == 'повар' ? 'selected' : ''; ?>>Повара</option>
                                        <option value="менеджер" <?php echo $position_filter == 'менеджер' ? 'selected' : ''; ?>>Менеджеры</option>
                                        <?php if (checkRole('администратор')): ?>
                                        <option value="администратор" <?php echo $position_filter == 'администратор' ? 'selected' : ''; ?>>Администраторы</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-outline-primary w-100">
                                        <i class="bi bi-filter"></i> Фильтр
                                    </button>
                                </div>
                                <div class="col-md-2">
                                    <a href="employees.php" class="btn btn-outline-secondary w-100">Сбросить</a>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                   
                    <?php if ($employees): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ФИО</th>
                                        <th>Должность</th>
                                        <th>Контакт</th>
                                        <th>Зарплата</th>
                                        <th>Успешных заказов</th>
                                        <th>Выручка</th>
                                        <th>Статус</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($employees as $emp): 
                                        $can_edit_employee = true;
                                        if ($emp['position'] == 'администратор' && !checkRole('администратор')) {
                                            $can_edit_employee = false;
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <?php if ($can_edit_employee): ?>
                                            <a href="employees.php?action=view&id=<?php echo $emp['id_employee']; ?>">
                                                <?php echo htmlspecialchars($emp['fio']); ?>
                                            </a>
                                            <?php else: ?>
                                            <?php echo htmlspecialchars($emp['fio']); ?>
                                            <?php endif; ?>
                                            <br>
                                            <small class="text-muted">Логин: <?php echo htmlspecialchars($emp['login']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge 
                                                <?php 
                                                switch($emp['position']) {
                                                    case 'администратор': echo 'bg-danger'; break;
                                                    case 'менеджер': echo 'bg-warning text-dark'; break;
                                                    case 'повар': echo 'bg-info'; break;
                                                    case 'официант': echo 'bg-success'; break;
                                                    default: echo 'bg-secondary';
                                                }
                                                ?>">
                                                <?php echo htmlspecialchars($emp['position']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($emp['phone']): ?>
                                                <div><?php echo htmlspecialchars($emp['phone']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($emp['email']): ?>
                                                <small><?php echo htmlspecialchars($emp['email']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo formatMoney($emp['salary']); ?></td>
                                        <td><?php echo $emp['successful_orders']; ?></td>
                                        <td><?php echo formatMoney($emp['total_revenue'] ?? 0); ?></td>
                                        <td>
                                            <span class="badge <?php echo $emp['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $emp['is_active'] ? 'Активен' : 'Неактивен'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <?php if ($can_edit_employee): ?>
                                                <a href="employees.php?action=view&id=<?php echo $emp['id_employee']; ?>" 
                                                   class="btn btn-info" title="Просмотр">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($can_edit_employee && (checkRole('администратор') || checkRole('менеджер'))): ?>
                                                <a href="employees.php?action=edit&id=<?php echo $emp['id_employee']; ?>" 
                                                   class="btn btn-warning" title="Редактировать">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php endif; ?>
                                                
                                                <?php 
                                                $current_user_id = getUserId();
                                                $can_delete = checkRole('администратор') && $can_edit_employee && $emp['id_employee'] != $current_user_id;
                                                ?>
                                                <?php if ($can_delete): ?>
                                                <button type="button" class="btn btn-danger" 
                                                        onclick="confirmDelete(<?php echo $emp['id_employee']; ?>, '<?php echo htmlspecialchars(addslashes($emp['fio'])); ?>')" 
                                                        title="Удалить">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                <?php elseif (checkRole('администратор') && $emp['id_employee'] == $current_user_id): ?>
                                                <button type="button" class="btn btn-danger disabled" 
                                                        title="Вы не можете удалить самого себя" disabled>
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
                                       href="employees.php?action=list&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&position=<?php echo $position_filter; ?>">
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
                            <p class="mt-2">Сотрудники не найдены</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <script>
            function confirmDelete(id, name) {
                if (confirm('Вы уверены, что хотите удалить сотрудника "' + name + '"?\n\nЕсли у сотрудника есть связанные записи (заказы, смены), он будет деактивирован.')) {
                    window.location.href = 'employees.php?action=delete&id=' + id;
                }
            }
            </script>
            <?php
            break;
    }
    
} catch (Exception $e) {
    setFlashMessage('error', 'Ошибка: ' . $e->getMessage());
    redirect('employees.php');
}

include 'footer.php';
?>