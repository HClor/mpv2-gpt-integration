# 📁 ПАПКА ДОКУМЕНТАЦИИ И УТИЛИТ

**Путь:** `/home/user/mpv2-gpt-integration/docs/`

Все файлы находятся в **проектной папке** (не во временной `/tmp/`)

---

## 📋 ФАЙЛЫ В ЭТОЙ ПАПКЕ

### 1. **modx_lms_export_queries.sql** (8.3 KB)
```
📍 Полный путь: /home/user/mpv2-gpt-integration/docs/modx_lms_export_queries.sql
```

**Назначение:** SQL запросы для экспорта структуры и данных БД

**Содержит:**
- ✅ Запросы для всех таблиц MODX (users, resources, groups)
- ✅ Запросы для всех 51 таблицы LMS системы
- ✅ Примеры данных (первые 10 записей из каждой таблицы)
- ✅ Информацию о структуре, индексах, Foreign Keys
- ✅ Команды mysqldump для экспорта

**Как использовать:**
```bash
# Вариант 1: В phpMyAdmin
# 1. Откройте phpMyAdmin → выберите базу данных
# 2. Перейдите на вкладку SQL
# 3. Скопируйте содержимое этого файла
# 4. Выполните запросы

# Вариант 2: Из командной строки
mysql -u root -p your_database < modx_lms_export_queries.sql

# Вариант 3: Отдельные запросы
mysql -u root -p -e "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='your_database'"
```

---

### 2. **detailed_modx_analysis.sql** (12 KB)
```
📍 Полный путь: /home/user/mpv2-gpt-integration/docs/detailed_modx_analysis.sql
```

**Назначение:** Подробные SQL запросы для анализа всех аспектов БД

**Содержит:**
- ✅ Информация о ресурсах (pages) и используемых сниппетах
- ✅ Информация о пользователях и ролях
- ✅ Полная структура всех таблиц LMS (SHOW CREATE TABLE)
- ✅ Анализ связей между таблицами (relationships, FK)
- ✅ Анализ индексов и производительности
- ✅ Анализ размера и использования БД
- ✅ Примеры реальных запросов которые приложение выполняет

**Как использовать:**
```bash
# Самый полный анализ БД
mysql -u root -p your_database < detailed_modx_analysis.sql > analysis_results.txt

# Отдельные части
mysql -u root -p your_database << EOF
-- Получить размер каждой таблицы
SELECT TABLE_NAME, ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) as 'Size_MB'
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC;
EOF
```

---

### 3. **export_modx_lms_db.sh** (5.1 KB, исполняемый)
```
📍 Полный путь: /home/user/mpv2-gpt-integration/docs/export_modx_lms_db.sh
```

**Назначение:** Bash скрипт для автоматического экспорта БД

**Содержит:**
- ✅ Автоматический экспорт структуры БД
- ✅ Автоматический экспорт с данными
- ✅ Извлечение описания таблиц
- ✅ Примеры данных из основных таблиц

**Создает файлы:**
```
database_structure.sql      - только структура (без данных)
database_with_data.sql      - структура + полные данные
table_descriptions.txt      - описание таблиц и их размеры
sample_data.txt             - примеры данных из каждой таблицы
```

**Как использовать:**
```bash
# 1. Перейдите в папку
cd /home/user/mpv2-gpt-integration/docs/

# 2. Запустите скрипт
./export_modx_lms_db.sh modx root password localhost

# Параметры:
# $1 - имя БД (обязательно)           → modx
# $2 - пользователь (обязательно)     → root
# $3 - пароль (обязательно)           → password
# $4 - хост (опционально)             → localhost (по умолчанию)

# Примеры:
./export_modx_lms_db.sh modx root password
./export_modx_lms_db.sh modx www-data mypass 192.168.1.10
./export_modx_lms_db.sh testsystem admin secret modx.local

# 3. Результаты будут в текущей папке
ls -la *.sql *.txt
```

**Что происходит:**
```
[1/4] Экспортирование структуры БД (без данных)...
✓ Сохранено: database_structure.sql

[2/4] Экспортирование структуры + данные...
✓ Сохранено: database_with_data.sql

[3/4] Получение описания таблиц и их размеров...
✓ Сохранено: table_descriptions.txt

[4/4] Получение примеров данных из таблиц...
✓ Сохранено: sample_data.txt
```

---

### 4. **LMS_FULL_SETUP_GUIDE.md** (19 KB)
```
📍 Полный путь: /home/user/mpv2-gpt-integration/docs/LMS_FULL_SETUP_GUIDE.md
```

**Назначение:** Полное руководство по запуску LMS системы на MODX Revo

**Содержит:**
- ✅ Статус всех 17 спринтов (100% завершены)
- ✅ Системные требования
- ✅ Описание всех компонентов (backend и frontend)
- ✅ Быстрая установка (15-30 минут)
- ✅ Описание всех 51 таблиц БД с примерами
- ✅ Конфигурация системы
- ✅ Описание всех 32 сниппетов
- ✅ Примеры страниц и URL
- ✅ SQL запросы для проверки
- ✅ Устранение проблем (troubleshooting)
- ✅ Чеклист полной установки

