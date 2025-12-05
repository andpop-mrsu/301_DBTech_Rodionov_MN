<?php

function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function getParam($name, $default = null) {
    return $_REQUEST[$name] ?? $default;
}

function getLastName($fullName) {
    $parts = explode(' ', trim($fullName));
    return $parts[0] ?? '';
}

function formatDate($dateString) {
    if (empty($dateString) || $dateString === null) {
        return '-';
    }

    $months = [
        1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
        5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
        9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
    ];

    $parts = explode('-', $dateString);
    if (count($parts) !== 3) {
        return $dateString;
    }

    $year = (int)$parts[0];
    $month = (int)$parts[1];
    $day = (int)$parts[2];

    if ($month < 1 || $month > 12) {
        return $dateString;
    }

    return $day . ' ' . $months[$month] . ' ' . $year;
}

function isBoxCompatibleWithCategory($boxDescription, $categoryName) {
    $description = mb_strtolower($boxDescription, 'UTF-8');
    $category = mb_strtolower($categoryName, 'UTF-8');

    if (strpos($description, 'универсальный') !== false) {
        return true;
    }

    if (strpos($description, 'мотоцикл') !== false) {
        return $category === 'мотоциклы';
    }

    if (strpos($description, 'легков') !== false || strpos($description, 'кроссовер') !== false) {
        return in_array($category, ['легковые', 'кроссоверы']);
    }

    if (strpos($description, 'микроавтобус') !== false || strpos($description, 'грузов') !== false) {
        return in_array($category, ['микроавтобусы', 'грузовые']);
    }

    return true;
}

function checkTimeConflict($db, $box_id, $appointment_date, $appointment_time, $service_id, $exclude_appointment_id = 0) {
    $stmt = $db->prepare("SELECT duration_minutes FROM Services WHERE id = :service_id LIMIT 1");
    $stmt->execute([':service_id' => $service_id]);
    $duration_minutes = $stmt->fetchColumn();

    if ($duration_minutes === false) {
        return ['conflict' => false, 'message' => ''];
    }

    $duration_minutes = (int)$duration_minutes;

    $time_parts = explode(':', $appointment_time);
    if (count($time_parts) < 2) {
        return ['conflict' => false, 'message' => ''];
    }
    $new_start_minutes = (int)$time_parts[0] * 60 + (int)$time_parts[1];
    $new_end_minutes = $new_start_minutes + $duration_minutes;

    $sql = "
        SELECT
            a.id,
            a.appointment_time,
            a.client_name,
            s.duration_minutes as service_duration,
            s.name as service_name
        FROM Appointments a
        JOIN Services s ON a.service_id = s.id
        WHERE a.box_id = :box_id
        AND a.appointment_date = :appointment_date
        AND a.status = 'Запланировано'
    ";

    if ($exclude_appointment_id > 0) {
        $sql .= " AND a.id != :exclude_id";
    }

    $stmt = $db->prepare($sql);
    $params = [
        ':box_id' => $box_id,
        ':appointment_date' => $appointment_date
    ];

    if ($exclude_appointment_id > 0) {
        $params[':exclude_id'] = $exclude_appointment_id;
    }

    $stmt->execute($params);
    $existing_appointments = $stmt->fetchAll();

    foreach ($existing_appointments as $existing) {
        $existing_time_parts = explode(':', $existing['appointment_time']);
        if (count($existing_time_parts) < 2) {
            continue;
        }
        $existing_start_minutes = (int)$existing_time_parts[0] * 60 + (int)$existing_time_parts[1];
        $existing_duration = (int)$existing['service_duration'];
        $existing_end_minutes = $existing_start_minutes + $existing_duration;

        if (($new_start_minutes >= $existing_start_minutes && $new_start_minutes < $existing_end_minutes) ||
            ($new_end_minutes > $existing_start_minutes && $new_end_minutes <= $existing_end_minutes) ||
            ($new_start_minutes <= $existing_start_minutes && $new_end_minutes >= $existing_end_minutes) ||
            ($new_start_minutes >= $existing_start_minutes && $new_end_minutes <= $existing_end_minutes)) {

            $existing_end_minutes_normalized = $existing_end_minutes % (24 * 60);
            $existing_end_hours = floor($existing_end_minutes_normalized / 60);
            $existing_end_mins = $existing_end_minutes_normalized % 60;
            $existing_end_time = sprintf('%02d:%02d', $existing_end_hours, $existing_end_mins);

            return [
                'conflict' => true,
                'message' => "Время конфликтует с существующей записью ({$existing['client_name']}, {$existing['appointment_time']}, {$existing['service_name']}). Бокс будет свободен с {$existing_end_time}"
            ];
        }
    }

    return ['conflict' => false, 'message' => ''];
}

function getCategoryBoxMapping($categories, $boxes) {
    $mapping = [];

    foreach ($categories as $cat) {
        $catId = (int)$cat['id'];
        $catName = mb_strtolower(trim($cat['name']), 'UTF-8');
        $mapping[$catId] = [];

        foreach ($boxes as $box) {
            $desc = mb_strtolower(trim($box['description']), 'UTF-8');
            $compatible = false;

            if (mb_strpos($desc, 'универсальный') !== false) {
                $compatible = true;
            }
            elseif ($catName === 'мотоциклы' && mb_strpos($desc, 'мотоцикл') !== false) {
                $compatible = true;
            }
            elseif (($catName === 'легковые' || $catName === 'кроссоверы') &&
                      (mb_strpos($desc, 'легков') !== false || mb_strpos($desc, 'кроссовер') !== false)) {
                $compatible = true;
            }
            elseif (($catName === 'микроавтобусы' || $catName === 'грузовые') &&
                      (mb_strpos($desc, 'микроавтобус') !== false || mb_strpos($desc, 'грузов') !== false)) {
                $compatible = true;
            }

            if ($compatible) {
                $mapping[$catId][] = (int)$box['id'];
            }
        }
    }

    return $mapping;
}

function getCarCategories($db) {
    static $categories = null;
    if ($categories === null) {
        $categories = $db->query("SELECT id, name FROM CarCategories ORDER BY name")->fetchAll();
    }
    return $categories;
}

function getActiveBoxes($db) {
    static $boxes = null;
    if ($boxes === null) {
        $boxes = $db->query("SELECT id, number, description FROM Boxes WHERE is_active = 1 ORDER BY number")->fetchAll();
    }
    return $boxes;
}

function getActiveServices($db) {
    static $services = null;
    if ($services === null) {
        $services = $db->query("
            SELECT s.id, s.name, s.car_category_id, c.name as category_name, s.price
            FROM Services s
            JOIN CarCategories c ON s.car_category_id = c.id
            WHERE s.is_active = 1
            ORDER BY c.name, s.name
        ")->fetchAll();
    }
    return $services;
}
