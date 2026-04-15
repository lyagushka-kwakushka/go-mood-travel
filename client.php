<?php
require_once 'config.php';
$search = $_GET['find'] ?? '';
$orders = [];
if ($search) {
    $stmt = $mysqli->prepare("
        SELECT o.order_id, o.order_date, o.participants_count, o.total, o.order_status,
               t.tour_name, td.start_date, c.full_name, c.phone, c.email
        FROM orders o
        JOIN clients c ON o.client_id = c.client_id
        JOIN tour_dates td ON o.tour_date_id = td.tour_date_id
        JOIN tours t ON td.tour_id = t.tour_id
        WHERE c.phone = ? OR c.email = ?
        ORDER BY o.order_date DESC
    ");
    $stmt->bind_param('ss', $search, $search);
    $stmt->execute();
    $orders = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html>
<head><title>Личный кабинет</title><link rel="stylesheet" href="style.css"></head>
<body>
<header><h1>Мои заказы</h1><nav><a href="index.php">Туры</a></nav></header>
<main>
    <form method="get">
        <label>Телефон или Email: <input type="text" name="find" value="<?= htmlspecialchars($search) ?>" required></label>
        <button type="submit">Найти</button>
    </form>

    <?php if ($search && $orders->num_rows): ?>
        <?php while($o = $orders->fetch_assoc()): ?>
            <div class="order-card">
                <h3>Заказ №<?= $o['order_id'] ?> — <?= htmlspecialchars($o['tour_name']) ?></h3>
                <p>Дата тура: <?= date('d.m.Y', strtotime($o['start_date'])) ?> | Участников: <?= $o['participants_count'] ?></p>
                <p>Сумма: <?= number_format($o['total'], 0, ',', ' ') ?> ₽ | Статус: <?= $o['order_status'] ?></p>

                <!-- Платежи -->
                <?php
                $pay = $mysqli->query("SELECT amount, payment_method, payment_status, transaction_id FROM payments WHERE order_id = {$o['order_id']}");
                if ($pay->num_rows):
                ?>
                <h4>Платежи</h4>
                <ul>
                <?php while($p = $pay->fetch_assoc()): ?>
                    <li><?= number_format($p['amount'], 0, ',', ' ') ?> ₽ (<?= $p['payment_method'] ?>) — <?= $p['payment_status'] ?></li>
                <?php endwhile; ?>
                </ul>
                <?php endif; ?>

                <!-- Документы -->
                <?php
                $doc = $mysqli->query("SELECT document_type, document_number, document_status FROM documents WHERE order_id = {$o['order_id']}");
                if ($doc->num_rows):
                ?>
                <h4>Документы</h4>
                <ul>
                <?php while($d = $doc->fetch_assoc()): ?>
                    <li><?= $d['document_type'] ?> №<?= $d['document_number'] ?> (<?= $d['document_status'] ?>)</li>
                <?php endwhile; ?>
                </ul>
                <?php endif; ?>

                <!-- Отзыв -->
                <?php
                $rev = $mysqli->query("SELECT rating, review_text, approved FROM reviews WHERE order_id = {$o['order_id']}");
                if ($rev->num_rows):
                    $r = $rev->fetch_assoc();
                    echo "<p><strong>Ваш отзыв:</strong> {$r['rating']}/5 — {$r['review_text']} " . ($r['approved'] ? '(опубликован)' : '(на модерации)') . "</p>";
                elseif ($o['order_status'] == 'completed'):
                ?>
                <form method="post" action="add_review.php">
                    <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
                    <label>Оценка: <select name="rating"><option>5</option><option>4</option><option>3</option><option>2</option><option>1</option></select></label>
                    <textarea name="review_text" placeholder="Ваш отзыв"></textarea>
                    <button type="submit">Отправить отзыв</button>
                </form>
                <?php endif; ?>
                <hr>
            </div>
        <?php endwhile; ?>
    <?php elseif ($search): ?>
        <p>Заказы не найдены.</p>
    <?php endif; ?>
</main>
</body>
</html>
</main>
</body>
</html>