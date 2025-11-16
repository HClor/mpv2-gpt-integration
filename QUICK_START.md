# ⚡ Быстрый старт LMS системы

## 📦 Что уже готово

✅ Все файлы на сервере
✅ База данных настроена
✅ Бэкенд API работает
✅ JavaScript и CSS подключены
✅ Безопасность настроена (CSRF)

## 🚀 3 шага до запуска

### Шаг 1: Импорт в MODX (15 минут)

#### 1.1 Шаблон
```
MODX Manager → Элементы → Шаблоны → Создать новый
Название: LMS Bootstrap 5
Файл: core/elements/templates/LMS_Bootstrap_5.tpl
```

#### 1.2 Чанки (3 шт)
```
MODX Manager → Элементы → Чанки → Создать новый

1. lmsHeader → core/elements/chunks/lmsHeader.tpl
2. menuItemTpl → core/elements/chunks/menuItemTpl.tpl
3. menuOuterTpl → core/elements/chunks/menuOuterTpl.tpl
```

#### 1.3 Сниппеты (13 шт)
```
MODX Manager → Элементы → Сниппеты → Создать новый

Основные:
1. testRunner → core/elements/snippets/testRunner.php
2. myTests → core/elements/snippets/myTests.php
3. myFavorites → core/elements/snippets/myFavorites.php
4. knowledgeAreasManager → core/elements/snippets/knowledgeAreasManager.php
5. authHandler → core/elements/snippets/authHandler.php
6. userMenu → core/elements/snippets/userMenu.php
7. userProfile → core/elements/snippets/userProfile.php
8. leaderboard → core/elements/snippets/leaderboard.php

Вспомогательные:
9. MenuWithACL → core/elements/snippets/MenuWithACL.php
10. Year → core/elements/snippets/Year.php
11. pdoCrumbs → core/elements/snippets/pdoCrumbs.php
12. csvImportForm → core/elements/snippets/csvImportForm.php
13. addTestForm → core/elements/snippets/addTestForm.php
```

### Шаг 2: Группы пользователей (5 минут)

```
MODX Manager → Безопасность → Группы доступа

1. LMS Admins (админы)
2. LMS Experts (эксперты/преподаватели)
3. LMS Students (студенты)
```

### Шаг 3: Создание страниц (10 минут)

```
MODX Manager → Ресурсы → Создать новый ресурс

Минимальная структура:

1. Вход (ID: 24)
   Контент: [[!authHandler]]
   Шаблон: LMS Bootstrap 5

2. Профиль (ID: 28)
   Контент: [[!userProfile]]
   Шаблон: LMS Bootstrap 5
   Права: только авторизованные

3. Мои тесты (ID: 36)
   Контент: [[!myTests]]
   Шаблон: LMS Bootstrap 5
   Права: только авторизованные

4. Тесты (папка, ID: 35)
   - внутри будут создаваться тесты

5. Избранное (ID: 115)
   Контент: [[!myFavorites]]
   Шаблон: LMS Bootstrap 5

6. Области знаний (ID: 125)
   Контент: [[!knowledgeAreasManager]]
   Шаблон: LMS Bootstrap 5

7. Рейтинг (ID: 34)
   Контент: [[!leaderboard]]
   Шаблон: LMS Bootstrap 5
```

## ⚙️ Системные настройки

```
MODX Manager → Система → Системные настройки → Создать новый параметр

1. Ключ: lms.auth_page
   Значение: 24 (ID страницы входа)

2. Ключ: lms.user_tests_folder
   Значение: 129 (ID папки для тестов)

3. Ключ: lms.test_template
   Значение: ID вашего шаблона LMS Bootstrap 5
```

## ✅ Проверка

### 1. Откройте страницу "Мои тесты"
- Должна отобразиться форма создания теста
- Консоль браузера (F12) без ошибок

### 2. Создайте тестового пользователя
- Добавьте в группу "LMS Experts"
- Создайте тест
- Добавьте вопросы

### 3. Пройдите тест
- Откройте созданный тест
- Запустите прохождение
- Ответьте на вопросы
- Проверьте результаты

## 🆘 Частые проблемы

### "MODX context required"
→ Используйте `[[!snippetName]]` с восклицательным знаком

### "CSRF token validation failed"
→ Очистите кеш MODX

### Не работают иконки
→ Проверьте подключение Bootstrap Icons в шаблоне

### Не отображаются вопросы
→ Проверьте что вопросы опубликованы (published = 1)

## 📚 Полная документация

Смотрите `FRONTEND_SETUP.md` для детальной инструкции.

## 🎯 Следующие шаги

После запуска:
1. Импортируйте вопросы через CSV
2. Настройте дизайн (файл: assets/components/testsystem/css/tsrunner.css)
3. Создайте категории тестов
4. Настройте права доступа

---

**Время на настройку:** ~30 минут
**Готово к продакшену:** ✅ Да
**Безопасность:** ✅ CSRF защита включена
**Responsive:** ✅ Bootstrap 5
