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

$categories = getCarCategories($db);
$allServices = getActiveServices($db);
$allBoxes = getActiveBoxes($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $box_id = (int)getParam('box_id', 0);
    $service_id = (int)getParam('service_id', 0);
    $appointment_date = getParam('appointment_date', '');
    $appointment_time = getParam('appointment_time', '');
    $client_name = trim(getParam('client_name', ''));
    $client_phone = trim(getParam('client_phone', ''));
    $status = getParam('status', 'Запланировано');
    $notes = trim(getParam('notes', ''));

    if (empty($client_name)) {
        $error = 'Необходимо указать имя клиента';
    } elseif (!$box_id) {
        $error = 'Необходимо выбрать бокс';
    } elseif (!$service_id) {
        $error = 'Необходимо выбрать услугу';
    } elseif (empty($appointment_date)) {
        $error = 'Необходимо указать дату';
    } elseif (empty($appointment_time)) {
        $error = 'Необходимо указать время';
    } else {
        $conflictCheck = checkTimeConflict($db, $box_id, $appointment_date, $appointment_time, $service_id);

        if ($conflictCheck['conflict']) {
            $error = $conflictCheck['message'];
        } else {
            try {
                $stmt = $db->prepare("
                    INSERT INTO Appointments (employee_id, box_id, service_id, appointment_date, appointment_time, status, client_name, client_phone, notes)
                    VALUES (:employee_id, :box_id, :service_id, :appointment_date, :appointment_time, :status, :client_name, :client_phone, :notes)
                ");
                $stmt->execute([
                    ':employee_id' => $employee_id,
                    ':box_id' => $box_id,
                    ':service_id' => $service_id,
                    ':appointment_date' => $appointment_date,
                    ':appointment_time' => $appointment_time,
                    ':status' => $status,
                    ':client_name' => $client_name,
                    ':client_phone' => $client_phone ?: null,
                    ':notes' => $notes ?: null
                ]);

                redirect('schedule.php?employee_id=' . $employee_id);
            } catch (PDOException $e) {
                $error = 'Ошибка при добавлении записи: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавить запись в график</title>
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
            border-bottom: 3px solid #17a2b8;
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
            border-color: #17a2b8;
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
            background: #17a2b8;
            color: white;
        }
        .btn-primary:hover {
            background: #138496;
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

            if (!categorySelect || !boxSelect || !serviceSelect) {
                console.error('Элементы формы не найдены!');
                return;
            }

            const selectedCategoryId = categorySelect.value;

            console.log('Selected category ID:', selectedCategoryId);
            console.log('Category mapping:', categoryBoxMapping);

            if (!selectedCategoryId) {
                
                boxSelect.disabled = true;
                serviceSelect.disabled = true;

                while (boxSelect.options.length > 1) {
                    boxSelect.remove(1);
                }
                boxSelect.options[0].textContent = 'Сначала выберите тип ТС';

                while (serviceSelect.options.length > 1) {
                    serviceSelect.remove(1);
                }
                serviceSelect.options[0].textContent = 'Сначала выберите тип ТС';

                boxSelect.value = '';
                serviceSelect.value = '';
                return;
            }

            boxSelect.disabled = false;
            serviceSelect.disabled = false;

            const catIdNum = parseInt(selectedCategoryId);
            const catIdStr = String(selectedCategoryId);

            let compatibleBoxIds = [];
            if (categoryBoxMapping[catIdNum] !== undefined) {
                compatibleBoxIds = categoryBoxMapping[catIdNum];
            } else if (categoryBoxMapping[catIdStr] !== undefined) {
                compatibleBoxIds = categoryBoxMapping[catIdStr];
            } else if (categoryBoxMapping[selectedCategoryId] !== undefined) {
                compatibleBoxIds = categoryBoxMapping[selectedCategoryId];
            }

            console.log('Category ID:', selectedCategoryId, 'as num:', catIdNum, 'as str:', catIdStr);
            console.log('Compatible box IDs:', compatibleBoxIds);
            console.log('Full mapping:', categoryBoxMapping);

            compatibleBoxIds = compatibleBoxIds.map(id => parseInt(id)).filter(id => !isNaN(id));

            if (!window.allBoxOptions) {
                window.allBoxOptions = [];
                Array.from(boxSelect.querySelectorAll('.box-option')).forEach(opt => {
                    window.allBoxOptions.push({
                        value: opt.value,
                        text: opt.textContent,
                        description: opt.getAttribute('data-description')
                    });
                });
            }

            while (boxSelect.options.length > 1) {
                boxSelect.remove(1);
            }
            boxSelect.options[0].textContent = 'Выберите бокс';

            let visibleBoxes = 0;
            window.allBoxOptions.forEach(boxOpt => {
                const boxId = parseInt(boxOpt.value);
                if (compatibleBoxIds.includes(boxId)) {
                    const opt = new Option(boxOpt.text, boxOpt.value);
                    boxSelect.add(opt);
                    visibleBoxes++;
                    console.log('Added box:', boxId, boxOpt.text);
                }
            });

            console.log('Visible boxes count:', visibleBoxes);

            if (!window.allServiceOptions) {
                window.allServiceOptions = [];
                Array.from(serviceSelect.querySelectorAll('.service-option')).forEach(opt => {
                    window.allServiceOptions.push({
                        value: opt.value,
                        text: opt.textContent,
                        categoryId: parseInt(opt.getAttribute('data-category-id')),
                        price: opt.getAttribute('data-price')
                    });
                });
            }

            while (serviceSelect.options.length > 1) {
                serviceSelect.remove(1);
            }
            serviceSelect.options[0].textContent = 'Выберите услугу';

            let visibleServices = 0;
            window.allServiceOptions.forEach(serviceOpt => {
                if (serviceOpt.categoryId === catIdNum) {
                    const opt = new Option(serviceOpt.text, serviceOpt.value);
                    opt.setAttribute('data-price', serviceOpt.price);
                    serviceSelect.add(opt);
                    visibleServices++;
                    console.log('Added service:', serviceOpt.categoryId, serviceOpt.text);
                }
            });

            console.log('Visible services count:', visibleServices);
        }

        document.addEventListener('DOMContentLoaded', function() {
            
            const boxSelect = document.getElementById('box_id');
            if (boxSelect && !window.allBoxOptions) {
                window.allBoxOptions = [];
                Array.from(boxSelect.querySelectorAll('.box-option')).forEach(opt => {
                    window.allBoxOptions.push({
                        value: opt.value,
                        text: opt.textContent,
                        description: opt.getAttribute('data-description')
                    });
                });
            }

            const serviceSelect = document.getElementById('service_id');
            if (serviceSelect && !window.allServiceOptions) {
                window.allServiceOptions = [];
                Array.from(serviceSelect.querySelectorAll('.service-option')).forEach(opt => {
                    window.allServiceOptions.push({
                        value: opt.value,
                        text: opt.textContent,
                        categoryId: parseInt(opt.getAttribute('data-category-id')),
                        price: opt.getAttribute('data-price')
                    });
                });
            }

            if (boxSelect) {
                while (boxSelect.options.length > 1) {
                    boxSelect.remove(1);
                }
                boxSelect.disabled = true;
            }

            if (serviceSelect) {
                while (serviceSelect.options.length > 1) {
                    serviceSelect.remove(1);
                }
                serviceSelect.disabled = true;
            }

            const categorySelect = document.getElementById('car_category_id');
            if (categorySelect && categorySelect.value) {
                filterBoxesAndServices();
            }
        });
    </script>
</head>
<body>
    <div class="container">
        <h1>Добавить запись в график: <?= h($employee['name']) ?></h1>

        <?php if ($error): ?>
            <div class="error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="appointment_date">Дата *</label>
                <input type="date" id="appointment_date" name="appointment_date" value="<?= h(getParam('appointment_date', date('Y-m-d'))) ?>" required>
            </div>

            <div class="form-group">
                <label for="appointment_time">Время *</label>
                <input type="time" id="appointment_time" name="appointment_time" value="<?= h(getParam('appointment_time', '')) ?>" required>
            </div>

            <div class="form-group">
                <label for="client_name">Имя клиента *</label>
                <input type="text" id="client_name" name="client_name" value="<?= h(getParam('client_name', '')) ?>" required>
            </div>

            <div class="form-group">
                <label for="client_phone">Телефон клиента</label>
                <input type="tel" id="client_phone" name="client_phone" value="<?= h(getParam('client_phone', '')) ?>" placeholder="+7-911-111-11-11">
            </div>

            <div class="form-group">
                <label for="car_category_id">Тип транспортного средства *</label>
                <select id="car_category_id" name="car_category_id" required onchange="filterBoxesAndServices()">
                    <option value="">Выберите тип ТС</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>" data-name="<?= h($category['name']) ?>">
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
                        <option value="<?= $box['id'] ?>" data-description="<?= h($box['description']) ?>" class="box-option" style="display: none;">
                            Бокс №<?= h($box['number']) ?> (<?= h($box['description']) ?>)
                        </option>
                    <?php endforeach; ?>
                    <!-- Опции будут добавлены динамически через JavaScript -->
                </select>
            </div>

            <div class="form-group">
                <label for="service_id">Услуга *</label>
                <select id="service_id" name="service_id" required>
                    <option value="">Сначала выберите тип ТС</option>
                    <?php foreach ($allServices as $service): ?>
                        <option value="<?= $service['id'] ?>"
                                data-category-id="<?= $service['car_category_id'] ?>"
                                data-price="<?= $service['price'] ?>"
                                class="service-option"
                                style="display: none;">
                            <?= h($service['name']) ?> (<?= number_format($service['price'], 2, '.', ' ') ?> ₽)
                        </option>
                    <?php endforeach; ?>
                    <!-- Опции будут добавлены динамически через JavaScript -->
                </select>
            </div>

            <div class="form-group">
                <label for="status">Статус</label>
                <select id="status" name="status">
                    <option value="Запланировано" <?= getParam('status', 'Запланировано') == 'Запланировано' ? 'selected' : '' ?>>Запланировано</option>
                    <option value="Выполнено" <?= getParam('status') == 'Выполнено' ? 'selected' : '' ?>>Выполнено</option>
                    <option value="Отменено" <?= getParam('status') == 'Отменено' ? 'selected' : '' ?>>Отменено</option>
                    <option value="Неявка" <?= getParam('status') == 'Неявка' ? 'selected' : '' ?>>Неявка</option>
                </select>
            </div>

            <div class="form-group">
                <label for="notes">Примечания</label>
                <textarea id="notes" name="notes" rows="3"><?= h(getParam('notes', '')) ?></textarea>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">Сохранить</button>
                <a href="schedule.php?employee_id=<?= $employee_id ?>" class="btn btn-secondary">Отмена</a>
            </div>
        </form>
    </div>
</body>
</html>
