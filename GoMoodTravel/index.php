<?php
define('DB_HOST', 'localhost');       
define('DB_USER', 'kotova1287');    
define('DB_PASS', '7YYL*DN7MGUAF4f4'); 
define('DB_NAME', 'kotova1287'); 
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoMoodTravel | Главная</title>
    <link rel="stylesheet" href="style.css">
    <style>

        .tours-section {
            margin-bottom: 2rem;
        }
        
        .no-dates {
            color: #ef4444;
            font-size: 0.9rem;
        }
        
        .tour-card .btn {
            margin-top: 1rem;
        }
        

        .tours-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-top: 2rem;
        }
        

        @media (max-width: 1200px) {
            .tours-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 900px) {
            .tours-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 600px) {
            .tours-grid {
                grid-template-columns: 1fr;
            }
        }
        .reviews-section {
            margin-top: 2rem;
            padding: 3.5rem 0;
            background: #f0f4f8;
            border-top: 1px solid #d1d9e6;
            border-bottom: 1px solid #d1d9e6;
        }
        
        .reviews-section h2 {
            text-align: center;
            font-size: 2rem;
            margin-bottom: 2.5rem;
            color: #1e293b;
            position: relative;
        }
        
        .reviews-section h2:after {
            content: '';
            display: block;
            width: 80px;
            height: 3px;
            background: #2563eb;
            margin: 1rem auto 0;
            border-radius: 2px;
        }
        
        .reviews-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        @media (max-width: 1000px) {
            .reviews-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 650px) {
            .reviews-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .review-card {
            background: white;
            border-radius: 16px;
            padding: 1.8rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
        }
        
        .review-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            border-color: #2563eb;
        }
        
        .review-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .review-author {
            font-weight: 700;
            color: #2563eb;
            font-size: 1.1rem;
        }
        
        .review-tour {
            color: #64748b;
            font-size: 0.85rem;
            background: #f8fafc;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
        }
        
        .review-rating {
            margin-left: auto;
            color: #fbbf24;
            font-size: 1.1rem;
            letter-spacing: 2px;
        }
        
        .review-text {
            flex: 1;
            margin: 0.75rem 0;
            line-height: 1.7;
            color: #1e293b;
            font-style: italic;
        }
        
        .review-text:before {
            content: "\201C";
            font-size: 1.5rem;
            color: #2563eb;
            opacity: 0.4;
            margin-right: 3px;
        }
        
        .review-text:after {
            content: "\201D";
            font-size: 1.5rem;
            color: #2563eb;
            opacity: 0.4;
            margin-left: 3px;
            vertical-align: bottom;
        }
        
        .review-date {
            color: #64748b;
            font-size: 0.8rem;
            margin-top: 0.75rem;
            text-align: right;
        }
        
        .no-reviews {
            text-align: center;
            color: #64748b;
            font-size: 1.1rem;
            grid-column: 1 / -1;
            padding: 2rem;
        }
        
        nav {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }
        
        nav a {
            color: #1e293b;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        
        nav a:hover {
            color: #2563eb;
        }
        
        header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e2e8f0;
        }
        
        header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2563eb;
        }
        
        main {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.6;
            margin: 0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        .filters {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            margin-bottom: 0.5rem;
            border: 1px solid #e2e8f0;
        }
        
        .filters select,
        .filters input {
            padding: 0.7rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            background: white;
            min-width: 180px;
        }
        
        .filters button {
            background: #2563eb;
            color: white;
            border: none;
            padding: 0.7rem 1.8rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .filters button:hover {
            background: #1d4ed8;
        }
        
        .tour-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
        }
        
        .tour-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        }
        
        .tour-card h3 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
            color: #1e293b;
        }
        
        .tour-card p {
            color: #64748b;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }
        
        .tour-card p strong {
            color: #1e293b;
        }
        
        .btn {
            display: inline-block;
            padding: 0.6rem 1.2rem;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            text-align: center;
            transition: background 0.2s;
            margin-top: auto;
        }
        
        .btn:hover {
            background: #1d4ed8;
        }
        
        h2 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            color: #1e293b;
        }
    </style>
</head>
<body>
<header>
    <h1><i class="fas fa-umbrella-beach"></i> GoMoodTravel</h1>
    <nav>
        <a href="index.php">Туры</a>
        <?php if (isset($_SESSION['client_id'])): ?>
            <a href="cabinet.php">Личный кабинет</a>
            <a href="logout.php">Выход</a>
        <?php else: ?>
            <a href="login.php">Вход</a>
    <!-- ... -->
    <a href="employee_login.php"> Для сотрудников</a>
        <?php endif; ?>
        <a href="admin.php">Для администратора</a>
    </nav>
</header>

