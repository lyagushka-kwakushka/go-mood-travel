<?php
require_once 'config.php';

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$order = $mysqli->query("
    SELECT o.*, c.full_name as client_name, c.phone, c.email, c.passport_series, c.passport_number,
           t.tour_name, t.description, td.start_date, td.end_date, td.current_price,
           e.full_name as employee_name,
           (SELECT SUM(amount) FROM payments WHERE order_id = o.order_id AND payment_status = 'completed') as paid_amount
    FROM orders o
    LEFT JOIN clients c ON o.client_id = c.client_id
    JOIN tour_dates td ON o.tour_date_id = td.tour_date_id
    JOIN tours t ON td.tour_id = t.tour_id
    LEFT JOIN employees e ON o.employee_id = e.employee_id
    WHERE o.order_id = $order_id
")->fetch_assoc();

if (!$order) die("Заказ не найден.");

$participants = $mysqli->query("SELECT * FROM tour_participants WHERE order_id = $order_id");
$payments = $mysqli->query("SELECT * FROM payments WHERE order_id = $order_id");
$documents = $mysqli->query("SELECT * FROM documents WHERE order_id = $order_id");

$status_labels = [
    'pending_contact' => 'Ожидает звонка',
    'pending' => 'Ожидает',
    'confirmed' => 'Подтверждён',
    'paid' => 'Оплачен',
    'cancelled' => 'Отменён',
    'completed' => 'Завершён'
];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Заказ №<?= $order_id ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <h1>Заказ №<?= $order_id ?></h1>
    <nav><a href="javascript:history.back()">← Назад</a></nav>
</header>
<main>
    <div class="order-info">
        <h3>Информация о заказе</h3>
        <p><strong>Тур:</strong> <?= htmlspecialchars($order['tour_name']) ?></p>
        <p><strong>Даты:</strong> <?= date('d.m.Y', strtotime($order['start_date'])) ?> — <?= date('d.m.Y', strtotime($order['end_date'])) ?></p>
        <p><strong>Статус:</strong> <?= $status_labels[$order['order_status']] ?? $order['order_status'] ?></p>
        <p><strong>Сумма:</strong> <?= number_format($order['total'], 0, ',', ' ') ?> ₽</p>
        <p><strong>Оплачено:</strong> <?= number_format($order['paid_amount'] ?? 0, 0, ',', ' ') ?> ₽</p>
        <p><strong>Менеджер:</strong> <?= htmlspecialchars($order['employee_name'] ?? 'Не назначен') ?></p>
    </div>
    
    <h3>Клиент</h3>
    <p><strong>ФИО:</strong> <?= htmlspecialchars($order['client_name'] ?? 'Не указан') ?></p>
    <p><strong>Телефон:</strong> <?= htmlspecialchars($order['phone'] ?? '—') ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($order['email'] ?? '—') ?></p>
    
    <h3>Участники</h3>
    <?php if ($participants->num_rows > 0): ?>
        <ul>
        <?php while($p = $participants->fetch_assoc()): ?>
            <li><?= htmlspecialchars($p['full_name']) ?> (<?= date('d.m.Y', strtotime($p['birth_date'])) ?>)</li>
        <?php endwhile; ?>
        </ul>
    <?php else: ?>
        <p>Информация об участниках не указана.</p>
    <?php endif; ?>
</main>
</body>
</html>