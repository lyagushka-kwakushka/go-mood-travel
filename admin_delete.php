<?php
require_once 'config.php';
if (!isset($_SESSION['admin'])) { header('Location: admin.php'); exit; }

$table = $_GET['table'] ?? '';
$id = $_GET['id'] ?? '';
if (!$table || !$id) die("Неверные параметры.");

// Получить первичный ключ
$res = $mysqli->query("SHOW COLUMNS FROM `$table`");
$pk_col = $res->fetch_assoc()['Field'];

$stmt = $mysqli->prepare("DELETE FROM `$table` WHERE `$pk_col` = ?");
$stmt->bind_param('s', $id);
$stmt->execute();

header("Location: admin.php?table=$table");
exit;