<main>
    <h2>Поиск туров</h2>
    <form method="get" class="filters">
        <select name="country">
            <option value="">Все страны</option>
            <?php
            $res = $mysqli->query("SELECT country_id, country_name FROM countries ORDER BY country_name");
            while($c = $res->fetch_assoc()):
                $sel = ($_GET['country'] ?? '') == $c['country_id'] ? 'selected' : '';
            ?>
            <option value="<?= $c['country_id'] ?>" <?= $sel ?>><?= htmlspecialchars($c['country_name']) ?></option>
            <?php endwhile; ?>
        </select>
        
        <select name="category">
            <option value="">Все категории</option>
            <?php
            $res = $mysqli->query("SELECT category_id, category_name FROM tour_categories ORDER BY category_name");
            while($cat = $res->fetch_assoc()):
                $sel = ($_GET['category'] ?? '') == $cat['category_id'] ? 'selected' : '';
            ?>
            <option value="<?= $cat['category_id'] ?>" <?= $sel ?>><?= htmlspecialchars($cat['category_name']) ?></option>
            <?php endwhile; ?>
        </select>
        
        <input type="text" name="search" placeholder="Название тура" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
        <button type="submit">Найти</button>
    </form>

    <div class="tours-section">
        <div class="tours-grid">
            <?php
            $where = [];
            $params = [];
            $types = '';
            
            if (!empty($_GET['country'])) {
                $where[] = "c.country_id = ?";
                $params[] = $_GET['country'];
                $types .= 'i';
            }
            if (!empty($_GET['category'])) {
                $where[] = "t.category_id = ?";
                $params[] = $_GET['category'];
                $types .= 'i';
            }
            if (!empty($_GET['search'])) {
                $where[] = "t.tour_name LIKE ?";
                $params[] = '%' . $_GET['search'] . '%';
                $types .= 's';
            }
            
            $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "
                SELECT t.tour_id, t.tour_name, t.description, t.base_price, t.duration_days,
                       c.country_name, ct.city_name, cat.category_name, op.operator_name,
                       MIN(td.start_date) AS next_date, MIN(td.current_price) AS price_from,
                       (SELECT COUNT(*) FROM tour_dates WHERE tour_id = t.tour_id AND available_spots > 0 AND start_date >= CURDATE()) AS dates_available
                FROM tours t
                JOIN cities ct ON t.city_id = ct.city_id
                JOIN countries c ON ct.country_id = c.country_id
                JOIN tour_categories cat ON t.category_id = cat.category_id
                JOIN tour_operators op ON t.operator_id = op.operator_id
                LEFT JOIN tour_dates td ON t.tour_id = td.tour_id AND td.start_date >= CURDATE() AND td.available_spots > 0
                $whereSQL
                GROUP BY t.tour_id
                ORDER BY next_date ASC
            ";

            $stmt = $mysqli->prepare($sql);
            if ($params) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $tours = $stmt->get_result();

            if ($tours->num_rows > 0):
                while($tour = $tours->fetch_assoc()):
            ?>
            <div class="tour-card">
                <h3><?= htmlspecialchars($tour['tour_name']) ?></h3>
                <p><strong><?= htmlspecialchars($tour['country_name']) ?>, <?= htmlspecialchars($tour['city_name']) ?></strong></p>
                <p><?= htmlspecialchars($tour['category_name']) ?> | <?= $tour['duration_days'] ?> дн.</p>
                <p><?= htmlspecialchars(mb_substr($tour['description'] ?? '', 0, 120)) ?>...</p>
                <?php if ($tour['next_date']): ?>
                <p><i class="fa-solid fa-calendar" style="color: rgb(36, 91, 187);"></i> Ближайшая дата: <?= date('d.m.Y', strtotime($tour['next_date'])) ?></p>
                <p><i class="fa-solid fa-coins" style="color: rgb(36, 91, 187);"></i> Цена от: <?= number_format($tour['price_from'], 0, ',', ' ') ?> ₽</p>
                <?php else: ?>
                <p class="no-dates">Нет доступных дат</p>
                <?php endif; ?>
                <a href="tour.php?id=<?= $tour['tour_id'] ?>" class="btn">Подробнее</a>
            </div>
            <?php 
                endwhile;
            else:
            ?>
            <p style="grid-column: 1/-1; text-align: center; padding: 2rem; color: #64748b;">Туры не найдены. Попробуйте изменить параметры поиска.</p>
            <?php endif; ?>
        </div>
    </div>
</main>

<section class="reviews-section">
    <h2><i class="fa-solid fa-star" style="color: rgb(255, 237, 57);"></i> Отзывы наших клиентов</h2>
    <div class="reviews-grid">
        <?php
        $reviews = $mysqli->query("
            SELECT r.rating, r.review_text, r.review_date, c.full_name, t.tour_name
            FROM reviews r
            JOIN orders o ON r.order_id = o.order_id
            JOIN clients c ON o.client_id = c.client_id
            JOIN tour_dates td ON o.tour_date_id = td.tour_date_id
            JOIN tours t ON td.tour_id = t.tour_id
            WHERE r.approved = 1
            ORDER BY r.review_date DESC
            LIMIT 6
        ");
        
        if ($reviews->num_rows > 0):
            while($rev = $reviews->fetch_assoc()):
        ?>
        <div class="review-card">
            <div class="review-header">
                <span class="review-author"><?= htmlspecialchars($rev['full_name']) ?></span>
                <span class="review-tour"><?= htmlspecialchars($rev['tour_name']) ?></span>
                <span class="review-rating"><?= str_repeat('★', $rev['rating']) . str_repeat('☆', 5 - $rev['rating']) ?></span>
            </div>
            <p class="review-text"><?= htmlspecialchars($rev['review_text']) ?></p>
            <span class="review-date"><?= date('d.m.Y', strtotime($rev['review_date'])) ?></span>
        </div>
        <?php 
            endwhile;
        else:
        ?>
        <p class="no-reviews">Пока нет отзывов. Будьте первым!</p>
        <?php endif; ?>
    </div>
</section>

</body>
</html>