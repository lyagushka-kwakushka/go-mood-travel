<?php
require_once 'config.php';

// Проверка авторизации
if (!isset($_SESSION['client_id'])) {
    header('Location: login.php');
    exit;
}

$client_id = $_SESSION['client_id'];
$client_name = $_SESSION['client_name'];

// Получение данных клиента
$stmt = $mysqli->prepare("SELECT * FROM clients WHERE client_id = ?");
$stmt->bind_param('i', $client_id);
$stmt->execute();
$client = $stmt->get_result()->fetch_assoc();

// Получение заказов клиента
$stmt = $mysqli->prepare("
    SELECT o.order_id, o.order_date, o.participants_count, o.total, o.order_status,
           t.tour_name, td.start_date, td.end_date
    FROM orders o
    JOIN tour_dates td ON o.tour_date_id = td.tour_date_id
    JOIN tours t ON td.tour_id = t.tour_id
    WHERE o.client_id = ?
    ORDER BY o.order_date DESC
");
$stmt->bind_param('i', $client_id);
$stmt->execute();
$orders = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Личный кабинет | <?= htmlspecialchars($client_name) ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .cabinet-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }
        .welcome {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
        }
        .welcome h2 {
            margin: 0 0 0.5rem 0;
            font-size: 1.8rem;
        }
        .client-info {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            border: 1px solid #e2e8f0;
        }
        .client-info h3 {
            margin-bottom: 1rem;
            color: #1e293b;
        }
        .client-info p {
            margin-bottom: 0.5rem;
        }
        .client-info a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 500;
        }
        .client-info a:hover {
            text-decoration: underline;
        }
        .order-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
        .order-card h4 {
            color: #1e293b;
            margin-bottom: 0.75rem;
        }
        .order-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-confirmed { background: #dbeafe; color: #1e40af; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .status-completed { background: #e0e7ff; color: #3730a3; }
        
        .section-title {
            margin: 1.5rem 0 1rem 0;
            color: #1e293b;
        }
        .btn-review {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        .btn-review:hover {
            background: #1d4ed8;
        }
        .review-form textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin: 0.5rem 0;
            resize: vertical;
        }
        .review-form select {
            padding: 0.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-left: 0.5rem;
        }
        .no-orders {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 12px;
            color: #64748b;
        }
        .logout-btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #ef4444;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-left: 1rem;
        }
        .logout-btn:hover {
            background: #dc2626;
        }
    </style>
</head>
<body>
<header>
    <h1>Личный кабинет</h1>
    <nav>
        <a href="index.php">Главная</a>
        <a href="logout.php" class="logout-btn">Выход</a>
    </nav>
</header>

