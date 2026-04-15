<?php
require_once 'config.php';

// ============================================
// Авторизация администратора
// ============================================
$admin_login = 'admin';
$admin_password = 'admin123';

if (!isset($_SESSION['admin'])) {
    if (isset($_POST['login']) && isset($_POST['password'])) {
        if ($_POST['login'] === $admin_login && $_POST['password'] === $admin_password) {
            $_SESSION['admin'] = true;
        } else {
            $error = 'Неверный логин или пароль';
        }
    }
    
    if (!isset($_SESSION['admin'])) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Вход в админ-панель</title>
            <link rel="stylesheet" href="style.css">
            <style>
                body {
                    margin: 0;
                    font-family: 'Inter', system-ui, -apple-system, sans-serif;
                    background: #f8fafc;
                }
                .login-page {
                    min-height: 100vh;
                    display: flex;
                    flex-direction: column;
                }
                /* Шапка как на обычных страницах */
                .login-header {
                    background: white;
                    padding: 1rem 2rem;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    border-bottom: 1px solid #e2e8f0;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
                }
                .login-header h1 {
                    font-size: 1.5rem;
                    font-weight: 700;
                    color: #2563eb;
                    margin: 0;
                }
                .login-header a {
                    color: #1e293b;
                    text-decoration: none;
                    font-weight: 500;
                    transition: color 0.2s;
                }
                .login-header a:hover {
                    color: #2563eb;
                }
                /* Контейнер формы */
                .login-container {
                    flex: 1;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    padding: 2rem;
                }
                .admin-login-form {
                    background: white;
                    padding: 2.5rem;
                    border-radius: 12px;
                    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
                    width: 100%;
                    max-width: 400px;
                }
                .admin-login-form h2 {
                    margin-top: 0;
                    margin-bottom: 1.5rem;
                    text-align: center;
                    color: #2563eb;
                }
                .error {
                    color: #ef4444;
                    text-align: center;
                    margin-bottom: 1rem;
                    padding: 0.5rem;
                    background: #fee;
                    border-radius: 8px;
                }
                .form-group {
                    margin-bottom: 1.25rem;
                }
                .form-group label {
                    display: block;
                    margin-bottom: 0.25rem;
                    font-weight: 500;
                }
                .form-group input {
                    width: 100%;
                    padding: 0.75rem;
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                    font-size: 1rem;
                }
                .btn-primary {
                    width: 100%;
                    padding: 0.75rem;
                    background: #2563eb;
                    color: white;
                    border: none;
                    border-radius: 8px;
                    font-size: 1rem;
                    font-weight: 500;
                    cursor: pointer;
                    transition: background 0.2s;
                }
                .btn-primary:hover {
                    background: #1d4ed8;
                }
            </style>
        </head>
        <body>
        <div class="login-page">
            <!-- Шапка с кнопкой "На главную" справа -->
            <header class="login-header">
                <h1>Админ-панель</h1>
                <nav>
                    <a href="index.php">← На главную</a>
                </nav>
            </header>
            
            <main class="login-container">
                <form method="post" class="admin-login-form">
                    <h2>Вход</h2>
                    <?php if (isset($error)): ?>
                        <p class="error"><?= htmlspecialchars($error) ?></p>
                    <?php endif; ?>
                    <div class="form-group">
                        <label>Логин:</label>
                        <input type="text" name="login" required>
                    </div>
                    <div class="form-group">
                        <label>Пароль:</label>
                        <input type="password" name="password" required>
                    </div>
                    <button type="submit" class="btn-primary">Войти</button>
                </form>
            </main>
        </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// ============================================
// Выход из админки
// ============================================
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// ============================================
// Функция для получения названия по ID
// ============================================
function getRelationName($mysqli, $table, $id_field, $name_field, $id) {
    if (!$id || $id === 'NULL' || $id === '') return '';
    $stmt = $mysqli->prepare("SELECT $name_field FROM $table WHERE $id_field = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row[$name_field];
    }
    return $id;
}

// ============================================
// Список всех таблиц
// ============================================
$tables = [
    'countries', 'cities', 'clients', 'employees', 'tour_operators', 'tour_categories',
    'transport', 'services', 'hotels', 'tours', 'tour_dates', 'orders', 'payments',
    'tour_participants', 'documents', 'reviews', 'tour_hotels', 'tour_services', 'tour_transport'
];

$selected_table = $_GET['table'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;

// ============================================
// Карта связей для замены ID на названия
// ============================================
$relation_map = [
    'country_id' => ['table' => 'countries', 'id_field' => 'country_id', 'name_field' => 'country_name'],
    'city_id' => ['table' => 'cities', 'id_field' => 'city_id', 'name_field' => 'city_name'],
    'category_id' => ['table' => 'tour_categories', 'id_field' => 'category_id', 'name_field' => 'category_name'],
    'operator_id' => ['table' => 'tour_operators', 'id_field' => 'operator_id', 'name_field' => 'operator_name'],
    'client_id' => ['table' => 'clients', 'id_field' => 'client_id', 'name_field' => 'full_name'],
    'employee_id' => ['table' => 'employees', 'id_field' => 'employee_id', 'name_field' => 'full_name'],
    'tour_id' => ['table' => 'tours', 'id_field' => 'tour_id', 'name_field' => 'tour_name'],
    'tour_date_id' => ['table' => 'tour_dates', 'id_field' => 'tour_date_id', 'name_field' => 'start_date'],
    'hotel_id' => ['table' => 'hotels', 'id_field' => 'hotel_id', 'name_field' => 'hotel_name'],
    'service_id' => ['table' => 'services', 'id_field' => 'service_id', 'name_field' => 'service_name'],
    'transport_id' => ['table' => 'transport', 'id_field' => 'transport_id', 'name_field' => 'transport_type'],
    'order_id' => ['table' => 'orders', 'id_field' => 'order_id', 'name_field' => 'order_id'],
    'departure_city_id' => ['table' => 'cities', 'id_field' => 'city_id', 'name_field' => 'city_name'],
    'arrival_city_id' => ['table' => 'cities', 'id_field' => 'city_id', 'name_field' => 'city_name'],
];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Админ-панель</title>
    <link rel="stylesheet" href="style.css">
    <style>

body {
    margin: 0;
    background: #f8fafc;
}
main.admin-layout {
    max-width: none !important;
    margin: 0 !important;
    padding: 1.5rem 1rem !important;
    display: flex;
    gap: 1.5rem;
    align-items: flex-start;
}


.admin-sidebar {
    width: 280px;
    background: white;
    padding: 1.5rem 1rem;
    border-radius: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    height: fit-content;
    flex-shrink: 0;
    margin: 0;
}


.admin-content {
    flex: 1;
    min-width: 0;
    margin: 0;
    padding: 0;
}


.admin-header {
    padding: 1rem 1rem;
    margin: 0;
}


.admin-sidebar h3 {
    margin-top: 0;
    margin-bottom: 1rem;
    font-size: 1.1rem;
    color: #1e293b;
}
.admin-sidebar ul {
    list-style: none;
    padding: 0;
    margin: 0;
}
.admin-sidebar li {
    margin-bottom: 0.25rem;
}
.admin-sidebar a {
    display: block;
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    color: #1e293b;
    text-decoration: none;
    transition: background 0.2s;
    word-break: break-word;
    white-space: normal;
    line-height: 1.3;
}
.admin-sidebar a:hover,
.admin-sidebar a.active {
    background: #2563eb;
    color: white;
}
/* Быстрые действия */
.quick-actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 20px;
    max-width: 320px;
}
.quick-action-btn {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 16px 20px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    text-decoration: none;
    color: #1e293b;
    font-size: 1rem;
    font-weight: 500;
    transition: all 0.2s;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}
.quick-action-btn:hover {
    background: #2563eb;
    color: white;
    transform: translateX(5px);
    box-shadow: 0 5px 15px rgba(37,99,235,0.2);
}
.quick-action-btn span {
    font-size: 1.8rem;
}
.admin-dashboard {
    max-width: none;
}
.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}
.table-wrapper {
    overflow-x: auto;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}
