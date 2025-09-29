import os
import csv
import re

def generate_sql_script():
    sql_script = []
    
    # Удаление существующих таблиц
    sql_script.append("DROP TABLE IF EXISTS tags;")
    sql_script.append("DROP TABLE IF EXISTS ratings;")
    sql_script.append("DROP TABLE IF EXISTS movies;")
    sql_script.append("DROP TABLE IF EXISTS users;")
    
    # Создание таблиц
    sql_script.append("""
    CREATE TABLE movies (
        id INTEGER PRIMARY KEY,
        title TEXT,
        year INTEGER,
        genres TEXT
    );
    """)
    
    sql_script.append("""
    CREATE TABLE ratings (
        id INTEGER PRIMARY KEY,
        user_id INTEGER,
        movie_id INTEGER,
        rating REAL,
        timestamp INTEGER
    );
    """)
    
    sql_script.append("""
    CREATE TABLE tags (
        id INTEGER PRIMARY KEY,
        user_id INTEGER,
        movie_id INTEGER,
        tag TEXT,
        timestamp INTEGER
    );
    """)
    
    sql_script.append("""
    CREATE TABLE users (
        id INTEGER PRIMARY KEY,
        name TEXT,
        email TEXT,
        gender TEXT,
        register_date TEXT,
        occupation TEXT
    );
    """)
    
    # Начинаем транзакцию
    sql_script.append("BEGIN TRANSACTION;")
    
    # Для обработки апстрофа
    def escape_value(value):
        if value is None or value == '':
            return "NULL"
        return f"'{str(value).replace("'", "''")}'"
    
    # Загрузка данных из users.txt
    print("Обработка users.txt...")
    with open('users.txt', 'r', encoding='utf-8') as file:
        for line in file:
            parts = line.strip().split('|')
            if len(parts) == 6:
                values = [escape_value(part) for part in parts]
                sql_script.append(f"INSERT INTO users VALUES ({', '.join(values)});")
    
    # Загрузка данных из movies.csv
    print("Обработка movies.csv...")
    with open('movies.csv', 'r', encoding='utf-8') as file:
        reader = csv.reader(file)
        next(reader)  # Скип заголовка
        for row in reader:
            if len(row) >= 3:
                # Извлекаем год
                title_with_year = row[1]
                year_match = re.search(r'\((\d{4})\)', title_with_year)
                year = year_match.group(1) if year_match else "NULL"
                
                # Удаляем год из названия
                title = re.sub(r'\s*\(\d{4}\)\s*$', '', title_with_year)
                
                values = [escape_value(row[0]), escape_value(title), 
                          escape_value(year), escape_value(row[2])]
                sql_script.append(f"INSERT INTO movies VALUES ({', '.join(values)});")
    
    # Загрузка данных из ratings.csv
    print("Обработка ratings.csv...")
    with open('ratings.csv', 'r', encoding='utf-8') as file:
        reader = csv.reader(file)
        next(reader)  # Скип заголовка
        for row in reader:
            if len(row) >= 4:
                values = [escape_value(value) for value in row]
                sql_script.append(f"INSERT INTO ratings VALUES (NULL, {', '.join(values)});")
    
    # Загрузка данных из tags.csv
    print("Обработка tags.csv...")
    with open('tags.csv', 'r', encoding='utf-8') as file:
        reader = csv.reader(file)
        next(reader)  # Скип заголовка
        for row in reader:
            if len(row) >= 4:
                values = [escape_value(value) for value in row]
                sql_script.append(f"INSERT INTO tags VALUES (NULL, {', '.join(values)});")
    
    # Завершаем транзакцию
    sql_script.append("COMMIT;")
    
    # Запись SQL скрипта в файл
    print("Запись SQL-скрипта...")
    with open('db_init.sql', 'w', encoding='utf-8') as file:
        file.write('\n'.join(sql_script))
    
    print("db_init.sql создан!")

if __name__ == '__main__':
    generate_sql_script()