# Быстрый старт - LMS Frontend

## 🚀 За 5 минут

### 1. Подключите файлы в `<head>`:

```html
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="/assets/components/testsystem/css/testsystem-extended.css" rel="stylesheet">
<meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
```

### 2. Подключите нужные модули перед `</body>`:

```html
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Выберите нужные модули: -->
<script src="/assets/components/testsystem/js/learning-materials.js"></script>
<script src="/assets/components/testsystem/js/learning-paths.js"></script>
<script src="/assets/components/testsystem/js/gamification.js"></script>
<script src="/assets/components/testsystem/js/notifications.js"></script>
<!-- и т.д. -->
```

### 3. Добавьте контейнеры в HTML:

#### Для учебных материалов:
```html
<div id="materials-list-container"></div>
```

#### Для траекторий обучения:
```html
<div id="learning-paths-container"></div>
```

#### Для профиля геймификации:
```html
<div id="gamification-profile-container"></div>
```

#### Для уведомлений (в header):
```html
<div id="notifications-bell"></div>
```

### 4. Готово! 🎉

Модули автоматически инициализируются при загрузке страницы.

---

## 📁 Готовые шаблоны

В папке `templates/` найдете готовые HTML страницы:

- `materials-list.html` - Список учебных материалов
- `gamification-profile.html` - Профиль пользователя с геймификацией
- `learning-paths.html` - Траектории обучения

**Просто скопируйте и адаптируйте под свой проект!**

---

## 🔌 Необходимые API endpoints

Убедитесь, что ваш backend реализует эти endpoints в:
`/assets/components/testsystem/ajax/testsystem.php`

### Минимальный набор для старта:

```php
// Sprint 9: Материалы
case 'getMaterials':
case 'getMaterialWithBlocks':
case 'createMaterial':

// Sprint 13: Геймификация
case 'getUserGamificationProfile':
case 'getUserGamificationSummary':

// Sprint 14: Уведомления
case 'getUnreadNotificationsCount':
case 'getRecentNotifications':
```

Полный список endpoints см. в `FRONTEND_INTEGRATION.md`

---

## 🧪 Проверка работы

Откройте консоль браузера (F12) и введите:

```javascript
// Должны быть доступны:
console.log(LearningMaterials);
console.log(Gamification);
console.log(Notifications);
```

Если видите `undefined` - проверьте подключение JS файлов.

---

## ❓ Частые вопросы

**Q: Модуль не инициализируется**
A: Проверьте наличие контейнера с нужным ID в HTML

**Q: Ошибка "CSRF token missing"**
A: Добавьте `<meta name="csrf-token">` в `<head>`

**Q: Ошибка 404 на API запросы**
A: Проверьте путь к `testsystem.php` и реализацию endpoints

**Q: Можно ли использовать без MODX?**
A: Да! Модули работают с любым PHP backend, главное реализовать API

---

## 📚 Дополнительная документация

- `FRONTEND_INTEGRATION.md` - Полное руководство по интеграции
- `README.md` - Общая информация о системе
- `API_DOCUMENTATION.md` - Документация backend API

---

## 💡 Совет для начинающих

Начните с одного модуля! Например:

1. Подключите `gamification.js`
2. Добавьте виджет в header
3. Убедитесь, что работает
4. Затем добавляйте другие модули

**Не подключайте все модули сразу - используйте только то, что нужно!**

---

Удачи! 🚀
