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

$stmt = $db->prepare("SELECT * FROM WorkRecords WHERE id = :id");
$stmt->execute([':id' => $id]);
$work = $stmt->fetch();

if (!$work) {
    redirect('index.php');
}

$categories = getCarCategories($db);
$allServices = getActiveServices($db);
$allBoxes = getActiveBoxes($db);

$currentCategoryId = null;
foreach ($allServices as $service) {
    if ($service['id'] == $work['service_id']) {
        $currentCategoryId = $service['car_category_id'];
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $box_id = (int)getParam('box_id', 0);
    $service_id = (int)getParam('service_id', 0);
    $work_date = getParam('work_date', '');
    $work_time = getParam('work_time', '');
    $actual_price = getParam('actual_price', '0');
    $notes = trim(getParam('notes', ''));

    if (!$box_id) {
        $error = 'Необходимо выбрать бокс';
    } elseif (!$service_id) {
        $error = 'Необходимо выбрать услугу';
    } elseif (!is_numeric($actual_price) || $actual_price < 0) {
        $error = 'Стоимость должна быть положительным числом';
    } else {
        try {
            $stmt = $db->prepare("
                UPDATE WorkRecords
                SET box_id = :box_id, service_id = :service_id, work_date = :work_date,
                    work_time = :work_time, actual_price = :actual_price, notes = :notes
                WHERE id = :id
            ");
            $stmt->execute([
                ':box_id' => $box_id,
                ':service_id' => $service_id,
                ':work_date' => $work_date,
                ':work_time' => $work_time,
                ':actual_price' => $actual_price,
                ':notes' => $notes ?: null,
                ':id' => $id
            ]);

            redirect('works.php?employee_id=' . $employee_id);
        } catch (PDOException $e) {
            $error = 'Ошибка при обновлении работы: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать выполненную работу</title>
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

            if (!categorySelect || !boxSelect || !serviceSelect) {
                console.error('Элементы формы не найдены!');
                return;
            }

            const selectedCategoryId = categorySelect.value;

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
            }

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

            const currentBoxValue = boxSelect.value;

            while (boxSelect.options.length > 1) {
                boxSelect.remove(1);
            }
            boxSelect.options[0].textContent = 'Выберите бокс';

            window.allBoxOptions.forEach(boxOpt => {
                const boxId = parseInt(boxOpt.value);
                if (compatibleBoxIds.includes(boxId)) {
                    const opt = new Option(boxOpt.text, boxOpt.value);
                    boxSelect.add(opt);
                }
            });

            if (currentBoxValue && Array.from(boxSelect.options).some(opt => opt.value == currentBoxValue)) {
                boxSelect.value = currentBoxValue;
            }

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

            const currentServiceValue = serviceSelect.value;

            while (serviceSelect.options.length > 1) {
                serviceSelect.remove(1);
            }
            serviceSelect.options[0].textContent = 'Выберите услугу';

            window.allServiceOptions.forEach(serviceOpt => {
                if (serviceOpt.categoryId === catIdNum) {
                    const opt = new Option(serviceOpt.text, serviceOpt.value);
                    opt.setAttribute('data-price', serviceOpt.price);
                    serviceSelect.add(opt);
                }
            });

            if (currentServiceValue && Array.from(serviceSelect.options).some(opt => opt.value == currentServiceValue)) {
                serviceSelect.value = currentServiceValue;
            }
        }

        function updatePrice() {
            const serviceSelect = document.getElementById('service_id');
            const priceField = document.getElementById('actual_price');

            if (serviceSelect && serviceSelect.value && services[serviceSelect.value]) {
                priceField.value = services[serviceSelect.value];
            }
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

            filterBoxesAndServices();

            if (serviceSelect) {
                serviceSelect.addEventListener('change', updatePrice);
            }
        });
    </script>
</head>
<body>
    <div class="container">
        <h1>Редактировать выполненную работу</h1>

        <?php if ($error): ?>
            <div class="error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="work_date">Дата *</label>
                <input type="date" id="work_date" name="work_date" value="<?= h($work['work_date']) ?>" required>
            </div>

            <div class="form-group">
                <label for="work_time">Время *</label>
                <input type="time" id="work_time" name="work_time" value="<?= h($work['work_time']) ?>" required>
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
                                class="box-option"
                                <?= $work['box_id'] == $box['id'] ? 'selected' : '' ?>
                                style="display: none;">
                            Бокс №<?= h($box['number']) ?> (<?= h($box['description']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="service_id">Услуга *</label>
                <select id="service_id" name="service_id" required onchange="updatePrice()">
                    <option value="">Сначала выберите тип ТС</option>
                    <?php foreach ($allServices as $service): ?>
                        <option value="<?= $service['id'] ?>"
                                data-category-id="<?= $service['car_category_id'] ?>"
                                data-price="<?= $service['price'] ?>"
                                class="service-option"
                                <?= $work['service_id'] == $service['id'] ? 'selected' : '' ?>
                                style="display: none;">
                            <?= h($service['name']) ?> (<?= number_format($service['price'], 2, '.', ' ') ?> ₽)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="actual_price">Стоимость (₽) *</label>
                <input type="number" id="actual_price" name="actual_price" step="0.01" min="0" value="<?= h($work['actual_price']) ?>" required>
            </div>

            <div class="form-group">
                <label for="notes">Примечания</label>
                <textarea id="notes" name="notes" rows="3"><?= h($work['notes'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">Сохранить</button>
                <a href="works.php?employee_id=<?= $employee_id ?>" class="btn btn-secondary">Отмена</a>
            </div>
        </form>
    </div>
</body>
</html>
