# 🗺️ КАРТА ЗАВИСИМОСТЕЙ КОМПОНЕНТОВ

**Дата:** 2025-11-13
**Проект:** MPV2 Test System (MODX REVO)

---

## 📦 АРХИТЕКТУРА СИСТЕМЫ

```
┌─────────────────────────────────────────────────────────┐
│                   MODX FRONTEND                         │
│  (Templates: base.tpl, LMS_Bootstrap_5.tpl)             │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│              SNIPPETS (31 шт.)                          │
│  - Рендеринг HTML                                       │
│  - Бизнес-логика                                        │
│  - SQL запросы                                          │
└────┬────────────────────────────────────────┬───────────┘
     │                                        │
     ▼                                        ▼
┌──────────────────┐              ┌─────────────────────┐
│  AJAX API        │◄─────────────┤  JAVASCRIPT         │
│  testsystem.php  │              │  - tsrunner.js      │
│  (3000+ строк)   │              │  - mytests.js       │
│                  │              │  - knowledge-*.js   │
└────────┬─────────┘              └─────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│                   DATABASE                              │
│  12 таблиц тестовой системы + MODX таблицы              │
└─────────────────────────────────────────────────────────┘
```

---

## 🔗 ГРУППЫ СНИППЕТОВ ПО ФУНКЦИОНАЛЬНОСТИ

### 1️⃣ **ЯДРО ТЕСТОВОЙ СИСТЕМЫ** (Test Core)

#### `testRunner.php` (733 строки) ⭐ ГЛАВНЫЙ
**Назначение:** Отображение и прохождение тестов
**Зависимости:**
- БД: `test_tests`, `test_questions`, `test_knowledge_areas`, `test_permissions`
- JS: `tsrunner.js` (обязательно)
- CSS: `tsrunner.css`
- Внешние: Quill.js (редактор)

**Вызывается на страницах:**
- Все страницы тестов (по resource_id)
- Области знаний (GET параметр `knowledge_area`)

**Основные режимы:**
1. Обычный тест (по resource_id)
2. Область знаний (knowledge_area)
3. Режим редактирования (для админов/экспертов)

---

#### `csvImportForm.php` (482 строки)
**Назначение:** Импорт вопросов из CSV/Excel
**Зависимости:**
- Composer: `phpoffice/phpspreadsheet`
- БД: `test_tests`, `test_questions`, `test_answers`, `test_permissions`

**Связи:**
- Вызывается из `testRunner.php` (кнопка "Импорт CSV")
- Вызывается из `addTestForm.php` (после создания теста)

---

#### `myTests.php`
**Назначение:** Список тестов пользователя
**Зависимости:**
- БД: `test_tests`, `test_sessions`
- Chunks: используется getChunk

---

#### `testsList.php`
**Назначение:** Публичный список тестов
**Зависимости:**
- БД: `test_tests`, `test_categories`, `test_questions`

---

### 2️⃣ **УПРАВЛЕНИЕ КОНТЕНТОМ** (Content Management)

#### `addTestForm.php` (391 строка)
**Назначение:** Создание новых тестов
**Зависимости:**
- БД: `test_tests`, `site_content` (создает ресурс MODX)
- Проверка прав: LMS Admins, LMS Experts
- Redirect: на страницу импорта после создания

**Связи:**
- → `csvImportForm.php` (редирект после создания)
- → `testRunner.php` (создает страницу для теста)

---

#### `manageCategories.php` (314 строк)
**Назначение:** CRUD категорий тестов
**Зависимости:**
- БД: `test_categories`, `test_tests`
- AJAX: обработка POST запросов

**Связи:**
- Используется в админ-панели

---

#### `knowledgeAreasManager.php` (243 строки)
**Назначение:** Управление областями знаний
**Зависимости:**
- БД: `test_knowledge_areas`, `test_tests`
- AJAX: обработка CRUD операций

**Связи:**
- ← `testRunner.php` (использует созданные области)

---

### 3️⃣ **ПОЛЬЗОВАТЕЛИ И АВТОРИЗАЦИЯ** (User Management)

#### `authHandler.php` (229 строк)
**Назначение:** Обработка входа/регистрации
**Зависимости:**
- БД: `users`, `user_attributes`
- ВАЖНО: Есть маркер проблемы безопасности

**Связи:**
- Точка входа для всех пользователей

---

#### `userProfile.php` (234 строки)
**Назначение:** Профиль пользователя
**Зависимости:**
- БД: `users`, `user_attributes`, `test_sessions`

---

