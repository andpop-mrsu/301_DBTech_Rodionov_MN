<?php
require_once 'config.php';
require_once 'helpers.php';

$db = getDbConnection();
$error = '';
$id = (int)getParam('id', 0);
$employee_id = (int)getParam('employee_id', 0);

if (!$id || !$employee_id) {
    redirect('index.php');
}

$stmt = $db->prepare("SELECT * FROM Appointments WHERE id = :id");
$stmt->execute([':id' => $id]);
$appointment = $stmt->fetch();

if (!$appointment) {
    redirect('index.php');
}

$categories = getCarCategories($db);
$allServices = getActiveServices($db);
$allBoxes = getActiveBoxes($db);

$currentCategoryId = null;
$defaultPrice = '0';
foreach ($allServices as $service) {
    if ($service['id'] == $appointment['service_id']) {
        $currentCategoryId = $service['car_category_id'];
        $defaultPrice = $service['price'];
        break;
    }
}

$stmt = $db->prepare("SELECT actual_price FROM WorkRecords WHERE appointment_id = :appointment_id LIMIT 1");
$stmt->execute([':appointment_id' => $id]);
$existingWorkPrice = $stmt->fetchColumn();
$hasExistingWork = ($existingWorkPrice !== false);
if ($hasExistingWork) {
    $defaultPrice = (string)$existingWorkPrice;
} else {
    $existingWorkPrice = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $box_id = (int)getParam('box_id', 0);
    $service_id = (int)getParam('service_id', 0);
    $appointment_date = getParam('appointment_date', '');
    $appointment_time = getParam('appointment_time', '');
    $client_name = trim(getParam('client_name', ''));
    $client_phone = trim(getParam('client_phone', ''));
    $status = getParam('status', 'Запланировано');
    $notes = trim(getParam('notes', ''));
    $actual_price = trim(getParam('actual_price', ''));
    $old_status = $appointment['status'];

    if (empty($client_name)) {
        $error = 'Необходимо указать имя клиента';
    } elseif (!$box_id) {
        $error = 'Необходимо выбрать бокс';
    } elseif (!$service_id) {
        $error = 'Необходимо выбрать услугу';
    } else {
        if ($status === 'Выполнено' && empty($actual_price)) {
            foreach ($allServices as $service) {
                if ($service['id'] == $service_id) {
                    $actual_price = $service['price'];
                    break;
                }
            }
            if (empty($actual_price)) {
                $actual_price = '0';
            }
        }

        if ($status === 'Выполнено' && !empty($actual_price) && (!is_numeric($actual_price) || $actual_price < 0)) {
            $error = 'Фактическая стоимость должна быть положительным числом';
        }

        if (empty($error) && $status === 'Запланировано' && !empty($appointment_date) && !empty($appointment_time)) {
            $conflictCheck = checkTimeConflict($db, $box_id, $appointment_date, $appointment_time, $service_id, $id);

            if ($conflictCheck['conflict']) {
                $error = $conflictCheck['message'];
            }
        }
    }

    if (empty($error)) {
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("
                UPDATE Appointments
                SET box_id = :box_id, service_id = :service_id, appointment_date = :appointment_date,
                    appointment_time = :appointment_time, status = :status, client_name = :client_name,
                    client_phone = :client_phone, notes = :notes
                WHERE id = :id
            ");
            $stmt->execute([
                ':box_id' => $box_id,
                ':service_id' => $service_id,
                ':appointment_date' => $appointment_date,
                ':appointment_time' => $appointment_time,
                ':status' => $status,
                ':client_name' => $client_name,
                ':client_phone' => $client_phone ?: null,
                ':notes' => $notes ?: null,
                ':id' => $id
            ]);

            if ($status === 'Выполнено') {
                $stmt = $db->prepare("SELECT id FROM WorkRecords WHERE appointment_id = :appointment_id");
                $stmt->execute([':appointment_id' => $id]);
                $existingWork = $stmt->fetch();

                if ($existingWork) {
                    $stmt = $db->prepare("
                        UPDATE WorkRecords
                        SET employee_id = :employee_id, box_id = :box_id, service_id = :service_id,
                            work_date = :work_date, work_time = :work_time, actual_price = :actual_price,
                            notes = :notes
                        WHERE appointment_id = :appointment_id
                    ");
                    $stmt->execute([
                        ':employee_id' => $employee_id,
                        ':box_id' => $box_id,
                        ':service_id' => $service_id,
                        ':work_date' => $appointment_date,
                        ':work_time' => $appointment_time,
                        ':actual_price' => $actual_price,
                        ':notes' => $notes ?: 'Работа выполнена по графику',
                        ':appointment_id' => $id
                    ]);
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO WorkRecords (appointment_id, employee_id, box_id, service_id, work_date, work_time, actual_price, notes)
                        VALUES (:appointment_id, :employee_id, :box_id, :service_id, :work_date, :work_time, :actual_price, :notes)
                    ");
                    $stmt->execute([
                        ':appointment_id' => $id,
                        ':employee_id' => $employee_id,
                        ':box_id' => $box_id,
                        ':service_id' => $service_id,
                        ':work_date' => $appointment_date,
                        ':work_time' => $appointment_time,
                        ':actual_price' => $actual_price,
                        ':notes' => $notes ?: 'Работа выполнена по графику'
                    ]);
                }
            } elseif ($old_status === 'Выполнено' && $status !== 'Выполнено') {
                $stmt = $db->prepare("DELETE FROM WorkRecords WHERE appointment_id = :appointment_id");
                $stmt->execute([':appointment_id' => $id]);
            }

            $db->commit();

            redirect('schedule.php?employee_id=' . $employee_id);
        } catch (PDOException $e) {
            $db->rollBack();
            $error = 'Ошибка при обновлении записи: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать запись в графике</title>
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
            border-bottom: 3px solid #28a745;
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
        input[type="tel"],
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
            border-color: #28a745;
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
            background: #28a745;
            color: white;
        }
        .btn-primary:hover {
            background: #218838;
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
        small {
            display: block;
            margin-top: 5px;
            font-size: 12px;
        }
    </style>
    <script>
        const services = {
            <?php foreach ($allServices as $service): ?>
            <?= $service['id'] ?>: <?= $service['price'] ?>,
            <?php endforeach; ?>
        };

        const categoryBoxMapping = <?php
            $mapping = getCategoryBoxMapping($categories, $allBoxes);
            $jsonMapping = [];
            foreach ($mapping as $catId => $boxIds) {
                $jsonMapping[(string)$catId] = $boxIds;
            }
            $jsonOutput = json_encode($jsonMapping, JSON_UNESCAPED_UNICODE);
            echo ($jsonOutput === false || empty($jsonOutput)) ? '{}' : $jsonOutput;
        ?>;

        function filterBoxesAndServices() {
            const categorySelect = document.getElementById('car_category_id');
            const boxSelect = document.getElementById('box_id');
            const serviceSelect = document.getElementById('service_id');

            const selectedCategoryId = categorySelect.value;

            if (!selectedCategoryId) {
                Array.from(boxSelect.options).forEach(opt => {
                    if (opt.value) opt.style.display = 'none';
                });
                Array.from(serviceSelect.options).forEach(opt => {
                    if (opt.value) opt.style.display = 'none';
                });
                return;
            }

            const selectedCategoryIdNum = parseInt(selectedCategoryId);
            let compatibleBoxIds = categoryBoxMapping[selectedCategoryIdNum] || categoryBoxMapping[selectedCategoryId] || [];

            compatibleBoxIds = compatibleBoxIds.map(id => parseInt(id));

            Array.from(boxSelect.options).forEach(opt => {
                if (!opt.value) {
                    opt.textContent = 'Выберите бокс';
                    opt.style.display = 'block';
                    opt.disabled = false;
                } else {
                    const boxId = parseInt(opt.value);
                    if (compatibleBoxIds.includes(boxId)) {
                        opt.style.display = 'block';
                        opt.disabled = false;
                    } else {
                        opt.style.display = 'none';
                        opt.disabled = true;
                        if (boxSelect.value == opt.value) {
                            boxSelect.value = '';
                        }
                    }
                }
            });

            const selectedCategoryIdNumForServices = parseInt(selectedCategoryId);

            Array.from(serviceSelect.options).forEach(opt => {
                if (!opt.value) {
                    opt.textContent = 'Выберите услугу';
                    opt.style.display = 'block';
                    opt.disabled = false;
                } else {
                    const serviceCategoryId = parseInt(opt.getAttribute('data-category-id'));
                    if (serviceCategoryId === selectedCategoryIdNumForServices) {
                        opt.style.display = 'block';
                        opt.disabled = false;
                    } else {
                        opt.style.display = 'none';
                        opt.disabled = true;
                        if (serviceSelect.value == opt.value) {
                            serviceSelect.value = '';
                        }
                    }
                }
            });
        }

        function togglePriceField() {
            const statusSelect = document.getElementById('status');
            const priceFieldGroup = document.getElementById('priceFieldGroup');
            const priceField = document.getElementById('actual_price');
            const priceRequired = document.getElementById('priceRequired');

            if (statusSelect.value === 'Выполнено') {
                priceFieldGroup.style.display = 'block';
                priceField.required = true;
                priceRequired.style.display = 'inline';

                if (!priceField.value || priceField.value === '0') {
                    updatePriceFromService();
                }
            } else {
                priceFieldGroup.style.display = 'none';
                priceField.required = false;
                priceRequired.style.display = 'none';
            }
        }

        function updatePriceFromService() {
            const serviceSelect = document.getElementById('service_id');
            const priceField = document.getElementById('actual_price');
            const selectedServiceId = serviceSelect.value;

            if (selectedServiceId && services[selectedServiceId]) {
                priceField.value = services[selectedServiceId];
            }
        }

        function updatePrice() {
            if (document.getElementById('status').value === 'Выполнено') {
                updatePriceFromService();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            togglePriceField();
            filterBoxesAndServices(); 

            document.getElementById('service_id').addEventListener('change', function() {
                updatePrice();
            });
        });
    </script>
</head>
<body>
    <div class="container">
        <h1>Редактировать запись в графике</h1>

        <?php if ($error): ?>
            <div class="error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="appointment_date">Дата *</label>
                <input type="date" id="appointment_date" name="appointment_date" value="<?= h($appointment['appointment_date']) ?>" required>
            </div>

            <div class="form-group">
                <label for="appointment_time">Время *</label>
                <input type="time" id="appointment_time" name="appointment_time" value="<?= h($appointment['appointment_time']) ?>" required>
            </div>

            <div class="form-group">
                <label for="client_name">Имя клиента *</label>
                <input type="text" id="client_name" name="client_name" value="<?= h($appointment['client_name']) ?>" required>
            </div>

            <div class="form-group">
                <label for="client_phone">Телефон клиента</label>
                <input type="tel" id="client_phone" name="client_phone" value="<?= h($appointment['client_phone'] ?? '') ?>" placeholder="+7-911-111-11-11">
            </div>

            <div class="form-group">
                <label for="car_category_id">Тип транспортного средства *</label>
                <select id="car_category_id" name="car_category_id" required onchange="filterBoxesAndServices()">
                    <option value="">Выберите тип ТС</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>" data-name="<?= h($category['name']) ?>" <?= $currentCategoryId == $category['id'] ? 'selected' : '' ?>>
                            <?= h($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="color: #6c757d; display: block; margin-top: 5px;">Выбор типа ТС определит доступные услуги и боксы</small>
            </div>

            <div class="form-group">
                <label for="box_id">Бокс *</label>
                <select id="box_id" name="box_id" required>
                    <option value="">Сначала выберите тип ТС</option>
                    <?php foreach ($allBoxes as $box): ?>
                        <option value="<?= $box['id'] ?>"
                                data-description="<?= h($box['description']) ?>"
                                <?= $appointment['box_id'] == $box['id'] ? 'selected' : '' ?>
                                style="display: none;">
                            Бокс №<?= h($box['number']) ?> (<?= h($box['description']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="service_id">Услуга *</label>
                <select id="service_id" name="service_id" required onchange="updatePrice();">
                    <option value="">Сначала выберите тип ТС</option>
                    <?php foreach ($allServices as $service): ?>
                        <option value="<?= $service['id'] ?>"
                                data-category-id="<?= $service['car_category_id'] ?>"
                                data-price="<?= $service['price'] ?>"
                                <?= $appointment['service_id'] == $service['id'] ? 'selected' : '' ?>
                                style="display: none;">
                            <?= h($service['name']) ?> (<?= number_format($service['price'], 2, '.', ' ') ?> ₽)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="status">Статус</label>
                <select id="status" name="status" onchange="togglePriceField()">
                    <option value="Запланировано" <?= $appointment['status'] == 'Запланировано' ? 'selected' : '' ?>>Запланировано</option>
                    <option value="Выполнено" <?= $appointment['status'] == 'Выполнено' ? 'selected' : '' ?>>Выполнено</option>
                    <option value="Отменено" <?= $appointment['status'] == 'Отменено' ? 'selected' : '' ?>>Отменено</option>
                    <option value="Неявка" <?= $appointment['status'] == 'Неявка' ? 'selected' : '' ?>>Неявка</option>
                </select>
            </div>

            <div class="form-group" id="priceFieldGroup" style="<?= $appointment['status'] === 'Выполнено' ? '' : 'display: none;' ?>">
                <label for="actual_price">Фактическая стоимость (₽) <span id="priceRequired" style="color: red;">*</span></label>
                <input type="number" id="actual_price" name="actual_price" step="0.01" min="0" value="<?= $hasExistingWork ? h($existingWorkPrice) : ($appointment['status'] === 'Выполнено' ? h($defaultPrice) : '') ?>" <?= $appointment['status'] === 'Выполнено' ? 'required' : '' ?>>
                <small style="color: #6c757d;">При статусе "Выполнено" запись автоматически добавится в выполненные работы</small>
            </div>

            <div class="form-group">
                <label for="notes">Примечания</label>
                <textarea id="notes" name="notes" rows="3"><?= h($appointment['notes'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">Сохранить</button>
                <a href="schedule.php?employee_id=<?= $employee_id ?>" class="btn btn-secondary">Отмена</a>
            </div>
        </form>
    </div>
</body>
</html>