.null-value {
    color: #94a3b8;
    font-style: italic;
}
.table-info {
    margin-top: 1rem;
    color: #64748b;
}
.btn-edit, .btn-delete {
    text-decoration: none;
    margin: 0 5px;
    font-size: 1.2rem;
}
.btn-edit:hover, .btn-delete:hover {
    opacity: 0.7;
}
.btn-add {
    display: inline-block;
    padding: 0.6rem 1.2rem;
    background: #2563eb;
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 500;
    transition: background 0.2s;
}
.btn-add:hover {
    background: #1d4ed8;
}
.actions {
    white-space: nowrap;
}
.pagination {
    margin-top: 1.5rem;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.pagination a {
    padding: 0.4rem 1rem;
    background: white;
    border-radius: 6px;
    text-decoration: none;
    color: #1e293b;
    border: 1px solid #e2e8f0;
}
.pagination a.active {
    background: #2563eb;
    color: white;
    border-color: #2563eb;
}
    </style>
</head>
<body>
<header class="admin-header">
    <h1>Администрирование</h1>
    <nav>
        <a href="index.php" target="_blank"> На сайт</a> 
        <a href="admin.php">Главная админки</a> 
        <a href="?logout=1">Выход</a>
    </nav>
</header>

<main class="admin-layout">
    
    <aside class="admin-sidebar">
        <h3>Таблицы базы данных</h3>
        <ul>
            <?php foreach($tables as $t): ?>
            <li>
                <a href="?table=<?= $t ?>" <?= $selected_table === $t ? 'class="active"' : '' ?>>
                    <?= ucfirst(str_replace('_', ' ', $t)) ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </aside>

    
    <section class="admin-content">
        <?php if ($selected_table && in_array($selected_table, $tables)): ?>
            <div class="table-header">
                <h2><?= ucfirst(str_replace('_', ' ', $selected_table)) ?></h2>
                <a href="admin_edit.php?table=<?= $selected_table ?>" class="btn-add">+ Добавить запись</a>
            </div>

            <?php
            
            $total_res = $mysqli->query("SELECT COUNT(*) AS cnt FROM `$selected_table`");
            $total = $total_res->fetch_assoc()['cnt'];
            $total_pages = ceil($total / $per_page);
            $offset = ($page - 1) * $per_page;

            $result = $mysqli->query("SELECT * FROM `$selected_table` LIMIT $offset, $per_page");
            
            if (!$result) {
                echo "<p class='error'>Ошибка запроса: " . $mysqli->error . "</p>";
            } else {
                // Получаем названия столбцов
                $columns = [];
                while ($field = $result->fetch_field()) {
                    $columns[] = $field->name;
                }
                
                echo '<div class="table-wrapper">';
                echo '<table class="data-table">';
                
                
                echo '<tr>';
                foreach ($columns as $col) {
                    echo '<th>' . htmlspecialchars($col) . '</th>';
                }
                echo '<th>Действия</th>';
                echo '</tr>';

                
                $current_table_pk = $columns[0];
                
                
                while ($row = $result->fetch_assoc()) {
                    echo '<tr>';
                    
                    foreach ($row as $key => $val) {
                        $display = '';
                        
                        
                        $is_current_pk = ($key === $current_table_pk);
                        
                        // Если это НЕ первичный ключ и есть связь - заменяем на название
                        if (!$is_current_pk && !is_null($val) && $val !== '' && isset($relation_map[$key])) {
                            $map = $relation_map[$key];
                            $display = htmlspecialchars(getRelationName(
                                $mysqli, 
                                $map['table'], 
                                $map['id_field'], 
                                $map['name_field'], 
                                $val
                            ));
                        } else {
                            // Форматируем вывод
                            if (is_null($val)) {
                                $display = '<i class="null-value">NULL</i>';
                            } elseif (strpos($key, 'date') !== false && $val && $val !== '0000-00-00') {
                                $display = date('d.m.Y', strtotime($val));
                            } elseif (strpos($key, 'datetime') !== false && $val) {
                                $display = date('d.m.Y H:i', strtotime($val));
                            } elseif (strpos($key, 'price') !== false || strpos($key, 'amount') !== false || 
                                      strpos($key, 'total') !== false || strpos($key, 'cost') !== false || 
                                      strpos($key, 'salary') !== false) {
                                $display = number_format($val, 0, ',', ' ') . ' ₽';
                            } else {
                                $display = htmlspecialchars(mb_substr($val, 0, 50));
                            }
                        }
                        
                        echo '<td>' . $display . '</td>';
                    }
                    
                    
                    $pk_val = $row[$current_table_pk];
                    echo '<td class="actions">';
                    echo '<a href="admin_edit.php?table=' . $selected_table . '&id=' . urlencode($pk_val) . '" class="btn-edit" title="Редактировать">✏️</a>';
                    echo '<a href="admin_delete.php?table=' . $selected_table . '&id=' . urlencode($pk_val) . '" class="btn-delete" title="Удалить" onclick="return confirm(\'Удалить запись?\')">🗑️</a>';
                    echo '</td>';
                    
                    echo '</tr>';
                }
                echo '</table>';
                echo '</div>';

                
                if ($total_pages > 1) {
                    echo '<div class="pagination">';
                    for ($i = 1; $i <= $total_pages; $i++) {
                        $active = $i == $page ? 'active' : '';
                        echo '<a href="?table=' . $selected_table . '&page=' . $i . '" class="' . $active . '">' . $i . '</a> ';
                    }
                    echo '</div>';
                }
                
                echo '<p class="table-info">Всего записей: ' . $total . '</p>';
            }
            ?>
            
        <?php else: ?>
            
            <div class="admin-dashboard">
                <h2>Добро пожаловать в панель управления</h2>
                <p>Выберите таблицу в левом меню для просмотра и редактирования данных.</p>
                
                <div class="stats">
    <div class="stat-item">
        <span class="stat-icon"><i class="fas fa-globe"></i></span>
        <span class="stat-value"><?= $mysqli->query("SELECT COUNT(*) FROM tours WHERE is_active = 1")->fetch_row()[0] ?></span>
        <span class="stat-label">активных туров</span>
    </div>
    <div class="stat-item">
        <span class="stat-icon"><i class="fas fa-clipboard-list"></i></span>
        <span class="stat-value"><?= $mysqli->query("SELECT COUNT(*) FROM orders")->fetch_row()[0] ?></span>
        <span class="stat-label">заказов</span>
    </div>
    <div class="stat-item">
        <span class="stat-icon"><i class="fas fa-users"></i></span>
        <span class="stat-value"><?= $mysqli->query("SELECT COUNT(*) FROM clients")->fetch_row()[0] ?></span>
        <span class="stat-label">клиентов</span>
    </div>
    <div class="stat-item">
        <span class="stat-icon"><i class="fas fa-hourglass-half"></i></span>
        <span class="stat-value"><?= $mysqli->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetch_row()[0] ?></span>
        <span class="stat-label">ожидают</span>
    </div>
</div>

<h3>Быстрые действия</h3>
<div class="quick-actions">
    <a href="?table=tours" class="quick-action-btn">
        <span><i class="fas fa-box"></i></span> Управление турами
    </a>
    <a href="?table=orders" class="quick-action-btn">
        <span><i class="fas fa-shopping-cart"></i></span> Просмотр заказов
    </a>
    <a href="?table=clients" class="quick-action-btn">
        <span><i class="fas fa-user"></i></span> Клиенты
    </a>
    <a href="?table=reviews" class="quick-action-btn">
        <span><i class="fas fa-star"></i></span> Модерация отзывов
    </a>
    <a href="admin_edit.php?table=tours" class="quick-action-btn">
        <span><i class="fas fa-plus"></i></span> Добавить тур
    </a>
    <a href="admin_edit.php?table=employees" class="quick-action-btn">
        <span><i class="fas fa-user-tie"></i></span> Добавить сотрудника
    </a>
</div>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>