<?php
require_once 'db_connection.php';
require_once 'functions.php';
$action = $_GET['action'] ?? 'form';
$order_id = $_GET['order_id'] ?? 0;
$phone = $_GET['phone'] ?? '';

try {
    global $db;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'submit') {
        $order_id = intval($_POST['order_id'] ?? 0);
        $phone = trim($_POST['phone'] ?? '');
        $rating = intval($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        
        $errors = [];
        
        if (empty($order_id) || $order_id <= 0) {
            $errors[] = 'Укажите номер заказа';
        }
        
        if (empty($phone)) {
            $errors[] = 'Укажите номер телефона';
        }
        
        if ($rating < 1 || $rating > 5) {
            $errors[] = 'Выберите оценку от 1 до 5';
        }
        
        if (empty($comment)) {
            $errors[] = 'Напишите отзыв';
        }
        
        if (empty($errors)) {
            $stmt = $db->prepare("
                SELECT o.id_order, o.id_client, c.phone as client_phone, o.status
                FROM orders o
                LEFT JOIN clients c ON o.id_client = c.id_client
                WHERE o.id_order = ?
            ");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();
            
            if (!$order) {
                $errors[] = 'Заказ не найден';
            } elseif ($order['status'] !== 'оплачен') {
                $errors[] = 'Отзыв можно оставить только для оплаченных заказов';
            } elseif (!empty($order['client_phone']) && $order['client_phone'] !== $phone) {
                $errors[] = 'Телефон не совпадает с телефоном в заказе';
            } else {
                $client_id = $order['id_client'];
                
                if (!$client_id && !empty($phone)) {
                    $stmt = $db->prepare("SELECT id_client FROM clients WHERE phone = ?");
                    $stmt->execute([$phone]);
                    $client = $stmt->fetch();
                    
                    if ($client) {
                        $client_id = $client['id_client'];
                    } else {
                        $stmt = $db->prepare("INSERT INTO clients (fio, phone, registration_date) VALUES (?, ?, CURDATE())");
                        $stmt->execute(['Клиент', $phone]);
                        $client_id = $db->lastInsertId();
                    }
                }
                
                $stmt = $db->prepare("
                    SELECT COUNT(*) FROM feedback 
                    WHERE id_order = ? AND id_client = ?
                ");
                $stmt->execute([$order_id, $client_id]);
                $existing_feedback = $stmt->fetchColumn();
                
                if ($existing_feedback > 0) {
                    $errors[] = 'Вы уже оставили отзыв на этот заказ';
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO feedback (id_client, id_order, rating, comment, feedback_date)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$client_id, $order_id, $rating, $comment]);
                    
                    $success = true;
                }
            }
        }
    }
    
    if (isset($success) && $success) {
        $page_title = 'Спасибо за отзыв';
        ?>
        <!DOCTYPE html>
        <html lang="ru">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
            <style>
                body {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                }
                .feedback-card {
                    background: white;
                    border-radius: 20px;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                    padding: 40px;
                    max-width: 500px;
                    width: 100%;
                    text-align: center;
                }
                .success-icon {
                    font-size: 80px;
                    color: #28a745;
                    margin-bottom: 20px;
                }
            </style>
        </head>
        <body>
            <div class="feedback-card">
                <i class="bi bi-check-circle-fill success-icon"></i>
                <h2 class="mb-3">Спасибо за ваш отзыв!</h2>
                <p class="text-muted mb-4">Ваш отзыв успешно отправлен и будет рассмотрен администрацией.</p>
                <a href="client_feedback.php" class="btn btn-primary">Оставить еще один отзыв</a>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
    
    $page_title = 'Оставить отзыв';
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
        <style>
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                padding: 40px 20px;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            .feedback-card {
                background: white;
                border-radius: 20px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                padding: 40px;
                max-width: 600px;
                margin: 0 auto;
            }
            .rating-stars {
                font-size: 40px;
                cursor: pointer;
                text-align: center;
                margin: 20px 0;
            }
            .rating-stars .bi-star {
                color: #ddd;
                transition: color 0.2s;
            }
            .rating-stars .bi-star:hover,
            .rating-stars .bi-star-fill {
                color: #ffc107;
            }
            .header-section {
                text-align: center;
                margin-bottom: 30px;
            }
            .header-section h1 {
                color: #333;
                margin-bottom: 10px;
            }
            .header-section p {
                color: #666;
            }
        </style>
    </head>
    <body>
        <div class="feedback-card">
            <div class="header-section">
                <h1><i class="bi bi-chat-heart"></i> Оставить отзыв</h1>
                <p>Поделитесь своим мнением о нашем обслуживании</p>
            </div>
            
            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="client_feedback.php?action=submit">
                <div class="mb-3">
                    <label class="form-label">Номер заказа *</label>
                    <input type="number" class="form-control" name="order_id" 
                           value="<?php echo htmlspecialchars($order_id); ?>" 
                           placeholder="Введите номер заказа" required>
                    <small class="text-muted">Номер заказа указан в чеке</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Телефон *</label>
                    <input type="tel" class="form-control" name="phone" 
                           value="<?php echo htmlspecialchars($phone); ?>" 
                           placeholder="+7 (999) 123-45-67" required>
                    <small class="text-muted">Телефон, указанный при оформлении заказа</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Оценка *</label>
                    <div class="rating-stars" id="ratingStars">
                        <i class="bi bi-star" data-rating="1"></i>
                        <i class="bi bi-star" data-rating="2"></i>
                        <i class="bi bi-star" data-rating="3"></i>
                        <i class="bi bi-star" data-rating="4"></i>
                        <i class="bi bi-star" data-rating="5"></i>
                    </div>
                    <input type="hidden" name="rating" id="ratingInput" required>
                    <small class="text-muted d-block text-center">Выберите оценку от 1 до 5 звезд</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Ваш отзыв *</label>
                    <textarea class="form-control" name="comment" rows="5" 
                              placeholder="Расскажите о вашем опыте посещения нашего кафе..." required></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 btn-lg">
                    <i class="bi bi-send"></i> Отправить отзыв
                </button>
            </form>
        </div>
        
        <script>
        const stars = document.querySelectorAll('#ratingStars .bi-star');
        const ratingInput = document.getElementById('ratingInput');
        
        stars.forEach(star => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                ratingInput.value = rating;
                
                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.classList.remove('bi-star');
                        s.classList.add('bi-star-fill');
                    } else {
                        s.classList.remove('bi-star-fill');
                        s.classList.add('bi-star');
                    }
                });
            });
            
            star.addEventListener('mouseenter', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.classList.remove('bi-star');
                        s.classList.add('bi-star-fill');
                    }
                });
            });
        });
        
        document.getElementById('ratingStars').addEventListener('mouseleave', function() {
            const currentRating = parseInt(ratingInput.value) || 0;
            stars.forEach((s, index) => {
                if (index < currentRating) {
                    s.classList.remove('bi-star');
                    s.classList.add('bi-star-fill');
                } else {
                    s.classList.remove('bi-star-fill');
                    s.classList.add('bi-star');
                }
            });
        });
        </script>
    </body>
    </html>
    <?php
    
} catch (Exception $e) {
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <title>Ошибка</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="alert alert-danger">
                <h4>Ошибка</h4>
                <p><?php echo htmlspecialchars($e->getMessage()); ?></p>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>






