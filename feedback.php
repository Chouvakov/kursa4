<?php
require_once 'db_connection.php';
require_once 'functions.php';
if (!isLoggedIn()) {
    redirect('index.php');
}
$user_role = getUserRole();
if (!in_array($user_role, ['менеджер', 'администратор', 'официант'])) {
    setFlashMessage('error', 'Доступ запрещен');
    redirect('dashboard.php');
}
$action = getGet('action', 'list');
$id = getGet('id', 0);
$rating_filter = getGet('rating', '');
$date_from = getGet('date_from', date('Y-m-d', strtotime('-30 days')));
$date_to = getGet('date_to', date('Y-m-d'));

try {
    global $db;
    
    switch ($action) {
        case 'view':
            $stmt = $db->prepare("
                SELECT f.*, c.fio as client_name, c.phone as client_phone,
                       o.id_order, o.total_amount, t.table_number,
                       m.fio as manager_name
                FROM feedback f
                LEFT JOIN clients c ON f.id_client = c.id_client
                LEFT JOIN orders o ON f.id_order = o.id_order
                LEFT JOIN tables t ON o.id_table = t.id_table
                LEFT JOIN employees m ON f.id_manager = m.id_employee
                WHERE f.id_feedback = ?
            ");
            $stmt->execute([$id]);
            $feedback = $stmt->fetch();
            
            if (!$feedback) {
                setFlashMessage('error', 'Отзыв не найден');
                redirect('feedback.php');
            }
            
            $user_id = getUserId();
            $is_manager_response = ($feedback['id_manager'] == $user_id);
            $error = '';
            $success = '';
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (isset($_POST['add_response']) || isset($_POST['edit_response'])) {
                    if ($user_role == 'менеджер' || $user_role == 'администратор') {
                        $response = getPost('manager_response');
                        
                        if (!empty($response)) {
                            if (isset($_POST['edit_response'])) {
                                if ($is_manager_response || $user_role == 'администратор') {
                                    $stmt = $db->prepare("
                                        UPDATE feedback 
                                        SET manager_response = ?, response_date = NOW()
                                        WHERE id_feedback = ?
                                    ");
                                    $stmt->execute([$response, $id]);
                                    
                                    $success = 'Ответ обновлен';
                                } else {
                                    $error = 'Вы можете изменять только свои ответы';
                                }
                            } else {
                                $stmt = $db->prepare("
                                    UPDATE feedback 
                                    SET manager_response = ?, id_manager = ?, response_date = NOW()
                                    WHERE id_feedback = ?
                                ");
                                $stmt->execute([$response, $user_id, $id]);
                                
                                $success = 'Ответ добавлен';
                                $is_manager_response = true; // После добавления это становится его ответом
                            }
                        } else {
                            $error = "Введите текст ответа";
                        }
                    } else {
                        $error = "У вас недостаточно прав для ответа на отзывы";
                    }
                }
                if (isset($_POST['delete_response'])) {
                    if ($user_role == 'администратор' || $is_manager_response) {
                        $stmt = $db->prepare("
                            UPDATE feedback 
                            SET manager_response = NULL, id_manager = NULL, response_date = NULL
                            WHERE id_feedback = ?
                        ");
                        $stmt->execute([$id]);
                        
                        $success = $user_role == 'администратор' ? 'Ответ менеджера удален администратором' : 'Ответ удален';
                        $feedback['manager_response'] = null;
                        $feedback['id_manager'] = null;
                        $feedback['response_date'] = null;
                        $is_manager_response = false;
                    } else {
                        $error = 'Вы можете удалять только свои ответы';
                    }
                }
            }
            if ($success || $error) {
                $stmt = $db->prepare("
                    SELECT f.*, c.fio as client_name, c.phone as client_phone,
                           o.id_order, o.total_amount, t.table_number,
                           m.fio as manager_name
                    FROM feedback f
                    LEFT JOIN clients c ON f.id_client = c.id_client
                    LEFT JOIN orders o ON f.id_order = o.id_order
                    LEFT JOIN tables t ON o.id_table = t.id_table
                    LEFT JOIN employees m ON f.id_manager = m.id_employee
                    WHERE f.id_feedback = ?
                ");
                $stmt->execute([$id]);
                $feedback = $stmt->fetch();
            }
            
            $page_title = 'Просмотр отзыва';
            include 'header.php';
            ?>
            
            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Отзыв #<?php echo $feedback['id_feedback']; ?></h5>
                    <div>
                        <span class="text-warning">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi bi-star<?php echo $i <= $feedback['rating'] ? '-fill' : ''; ?>"></i>
                            <?php endfor; ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6>Информация о клиенте:</h6>
                            <p><strong>Клиент:</strong> <?php echo htmlspecialchars($feedback['client_name']); ?></p>
                            <p><strong>Телефон:</strong> <?php echo htmlspecialchars($feedback['client_phone']); ?></p>
                            <?php if ($feedback['id_order']): ?>
                                <p><strong>Заказ:</strong> <a href="orders.php?action=view&id=<?php echo $feedback['id_order']; ?>">#<?php echo $feedback['id_order']; ?></a></p>
                                <p><strong>Сумма заказа:</strong> <?php echo formatMoney($feedback['total_amount']); ?></p>
                                <p><strong>Столик:</strong> <?php echo htmlspecialchars($feedback['table_number']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h6>Детали отзыва:</h6>
                            <p><strong>Дата отзыва:</strong> <?php echo formatDate($feedback['feedback_date']); ?></p>
                            <?php if ($feedback['response_date']): ?>
                                <p><strong>Дата ответа:</strong> <?php echo formatDate($feedback['response_date']); ?></p>
                                <p><strong>Ответил:</strong> <?php echo htmlspecialchars($feedback['manager_name']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <h6>Отзыв клиента:</h6>
                            <div class="alert alert-warning">
                                <?php echo nl2br(htmlspecialchars($feedback['comment'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($feedback['manager_response']): ?>
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <h6>Ответ менеджера:</h6>
                            <div class="alert alert-success position-relative">
                                <?php echo nl2br(htmlspecialchars($feedback['manager_response'])); ?>
                                
                                <?php if ($is_manager_response || $user_role == 'администратор'): ?>
                                <div class="position-absolute top-0 end-0 mt-2 me-2">
                                    <button type="button" class="btn btn-sm btn-outline-warning" 
                                            data-bs-toggle="modal" data-bs-target="#editResponseModal">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('Вы уверены, что хотите удалить ответ?');">
                                        <input type="hidden" name="delete_response" value="1">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (($user_role == 'менеджер' || $user_role == 'администратор')): ?>
                        <?php if (!$feedback['manager_response']): ?>
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h6>Добавить ответ:</h6>
                                <form method="POST">
                                    <div class="mb-3">
                                        <textarea class="form-control" name="manager_response" rows="4" required></textarea>
                                    </div>
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" name="add_response" class="btn btn-primary">
                                            <i class="bi bi-send"></i> Отправить ответ
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <a href="feedback.php" class="btn btn-secondary">Назад к списку</a>
                    <?php
                    if ($user_role == 'администратор'):
                    ?>
                    <button type="button" class="btn btn-danger float-end"
                            onclick="if(confirm('Вы уверены, что хотите удалить весь отзыв?')) window.location.href='feedback.php?action=delete&id=<?php echo $id; ?>'">
                        <i class="bi bi-trash"></i> Удалить отзыв
                    </button>
                    <?php endif; ?>
                </div>
            </div>
         
            <?php if ($feedback['manager_response'] && ($is_manager_response || $user_role == 'администратор')): ?>
            <div class="modal fade" id="editResponseModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST">
                            <div class="modal-header">
                                <h5 class="modal-title">Редактирование ответа</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label for="edit_manager_response" class="form-label">Текст ответа</label>
                                    <textarea class="form-control" id="edit_manager_response" 
                                              name="manager_response" rows="6" required><?php echo htmlspecialchars($feedback['manager_response']); ?></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                <button type="submit" name="edit_response" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Сохранить изменения
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <script>
            setTimeout(function() {
                var alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 10000);
            </script>
            <?php
            break;
        case 'delete_response':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $feedback_id = getPost('feedback_id', $id);
                $stmt = $db->prepare("SELECT id_manager FROM feedback WHERE id_feedback = ?");
                $stmt->execute([$feedback_id]);
                $feedback_data = $stmt->fetch();
                
                if (!$feedback_data) {
                    setFlashMessage('error', 'Отзыв не найден');
                    redirect('feedback.php');
                }
                
                $response_manager_id = $feedback_data['id_manager'];
                $current_user_id = getUserId();
                if ($user_role == 'администратор' || ($user_role == 'менеджер' && $response_manager_id == $current_user_id)) {
                    $stmt = $db->prepare("
                        UPDATE feedback 
                        SET manager_response = NULL, id_manager = NULL, response_date = NULL
                        WHERE id_feedback = ?
                    ");
                    $stmt->execute([$feedback_id]);
                    
                    setFlashMessage('success', $user_role == 'администратор' ? 'Ответ менеджера удален' : 'Ответ удален');
                } else {
                    setFlashMessage('error', 'Недостаточно прав для удаления ответа');
                }
                
                redirect('feedback.php');
            }
            break;
        case 'delete':
            if ($user_role !== 'администратор') {
                setFlashMessage('error', 'Недостаточно прав');
                redirect('feedback.php');
            }
            
            $stmt = $db->prepare("DELETE FROM feedback WHERE id_feedback = ?");
            $stmt->execute([$id]);
            
            setFlashMessage('success', 'Отзыв удален');
            redirect('feedback.php');
            break;
        default:
            $page = getGet('page', 1);
            $per_page = 20;
            $offset = ($page - 1) * $per_page;
            
            $where = [];
            $params = [];
            
            if ($rating_filter !== '') {
                $where[] = "f.rating = ?";
                $params[] = $rating_filter;
            }
            
            if ($date_from && $date_to) {
                $where[] = "(DATE(f.feedback_date) BETWEEN ? AND ? OR (f.response_date IS NOT NULL AND DATE(f.response_date) BETWEEN ? AND ?))";
                $params[] = $date_from;
                $params[] = $date_to;
                $params[] = $date_from;
                $params[] = $date_to;
            }
            
            $where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";
            $count_sql = "SELECT COUNT(*) FROM feedback f $where_clause";
            $count_stmt = $db->prepare($count_sql);
            $count_stmt->execute($params);
            $total_feedback = $count_stmt->fetchColumn();
            $total_pages = ceil($total_feedback / $per_page);
            $sql = "
                SELECT f.*, c.fio as client_name, 
                       o.id_order, o.total_amount, t.table_number,
                       m.fio as manager_name, m.id_employee as manager_id
                FROM feedback f
                LEFT JOIN clients c ON f.id_client = c.id_client
                LEFT JOIN orders o ON f.id_order = o.id_order
                LEFT JOIN tables t ON o.id_table = t.id_table
                LEFT JOIN employees m ON f.id_manager = m.id_employee
                $where_clause
                ORDER BY f.feedback_date DESC
                LIMIT $per_page OFFSET $offset
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $feedbacks = $stmt->fetchAll();
            
            $page_title = 'Отзывы';
            include 'header.php';
            ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Отзывы клиентов</h5>
                </div>
                <div class="card-body">
              
                    <form method="GET" class="row g-2 mb-4">
                        <div class="col-md-2">
                            <select class="form-select" name="rating">
                                <option value="">Все оценки</option>
                                <option value="5" <?php echo $rating_filter == '5' ? 'selected' : ''; ?>>5 звезд</option>
                                <option value="4" <?php echo $rating_filter == '4' ? 'selected' : ''; ?>>4 звезды</option>
                                <option value="3" <?php echo $rating_filter == '3' ? 'selected' : ''; ?>>3 звезды</option>
                                <option value="2" <?php echo $rating_filter == '2' ? 'selected' : ''; ?>>2 звезды</option>
                                <option value="1" <?php echo $rating_filter == '1' ? 'selected' : ''; ?>>1 звезда</option>
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
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-outline-primary w-100">
                                <i class="bi bi-filter"></i> Фильтровать
                            </button>
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <a href="feedback.php" class="btn btn-outline-secondary w-100">Сбросить</a>
                        </div>
                    </form>
                    
                
                    <?php if ($feedbacks): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Дата</th>
                                        <th>Клиент</th>
                                        <th>Оценка</th>
                                        <th>Отзыв</th>
                                        <th>Заказ</th>
                                        <th>Ответ</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $current_user_id = getUserId();
                                    foreach ($feedbacks as $fb): 
                                        $is_user_response = ($fb['manager_id'] == $current_user_id);
                                    ?>
                                    <tr>
                                        <td><?php echo formatDate($fb['feedback_date'], 'd.m.Y'); ?></td>
                                        <td><?php echo htmlspecialchars($fb['client_name']); ?></td>
                                        <td>
                                            <span class="text-warning">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="bi bi-star<?php echo $i <= $fb['rating'] ? '-fill' : ''; ?>"></i>
                                                <?php endfor; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($fb['comment']): ?>
                                                <small><?php echo mb_strimwidth(htmlspecialchars($fb['comment']), 0, 100, '...'); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">Нет текста</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($fb['id_order']): ?>
                                                <a href="orders.php?action=view&id=<?php echo $fb['id_order']; ?>" class="badge bg-info">
                                                    #<?php echo $fb['id_order']; ?>
                                                </a>
                                                <br>
                                                <small><?php echo htmlspecialchars($fb['table_number']); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($fb['manager_response']): ?>
                                                <span class="badge <?php echo $is_user_response ? 'bg-info' : 'bg-success'; ?>">
                                                    <?php echo $is_user_response ? 'Ваш ответ' : 'Есть ответ'; ?>
                                                </span>
                                                <?php if ($fb['manager_name']): ?>
                                                <br>
                                                <small><?php echo htmlspecialchars($fb['manager_name']); ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Нет ответа</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="feedback.php?action=view&id=<?php echo $fb['id_feedback']; ?>" 
                                                   class="btn btn-info" title="Просмотр">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <?php if ($is_user_response || $user_role == 'администратор'): ?>
                                                <a href="feedback.php?action=view&id=<?php echo $fb['id_feedback']; ?>#edit" 
                                                   class="btn btn-warning" title="Редактировать ответ">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php endif; ?>
                                                <?php if ($fb['manager_response'] && $user_role == 'администратор'): ?>
                                                <form method="POST" action="feedback.php?action=delete_response&id=<?php echo $fb['id_feedback']; ?>" 
                                                      style="display: inline;" 
                                                      onsubmit="return confirm('Вы уверены, что хотите удалить ответ менеджера?');">
                                                    <input type="hidden" name="feedback_id" value="<?php echo $fb['id_feedback']; ?>">
                                                    <button type="submit" class="btn btn-danger" title="Удалить ответ">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
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
                                       href="feedback.php?page=<?php echo $i; ?>&rating=<?php echo $rating_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-chat-left-text" style="font-size: 48px;"></i>
                            <p class="mt-2">Отзывы не найдены</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
    }
    
} catch (Exception $e) {
    setFlashMessage('error', 'Ошибка: ' . $e->getMessage());
    redirect('feedback.php');
}

include 'footer.php';
?>