#### `manageUsers.php` (267 строк)
**Назначение:** Управление пользователями (админ)
**Зависимости:**
- БД: `users`, `member_groups`
- Роли: только LMS Admins

---

#### `getUserRights.php`
**Назначение:** API проверки прав доступа
**Зависимости:**
- БД: `member_groups`, `membergroup_names`

---

### 4️⃣ **СТАТИСТИКА И ЛИДЕРБОРДЫ** (Analytics)

#### `leaderboard.php` (269 строк)
**Назначение:** Основной лидерборд
**Зависимости:**
- БД: `test_user_stats`, `users`, `user_attributes`
- Алгоритм: расчет серий (streaks)

**Связи:**
- Получает агрегированные данные

---

#### `leaderboardCompact.php`
**Назначение:** Компактная версия лидерборда
**Зависимости:**
- БД: `test_user_stats`

---

#### `leaderboardCategories.php`
**Назначение:** Лидерборд по категориям
**Зависимости:**
- БД: `test_category_stats`, `test_categories`

---

#### `getSystemStats.php`
**Назначение:** Общая статистика системы
**Зависимости:**
- БД: `test_tests`, `test_questions`, `test_categories`, `test_sessions`

---

#### `getUserStats.php`
**Назначение:** Статистика конкретного пользователя
**Зависимости:**
- БД: `test_sessions`, `test_user_answers`

---

### 5️⃣ **УТИЛИТЫ И ХЕЛПЕРЫ** (Utilities)

#### `myFavorites.php` (448 строк)
**Назначение:** Избранные тесты
**Зависимости:**
- БД: `test_favorites`, `test_tests`
- JS: `tsrunner.js` (режим favorites)

---

#### `categoriesAndTests.php` (157 строк)
**Назначение:** Навигация по категориям
**Зависимости:**
- БД: `test_categories`, `test_tests`, `test_questions`
- Chunks: используется

---

#### `MenuWithACL.php` (141 строка)
**Назначение:** Меню с проверкой прав
**Зависимости:**
- MODX: проверка ресурсов

---

#### `userMenu.php`
**Назначение:** Персональное меню пользователя

---

#### `getLearningResourceIds.php` (250 строк)
**Назначение:** ID ресурсов обучающих материалов
**Зависимости:**
- БД: `test_tests`, `test_questions`, `test_favorites`

---

#### `learningMaterials.php` (185 строк)
**Назначение:** Обучающие материалы

---

### 6️⃣ **ВОССТАНОВЛЕНИЕ ПАРОЛЯ** (Password Recovery)

#### `forgotPasswordHandler.php`
**Назначение:** Восстановление пароля

---

#### `resetPasswordHandler.php`
**Назначение:** Сброс пароля

---

#### `activateAccount.php`
**Назначение:** Активация аккаунта

---

### 7️⃣ **ПРОЧИЕ** (Misc)

#### `getTestCategories.php`
**Назначение:** API список категорий

---

#### `getTestInfoBatch.php`
**Назначение:** Пакетная загрузка информации о тестах

---

#### `getTopUsers.php`
**Назначение:** Топ пользователей

---

#### `categoriesList.php`
**Назначение:** Список категорий

---

#### `manageUsersRoleDetector.php`
**Назначение:** Определение роли пользователя

---

#### `phpthumbon.php`
**Назначение:** Генерация миниатюр изображений

---

## 🌐 AJAX API (testsystem.php)

**Расположение:** `/assets/components/testsystem/ajax/testsystem.php`
**Размер:** ~3000+ строк

### Действия (Actions):

#### Тестирование:
- `getTestInfo` - Информация о тесте
- `startTest` - Начать тест (создает session)
- `getNextQuestion` - Следующий вопрос
- `submitAnswer` - Отправить ответ
- `finishTest` - Завершить тест

#### Управление вопросами:
- `saveQuestion` - Сохранить вопрос (create/update)
- `getQuestion` - Получить вопрос
- `deleteQuestion` - Удалить вопрос
- `togglePublish` - Опубликовать/снять с публикации
- `toggleLearning` - Переключить режим обучения

#### Управление тестами:
- `updateTestSettings` - Обновить настройки теста
- `deleteTest` - Удалить тест
- `cloneTest` - Клонировать тест

#### Избранное:
- `toggleFavorite` - Добавить/удалить из избранного
- `getFavorites` - Получить избранное

#### Области знаний:
- `getKnowledgeAreas` - Список областей
- `saveKnowledgeArea` - Сохранить область
- `deleteKnowledgeArea` - Удалить область

