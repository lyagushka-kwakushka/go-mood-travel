<?php
require_once 'config.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Основная информация о туре
$stmt = $mysqli->prepare("
    SELECT t.*, 
           c.country_name, 
           ct.city_name, 
           cat.category_name, 
           op.operator_name, 
           op.license_number
    FROM tours t
    JOIN cities ct ON t.city_id = ct.city_id
    JOIN countries c ON ct.country_id = c.country_id
    JOIN tour_categories cat ON t.category_id = cat.category_id
    JOIN tour_operators op ON t.operator_id = op.operator_id
    WHERE t.tour_id = ?
");
$stmt->bind_param('i', $id);
$stmt->execute();
$tour = $stmt->get_result()->fetch_assoc();
if (!$tour) die("Тур не найден.");

// Даты тура
$dates = $mysqli->query("
    SELECT * FROM tour_dates
    WHERE tour_id = $id AND start_date >= CURDATE() AND available_spots > 0
    ORDER BY start_date
");

// Отели
$hotels = $mysqli->query("
    SELECT h.hotel_name, h.category, h.address, th.nights_count, th.room_type, th.meal_plan
    FROM tour_hotels th
    JOIN hotels h ON th.hotel_id = h.hotel_id
    WHERE th.tour_id = $id
");

// Услуги
$services = $mysqli->query("
    SELECT s.service_name, s.description, ts.included_in_price, ts.additional_cost
    FROM tour_services ts
    JOIN services s ON ts.service_id = s.service_id
    WHERE ts.tour_id = $id
");

// Транспорт
$transport = $mysqli->query("
    SELECT tr.transport_type, tt.departure_datetime, tt.arrival_datetime, tt.transport_number,
           dep.city_name AS dep_city, arr.city_name AS arr_city
    FROM tour_transport tt
    JOIN transport tr ON tt.transport_id = tr.transport_id
    JOIN cities dep ON tt.departure_city_id = dep.city_id
    JOIN cities arr ON tt.arrival_city_id = arr.city_id
    WHERE tt.tour_id = $id
");

// Данные авторизованного клиента
$client_data = null;
$saved_participants = [];
$is_authorized = isset($_SESSION['client_id']);

if ($is_authorized) {
    $stmt = $mysqli->prepare("
        SELECT client_id, full_name, phone, email, passport_series, passport_number, issue_date, birth_date 
        FROM clients 
        WHERE client_id = ?
    ");
    $stmt->bind_param('i', $_SESSION['client_id']);
    $stmt->execute();
    $client_data = $stmt->get_result()->fetch_assoc();
    
    // Сохранённые участники (без self и без самого клиента)
    // Проверяем, есть ли поле oms_number в таблице
    $has_oms = false;
    $check = $mysqli->query("SHOW COLUMNS FROM tour_participants LIKE 'oms_number'");
    if ($check->num_rows > 0) $has_oms = true;
    
    $fields = "tp.full_name, tp.birth_date, tp.passport_series, tp.passport_number, tp.client_relation";
    if ($has_oms) $fields .= ", tp.oms_number";
    
    $stmt = $mysqli->prepare("
        SELECT DISTINCT $fields
        FROM tour_participants tp
        JOIN orders o ON tp.order_id = o.order_id
        WHERE o.client_id = ? 
          AND tp.client_relation != 'self'
          AND tp.full_name != ?
        ORDER BY tp.full_name
    ");
    $client_name = $client_data['full_name'] ?? '';
    $stmt->bind_param('is', $_SESSION['client_id'], $client_name);
    $stmt->execute();
    $saved_participants = $stmt->get_result();
}

// Цены для JavaScript
$dates_array = [];
$dates->data_seek(0);
while ($d = $dates->fetch_assoc()) {
    $dates_array[] = [
        'id' => $d['tour_date_id'],
        'price' => $d['current_price'],
        'start' => $d['start_date'],
        'end' => $d['end_date'],
        'spots' => $d['available_spots']
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($tour['tour_name']) ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .saved-participants {
            background: #f0f9ff;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border: 1px solid #bae6fd;
        }
        .saved-participants h5 {
            margin-bottom: 1rem;
            color: #0369a1;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .participant-checkbox {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.5rem;
            border-bottom: 1px solid #e0f2fe;
        }
        .participant-checkbox:last-child {
            border-bottom: none;
        }
        .participant-checkbox input {
            width: auto;
        }
        .participant-checkbox label {
            flex: 1;
            margin: 0;
            font-weight: normal;
        }
        .btn-select-all {
            background: none;
            border: 1px solid #2563eb;
            color: #2563eb;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
        }
        .tour-info {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .dates-table {
            width: 100%;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 2rem;
        }
        .dates-table th {
            background: #f1f5f9;
            padding: 0.75rem;
            text-align: left;
        }
        .dates-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
        }
        .participant {
            background: #f8fafc;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .participant input,
        .participant select {
            margin: 0.5rem 0.25rem;
            padding: 0.6rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }
        .btn-primary {
            background: #2563eb;
            color: white;
            border: none;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 1rem;
        }
        .btn-primary:hover {
            background: #1d4ed8;
        }
        .btn-secondary {
            background: white;
            color: #2563eb;
            border: 1px solid #2563eb;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            margin: 0.25rem;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.25rem;
            font-weight: 500;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            max-width: 400px;
            padding: 0.7rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        .price-calculator {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }
        .price-total {
            font-size: 1.5rem;
            font-weight: 700;
            color: #166534;
        }
        .price-breakdown {
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #64748b;
        }
        .client-not-participating {
            background: #fef3c7;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid #fde68a;
        }
        .client-not-participating label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .client-not-participating input {
            width: auto;
        }
        .document-section {
            margin-top: 0.5rem;
            padding: 0.75rem;
            background: #f1f5f9;
            border-radius: 6px;
        }
        .document-section label {
            display: block;
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
            color: #475569;
        }
        .simple-price-info {
            background: #e0f2fe;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            text-align: center;
        }
        .simple-price-info .price {
            font-size: 1.8rem;
            font-weight: 700;
            color: #0369a1;
        }
    </style>
</head>
<body>
<header>
    <h1><?= htmlspecialchars($tour['tour_name']) ?></h1>
    <nav><a href="index.php">← Назад к турам</a></nav>
</header>

<main class="tour-detail">
    <div class="tour-info">
        <p><strong>Страна/город:</strong> <?= htmlspecialchars($tour['country_name']) ?>, <?= htmlspecialchars($tour['city_name']) ?></p>
        <p><strong>Категория:</strong> <?= htmlspecialchars($tour['category_name']) ?></p>
        <p><strong>Туроператор:</strong> <?= htmlspecialchars($tour['operator_name']) ?> (лицензия <?= $tour['license_number'] ?>)</p>
        <p><strong>Длительность:</strong> <?= $tour['duration_days'] ?> дней</p>
        <p><strong>Базовая цена:</strong> <?= number_format($tour['base_price'], 0, ',', ' ') ?> ₽ (за взрослого)</p>
        <p><?= nl2br(htmlspecialchars($tour['description'] ?? '')) ?></p>
    </div>

    <h3><i class="fa-solid fa-calendar-check" style="color: rgb(36, 91, 187);"></i> Доступные даты</h3>
    <?php if (count($dates_array) > 0): ?>
    <table class="dates-table">
        <tr><th>Заезд</th><th>Выезд</th><th>Мест</th><th>Цена за взрослого</th></tr>
        <?php foreach ($dates_array as $d): ?>
        <tr>
            <td><?= date('d.m.Y', strtotime($d['start'])) ?></td>
            <td><?= date('d.m.Y', strtotime($d['end'])) ?></td>
            <td><?= $d['spots'] ?></td>
            <td><?= number_format($d['price'], 0, ',', ' ') ?> ₽</td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p>Нет доступных дат.</p>
    <?php endif; ?>

    <h3><i class="fa-solid fa-bed" style="color: rgb(36, 91, 187);"></i> Отели</h3>
    <?php if ($hotels->num_rows > 0): ?>
    <ul>
        <?php while($h = $hotels->fetch_assoc()): ?>
        <li><?= htmlspecialchars($h['hotel_name']) ?> (<?= $h['category'] ?>), <?= $h['nights_count'] ?> ночей, <?= $h['room_type'] ?>, питание: <?= $h['meal_plan'] ?></li>
        <?php endwhile; ?>
    </ul>
    <?php else: echo "<p>Информация отсутствует.</p>"; endif; ?>

    <h3><i class="fa-solid fa-bus" style="color: rgb(36, 91, 187);"></i> Транспорт</h3>
    <?php if ($transport->num_rows > 0): ?>
    <ul>
        <?php while($tr = $transport->fetch_assoc()): ?>
        <li><?= $tr['transport_type'] ?>: <?= $tr['dep_city'] ?> → <?= $tr['arr_city'] ?>, <?= date('d.m.Y H:i', strtotime($tr['departure_datetime'])) ?> (рейс <?= $tr['transport_number'] ?>)</li>
        <?php endwhile; ?>
    </ul>
    <?php else: echo "<p>Информация отсутствует.</p>"; endif; ?>

    <h3><i class="fa-solid fa-suitcase" style="color: rgb(36, 91, 187);"></i> Услуги</h3>
    <?php if ($services->num_rows > 0): ?>
    <ul>
        <?php while($s = $services->fetch_assoc()): ?>
        <li><?= htmlspecialchars($s['service_name']) ?> - <?= $s['included_in_price'] ? 'включено' : number_format($s['additional_cost'], 0, ',', ' ') . ' ₽' ?></li>
        <?php endwhile; ?>
    </ul>
    <?php else: echo "<p>Дополнительных услуг нет.</p>"; endif; ?>

    <h3><i class="fa-solid fa-book" style="color: rgb(36, 91, 187);"></i> Бронирование</h3>
    <form method="post" action="book.php" id="bookingForm">
        <input type="hidden" name="tour_id" value="<?= $tour['tour_id'] ?>">
        
        <div class="form-group">
            <label>Выберите дату:</label>
            <select name="tour_date_id" id="tourDateSelect" required onchange="updatePrice()">
                <option value="">-- Выберите дату --</option>
                <?php foreach ($dates_array as $d): ?>
                <option value="<?= $d['id'] ?>" data-price="<?= $d['price'] ?>">
                    <?= date('d.m.Y', strtotime($d['start'])) ?> — 
                    <?= number_format($d['price'], 0, ',', ' ') ?> ₽ (мест: <?= $d['spots'] ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($is_authorized): ?>
        <!-- Авторизованный клиент -->
        <div class="client-not-participating">
            <label>
                <input type="checkbox" id="clientNotParticipating" onchange="toggleClientParticipation()">
                <strong>Я не участвую в туре (бронирую для других людей)</strong>
            </label>
        </div>

        <div id="clientDataSection">
            <h4>Данные клиента (заказчика)</h4>
            <div class="form-group">
                <label>ФИО:</label>
                <input type="text" name="client_name" id="clientName" value="<?= htmlspecialchars($client_data['full_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Телефон (11 цифр):</label>
                <input type="text" name="client_phone" id="clientPhone" pattern="[78][0-9]{10}" value="<?= htmlspecialchars($client_data['phone'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="client_email" id="clientEmail" value="<?= htmlspecialchars($client_data['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Серия паспорта (4 цифры):</label>
                <input type="text" name="passport_series" id="passportSeries" pattern="[0-9]{4}" value="<?= htmlspecialchars($client_data['passport_series'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Номер паспорта (6 цифр):</label>
                <input type="text" name="passport_number" id="passportNumber" pattern="[0-9]{6}" value="<?= htmlspecialchars($client_data['passport_number'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Дата выдачи:</label>
                <input type="date" name="issue_date" id="issueDate" value="<?= htmlspecialchars($client_data['issue_date'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Дата рождения:</label>
                <input type="date" name="birth_date" id="birthDate" value="<?= htmlspecialchars($client_data['birth_date'] ?? '') ?>">
            </div>
        </div>

        <!-- Сохранённые участники -->
        <?php if ($saved_participants && $saved_participants->num_rows > 0): ?>
        <div class="saved-participants">
            <h5>
                👥 Сохранённые участники из предыдущих заказов
                <button type="button" class="btn-select-all" onclick="toggleAllParticipants(this)">Выбрать всех</button>
            </h5>
            <div id="savedParticipantsList">
                <?php while($sp = $saved_participants->fetch_assoc()): 
                    $participant_data = base64_encode(json_encode($sp));
                    $age = date_diff(date_create($sp['birth_date']), date_create('today'))->y;
                ?>
                <div class="participant-checkbox">
                    <input type="checkbox" name="saved_participants[]" value="<?= htmlspecialchars($participant_data) ?>" 
                           id="sp_<?= md5($participant_data) ?>" onchange="updatePrice()">
                    <label for="sp_<?= md5($participant_data) ?>">
                        <strong><?= htmlspecialchars($sp['full_name']) ?></strong> 
                        (др: <?= date('d.m.Y', strtotime($sp['birth_date'])) ?>, 
                        возраст: <?= $age ?> лет)
                        <?php if ($age < 14): ?>
                            <br><small style="color: #64748b;">📋 Полис ОМС: <?= htmlspecialchars($sp['oms_number'] ?? 'не указан') ?></small>
                        <?php else: ?>
                            <br><small style="color: #64748b;">📄 Пас. <?= htmlspecialchars($sp['passport_series']) ?> <?= htmlspecialchars($sp['passport_number']) ?></small>
                        <?php endif; ?>
                    </label>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <h4>Участники тура</h4>
        <div id="participants-container">
            <div class="participant" id="participant-self">
                <p><strong>Участник 1 (клиент)</strong></p>
                <input type="text" name="participants[0][full_name]" placeholder="ФИО" id="partName0" required>
                <input type="date" name="participants[0][birth_date]" id="partBirth0" required onchange="updateParticipantAge(this); updatePrice();">
                
                <div id="participant0-docs" class="document-section">
                    <div id="participant0-passport" style="display:block;">
                        <label>Паспорт:</label>
                        <input type="text" name="participants[0][passport_series]" placeholder="Серия (4 цифры)" pattern="[0-9]{4}">
                        <input type="text" name="participants[0][passport_number]" placeholder="Номер (6 цифр)" pattern="[0-9]{6}">
                    </div>
                    <div id="participant0-oms" style="display:none;">
                        <label>Полис ОМС:</label>
                        <input type="text" name="participants[0][oms_number]" placeholder="Номер полиса (16 цифр)" pattern="[0-9]{16}">
                    </div>
                </div>
                
                <input type="hidden" name="participants[0][relation]" value="self">
            </div>
        </div>
        <button type="button" id="add-participant" class="btn-secondary">+ Добавить участника</button>

        <?php else: ?>
        <!-- Неавторизованный клиент -->
        <h4>Ваши данные</h4>
        <div class="form-group">
            <label>ФИО:</label>
            <input type="text" name="client_name" required>
        </div>
        <div class="form-group">
            <label>Телефон (11 цифр):</label>
            <input type="text" name="client_phone" pattern="[78][0-9]{10}" required>
        </div>
        <div class="form-group">
            <label>Email:</label>
            <input type="email" name="client_email">
        </div>
        <div class="form-group">
            <label>Количество участников (включая вас):</label>
            <input type="number" name="participants_count" id="participantsCountSimple" min="1" value="1" required onchange="updateSimplePrice()">
        </div>
        <input type="hidden" name="simple_booking" value="1">
        
        <!-- Калькулятор для неавторизованных -->
        <div class="simple-price-info" id="simplePriceInfo" style="display:none;">
            <p>Примерная стоимость:</p>
            <p class="price" id="simpleTotalPrice">0 ₽</p>
            <small>Точная стоимость будет рассчитана после уточнения возраста участников</small>
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label>Особые пожелания:</label>
            <textarea name="special_requirements" rows="3" style="width:100%; max-width:600px;"></textarea>
        </div>

        <!-- Калькулятор цены для авторизованных -->
        <div class="price-calculator" id="priceCalculator" style="display:none;">
            <h4>💰 Расчёт стоимости</h4>
            <p>Базовая цена за взрослого: <span id="basePriceDisplay">0</span> ₽</p>
            <p>Количество участников: <span id="participantCount">0</span></p>
            <p>Детей (2-12 лет): <span id="childrenCount">0</span> (скидка 30%)</p>
            <p>Младенцев (до 2 лет): <span id="infantCount">0</span> (бесплатно)</p>
            <hr>
            <p class="price-total">Итого: <span id="totalPrice">0</span> ₽</p>
            <div class="price-breakdown" id="priceBreakdown"></div>
        </div>
        
       <?php if ($is_authorized): ?>
    <button type="submit" class="btn-primary">Забронировать</button>
<?php else: ?>
    <input type="hidden" name="is_request" value="1">
    <button type="submit" class="btn-primary">Оставить заявку</button>
<?php endif; ?>
    </form>
</main>

<script>
const datesData = <?= json_encode($dates_array) ?>;
let selectedPrice = 0;

function calculateAge(birthDate) {
    const today = new Date();
    const birth = new Date(birthDate);
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
        age--;
    }
    return age;
}

function updateParticipantAge(inputElement) {
    const participantDiv = inputElement.closest('.participant');
    const birthValue = inputElement.value;
    if (!birthValue) return;
    
    const age = calculateAge(birthValue);
    const passportDiv = participantDiv.querySelector('[id$="-passport"]');
    const omsDiv = participantDiv.querySelector('[id$="-oms"]');
    const passportInputs = participantDiv.querySelectorAll('input[name$="[passport_series]"], input[name$="[passport_number]"]');
    const omsInputs = participantDiv.querySelectorAll('input[name$="[oms_number]"]');
    
    if (age < 14) {
        if (passportDiv) passportDiv.style.display = 'none';
        if (omsDiv) omsDiv.style.display = 'block';
        passportInputs.forEach(i => i.required = false);
        omsInputs.forEach(i => i.required = true);
    } else {
        if (passportDiv) passportDiv.style.display = 'block';
        if (omsDiv) omsDiv.style.display = 'none';
        passportInputs.forEach(i => i.required = true);
        omsInputs.forEach(i => i.required = false);
    }
    
    const isSelf = participantDiv.id === 'participant-self';
    if (!isSelf) {
        updateRelationOptions(participantDiv, age);
    }
}

function updateRelationOptions(participantDiv, age) {
    const relationSelect = participantDiv.querySelector('select[name$="[relation]"]');
    if (!relationSelect) return;
    
    // Проверка для "супруг(а)" - только с 16 лет
    const spouseOption = Array.from(relationSelect.options).find(opt => opt.value === 'spouse');
    if (age < 16) {
        if (spouseOption) spouseOption.style.display = 'none';
        if (relationSelect.value === 'spouse') relationSelect.value = 'other';
    } else {
        if (spouseOption) spouseOption.style.display = '';
    }
    
    // Проверка для "родитель" - участник должен быть старше клиента минимум на 14 лет
    const clientBirthInput = document.getElementById('birthDate');
    const parentOption = Array.from(relationSelect.options).find(opt => opt.value === 'parent');
    
    if (clientBirthInput && clientBirthInput.value && parentOption) {
        const clientAge = calculateAge(clientBirthInput.value);
        // Участник должен быть СТАРШЕ клиента минимум на 14 лет
        // То есть возраст участника >= возраст клиента + 14
        if (age < clientAge + 14) {
            parentOption.style.display = 'none';
            if (relationSelect.value === 'parent') relationSelect.value = 'other';
        } else {
            parentOption.style.display = '';
        }
    }
}

function updatePrice() {
    const dateSelect = document.getElementById('tourDateSelect');
    if (!dateSelect || !dateSelect.value) {
        document.getElementById('priceCalculator').style.display = 'none';
        return;
    }
    
    const selectedOption = dateSelect.options[dateSelect.selectedIndex];
    selectedPrice = parseFloat(selectedOption.dataset.price);
    
    let totalPrice = 0;
    let participantCount = 0;
    let childrenCount = 0;
    let infantCount = 0;
    let breakdown = [];
    
    // Сохранённые участники
    const savedCheckboxes = document.querySelectorAll('input[name="saved_participants[]"]:checked');
    savedCheckboxes.forEach(cb => {
        try {
            const data = JSON.parse(atob(cb.value));
            const age = calculateAge(data.birth_date);
            participantCount++;
            
            if (age < 2) {
                infantCount++;
                breakdown.push(`${data.full_name} (младенец): 0 ₽`);
            } else if (age < 12) {
                childrenCount++;
                const price = selectedPrice * 0.7;
                totalPrice += price;
                breakdown.push(`${data.full_name} (ребёнок): ${Math.round(price).toLocaleString('ru-RU')} ₽`);
            } else {
                totalPrice += selectedPrice;
                breakdown.push(`${data.full_name} (взрослый): ${selectedPrice.toLocaleString('ru-RU')} ₽`);
            }
        } catch (e) {}
    });
    
    // Новые участники
    const participants = document.querySelectorAll('#participants-container .participant');
    participants.forEach((p) => {
        if (p.style.display === 'none') return;
        
        const birthInput = p.querySelector('input[name$="[birth_date]"]');
        const nameInput = p.querySelector('input[name$="[full_name]"]');
        
        if (birthInput && birthInput.value && nameInput && nameInput.value) {
            const age = calculateAge(birthInput.value);
            participantCount++;
            
            if (age < 2) {
                infantCount++;
                breakdown.push(`${nameInput.value} (младенец): 0 ₽`);
            } else if (age < 12) {
                childrenCount++;
                const price = selectedPrice * 0.7;
                totalPrice += price;
                breakdown.push(`${nameInput.value} (ребёнок): ${Math.round(price).toLocaleString('ru-RU')} ₽`);
            } else {
                totalPrice += selectedPrice;
                breakdown.push(`${nameInput.value} (взрослый): ${selectedPrice.toLocaleString('ru-RU')} ₽`);
            }
        }
    });
    
    document.getElementById('priceCalculator').style.display = 'block';
    document.getElementById('basePriceDisplay').textContent = selectedPrice.toLocaleString('ru-RU');
    document.getElementById('participantCount').textContent = participantCount;
    document.getElementById('childrenCount').textContent = childrenCount;
    document.getElementById('infantCount').textContent = infantCount;
    document.getElementById('totalPrice').textContent = Math.round(totalPrice).toLocaleString('ru-RU');
    document.getElementById('priceBreakdown').innerHTML = breakdown.join('<br>') || 'Выберите участников';
}

// Расчёт для неавторизованных
function updateSimplePrice() {
    const dateSelect = document.getElementById('tourDateSelect');
    const countInput = document.getElementById('participantsCountSimple');
    const infoDiv = document.getElementById('simplePriceInfo');
    const priceSpan = document.getElementById('simpleTotalPrice');
    
    if (!dateSelect || !dateSelect.value || !countInput || !countInput.value) {
        if (infoDiv) infoDiv.style.display = 'none';
        return;
    }
    
    const selectedOption = dateSelect.options[dateSelect.selectedIndex];
    const price = parseFloat(selectedOption.dataset.price);
    const count = parseInt(countInput.value) || 1;
    const total = price * count;
    
    if (infoDiv) {
        infoDiv.style.display = 'block';
        priceSpan.textContent = Math.round(total).toLocaleString('ru-RU') + ' ₽';
    }
}

function toggleClientParticipation() {
    const checkbox = document.getElementById('clientNotParticipating');
    const selfParticipant = document.getElementById('participant-self');
    
    if (checkbox.checked) {
        selfParticipant.style.display = 'none';
        selfParticipant.querySelectorAll('input').forEach(i => i.required = false);
    } else {
        selfParticipant.style.display = 'block';
        selfParticipant.querySelectorAll('input[type="text"], input[type="date"]').forEach(i => i.required = true);
    }
    updatePrice();
}

document.getElementById('add-participant')?.addEventListener('click', function() {
    const container = document.getElementById('participants-container');
    const idx = container.children.length;
    const html = `
        <div class="participant" id="participant${idx}">
            <p><strong>Участник ${idx}</strong> <button type="button" class="btn-secondary" onclick="this.closest('.participant').remove(); updatePrice();">Удалить</button></p>
            <input type="text" name="participants[${idx}][full_name]" placeholder="ФИО" required>
            <input type="date" name="participants[${idx}][birth_date]" required onchange="updateParticipantAge(this); updatePrice();">
            
            <div id="participant${idx}-docs" class="document-section">
                <div id="participant${idx}-passport" style="display:block;">
                    <label>Паспорт:</label>
                    <input type="text" name="participants[${idx}][passport_series]" placeholder="Серия (4 цифры)" pattern="[0-9]{4}">
                    <input type="text" name="participants[${idx}][passport_number]" placeholder="Номер (6 цифр)" pattern="[0-9]{6}">
                </div>
                <div id="participant${idx}-oms" style="display:none;">
                    <label>Полис ОМС:</label>
                    <input type="text" name="participants[${idx}][oms_number]" placeholder="Номер полиса (16 цифр)" pattern="[0-9]{16}">
                </div>
            </div>
            
            <select name="participants[${idx}][relation]">
                <option value="spouse">Супруг(а)</option>
                <option value="child">Ребёнок</option>
                <option value="parent">Родитель</option>
                <option value="other">Другой</option>
            </select>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
});

function toggleAllParticipants(btn) {
    const container = btn.closest('.saved-participants');
    const checkboxes = container.querySelectorAll('input[type="checkbox"]');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => cb.checked = !allChecked);
    updatePrice();
}

<?php if ($is_authorized && $client_data): ?>
document.addEventListener('DOMContentLoaded', function() {
    const clientName = <?= json_encode($client_data['full_name'] ?? '') ?>;
    const clientBirth = <?= json_encode($client_data['birth_date'] ?? '') ?>;
    const clientSeries = <?= json_encode($client_data['passport_series'] ?? '') ?>;
    const clientNumber = <?= json_encode($client_data['passport_number'] ?? '') ?>;
    
    if (clientName) document.getElementById('partName0').value = clientName;
    if (clientBirth) {
        document.getElementById('partBirth0').value = clientBirth;
        updateParticipantAge(document.getElementById('partBirth0'));
    }
    
    const seriesInput = document.querySelector('input[name="participants[0][passport_series]"]');
    const numberInput = document.querySelector('input[name="participants[0][passport_number]"]');
    if (clientSeries && seriesInput) seriesInput.value = clientSeries;
    if (clientNumber && numberInput) numberInput.value = clientNumber;
    
    updatePrice();
});
<?php endif; ?>

document.getElementById('tourDateSelect')?.addEventListener('change', function() {
    updatePrice();
    updateSimplePrice();
});

// Для неавторизованных - слушаем изменение количества
document.getElementById('participantsCountSimple')?.addEventListener('input', updateSimplePrice);
</script>
</body>
</html>