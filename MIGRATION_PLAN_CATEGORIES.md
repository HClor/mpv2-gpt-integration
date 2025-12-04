# 🔄 ПЛАН МИГРАЦИИ НА СИСТЕМУ КАТЕГОРИЙ (ВАРИАНТ B)

## 📅 Дата: 2025-12-04
## 🎯 Цель: Переход от ресурсов MODX к таблице modx_test_categories

---

## ⚠️ ВАЖНО ПЕРЕД НАЧАЛОМ

```bash
# Создайте backup БД!
mysqldump -u root -p lmixru_mpv2 > backup_$(date +%Y%m%d_%H%M%S).sql
```

---

## 📋 ЭТАПЫ МИГРАЦИИ

### **ЭТАП 1: Подготовка БД** ✅ ГОТОВО

**Файл:** `migrate_to_categories_system.sql`

**Действия:**
1. Добавить `category_id` в `modx_test_tests`
2. Добавить `category_id` в `modx_test_learning_materials` (если есть)
3. Создать таблицу `modx_test_category_experts`
4. Мигрировать данные: категории из parent ресурсов → category_id
5. Создать служебные категории

**Выполнить:**
```sql
-- В phpMyAdmin выполните: migrate_to_categories_system.sql
```

---

### **ЭТАП 2: Обновление API** 🔧 В РАБОТЕ

**Файлы для изменения:**

#### 1. `assets/components/testsystem/ajax/testsystem.php` ✅ ИСПРАВЛЕНО
- ✅ Метод `getAvailableTestsTree` - исправлено (использует c.name)
- 🔧 Проверить все остальные методы работы с тестами

#### 2. `assets/components/testsystem/controllers/SessionController.php`
- Проверить сохранение статистики по категориям

#### 3. `assets/components/testsystem/controllers/CategoryController.php`
- Проверить CRUD операции с категориями
- Добавить методы для работы с экспертами

---

### **ЭТАП 3: Обновление сниппетов** 📝 TODO

**Файлы для проверки:**

#### 1. `core/elements/snippets/addTestForm.php`
- Форма создания теста - должна использовать category_id
- Dropdown со списком категорий из modx_test_categories

#### 2. `core/elements/snippets/testsList.php`
- Список тестов - группировка по category_id

#### 3. `core/elements/snippets/myFavorites.php`
- Проверить отображение категорий в избранном

#### 4. `core/elements/snippets/manageCategories.php`
- Управление категориями
- Добавить UI для назначения экспертов

#### 5. `core/elements/snippets/learningMaterialsTemplate.php`
- Учебные материалы - группировка по category_id

---

### **ЭТАП 4: Обновление Services** 🛠️ TODO

**Файлы:**

1. `core/components/testsystem/services/CategoryPermissionService.php`
   - Проверка прав экспертов на категории

2. `core/components/testsystem/services/AnalyticsService.php`
   - Статистика по категориям

3. `core/components/testsystem/services/GamificationService.php`
   - Достижения по категориям

4. `core/components/testsystem/services/LearningMaterialService.php`
   - Материалы с category_id

5. `core/components/testsystem/services/ReportService.php`
   - Отчеты по категориям

---

### **ЭТАП 5: API методы для экспертов** 🆕 TODO

**Новые методы в testsystem.php:**

1. `assignCategoryExpert` - назначить эксперта на категорию
2. `removeCategoryExpert` - убрать эксперта
3. `getCategoryExperts` - список экспертов категории
4. `getUserCategories` - категории пользователя (где он эксперт)
5. `checkCategoryPermission` - проверка прав на категорию

---

### **ЭТАП 6: Обновление UI** 🎨 TODO

**Компоненты:**

1. **manageCategories** - добавить:
   - Список экспертов категории
   - Форма назначения эксперта
   - Права доступа (can_manage_tests, can_manage_questions, can_approve)

2. **addTestForm** - изменить:
   - Dropdown категорий из modx_test_categories
   - Убрать зависимость от parent ресурса

3. **testsList** - изменить:
   - Группировка по c.name вместо parent_r.pagetitle

---

## 🔍 ПРОВЕРКА ПОСЛЕ МИГРАЦИИ

### 1. БД проверка:
```sql
-- Все тесты имеют категории?
SELECT COUNT(*) as 'Тестов без категории'
FROM modx_test_tests
WHERE is_active = 1 AND category_id IS NULL;

-- Распределение по категориям
SELECT c.name, COUNT(t.id) as tests_count
FROM modx_test_categories c
LEFT JOIN modx_test_tests t ON t.category_id = c.id
GROUP BY c.id
ORDER BY c.name;
```

### 2. UI проверка:
- [ ] Создание области знаний - категории отображаются правильно
- [ ] Список тестов - группировка работает
- [ ] Создание теста - можно выбрать категорию
- [ ] Управление категориями - CRUD работает
- [ ] Назначение экспертов - форма работает

### 3. Права доступа:
- [ ] Эксперт видит только свои категории
- [ ] Эксперт может управлять тестами в своих категориях
- [ ] Админ видит все категории

---

## 📊 МЕТРИКИ УСПЕХА

- ✅ 0 тестов без category_id
- ✅ Все категории из ресурсов мигрированы
- ✅ API работает с новой системой
- ✅ UI использует modx_test_categories
- ✅ Эксперты могут управлять своими категориями

---

## 🚀 ПОРЯДОК ВЫПОЛНЕНИЯ

1. ✅ **Backup БД**
2. 🔧 **Выполнить SQL миграцию** (`migrate_to_categories_system.sql`)
3. 🔧 **Назначить категории тестам вручную** (если автомиграция не справилась)
4. 🔧 **Обновить код на сервере** (`git pull`)
5. 🔧 **Протестировать UI**
6. 🔧 **Назначить первых экспертов**
7. ✅ **Проверить все работает**

---

## 📝 СПИСОК ИЗМЕНЯЕМЫХ ФАЙЛОВ

### Уже изменено:
- [x] `assets/components/testsystem/ajax/testsystem.php` (getAvailableTestsTree)
- [x] `assets/components/testsystem/js/knowledge-areas.js` (modal backdrop fix)

### Требуют изменений:
- [ ] `assets/components/testsystem/ajax/testsystem.php` (остальные методы)
- [ ] `core/elements/snippets/addTestForm.php`
- [ ] `core/elements/snippets/testsList.php`
- [ ] `core/elements/snippets/myFavorites.php`
- [ ] `core/elements/snippets/manageCategories.php`
- [ ] `core/elements/snippets/learningMaterialsTemplate.php`
- [ ] `assets/components/testsystem/controllers/SessionController.php`
- [ ] `assets/components/testsystem/controllers/CategoryController.php`
- [ ] `core/components/testsystem/services/CategoryPermissionService.php`

### Требуют проверки:
- [ ] `core/components/testsystem/services/AnalyticsService.php`
- [ ] `core/components/testsystem/services/GamificationService.php`
- [ ] `core/components/testsystem/services/LearningMaterialService.php`

---

## 🎯 СЛЕДУЮЩИЕ ШАГИ

**ВЫ СЕЙЧАС:**

1. **Выполните SQL миграцию** в phpMyAdmin
2. **Покажите результаты:**
   - Сколько категорий создано?
   - Сколько тестов получили category_id?
   - Сколько тестов остались без категории?

**Я ЗАТЕМ:**

1. Обновлю все файлы из списка
2. Создам API для работы с экспертами
3. Обновлю UI manageCategories

---

**Готовы начать? Выполните `migrate_to_categories_system.sql` в phpMyAdmin!**