<main class="cabinet-container">
    <div class="welcome">
        <h2>Добро пожаловать, <?= htmlspecialchars($client_name) ?>!</h2>
        <p>Здесь вы можете просматривать свои заказы и оставлять отзывы</p>
    </div>
    
    <div class="client-info">
        <h3>📋 Ваши данные</h3>
        <p><strong>Телефон:</strong> <?= htmlspecialchars($client['phone'] ?? 'Не указан') ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($client['email'] ?? 'Не указан') ?></p>
        <p><strong>Паспорт:</strong> 
            <?php if (!empty($client['passport_series']) && !empty($client['passport_number'])): ?>
                <?= htmlspecialchars($client['passport_series']) ?> <?= htmlspecialchars($client['passport_number']) ?>
            <?php else: ?>
                Не указан
            <?php endif; ?>
        </p>
        <p><a href="edit_profile.php">✏️ Редактировать профиль</a></p>
    </div>

    <h3 class="section-title">📅 История заказов</h3>
    <?php if ($orders->num_rows > 0): ?>
        <?php while($o = $orders->fetch_assoc()): ?>
            <div class="order-card">
                <h4>Заказ №<?= $o['order_id'] ?> — <?= htmlspecialchars($o['tour_name']) ?></h4>
                <p>
                    <strong>Даты:</strong> 
                    <?= date('d.m.Y', strtotime($o['start_date'])) ?> — 
                    <?= date('d.m.Y', strtotime($o['end_date'])) ?>
                </p>
                <p><strong>Участников:</strong> <?= $o['participants_count'] ?></p>
                <p><strong>Сумма:</strong> <?= number_format($o['total'], 0, ',', ' ') ?> ₽</p>
                <p>
                    <strong>Статус:</strong> 
                    <span class="order-status status-<?= $o['order_status'] ?>">
                        <?php
                        $statuses = [
                            'pending' => 'Ожидает',
                            'confirmed' => 'Подтверждён',
                            'paid' => 'Оплачен',
                            'cancelled' => 'Отменён',
                            'completed' => 'Завершён'
                        ];
                        echo $statuses[$o['order_status']] ?? $o['order_status'];
                        ?>
                    </span>
                </p>
                
                <!-- Платежи -->
                <?php
                $pay = $mysqli->query("
                    SELECT amount, payment_method, payment_status, transaction_id 
                    FROM payments 
                    WHERE order_id = {$o['order_id']}
                ");
                if ($pay->num_rows > 0):
                ?>
                <details style="margin-top: 1rem;">
                    <summary style="cursor: pointer; color: #2563eb;">💰 Платежи</summary>
                    <ul style="margin-top: 0.5rem; padding-left: 1.5rem;">
                    <?php while($p = $pay->fetch_assoc()): ?>
                        <li>
                            <?= number_format($p['amount'], 0, ',', ' ') ?> ₽ 
                            (<?= $p['payment_method'] ?>) — 
                            <?php
                            $pay_statuses = [
                                'pending' => 'Ожидает',
                                'completed' => 'Выполнен',
                                'failed' => 'Ошибка',
                                'refunded' => 'Возврат'
                            ];
                            echo $pay_statuses[$p['payment_status']] ?? $p['payment_status'];
                            ?>
                        </li>
                    <?php endwhile; ?>
                    </ul>
                </details>
                <?php endif; ?>
                
                <!-- Документы -->
                <?php
                $doc = $mysqli->query("
                    SELECT document_type, document_number, document_status 
                    FROM documents 
                    WHERE order_id = {$o['order_id']}
                ");
                if ($doc->num_rows > 0):
                ?>
                <details style="margin-top: 0.5rem;">
                    <summary style="cursor: pointer; color: #2563eb;">📄 Документы</summary>
                    <ul style="margin-top: 0.5rem; padding-left: 1.5rem;">
                    <?php while($d = $doc->fetch_assoc()): ?>
                        <li>
                            <?= $d['document_type'] ?> №<?= $d['document_number'] ?> 
                            (<?= $d['document_status'] ?>)
                        </li>
                    <?php endwhile; ?>
                    </ul>
                </details>
                <?php endif; ?>
                
                <!-- Отзыв -->
                <?php
                $rev = $mysqli->query("
                    SELECT rating, review_text, approved, review_date 
                    FROM reviews 
                    WHERE order_id = {$o['order_id']}
                ");
                if ($rev->num_rows > 0):
                    $r = $rev->fetch_assoc();
                ?>
                <div style="margin-top: 1rem; padding: 0.75rem; background: #f8fafc; border-radius: 8px;">
                    <p><strong>⭐ Ваш отзыв:</strong> <?= str_repeat('★', $r['rating']) . str_repeat('☆', 5 - $r['rating']) ?></p>
                    <p><?= htmlspecialchars($r['review_text']) ?></p>
                    <p style="font-size: 0.8rem; color: #64748b;">
                        <?= $r['approved'] ? '✅ Опубликован' : '⏳ На модерации' ?> 
                        (<?= date('d.m.Y', strtotime($r['review_date'])) ?>)
                    </p>
                </div>
                <?php elseif ($o['order_status'] == 'completed'): ?>
                <div style="margin-top: 1rem;">
                    <form method="post" action="add_review.php" class="review-form">
                        <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
                        <label>
                            <strong>Оставить отзыв:</strong>
                            <select name="rating">
                                <option value="5">5 ★</option>
                                <option value="4">4 ★</option>
                                <option value="3">3 ★</option>
                                <option value="2">2 ★</option>
                                <option value="1">1 ★</option>
                            </select>
                        </label>
                        <textarea name="review_text" placeholder="Поделитесь впечатлениями о туре..." rows="3" required></textarea>
                        <button type="submit" class="btn-review">Отправить отзыв</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="no-orders">
            <p>📭 У вас пока нет заказов.</p>
            <a href="index.php" class="btn-review" style="margin-top: 1rem;">Перейти к выбору туров</a>
        </div>
    <?php endif; ?>
</main>
</body>
</html>