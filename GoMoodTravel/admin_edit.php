<?php
require_once 'config.php';
if (!isset($_SESSION['admin'])) { header('Location: admin.php'); exit; }

$table = $_GET['table'] ?? '';
$id = $_GET['id'] ?? null;
$is_edit = !is_null($id);

if (!$table) { header('Location: admin.php'); exit; }

// Получить структуру таблицы
$columns = [];
$res = $mysqli->query("SHOW COLUMNS FROM `$table`");
while ($col = $res->fetch_assoc()) {
    $columns[] = $col;
}

// Если редактирование, получить текущие значения
$row = [];
if ($is_edit) {
    $pk_col = $columns[0]['Field'];
    $stmt = $mysqli->prepare("SELECT * FROM `$table` WHERE `$pk_col` = ?");
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    if (!$row) die("Запись не найдена.");
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [];
    foreach ($columns as $col) {
        $field = $col['Field'];
        if ($col['Extra'] == 'auto_increment') continue; // автоинкремент не трогаем
        $val = $_POST[$field] ?? null;
        if ($val === '') $val = null;
        $data[$field] = $val;
    }

    if ($is_edit) {
        $pk = $columns[0]['Field'];
        $sets = [];
        $params = [];
        $types = '';
        foreach ($data as $k => $v) {
            $sets[] = "`$k` = ?";
            $params[] = $v;
            $types .= 's';
        }
        $params[] = $id;
        $types .= 's';
        $sql = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE `$pk` = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
    } else {
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        $sql = "INSERT INTO `$table` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $mysqli->prepare($sql);
        $types = str_repeat('s', count($data));
        $stmt->bind_param($types, ...array_values($data));
        $stmt->execute();
    }
    header("Location: admin.php?table=$table");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head><title><?= $is_edit ? 'Редактирование' : 'Добавление' ?></title><link rel="stylesheet" href="style.css"></head>
<body>
<header><h1><?= $is_edit ? 'Редактировать' : 'Добавить' ?> запись в <?= $table ?></h1></header>
<main>
    <form method="post" class="edit-form">
        <?php foreach ($columns as $col):
            $field = $col['Field'];
            if ($col['Extra'] == 'auto_increment') continue;
            $type = $col['Type'];
            $value = $is_edit ? htmlspecialchars($row[$field] ?? '') : '';
        ?>
        <div class="form-group">
            <label><?= $field ?> (<?= $type ?>)</label>
            <?php if (strpos($type, 'text') !== false): ?>
                <textarea name="<?= $field ?>"><?= $value ?></textarea>
            <?php elseif (strpos($type, 'enum') !== false):
                preg_match("/^enum\('(.*)'\)$/", $type, $matches);
                $options = explode("','", $matches[1]);
            ?>
                <select name="<?= $field ?>">
                    <option value="">--</option>
                    <?php foreach($options as $opt): ?>
                        <option value="<?= $opt ?>" <?= $value == $opt ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
            <?php elseif (strpos($type, 'date') !== false || strpos($type, 'timestamp') !== false): ?>
                <input type="datetime-local" name="<?= $field ?>" value="<?= $value ? date('Y-m-d\TH:i', strtotime($value)) : '' ?>">
            <?php else: ?>
                <input type="text" name="<?= $field ?>" value="<?= $value ?>">
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <button type="submit" class="btn-primary">Сохранить</button>
        <a href="admin.php?table=<?= $table ?>" class="btn">Отмена</a>
    </form>
</main>
</body>
</html>