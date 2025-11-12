#!/bin/bash
chcp 65001

sqlite3 movies_rating.db < db_init.sql

echo "1. Для каждого фильма выведите его название, год выпуска и средний рейтинг. Дополнительно добавьте столбец rank_by_avg_rating, в котором укажите ранг фильма среди всех фильмов по убыванию среднего рейтинга (фильмы с одинаковым средним рейтингом должны получить одинаковый ранг). Используйте оконную функцию RANK() или DENSE_RANK(). В результирующем наборе данных оставить 10 фильмов с наибольшим рангом."
echo -----------------
sqlite3 movies_rating.db -box -echo \
"SELECT
  m.title,
  m.year,
  t.avg_rating,
  DENSE_RANK() OVER (ORDER BY t.avg_rating DESC) AS rank_by_avg_rating
FROM movies m
JOIN (
  SELECT movie_id, AVG(rating) AS avg_rating
  FROM ratings
  GROUP BY movie_id
) t ON m.id = t.movie_id
ORDER BY t.avg_rating DESC
LIMIT 10;"

echo "2. С помощью рекурсивного CTE выделить все жанры фильмов, имеющиеся в таблице movies. Для каждого жанра рассчитать средний рейтинг avg_rating фильмов в этом жанре. Выведите genre, avg_rating и ранг жанра по убыванию среднего рейтинга, используя оконную функцию RANK()."
echo -----------------
sqlite3 movies_rating.db -box -echo \
"WITH RECURSIVE genres_cte (movie_id, genre, remaining_genres) AS (
  SELECT
    id,
    SUBSTR(genres, 1, INSTR(genres || '|', '|') -1),
    SUBSTR(genres, INSTR(genres || '|', '|') + 1)
  FROM movies
  UNION ALL
  SELECT
    movie_id,
    SUBSTR(remaining_genres, 1, INSTR(remaining_genres || '|', '|') -1),
    SUBSTR(remaining_genres, INSTR(remaining_genres || '|', '|') + 1)
  FROM genres_cte
  WHERE remaining_genres != '')
SELECT
  g.genre,
  ROUND(AVG(r.rating), 2) AS avg_rating,
  RANK() OVER (ORDER BY AVG(r.rating) DESC) as rating_rank
FROM genres_cte g
JOIN ratings r ON g.movie_id = r.movie_id
WHERE g.genre != '' AND g.genre IS NOT NULL
GROUP BY g.genre
ORDER BY avg_rating DESC;"

echo "3. Посчитайте количество фильмов в каждом жанре. Выведите два столбца: genre и movie_count, отсортировав результат по убыванию количества фильмов."
echo -----------------
sqlite3 movies_rating.db -box -echo \
"WITH RECURSIVE genres_cte (movie_id, genre, remaining_genres) AS (
  SELECT
    id,
    SUBSTR(genres, 1, INSTR(genres || '|', '|') -1),
    SUBSTR(genres, INSTR(genres || '|', '|') + 1)
  FROM movies
  UNION ALL
  SELECT
    movie_id,
    SUBSTR(remaining_genres, 1, INSTR(remaining_genres || '|', '|') -1),
    SUBSTR(remaining_genres, INSTR(remaining_genres || '|', '|') + 1)
  FROM genres_cte
  WHERE remaining_genres != '')
SELECT
  g.genre,
  COUNT(*) as movie_count
FROM genres_cte g
WHERE g.genre != ''
GROUP BY g.genre
ORDER BY movie_count DESC"

echo "4. Найдите жанры, в которых чаще всего оставляют теги (комментарии). Для этого подсчитайте общее количество записей в таблице tags для фильмов каждого жанра. Выведите genre, tag_count и долю этого жанра в общем числе тегов (tag_share), выраженную в процентах."
echo -----------------
sqlite3 movies_rating.db -box -echo \
"WITH RECURSIVE genres_cte (movie_id, genre, remaining_genres) AS (
  SELECT
    id,
    SUBSTR(genres, 1, INSTR(genres || '|', '|') -1),
    SUBSTR(genres, INSTR(genres || '|', '|') + 1)
  FROM movies
  UNION ALL
  SELECT
    movie_id,
    SUBSTR(remaining_genres, 1, INSTR(remaining_genres || '|', '|') -1),
    SUBSTR(remaining_genres, INSTR(remaining_genres || '|', '|') + 1)
  FROM genres_cte
  WHERE remaining_genres != ''
),
genre_tag_count AS (
  SELECT
    g.genre,
    COUNT(t.id) AS tag_count
  FROM genres_cte g
  JOIN tags t ON g.movie_id = t.movie_id
  WHERE g.genre != ''
  GROUP BY g.genre
)
SELECT
  gtc.genre,
  gtc.tag_count,
  ROUND(100 * gtc.tag_count / (SELECT COUNT(*) FROM tags),2)