#### Права доступа:
- `checkEditRights` - Проверка прав редактирования
- `getTestPermissions` - Получить права доступа
- `updatePermissions` - Обновить права

#### Статистика:
- `getUserTestHistory` - История пользователя
- `getDetailedResults` - Детальные результаты

**Зависимости:**
- Все frontend JS файлы

---

## 📱 JAVASCRIPT МОДУЛИ

### `tsrunner.js` (главный)
**Размер:** ~2500 строк
**Назначение:** Логика прохождения тестов

**API вызовы:**
- `startTest`
- `getNextQuestion`
- `submitAnswer`
- `saveQuestion` (редактирование)
- `checkEditRights`

**Режимы работы:**
1. Обычный тест (training/exam)
2. Область знаний
3. Режим обучения (learning view)
4. Избранное (favorites view)

---

### `mytests.js`
**Назначение:** Управление тестами пользователя

---

### `knowledge-areas.js`
**Назначение:** UI для областей знаний

---

## 🔄 КРИТИЧЕСКИЕ ЦЕПОЧКИ ЗАВИСИМОСТЕЙ

### Цепочка 1: Создание и прохождение теста
```
addTestForm.php
    ↓ (создает test_id + resource_id)
csvImportForm.php
    ↓ (импортирует вопросы)
testRunner.php
    ↓ (отображает тест)
tsrunner.js
    ↓ (AJAX вызовы)
testsystem.php
    ↓ (обработка логики)
DATABASE (сохранение результатов)
```

### Цепочка 2: Прохождение теста пользователем
```
testsList.php / categoriesAndTests.php
    ↓ (выбор теста)
testRunner.php
    ↓ (проверка прав доступа)
    ↓ (загрузка tsrunner.js)
tsrunner.js → startTest()
    ↓ (AJAX: startTest)
testsystem.php → createSession()
    ↓
tsrunner.js → getNextQuestion()
    ↓ (AJAX: getNextQuestion)
testsystem.php → loadRandomQuestion()
    ↓
User → submitAnswer()
    ↓ (AJAX: submitAnswer)
testsystem.php → validateAnswer()
    ↓ (save to test_user_answers)
    ↓ (update test_sessions)
    ↓ (update test_user_stats)
showResults()
```

### Цепочка 3: Области знаний
```
knowledgeAreasManager.php
    ↓ (CRUD областей)
testsystem.php (saveKnowledgeArea)
    ↓ (сохраняет test_knowledge_areas)
testRunner.php?knowledge_area=X
    ↓ (загружает вопросы из нескольких тестов)
tsrunner.js (knowledge area mode)
    ↓ (случайная выборка вопросов)
```

---

## ⚠️ ПРОБЛЕМЫ ЗАВИСИМОСТЕЙ

### 🔴 Критические:
1. **Циклическая зависимость:** `testRunner.php` ↔ `testsystem.php` (shared logic)
2. **Монолит:** `testsystem.php` — 3000+ строк, обрабатывает 30+ actions
3. **Жесткая связанность:** JS напрямую вызывает AJAX URL (hardcoded)
4. **Дублирование проверки прав:** Код повторяется в 10+ файлах

### 🟡 Средние:
1. **Отсутствие слоя сервисов:** Бизнес-логика размазана по сниппетам
2. **Нет единой точки входа:** 31 сниппет работают независимо
3. **Хардкоженные ID страниц:** `$TESTS_ROOT_ID = 35` в коде
4. **Отсутствие автозагрузки:** Нет PSR-4, composer autoload не используется

### 🟢 Низкие:
1. Отсутствие документации API
2. Нет версионирования API
3. Нет rate limiting для AJAX

---

## 💡 РЕКОМЕНДАЦИИ ПО РЕФАКТОРИНГУ

### Немедленно:
1. ✅ Выделить **AccessService** (проверка прав)
2. ✅ Создать **ApiRouter** (замена монолитного testsystem.php)
3. ✅ Вынести хардкоженные ID в конфиг

### Краткосрочно:
1. Разбить `testsystem.php` на контроллеры по доменам:
   - TestController
   - QuestionController
   - SessionController
   - FavoritesController
   - KnowledgeAreaController
2. Создать сервисный слой (Service Layer)
3. Внедрить Repository Pattern

### Долгосрочно:
1. Миграция на REST API с версионированием
2. Внедрение DI Container
3. Разделение frontend и backend (SPA подход)

---

**Файл создан автоматически в рамках Этапа 1 аудита кода.**
