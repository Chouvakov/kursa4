<?php
require_once 'db_connection.php';
require_once 'functions.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    die('<div class="alert alert-danger">Для доступа к этой странице необходимо <a href="index.php">авторизоваться</a></div>');
}
$user_role = $_SESSION['role'] ?? '';
if (!in_array($user_role, ['менеджер', 'администратор'])) {
    die('<div class="alert alert-danger">Доступ запрещен. Недостаточно прав.</div>');
}
$is_admin = ($user_role === 'администратор');
$action = isset($_GET['action']) ? trim($_GET['action']) : 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$date = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
$employee_id = isset($_GET['employee_id']) ? trim($_GET['employee_id']) : '';

try {
    global $db;
    $selected_date = new DateTime($date);
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    if (!$is_admin && $selected_date < $today) {
        $date = date('Y-m-d');
        $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Менеджер не может редактировать прошедшие даты. Показана сегодняшняя дата.'];
        header("Location: schedule.php?date=" . $date);
        exit();
    }
    $default_shifts = [
        ['shift_name' => 'Утренняя смена', 'start_time' => '08:00', 'end_time' => '16:00'],
        ['shift_name' => 'Вечерняя смена', 'start_time' =>         '16:00', 'end_time' => '00:00']
    ];
    foreach ($default_shifts as $shift) {
        $check_shift_stmt = $db->prepare("
            SELECT id_shift FROM shifts 
            WHERE shift_name = ? AND work_date = ?
        ");
        $check_shift_stmt->execute([$shift['shift_name'], $date]);
        
        if (!$check_shift_stmt->fetch()) {
            $create_shift_stmt = $db->prepare("
                INSERT INTO shifts (shift_name, start_time, end_time, work_date)
                VALUES (?, ?, ?, ?)
            ");
            $create_shift_stmt->execute([
                $shift['shift_name'],
                $shift['start_time'],
                $shift['end_time'],
                $date
            ]);
        }
    }
    if ($action === 'assign_employee' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $employee_id = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : 0;
        $shift_id = isset($_POST['shift_id']) ? intval($_POST['shift_id']) : 0;
        $schedule_date = isset($_POST['schedule_date']) ? trim($_POST['schedule_date']) : '';
        if (!$is_admin) {
            $schedule_date_obj = new DateTime($schedule_date);
            if ($schedule_date_obj < $today) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'Менеджер не может назначать смены на прошедшие даты'];
                header("Location: schedule.php?date=" . $date);
                exit();
            }
        }
        
        if (empty($employee_id) || empty($shift_id) || empty($schedule_date)) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Все поля обязательны'];
        } else {
            $check_stmt = $db->prepare("
                SELECT COUNT(*) FROM employee_schedule 
                WHERE id_employee = ? AND schedule_date = ?
            ");
            $check_stmt->execute([$employee_id, $schedule_date]);
            $already_assigned = $check_stmt->fetchColumn();
            
            if ($already_assigned) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'Сотрудник уже назначен на эту дату'];
            } else {
                $stmt = $db->prepare("
                    INSERT INTO employee_schedule (id_employee, id_shift, schedule_date)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$employee_id, $shift_id, $schedule_date]);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Сотрудник назначен на смену'];
            }
        }
        
        header("Location: schedule.php?date=" . urlencode($schedule_date));
        exit();
    }
    if ($action === 'remove_assignment') {
        $assignment_id = isset($_GET['assignment_id']) ? intval($_GET['assignment_id']) : 0;
        $redirect_date = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
        if (!$is_admin) {
            $redirect_date_obj = new DateTime($redirect_date);
            if ($redirect_date_obj < $today) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'Менеджер не может удалять назначения на прошедшие даты'];
                header("Location: schedule.php?date=" . $redirect_date);
                exit();
            }
        }
        
        if ($assignment_id > 0) {
            $stmt = $db->prepare("DELETE FROM employee_schedule WHERE id_schedule = ?");
            $stmt->execute([$assignment_id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Назначение удалено'];
        }
        
        header("Location: schedule.php?date=" . urlencode($redirect_date));
        exit();
    }
    if ($action === 'add_shift' && $_SERVER['REQUEST_METHOD'] === 'POST' && $is_admin) {
        $shift_name = isset($_POST['shift_name']) ? trim($_POST['shift_name']) : '';
        $start_time = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
        $end_time = isset($_POST['end_time']) ? trim($_POST['end_time']) : '';
        $work_date = isset($_POST['work_date']) ? trim($_POST['work_date']) : '';
        
        if (empty($shift_name) || empty($start_time) || empty($end_time) || empty($work_date)) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Все поля обязательны'];
        } else {
            $check_stmt = $db->prepare("
                SELECT COUNT(*) FROM shifts 
                WHERE shift_name = ? AND work_date = ?
            ");
            $check_stmt->execute([$shift_name, $work_date]);
            $exists = $check_stmt->fetchColumn();
            
            if ($exists) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'Смена с таким названием уже существует на эту дату'];
            } else {
                $stmt = $db->prepare("
                    INSERT INTO shifts (shift_name, start_time, end_time, work_date)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$shift_name, $start_time, $end_time, $work_date]);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Смена добавлена'];
            }
        }
        
        header("Location: schedule.php?date=" . urlencode($work_date));
        exit();
    }
    if ($action === 'edit_shift' && $_SERVER['REQUEST_METHOD'] === 'POST' && $is_admin) {
        $shift_id = isset($_POST['shift_id']) ? intval($_POST['shift_id']) : 0;
        $shift_name = isset($_POST['shift_name']) ? trim($_POST['shift_name']) : '';
        $start_time = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
        $end_time = isset($_POST['end_time']) ? trim($_POST['end_time']) : '';
        $work_date = isset($_POST['work_date']) ? trim($_POST['work_date']) : '';
        
        if (empty($shift_id) || empty($shift_name) || empty($start_time) || empty($end_time) || empty($work_date)) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Все поля обязательны'];
        } else {
            $check_stmt = $db->prepare("
                SELECT COUNT(*) FROM shifts 
                WHERE shift_name = ? AND work_date = ? AND id_shift != ?
            ");
            $check_stmt->execute([$shift_name, $work_date, $shift_id]);
            $exists = $check_stmt->fetchColumn();
            
            if ($exists) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'Смена с таким названием уже существует на эту дату'];
            } else {
                $stmt = $db->prepare("
                    UPDATE shifts 
                    SET shift_name = ?, start_time = ?, end_time = ?, work_date = ?
                    WHERE id_shift = ?
                ");
                $stmt->execute([$shift_name, $start_time, $end_time, $work_date, $shift_id]);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Смена обновлена'];
            }
        }
        
        header("Location: schedule.php?date=" . urlencode($work_date));
        exit();
    }
    if ($action === 'delete_shift' && $is_admin) {
        $shift_id = isset($_GET['shift_id']) ? intval($_GET['shift_id']) : 0;
        $redirect_date = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
        if ($shift_id > 0) {
            $check_assignments = $db->prepare("
                SELECT COUNT(*) FROM employee_schedule 
                WHERE id_shift = ?
            ");
            $check_assignments->execute([$shift_id]);
            $has_assignments = $check_assignments->fetchColumn();
            
            if ($has_assignments) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'Нельзя удалить смену, на которую назначены сотрудники'];
            } else {
                $stmt = $db->prepare("DELETE FROM shifts WHERE id_shift = ?");
                $stmt->execute([$shift_id]);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Смена удалена'];
            }
        }
        
        header("Location: schedule.php?date=" . urlencode($redirect_date));
        exit();
    }
    $page_title = 'Расписание смен';
    $shifts_stmt = $db->prepare("
        SELECT * FROM shifts 
        WHERE work_date = ?
        ORDER BY start_time
    ");
    $shifts_stmt->execute([$date]);
    $shifts = $shifts_stmt->fetchAll();
    $assignments_stmt = $db->prepare("
        SELECT es.*, e.fio, e.position, s.shift_name, s.start_time, s.end_time
        FROM employee_schedule es
        LEFT JOIN employees e ON es.id_employee = e.id_employee
        LEFT JOIN shifts s ON es.id_shift = s.id_shift
        WHERE es.schedule_date = ?
        ORDER BY s.start_time, e.fio
    ");
    $assignments_stmt->execute([$date]);
    $assignments = $assignments_stmt->fetchAll();
    $assignments_by_shift = [];
    foreach ($assignments as $assignment) {
        $shift_id = $assignment['id_shift'];
        $assignments_by_shift[$shift_id][] = $assignment;
    }
    $employees = $db->query("
        SELECT id_employee, fio, position 
        FROM employees 
        WHERE position IN ('официант', 'повар') AND is_active = 1
        ORDER BY position, fio
    ")->fetchAll();
    $stats_stmt = $db->prepare("
        SELECT e.id_employee, e.fio, e.position,
               COUNT(es.id_schedule) as shifts_today
        FROM employees e
        LEFT JOIN employee_schedule es ON e.id_employee = es.id_employee 
            AND es.schedule_date = ?
        WHERE e.position IN ('официант', 'повар') AND e.is_active = 1
        GROUP BY e.id_employee
        ORDER BY e.position, e.fio
    ");
    $stats_stmt->execute([$date]);
    $employee_stats = $stats_stmt->fetchAll();
    $current_month = date('m', strtotime($date));
    $current_year = date('Y', strtotime($date));
    $today_str = date('Y-m-d');
    
    $stmt = $db->prepare("
        SELECT DISTINCT schedule_date
        FROM employee_schedule 
        WHERE MONTH(schedule_date) = ? AND YEAR(schedule_date) = ?
        ORDER BY schedule_date
    ");
    $stmt->execute([$current_month, $current_year]);
    $busy_days = array_column($stmt->fetchAll(), 'schedule_date');
    $flash_html = '';
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        
        $alert_class = [
            'success' => 'alert-success',
            'error'   => 'alert-danger',
            'warning' => 'alert-warning',
            'info'    => 'alert-info'
        ][$flash['type']] ?? 'alert-info';
        
        $flash_html = '<div class="alert ' . $alert_class . ' alert-dismissible fade show" role="alert">
            ' . htmlspecialchars($flash['message']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    }
    $page_title = 'Расписание смен';
    include 'header.php';
    ?>
    <style>
        .shift-card {
            border: 2px solid #667eea;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .calendar-day {
            padding: 5px;
            text-align: center;
            cursor: pointer;
        }
        .calendar-day:hover {
            background-color: #e9ecef;
        }
        .calendar-day.active {
            background-color: #667eea;
            color: white;
        }
        .calendar-day.has-shift {
            color: #28a745;
            font-weight: bold;
        }
        .calendar-day.past-date {
            background-color: #f8f9fa;
            color: #adb5bd;
        }
        .calendar-day.past-date a {
            text-decoration: none;
        }
        .readonly-badge {
            background-color: #6c757d;
            color: white;
            font-size: 0.8em;
            padding: 2px 6px;
            border-radius: 3px;
            margin-left: 5px;
        }
        .shift-actions {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #dee2e6;
        }
        .default-shift {
            border-left: 4px solid #28a745;
        }
        .custom-shift {
            border-left: 4px solid #ffc107;
        }
    </style>
    <?php echo $flash_html; ?>
            
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Расписание смен <?php echo $is_admin ? '(Администратор)' : '(Менеджер)'; ?></h5>
                    <?php if ($is_admin): ?>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addShiftModal">
                        <i class="bi bi-plus-circle"></i> Добавить смену
                    </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <form method="GET" class="d-flex">
                                <input type="date" class="form-control me-2" name="date" 
                                       value="<?php echo htmlspecialchars($date); ?>" 
                                       <?php if (!$is_admin): ?>min="<?php echo date('Y-m-d'); ?>"<?php endif; ?>
                                       onchange="this.form.submit()">
                                <input type="hidden" name="action" value="list">
                            </form>
                        </div>
                        <div class="col-md-9">
                            <div class="alert alert-info py-2 mb-0">
                                <strong><?php echo date('d.m.Y', strtotime($date)); ?></strong> - 
                                <?php 
                                $days = ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'];
                                echo $days[date('w', strtotime($date))]; 
                                ?> |
                                <strong>Назначено:</strong> <?php echo count($assignments); ?> сотрудников |
                                <strong>Смен:</strong> <?php echo count($shifts); ?>
                                <?php if ($selected_date < $today): ?>
                                    <span class="badge bg-secondary float-end">Прошедшая дата <?php echo $is_admin ? '(админ может редактировать)' : '(только просмотр)'; ?></span>
                                <?php elseif ($selected_date > $today): ?>
                                    <span class="badge bg-warning float-end">Будущая дата</span>
                                <?php else: ?>
                                    <span class="badge bg-success float-end">Сегодня</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0">Смены на <?php echo date('d.m.Y', strtotime($date)); ?></h6>
                                </div>
                                <div class="card-body">
                                    <?php if (!$is_admin && $selected_date < $today): ?>
                                        <div class="alert alert-warning">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            <strong>Внимание менеджеру:</strong> Вы просматриваете прошедшую дату. 
                                            Вы не можете вносить изменения в прошедшие даты.
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($shifts): ?>
                                        <div class="row">
                                            <?php foreach ($shifts as $shift): 
                                                $shift_assignments = $assignments_by_shift[$shift['id_shift']] ?? [];
                                                $is_past_date = $selected_date < $today;
                                                $readonly = (!$is_admin && $is_past_date);
                                                $is_default_shift = in_array($shift['shift_name'], ['Утренняя смена', 'Вечерняя смена']);
                                                $card_class = $is_default_shift ? 'default-shift' : 'custom-shift';
                                            ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="card border-primary <?php echo $card_class; ?>">
                                                    <div class="card-header bg-primary text-white py-2">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <h6 class="mb-0">
                                                                <?php echo htmlspecialchars($shift['shift_name']); ?>
                                                                <?php if ($is_default_shift): ?>
                                                                    <span class="badge bg-success" style="font-size: 0.7em;">стандартная</span>
                                                                <?php endif; ?>
                                                            </h6>
                                                            <div>
                                                                <span class="badge bg-light text-dark">
                                                                    <?php echo substr($shift['start_time'], 0, 5); ?> - <?php echo substr($shift['end_time'], 0, 5); ?>
                                                                </span>
                                                                <?php if ($readonly): ?>
                                                                    <span class="readonly-badge">только просмотр</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="card-body">
                                                        <p class="mb-2">
                                                            <strong>Сотрудников:</strong> <?php echo count($shift_assignments); ?>
                                                        </p>
                                                        
                                                        <?php if ($shift_assignments): ?>
                                                            <div class="mb-3">
                                                                <h6>Назначенные сотрудники:</h6>
                                                                <ul class="list-group list-group-flush">
                                                                    <?php foreach ($shift_assignments as $assignment): ?>
                                                                    <li class="list-group-item d-flex justify-content-between align-items-center py-1 px-2">
                                                                        <div>
                                                                            <strong><?php echo htmlspecialchars($assignment['fio']); ?></strong>
                                                                            <br>
                                                                            <small class="text-muted"><?php echo $assignment['position']; ?></small>
                                                                        </div>
                                                                        <?php if (!$readonly): ?>
                                                                        <button type="button" class="btn btn-sm btn-danger"
                                                                                onclick="if(confirm('Удалить назначение?')) window.location.href='schedule.php?action=remove_assignment&assignment_id=<?php echo $assignment['id_schedule']; ?>&date=<?php echo $date; ?>'">
                                                                            <i class="bi bi-trash"></i>
                                                                        </button>
                                                                        <?php endif; ?>
                                                                    </li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            </div>
                                                        <?php else: ?>
                                                            <p class="text-muted">Нет назначенных сотрудников</p>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (!$readonly): ?>
                                                        <form method="POST" action="schedule.php?action=assign_employee" 
                                                              class="row g-1 mb-2">
                                                            <div class="col-8">
                                                                <select class="form-select form-select-sm" name="employee_id" required>
                                                                    <option value="">Выберите сотрудника</option>
                                                                    <?php foreach ($employees as $emp): ?>
                                                                    <option value="<?php echo $emp['id_employee']; ?>">
                                                                        <?php echo htmlspecialchars($emp['fio']); ?> (<?php echo $emp['position']; ?>)
                                                                    </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-4">
                                                                <input type="hidden" name="shift_id" value="<?php echo $shift['id_shift']; ?>">
                                                                <input type="hidden" name="schedule_date" value="<?php echo $date; ?>">
                                                                <button type="submit" class="btn btn-sm btn-success w-100">
                                                                    Назначить
                                                                </button>
                                                            </div>
                                                        </form>
                                                        <?php endif; ?>
                                                        

                                                        <?php if ($is_admin && !$is_default_shift): ?>
                                                        <div class="shift-actions">
                                                            <div class="d-flex justify-content-between">
                                                                <button type="button" class="btn btn-sm btn-warning" 
                                                                   onclick="editShift(<?php echo $shift['id_shift']; ?>, '<?php echo htmlspecialchars($shift['shift_name']); ?>', '<?php echo $shift['start_time']; ?>', '<?php echo $shift['end_time']; ?>', '<?php echo $shift['work_date']; ?>')">
                                                                    <i class="bi bi-pencil"></i> Редактировать
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-danger"
                                                                        onclick="if(confirm('Удалить смену?')) window.location.href='schedule.php?action=delete_shift&shift_id=<?php echo $shift['id_shift']; ?>&date=<?php echo $date; ?>'">
                                                                    <i class="bi bi-trash"></i> Удалить
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($readonly && !$is_admin): ?>
                                                        <div class="alert alert-secondary mt-2">
                                                            <small><i class="bi bi-info-circle"></i> Редактирование недоступно для менеджера на прошедшие даты</small>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center text-muted py-4">
                                            <i class="bi bi-calendar-x" style="font-size: 48px;"></i>
                                            <p class="mt-2">На выбранную дату нет смен</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Статистика сотрудников</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Сотрудник</th>
                                                    <th>Должность</th>
                                                    <th>Назначен</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($employee_stats as $stat): 
                                                    $is_assigned = $stat['shifts_today'] > 0;
                                                ?>
                                                <tr>
                                                    <td>
                                                        <small><?php echo htmlspecialchars($stat['fio']); ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary">
                                                            <?php echo $stat['position']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($is_assigned): ?>
                                                        <span class="badge bg-success">Да</span>
                                                        <?php else: ?>
                                                        <span class="badge bg-secondary">Нет</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <h6>Быстрое назначение:</h6>
                                        <form method="GET" class="row g-2">
                                            <div class="col-md-12">
                                                <select class="form-select form-select-sm" name="employee_id" 
                                                        onchange="if(this.value) window.location.href='employees.php?action=view&id=' + this.value">
                                                    <option value="">Перейти к сотруднику...</option>
                                                    <?php foreach ($employees as $emp): ?>
                                                    <option value="<?php echo $emp['id_employee']; ?>">
                                                        <?php echo htmlspecialchars($emp['fio']); ?> (<?php echo $emp['position']; ?>)
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Календарь на месяц -->
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h6 class="mb-0">Календарь</h6>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $first_day = date('N', strtotime("$current_year-$current_month-01"));
                                    $days_in_month = date('t', strtotime("$current_year-$current_month-01"));
                                    ?>
                                    
                                    <div class="text-center">
                                        <h6><?php echo date('F Y', strtotime($date)); ?></h6>
                                        <div class="row">
                                            <div class="col p-1 border"><small>Пн</small></div>
                                            <div class="col p-1 border"><small>Вт</small></div>
                                            <div class="col p-1 border"><small>Ср</small></div>
                                            <div class="col p-1 border"><small>Чт</small></div>
                                            <div class="col p-1 border"><small>Пт</small></div>
                                            <div class="col p-1 border"><small>Сб</small></div>
                                            <div class="col p-1 border"><small>Вс</small></div>
                                        </div>
                                        
                                        <?php
                                        $day = 1;
                                        for ($i = 0; $i < 6; $i++): ?>
                                            <div class="row">
                                                <?php for ($j = 1; $j <= 7; $j++): ?>
                                                    <?php if (($i == 0 && $j < $first_day) || $day > $days_in_month): ?>
                                                        <div class="col p-2 border bg-light"></div>
                                                    <?php else: 
                                                        $current_date = "$current_year-$current_month-" . str_pad($day, 2, '0', STR_PAD_LEFT);
                                                        $is_today = $current_date == date('Y-m-d');
                                                        $has_shifts = in_array($current_date, $busy_days);
                                                        $is_past = $current_date < date('Y-m-d');
                                                    ?>
                                                        <div class="col p-2 border text-center calendar-day <?php echo $is_today ? 'active' : ($is_past ? 'past-date' : ''); ?>">
                                                            <?php if ($is_past && !$is_admin): ?>
                                                                <small style="color: #adb5bd;">
                                                                    <?php echo $day; ?>
                                                                    <?php if ($has_shifts): ?>
                                                                        <br><i class="bi bi-circle-fill" style="font-size: 8px;"></i>
                                                                    <?php endif; ?>
                                                                </small>
                                                            <?php else: ?>
                                                                <small>
                                                                    <a href="schedule.php?date=<?php echo $current_date; ?>" 
                                                                       class="<?php echo $is_today ? 'text-white' : ($has_shifts ? 'text-success' : 'text-dark'); ?>"
                                                                       style="text-decoration: none;">
                                                                        <?php echo $day; ?>
                                                                        <?php if ($has_shifts): ?>
                                                                            <br><i class="bi bi-circle-fill" style="font-size: 8px;"></i>
                                                                        <?php endif; ?>
                                                                    </a>
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php $day++; ?>
                                                    <?php endif; ?>
                                                <?php endfor; ?>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Модальное окно для добавления смены (только для администратора) -->
        <?php if ($is_admin): ?>
        <div class="modal fade" id="addShiftModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="schedule.php?action=add_shift">
                        <div class="modal-header">
                            <h5 class="modal-title">Добавить новую смену</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Название смены *</label>
                                <input type="text" class="form-control" name="shift_name" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Начало смены *</label>
                                    <input type="time" class="form-control" name="start_time" value="08:00" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Конец смены *</label>
                                    <input type="time" class="form-control" name="end_time" value="16:00" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Дата смены *</label>
                                <input type="date" class="form-control" name="work_date" value="<?php echo $date; ?>" required>
                                <small class="text-muted">Администратор может добавлять смены на любые даты (включая прошлые)</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                            <button type="submit" class="btn btn-primary">Добавить смену</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Модальное окно для редактирования смены (только для администратора) -->
        <div class="modal fade" id="editShiftModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="schedule.php?action=edit_shift" id="editShiftForm">
                        <div class="modal-header">
                            <h5 class="modal-title">Редактировать смену</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="shift_id" id="edit_shift_id">
                            
                            <div class="mb-3">
                                <label class="form-label">Название смены *</label>
                                <input type="text" class="form-control" name="shift_name" id="edit_shift_name" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Начало смены *</label>
                                    <input type="time" class="form-control" name="start_time" id="edit_start_time" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Конец смены *</label>
                                    <input type="time" class="form-control" name="end_time" id="edit_end_time" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Дата смены *</label>
                                <input type="date" class="form-control" name="work_date" id="edit_work_date" required>
                                <small class="text-muted">Администратор может редактировать смены на любые даты</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                            <button type="submit" class="btn btn-primary">Сохранить изменения</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.querySelector('input[type="date"]');
            const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
            
            if (!isAdmin) {
                const today = new Date().toISOString().split('T')[0];
                dateInput.min = today;
                dateInput.addEventListener('change', function() {
                    const selectedDate = new Date(this.value);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    
                    if (selectedDate < today) {
                        alert('Менеджер не может выбирать прошедшие даты');
                        this.value = today.toISOString().split('T')[0];
                        this.form.submit();
                    }
                });
            }
        });
        
        <?php if ($is_admin): ?>
        function editShift(shiftId, shiftName, startTime, endTime, workDate) {
            document.getElementById('edit_shift_id').value = shiftId;
            document.getElementById('edit_shift_name').value = shiftName;
            document.getElementById('edit_start_time').value = startTime;
            document.getElementById('edit_end_time').value = endTime;
            document.getElementById('edit_work_date').value = workDate;
            const modal = new bootstrap.Modal(document.getElementById('editShiftModal'));
            modal.show();
        }
        document.getElementById('editShiftForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            this.submit();
        });
        <?php endif; ?>
        </script>
    <?php
    include 'footer.php';
} catch (Exception $e) {
    include 'header.php';
    echo '<div class="alert alert-danger">Ошибка: ' . htmlspecialchars($e->getMessage()) . '</div>';
    include 'footer.php';
}