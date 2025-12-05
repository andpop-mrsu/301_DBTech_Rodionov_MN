<?php
/**
 * index.php - главная страница приложения
 * Отображает список всех мастеров автомойки, отсортированный по фамилии
 * 
 * Основные страницы:
 * - index.php - главная страница со списком мастеров
 * - dismissed.php - страница уволенных сотрудников
 * - cancelled.php - страница отмененных заказов и неявок
 * 
 * Управление мастерами:
 * - master_create.php - форма добавления нового мастера
 * - master_edit.php - форма редактирования данных мастера
 * - master_delete.php - страница подтверждения удаления мастера
 * 
 * Управление графиком работы:
 * - schedule.php - список записей в графике работы мастера
 * - schedule_create.php - форма добавления записи в график
 * - schedule_edit.php - форма редактирования записи в графике
 * - schedule_delete.php - страница подтверждения удаления записи из графика
 * 
 * Управление выполненными работами:
 * - works.php - список выполненных работ мастера
 * - works_create.php - форма добавления выполненной работы
 * - works_edit.php - форма редактирования выполненной работы
 * - works_delete.php - страница подтверждения удаления выполненной работы
 * 
 * Вспомогательные файлы:
 * - config.php - конфигурация подключения к базе данных
 * - helpers.php - вспомогательные функции (форматирование, валидация и т.д.)
 */

require_once 'config.php';
require_once 'helpers.php';

$db = getDbConnection();

$stmt = $db->prepare("
    SELECT id, name, position, hire_date, phone, email
    FROM Employees
    WHERE position = 'Мастер' AND is_active = 1
");
$stmt->execute();
$masters = $stmt->fetchAll();

usort($masters, function($a, $b) {
    $aLast = getLastName($a['name']);
    $bLast = getLastName($b['name']);
    return strcasecmp($aLast, $bLast);
});
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мастера автомойки</title>
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
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        .masters-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .masters-table th,
        .masters-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .masters-table th {
            background: #007bff;
            color: white;
            font-weight: 600;
        }
        .masters-table tr:hover {
            background: #f8f9fa;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            margin: 2px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn-edit {
            background: #28a745;
            color: white;
            padding: 8px 10px;
            font-size: 0;
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        .btn-edit:hover {
            background: #218838;
        }
        .btn-edit::before {
            content: '';
            width: 16px;
            height: 16px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='white'%3E%3Cpath d='M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z'/%3E%3C/svg%3E");
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }
        .btn-delete {
            background: #dc3545;
            color: white;
            padding: 8px 10px;
            font-size: 0;
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        .btn-delete:hover {
            background: #c82333;
        }
        .btn-delete::before {
            content: '';
            width: 16px;
            height: 16px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='white'%3E%3Cpath d='M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z'/%3E%3C/svg%3E");
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }
        .btn-schedule {
            background: #17a2b8;
            color: white;
        }
        .btn-schedule:hover {
            background: #138496;
        }
        .btn-works {
            background: #ffc107;
            color: #212529;
        }
        .btn-works:hover {
            background: #e0a800;
        }
        .btn-add {
            background: #007bff;
            color: white;
            padding: 12px 24px;
            font-size: 16px;
            margin-top: 20px;
        }
        .btn-add:hover {
            background: #0056b3;
        }
        .actions {
            white-space: nowrap;
        }
        .top-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .btn-dismissed {
            background: #6c757d;
            color: white;
            padding: 10px 18px;
            font-size: 14px;
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.3s;
        }
        .btn-dismissed:hover {
            background: #5a6268;
        }
        .btn-add {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            font-size: 14px;
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.3s;
        }
        .btn-add:hover {
            background: #0056b3;
        }
        .btn-add-large {
            padding: 12px 24px;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Мастера автомойки</h1>

        <div class="top-buttons">
            <a href="master_create.php" class="btn btn-add btn-add-large">Добавить мастера</a>
            <a href="dismissed.php" class="btn btn-dismissed">Уволенные сотрудники</a>
        </div>

        <?php if (empty($masters)): ?>
            <p>Мастера не найдены.</p>
        <?php else: ?>
            <table class="masters-table">
                <thead>
                    <tr>
                        <th>ФИО</th>
                        <th>Должность</th>
                        <th>Дата найма</th>
                        <th>Телефон</th>
                        <th>Email</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($masters as $master): ?>
                        <tr>
                            <td><?= h($master['name']) ?></td>
                            <td><?= h($master['position']) ?></td>
                            <td><?= formatDate($master['hire_date']) ?></td>
                            <td><?= h($master['phone'] ?? '-') ?></td>
                            <td><?= h($master['email'] ?? '-') ?></td>
                            <td class="actions">
                                <a href="master_edit.php?id=<?= $master['id'] ?>" class="btn btn-edit" title="Редактировать"></a>
                                <a href="master_delete.php?id=<?= $master['id'] ?>" class="btn btn-delete" title="Удалить"></a>
                                <a href="schedule.php?employee_id=<?= $master['id'] ?>" class="btn btn-schedule">График</a>
                                <a href="works.php?employee_id=<?= $master['id'] ?>" class="btn btn-works">Выполненные работы</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
