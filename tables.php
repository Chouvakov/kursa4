<?php
require_once 'db_connection.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    redirect('index.php');
}
$user_role = getUserRole();
$allowed_roles = ['официант', 'менеджер', 'администратор', 'повар'];
if (!in_array($user_role, $allowed_roles)) {
    setFlashMessage('error', 'Доступ запрещен');
    redirect('dashboard.php');
}

$action = getGet('action', 'list');
$id = getGet('id', 0);

try {
    global $db;
    
    switch ($action) {
        case 'change_status':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $table_id = getPost('table_id');
                $status = getPost('status');
                if ($status === 'свободен') {
                    $stmt = $db->prepare("
                        SELECT COUNT(*) as active_orders
                        FROM orders 
                        WHERE id_table = ? 
                        AND status NOT IN ('оплачен', 'отменен')
                    ");
                    $stmt->execute([$table_id]);
                    $result = $stmt->fetch();
                    
                    if ($result['active_orders'] > 0) {
                        setFlashMessage('error', 'Нельзя освободить столик: у столика есть активные заказы. Сначала оплатите или отмените все заказы.');
                        redirect('tables.php');
                        break;
                    }
                }
                
                $stmt = $db->prepare("UPDATE tables SET status = ? WHERE id_table = ?");
                $stmt->execute([$status, $table_id]);
                
                setFlashMessage('success', 'Статус столика обновлен');
                redirect('tables.php');
            }
            break;
            
        case 'reserve':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $table_id = getPost('table_id');
                $client_name = getPost('client_name');
                $reserve_time = getPost('reserve_time');
                $phone = getPost('phone');
                

                $stmt = $db->prepare("SELECT status FROM tables WHERE id_table = ?");
                $stmt->execute([$table_id]);
                $table = $stmt->fetch();
                
                if ($table['status'] !== 'свободен') {
                    setFlashMessage('error', 'Столик уже занят или забронирован');
                    redirect('tables.php');
                }
                

                $stmt = $db->prepare("UPDATE tables SET status = 'забронирован' WHERE id_table = ?");
                $stmt->execute([$table_id]);
                
                setFlashMessage('success', "Столик забронирован на имя $client_name");
                redirect('tables.php');
            }
            break;
            
        default:
   
            $stmt = $db->query("SELECT * FROM tables ORDER BY table_number");
            $tables = $stmt->fetchAll();
            
            include 'header.php';
            ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Управление столиками</h5>
                    <div>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#reserveModal">
                            <i class="bi bi-calendar-plus"></i> Забронировать
                        </button>
                    </div>
                </div>
                <div class="card-body">
    
                    <div class="row mb-4">
                        <div class="col-md-3 mb-2">
                            <div class="d-flex align-items-center">
                                <span class="badge bg-success me-2" style="width: 20px; height: 20px;"></span>
                                <span>Свободен</span>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="d-flex align-items-center">
                                <span class="badge bg-danger me-2" style="width: 20px; height: 20px;"></span>
                                <span>Занят</span>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="d-flex align-items-center">
                                <span class="badge bg-warning me-2" style="width: 20px; height: 20px;"></span>
                                <span>Забронирован</span>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="d-flex align-items-center">
                                <span class="badge bg-secondary me-2" style="width: 20px; height: 20px;"></span>
                                <span>Недоступен</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <?php foreach ($tables as $table): 
                            $status_class = [
                                'свободен' => 'success',
                                'занят' => 'danger',
                                'забронирован' => 'warning',
                                'недоступен' => 'secondary'
                            ][$table['status']] ?? 'secondary';
                        ?>
                        <div class="col-md-4 mb-4">
                            <div class="card border-<?php echo $status_class; ?>">
                                <div class="card-header bg-<?php echo $status_class; ?> text-white d-flex justify-content-between">
                                    <h6 class="mb-0">Столик <?php echo $table['table_number']; ?></h6>
                                    <span class="badge bg-light text-dark">до <?php echo $table['capacity']; ?> чел.</span>
                                </div>
                                <div class="card-body">
                                    <p><strong>Расположение:</strong> <?php echo $table['location'] ?? '—'; ?></p>
                                    <p><strong>Статус:</strong> 
                                        <span class="badge bg-<?php echo $status_class; ?>">
                                            <?php echo $table['status']; ?>
                                        </span>
                                    </p>
                                    
                                    <div class="btn-group w-100">
                                        <?php if ($table['status'] == 'свободен'): ?>
                                        <button class="btn btn-success btn-sm" 
                                                onclick="changeStatus(<?php echo $table['id_table']; ?>, 'занят')">
                                            Занять
                                        </button>
                                        <button class="btn btn-warning btn-sm" 
                                                onclick="openReserveModal(<?php echo $table['id_table']; ?>, '<?php echo $table['table_number']; ?>')">
                                            Бронь
                                        </button>
                                        <?php elseif ($table['status'] == 'занят'): ?>
                                        <button class="btn btn-primary btn-sm" 
                                                onclick="changeStatus(<?php echo $table['id_table']; ?>, 'свободен')">
                                            Освободить
                                        </button>
                                        <?php elseif ($table['status'] == 'забронирован'): ?>
                                        <button class="btn btn-info btn-sm" 
                                                onclick="changeStatus(<?php echo $table['id_table']; ?>, 'свободен')">
                                            Отменить бронь
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($table['status'] == 'свободен' || $table['status'] == 'забронирован'): ?>
                                        <a href="orders.php?action=new&table_id=<?php echo $table['id_table']; ?>" 
                                        class="btn btn-primary btn-sm">
                                            Создать заказ
                                        </a>
                                        <?php else: ?>
                                        <a href="orders.php?table_id=<?php echo $table['id_table']; ?>" 
                                        class="btn btn-outline-primary btn-sm">
                                            Просмотр заказов
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="modal fade" id="reserveModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="tables.php?action=reserve">
                            <div class="modal-header">
                                <h5 class="modal-title">Бронирование столика</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Столик</label>
                                    <select class="form-select" id="reserve_table_id" name="table_id" required>
                                        <option value="">Выберите столик</option>
                                        <?php 
                                        $free_tables = array_filter($tables, fn($t) => $t['status'] === 'свободен');
                                        foreach ($free_tables as $table): 
                                        ?>
                                        <option value="<?php echo $table['id_table']; ?>">
                                            Столик <?php echo $table['table_number']; ?> (<?php echo $table['capacity']; ?> чел.)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Имя клиента</label>
                                    <input type="text" class="form-control" name="client_name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Телефон</label>
                                    <input type="tel" class="form-control" name="phone" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Время бронирования</label>
                                    <input type="datetime-local" class="form-control" name="reserve_time" 
                                        value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                <button type="submit" class="btn btn-primary">Забронировать</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <script>
            function changeStatus(tableId, newStatus) {
                if (confirm('Изменить статус столика?')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'tables.php?action=change_status';
                    
                    const tableIdInput = document.createElement('input');
                    tableIdInput.type = 'hidden';
                    tableIdInput.name = 'table_id';
                    tableIdInput.value = tableId;
                    
                    const statusInput = document.createElement('input');
                    statusInput.type = 'hidden';
                    statusInput.name = 'status';
                    statusInput.value = newStatus;
                    
                    form.appendChild(tableIdInput);
                    form.appendChild(statusInput);
                    document.body.appendChild(form);
                    form.submit();
                }
            }
            
            function openReserveModal(tableId, tableNumber) {
                document.getElementById('reserve_table_id').value = tableId;
                const modal = new bootstrap.Modal(document.getElementById('reserveModal'));
                modal.show();
            }
            </script>
            <?php
    }
    
} catch (Exception $e) {
    setFlashMessage('error', 'Ошибка: ' . $e->getMessage());
    redirect('tables.php');
}

include 'footer.php';
?>