FROM genre_tag_count gtc
ORDER BY gtc.tag_count DESC;
"

echo "5. Для каждого пользователя рассчитайте: общее количество выставленных оценок, средний выставленный рейтинг, дату первой и последней оценки (по полю timestamp в таблице ratings). Выведите user_id, rating_count, avg_rating, first_rating_date, last_rating_date. Отсортируйте результат по убыванию количества оценок и выведите только 10 первых строк."
echo -----------------
sqlite3 movies_rating.db -box -echo \
"SELECT
  user_id,
  COUNT(*) AS rating_count,
  AVG(rating) AS avg_rating,
  STRFTIME('%d.%m.%Y  %H:%M:%S',MIN(timestamp), 'unixepoch') AS first_rating_date,
  STRFTIME('%d.%m.%Y  %H:%M:%S',MAX(timestamp), 'unixepoch') AS last_rating_date
FROM ratings
GROUP BY user_id
ORDER BY rating_count DESC
LIMIT 10;
"

echo "6. Сегментируйте пользователей по типу поведения:
* «Комментаторы» — пользователи, у которых количество тегов (tags) больше количества оценок (ratings),
* «Оценщики» — наоборот, оценок больше, чем тегов,
* «Активные» — и оценок, и тегов ≥ 10,
* «Пассивные» — и оценок, и тегов < 5.
Выведите user_id, общее число оценок, общее число тегов и категорию поведения. Используйте CASE."
echo -----------------
sqlite3 movies_rating.db -box -echo \
"WITH ratings_user AS (
  SELECT
	user_id,
	COUNT(*) AS rating_count
  FROM ratings
  GROUP BY user_id
),
tags_user AS (
  SELECT
    user_id,
	COUNT(*) AS tag_count
  FROM tags
  GROUP BY user_id
)
SELECT
  u.id,
  COALESCE(ru.rating_count, 0) AS total_rating,
  COALESCE(tu.tag_count, 0) AS total_tag,
  CASE
    WHEN COALESCE(ru.rating_count, 0) >= 10 AND COALESCE(tu.tag_count, 0) >= 10 THEN 'Активные'
    WHEN COALESCE(ru.rating_count, 0) < 5 AND COALESCE(tu.tag_count, 0) < 5 THEN 'Пассивные'
    WHEN COALESCE(tu.tag_count, 0) > COALESCE(ru.rating_count, 0) THEN 'Комментаторы'
    WHEN COALESCE(ru.rating_count, 0) > COALESCE(tu.tag_count, 0) THEN 'Оценщики'
    ELSE 'Не определено'
  END AS category
FROM users u
LEFT JOIN ratings_user ru ON u.id = ru.user_id
LEFT JOIN tags_user tu ON u.id = tu.user_id
"

echo "7. Для каждого пользователя выведите его имя и последний фильм, который он оценил (по времени из ratings.timestamp). Если пользователь не оценивал ни одного фильма, он всё равно должен быть в результате (с NULL в полях фильма).
Результат: user_id, name, last_rated_movie_title, last_rating_timestamp."
echo -----------------
sqlite3 movies_rating.db -box -echo \
"WITH ratings_u AS (
  SELECT
    user_id,
    movie_id,
    STRFTIME('%d.%m.%Y %H:%M:%S', timestamp, 'unixepoch') AS timestamp,
    ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY timestamp DESC) AS rn
  FROM ratings
)
SELECT
  u.id AS user_id,
  u.name,
  m.title AS last_rated_movie_title,
  r.timestamp AS last_rating_timestamp
FROM users u
LEFT JOIN ratings_u r ON u.id = r.user_id AND r.rn = 1
LEFT JOIN movies m ON r.movie_id = m.id
ORDER BY u.id;
"
