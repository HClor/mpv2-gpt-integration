-- Проверка наличия ресурсов MODX для функционала "Области знаний"
-- Выполните этот скрипт чтобы увидеть текущие страницы

-- 1. Проверяем страницы с ID 125 и 145
SELECT
    id,
    pagetitle AS 'Название',
    alias AS 'URL-алиас',
    parent AS 'Родитель ID',
    published AS 'Опубликована',
    template AS 'Шаблон ID',
    content AS 'Содержимое (первые 200 символов)'
FROM modx_site_content
WHERE id IN (125, 145)
ORDER BY id;

-- 2. Если страницы не найдены, покажем похожие по названию
SELECT
    id,
    pagetitle AS 'Название',
    alias AS 'URL-алиас',
    parent AS 'Родитель ID',
    published AS 'Опубликована'
FROM modx_site_content
WHERE
    pagetitle LIKE '%област%' OR
    pagetitle LIKE '%знани%' OR
    pagetitle LIKE '%knowledge%' OR
    alias LIKE '%oblast%' OR
    alias LIKE '%knowledge%'
ORDER BY id;

-- 3. Покажем родительские страницы (возможно "Профиль" или "Тесты")
SELECT
    id,
    pagetitle AS 'Название',
    alias AS 'URL-алиас',
    (SELECT COUNT(*) FROM modx_site_content WHERE parent = sc.id) AS 'Дочерних страниц'
FROM modx_site_content sc
WHERE
    pagetitle LIKE '%Профил%' OR
    pagetitle LIKE '%Тест%' OR
    pagetitle LIKE '%Profile%' OR
    alias LIKE '%profile%' OR
    alias LIKE '%tests%'
ORDER BY id;

-- 4. Проверим сниппеты
SELECT
    id,
    name AS 'Название',
    category AS 'Категория',
    description AS 'Описание'
FROM modx_site_snippets
WHERE name IN ('knowledgeAreasManager', 'testRunner')
ORDER BY name;