**Как использовать:**
```bash
# Просмотреть в консоли
cat LMS_FULL_SETUP_GUIDE.md

# Или открыть в редакторе
nano LMS_FULL_SETUP_GUIDE.md
vim LMS_FULL_SETUP_GUIDE.md

# Или скопировать на локальный компьютер для чтения
scp user@server:/home/user/mpv2-gpt-integration/docs/LMS_FULL_SETUP_GUIDE.md ~/
```

---

### 5. **DOCUMENTATION_CLEANUP_RECOMMENDATIONS.md** (12 KB)
```
📍 Полный путь: /home/user/mpv2-gpt-integration/docs/DOCUMENTATION_CLEANUP_RECOMMENDATIONS.md
```

**Назначение:** Рекомендации по удалению устаревшей документации

**Содержит:**
- ✅ Анализ всех 23 файлов документации на main ветке
- ✅ Список файлов для удаления (с причинами)
- ✅ Файлы для оставления
- ✅ Таблица с решениями
- ✅ Команды git для удаления
- ✅ Рекомендации по долгосрочной организации документации

**Как использовать:**
```bash
# Прочитать рекомендации
cat DOCUMENTATION_CLEANUP_RECOMMENDATIONS.md

# Выполнить рекомендации на main ветке
git checkout main
rm AUDIT_*.md DEPLOY_INSTRUCTIONS.md READINESS_AUDIT.md
git add -A
git commit -m "Remove outdated audit documents"
git push origin main
```

---

## 🚀 ПРИМЕРЫ ИСПОЛЬЗОВАНИЯ

### Пример 1: Получить дамп структуры БД
```bash
cd /home/user/mpv2-gpt-integration/docs/
./export_modx_lms_db.sh modx root password
cat database_structure.sql | head -100
```

### Пример 2: Просмотреть примеры данных
```bash
cd /home/user/mpv2-gpt-integration/docs/
./export_modx_lms_db.sh modx root password
cat sample_data.txt
```

### Пример 3: Выполнить анализ таблиц
```bash
cd /home/user/mpv2-gpt-integration/docs/
mysql -u root -p modx < detailed_modx_analysis.sql > analysis_report.txt
cat analysis_report.txt
```

### Пример 4: Загрузить дамп на другой сервер
```bash
# На исходном сервере
cd /home/user/mpv2-gpt-integration/docs/
./export_modx_lms_db.sh modx root password
gzip database_with_data.sql

# Скопировать на новый сервер
scp database_with_data.sql.gz user@new-server:/tmp/

# На новом сервере
ssh user@new-server
cd /tmp
gunzip database_with_data.sql.gz
mysql -u root -p new_database < database_with_data.sql
```

---

## 📊 СТРУКТУРА ПРОЕКТА

```
/home/user/mpv2-gpt-integration/
├── docs/                              ← ВЫ ЗДЕСЬ
│   ├── README.md                      ← Этот файл
│   ├── modx_lms_export_queries.sql    ← SQL запросы
│   ├── detailed_modx_analysis.sql     ← Подробный анализ
│   ├── export_modx_lms_db.sh          ← Bash скрипт (исполняемый)
│   ├── LMS_FULL_SETUP_GUIDE.md        ← Полное руководство
│   └── DOCUMENTATION_CLEANUP_RECOMMENDATIONS.md
│
├── core/components/testsystem/        ← Backend компоненты
│   ├── bootstrap.php
│   ├── services/                      ← 14 сервисов
│   ├── repositories/
│   ├── controllers/
│   ├── sql/                           ← SQL миграции
│   └── ...
│
├── assets/components/testsystem/      ← Frontend компоненты
│   ├── ajax/testsystem.php
│   ├── controllers/
│   ├── templates/
│   ├── css/
│   └── ...
│
├── QUICKSTART.md                      ← Быстрый старт
├── IMPLEMENTATION_GUIDE.md            ← Внедрение архитектуры
├── PRODUCTION_READY_REPORT.md         ← Отчет о готовности
└── ...
```

---

## 💡 ПОЛЕЗНЫЕ КОМАНДЫ

```bash
# Перейти в папку с файлами
cd /home/user/mpv2-gpt-integration/docs/

# Список всех файлов
ls -lah

# Посмотреть размер каждого файла
du -h *

# Выполнить SQL скрипт
mysql -u root -p my_database < modx_lms_export_queries.sql

# Запустить bash скрипт для экспорта
./export_modx_lms_db.sh modx root password localhost

# Поискать информацию в файлах
grep -r "table" . | head -20

# Посмотреть содержимое с нумерацией строк
cat -n modx_lms_export_queries.sql | head -50
```

---

## ✅ ГОТОВО!

Все файлы находятся в `/home/user/mpv2-gpt-integration/docs/`

Вы можете использовать их для:
- 📊 Анализа структуры БД
- 💾 Экспорта данных
- 🚀 Запуска LMS системы
- 📋 Организации документации

**Начните с чтения `LMS_FULL_SETUP_GUIDE.md` для полного понимания системы!** 🎉
