<?php

define('DB_PATH', __DIR__ . '/../data/carwash.db');

function getDbConnection() {
    try {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die('Ошибка подключения к базе данных: ' . $e->getMessage());
    }
}

function initDatabase() {
    $dbFile = DB_PATH;
    $sqlFile = __DIR__ . '/../data/db_init.sql';

    if (!file_exists($dbFile) && file_exists($sqlFile)) {
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = file_get_contents($sqlFile);
        $pdo->exec($sql);
    }
}

initDatabase();
