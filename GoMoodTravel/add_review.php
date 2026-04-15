<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: client.php');
    exit;
}

$order_id = (int)$_POST['order_id'];
$rating = (int)$_POST['rating'];
$review_text = trim($_POST['review_text'] ?? '');


$stmt = $mysqli->prepare("INSERT INTO reviews (order_id, rating, review_text, approved) VALUES (?, ?, ?, 0)");
$stmt->bind_param('iis', $order_id, $rating, $review_text);
if ($stmt->execute()) {
    $message = "Спасибо! Ваш отзыв отправлен на модерацию.";
} else {
    $message = "Ошибка при отправке отзыва.";
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Отзыв</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header><h1>Статус отзыва</h1></header>
<main><p><?= htmlspecialchars($message) ?></p><a href="client.php">Вернуться в кабинет</a></main>
</body>
</html>