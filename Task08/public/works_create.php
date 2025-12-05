<?php
require_once 'config.php';
require_once 'helpers.php';

$db = getDbConnection();
$error = '';
$employee_id = (int)getParam('employee_id', 0);

if (!$employee_id) {
    redirect('index.php');
}

$stmt = $db->prepare("SELECT name FROM Employees WHERE id = :id");
$stmt->execute([':id' => $employee_id]);
$employee = $stmt->fetch();

if (!$employee) {
    redirect('index.php');
}

$services = $db->query("SELECT id, name, price FROM Services WHERE is_active = 1 ORDER BY name")->fetchAll();
$boxes = $db->query("SELECT id, number FROM Boxes WHERE is_active = 1 ORDER BY number")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $box_id = (int)getParam('box_id', 0);
    $service_id = (int)getParam('service_id', 0);
    $work_date = getParam('work_date', date('Y-m-d'));
    $work_time = getParam('work_time', date('H:i'));
    $actual_price = getParam('actual_price', '0');
    $notes = trim(getParam('notes', ''));

    if (!$box_id) {
        $error = 'Необходимо выбрать бокс';
    } elseif (!$service_id) {
        $error = 'Необходимо выбрать услугу';
    } elseif (empty($work_date)) {
        $error = 'Необходимо указать дату';
    } elseif (empty($work_time)) {
        $error = 'Необходимо указать время';
    } elseif (!is_numeric($actual_price) || $actual_price < 0) {
        $error = 'Стоимость должна быть положительным числом';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO WorkRecords (employee_id, box_id, service_id, work_date, work_time, actual_price, notes)
                VALUES (:employee_id, :box_id, :service_id, :work_date, :work_time, :actual_price, :notes)
            ");
            $stmt->execute([
                ':employee_id' => $employee_id,
                ':box_id' => $box_id,
                ':service_id' => $service_id,
                ':work_date' => $work_date,
                ':work_time' => $work_time,
                ':actual_price' => $actual_price,
                ':notes' => $notes ?: null
            ]);

            redirect('works.php?employee_id=' . $employee_id);
        } catch (PDOException $e) {
            $error = 'Ошибка при добавлении работы: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавить выполненную работу</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
            border-bottom: 3px solid #ffc107;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        input[type="text"],
        input[type="date"],
        input[type="time"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #ffc107;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin-right: 10px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #ffc107;
            color: #212529;
            font-weight: 600;
        }
        .btn-primary:hover {
            background: #e0a800;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .service-price {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
    <script>
        function updatePrice() {
            const serviceSelect = document.getElementById('service_id');
            const priceInput = document.getElementById('actual_price');
            const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
            if (selectedOption && selectedOption.dataset.price) {
                priceInput.value = selectedOption.dataset.price;
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <h1>Добавить выполненную работу: <?= h($employee['name']) ?></h1>

        <?php if ($error): ?>
            <div class="error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="work_date">Дата *</label>
                <input type="date" id="work_date" name="work_date" value="<?= h(getParam('work_date', date('Y-m-d'))) ?>" required>
            </div>

            <div class="form-group">
                <label for="work_time">Время *</label>
                <input type="time" id="work_time" name="work_time" value="<?= h(getParam('work_time', date('H:i'))) ?>" required>
            </div>

            <div class="form-group">
                <label for="box_id">Бокс *</label>
                <select id="box_id" name="box_id" required>
                    <option value="">Выберите бокс</option>
                    <?php foreach ($boxes as $box): ?>
                        <option value="<?= $box['id'] ?>" <?= getParam('box_id') == $box['id'] ? 'selected' : '' ?>>
                            Бокс №<?= h($box['number']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="service_id">Услуга *</label>
                <select id="service_id" name="service_id" required onchange="updatePrice()">
                    <option value="">Выберите услугу</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?= $service['id'] ?>" data-price="<?= h($service['price']) ?>" <?= getParam('service_id') == $service['id'] ? 'selected' : '' ?>>
                            <?= h($service['name']) ?> (<?= number_format($service['price'], 2, '.', ' ') ?> ₽)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="actual_price">Стоимость (₽) *</label>
                <input type="number" id="actual_price" name="actual_price" step="0.01" min="0" value="<?= h(getParam('actual_price', '0')) ?>" required>
            </div>

            <div class="form-group">
                <label for="notes">Примечания</label>
                <textarea id="notes" name="notes" rows="3"><?= h(getParam('notes', '')) ?></textarea>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">Сохранить</button>
                <a href="works.php?employee_id=<?= $employee_id ?>" class="btn btn-secondary">Отмена</a>
            </div>
        </form>
    </div>
</body>
</html>
