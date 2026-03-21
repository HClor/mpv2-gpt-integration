# Аудит CSRF — PHP/JS поверхность атаки

Дата аудита: 2026-03-21

## Объем проверки

Проверены:
- PHP-обработчики форм и AJAX-эндпоинтов в `core/elements/snippets/`, `assets/components/testsystem/ajax/`, `assets/components/testsystem/controllers/`, `core/components/testsystem/security/`.
- JS-файлы фронтенда в `assets/components/testsystem/js/`, которые отправляют state-changing запросы.

## Методика

1. Найдены все PHP и JS файлы, где есть `POST`, `fetch()`, `$_POST`, `action`, `csrf`, `X-CSRF-Token`.
2. Сопоставлены frontend-вызовы с backend-обработчиками.
3. Смоделированы роли пользователей и сценарии принудительных действий через браузер жертвы.
4. Отдельно проверены:
   - наличие и отсутствие CSRF-проверки;
   - обходы через `GET` для state-changing действий;
   - ошибочно освобожденные от CSRF `action` в `testsystem.php`;
   - legacy/form snippets вне общего AJAX-шлюза.

## Модель ролей

В ходе аудита использовалась следующая модель ролей:
- **Гость** — неавторизованный пользователь.
- **Студент** — обычный web-пользователь.
- **Эксперт** — пользователь из группы `LMS Experts`, имеющий право редактирования части сущностей.
- **Администратор** — пользователь из группы `LMS Admins`, а также специальные ID `1` и `2` в helper-логике.
- **Владелец теста / shared editor** — не обязательно админ/эксперт, но пользователь с правом `canEdit` на конкретный тест.
- **Админ/эксперт категории** — роли, выдаваемые логикой category permissions.

## Общая оценка

В проекте есть **базовая централизованная CSRF-защита**, реализованная в `CsrfProtection` и подключенная в большинстве форм и AJAX-запросов. Однако обнаружены несколько критичных исключений и обходов, из-за которых защита **не является цельной**.

### Что защищено хорошо
- Большинство JS-модулей передают `csrf_token` или `X-CSRF-TOKEN`.
- Основной AJAX-шлюз `assets/components/testsystem/ajax/testsystem.php` выполняет CSRF-проверку для большинства POST-запросов.
- Класс `CsrfProtection` использует серверную сессию, срок жизни токена и `hash_equals`.
- Критичные формы авторизации, регистрации, добавления тестов, импорта CSV, reset/forgot password, категорий и части профиля проверяют CSRF.

### Что сломано концептуально
- Есть **state-changing действия, намеренно выведенные из CSRF-проверки**.
- Есть **изменяющие состояние операции, доступные через GET**, что позволяет обходить CSRF-контроль полностью.
- Есть **админские HTML-формы вообще без CSRF-поля и без проверки**.
- Есть **logout CSRF**, где защита либо отсутствует, либо только логируется, но не блокирует выполнение.

---

## Ключевые находки

## 1. Критично: `manageUsers.php` позволяет выполнять админские действия без CSRF

**Файл:** `core/elements/snippets/manageUsers.php`

### Что происходит
Сниппет обрабатывает POST-действия:
- `change_role`
- `toggle_block`
- `create_user`

Но в нем **нет**:
- `CsrfProtection::validateRequest(...)`
- hidden CSRF field в формах
- альтернативной собственной CSRF-проверки

### Почему это опасно
Если администратор авторизован в web-контексте, злоумышленник может заставить его браузер выполнить POST на страницу управления пользователями и:
- изменить роль пользователя;
- заблокировать/разблокировать пользователя;
- создать нового пользователя с произвольной ролью.

### Моделирование по ролям
- **Гость**: не эксплуатирует напрямую, но может подготовить вредоносную страницу.
- **Студент**: может атаковать администратора, если знает URL страницы.
- **Эксперт**: аналогично может атаковать администратора.
- **Администратор-жертва**: получает принудительное выполнение опасных действий со своими привилегиями.

### Риск
**Критический / High** — возможен полный административный impact через CSRF.

### Минимальный PoC-сценарий
Авто-submit формы в скрытом iframe/странице на внешний сайт с полями `create_user`, `username`, `email`, `password`, `role=admin`.

---

## 2. Критично: `assignCategoryExpert` и `removeCategoryExpert` ошибочно выведены из CSRF-проверки

**Файлы:**
- `assets/components/testsystem/ajax/testsystem.php`
- `assets/components/testsystem/controllers/CategoryController.php`
- клиентский вызов: `assets/components/testsystem/js/category-experts.js`

### Что происходит
В `testsystem.php` действия:
- `assignCategoryExpert`
- `removeCategoryExpert`

включены в `$csrfExemptActions`, хотя это **изменяющие состояние** операции. При этом сами методы контроллера:
- требуют авторизацию;
- требуют admin-права;
- **не компенсируют отсутствие CSRF своей внутренней проверкой**.

### Почему это опасно
Если администратор LMS откроет вредоносную страницу, злоумышленник может от его имени:
- назначить эксперта на категорию;
- снять эксперта с категории;
- поменять набор полномочий (`can_manage_tests`, `can_manage_questions`, `can_approve`).

Это уже не просто nuisance: это изменение матрицы доступа и косвенное расширение прав для дальнейших действий.

### Моделирование по ролям
- **Студент / внешний атакующий**: может атаковать администратора.
- **Эксперт**: тоже может атаковать администратора.
- **Администратор-жертва**: выполняет назначение/отзыв прав без ведома.

### Риск
**Высокий / High** — несанкционированное управление доступом.

---

## 3. Критично: `deleteQuestion` можно вызвать через GET, обходя CSRF вообще

**Файлы:**
- `assets/components/testsystem/ajax/testsystem.php`
- `assets/components/testsystem/controllers/QuestionController.php`

### Что происходит
В `testsystem.php` есть специальный разбор:
- если `action=deleteQuestion` и в `$_GET` есть `question_id`, то он пробрасывается в `$data`.

CSRF-проверка выполняется **только для POST**, а GET-запросы в этом шлюзе не проверяются.

Далее `QuestionController::deleteQuestion()` выполняет удаление вопроса и связанных ответов.

### Почему это опасно
Это прямой обход проектной модели защиты:
- запрос не обязан быть POST;
- токен не нужен;
- достаточно, чтобы жертва имела права на редактирование теста.

В зависимости от SameSite-поведения браузера, атака особенно реалистична через:
- top-level navigation по ссылке;
- `window.location` / `<a href>` / редиректы;
- иногда через другие кросс-сайтовые сценарии, если cookie-конфигурация ослаблена.

### Моделирование по ролям
- **Студент с delegated edit rights**: может быть жертвой.
- **Владелец теста / shared editor**: основной класс жертв.
- **Эксперт**: тоже жертва, если редактирует тесты.
- **Администратор**: также уязвим.

### Риск
**Критический / High** — удаление контента через GET без CSRF.

---

## 4. Высокий риск: отладочные действия `debugPathProgress` и `testCompleteStep` доступны без CSRF, включая GET

**Файлы:**
- `assets/components/testsystem/ajax/testsystem.php`
- `assets/components/testsystem/controllers/LearningPathController.php`

### Что происходит
Оба action включены в `$csrfExemptActions`.
Дополнительно `testsystem.php` явно читает параметры `path_id`, `step_id`, `progress_id` из `$_GET` для этих действий.

При этом:
- `debugPathProgress` может при `init=1` инициализировать progress-записи;
- `testCompleteStep` выполняет `UPDATE ... SET status = 'completed' ...`.

То есть это **не read-only**, несмотря на exemption.

### Почему это опасно
Даже если это “временная диагностика”, эти действия доступны в production-коде. Авторизованного пользователя можно заставить:
- создать/инициализировать прогресс;
- пометить шаги траектории как завершенные;
- исказить learning-path аналитику и состояние обучения.

### Моделирование по ролям
- **Студент**: может стать жертвой и получить ложный прогресс.
- **Эксперт/админ**: тоже уязвимы, если участвуют в траекториях.

### Риск
**Высокий / High** — компрометация учебного прогресса и данных.

---

## 5. Средний риск: `userMenu.php` реализует logout без какой-либо CSRF-защиты

**Файл:** `core/elements/snippets/userMenu.php`

### Что происходит
Сниппет обрабатывает POST `login_logout`, удаляет web-session и редиректит пользователя.
Ни hidden CSRF field, ни серверной проверки нет.

### Почему это опасно
Это классический **logout CSRF**.
Последствия обычно ограничены разлогином, но возможны:
- прерывание сессии во время работы;
- UX-деградация;
- маскировка более сложных атак.

### Риск
**Средний / Medium**.

---

## 6. Средний риск: `logoutHandler.php` формально проверяет CSRF, но при провале все равно выполняет logout

**Файл:** `core/elements/snippets/logoutHandler.php`

### Что происходит
При невалидном токене код только пишет warning в лог и **все равно удаляет web-session**.

### Итог
С точки зрения атакующего это фактически такой же logout CSRF, только с логированием.

### Риск
**Средний / Medium**.

---

## 7. Низкий/архитектурный риск: смешение нескольких CSRF-механизмов

**Файлы:**
- `core/components/testsystem/security/CsrfProtection.php`
- `core/elements/snippets/userProfile.php`

### Что происходит
Основная система использует `csrf_token` через `CsrfProtection`, а `userProfile.php` использует отдельный session-ключ `$_SESSION['csrf']` и поле `csrf`.

### Почему это важно
Это не дает immediate bypass в самом `userProfile.php`, но:
- увеличивает шанс ошибок при рефакторинге;
- ломает единообразие;
- затрудняет централизованный аудит.

### Риск
**Низкий / Low**, но требует унификации.

---

## JS-аудит: какие файлы отправляют state-changing запросы

Ниже перечислены основные JS/inline-JS точки, отправляющие мутационные запросы. В большинстве случаев frontend **передает токен корректно**, и проблема находится не в JS, а в backend-исключениях.

### JS-файлы, где CSRF передается корректно
- `assets/components/testsystem/js/tsrunner.js`
- `assets/components/testsystem/js/learning-materials.js`
- `assets/components/testsystem/js/gamification.js`
- `assets/components/testsystem/js/test-permissions.js`
- `assets/components/testsystem/js/mytests.js`
- `assets/components/testsystem/js/knowledge-areas.js`
- `assets/components/testsystem/js/certificates.js`
- `assets/components/testsystem/js/analytics.js`
- `assets/components/testsystem/js/learning-paths.js`
- `assets/components/testsystem/js/category-experts.js`
- `assets/components/testsystem/js/test-cards.js`
- `assets/components/testsystem/js/category-permissions.js`
- `assets/components/testsystem/js/notifications.js`

### Важное замечание по JS
Даже когда JS прикладывает `csrf_token`, это **не спасает**, если backend:
- освобождает action от CSRF-проверки;
- принимает мутацию по GET;
- не делает server-side validation в HTML form snippet.

Именно это и происходит в найденных high-risk сценариях.

---

## PHP-аудит: проверенные state-changing обработчики

### Выглядят защищенными CSRF
- `core/elements/snippets/authHandler.php`
- `core/elements/snippets/manageCategories.php`
- `core/elements/snippets/addTestForm.php`
- `core/elements/snippets/csvImportForm.php`
- `core/elements/snippets/forgotPasswordHandler.php`
- `core/elements/snippets/resetPasswordHandler.php`
- `core/elements/snippets/userProfile.php` (собственный механизм)
- `assets/components/testsystem/ajax/upload-image.php`
- `assets/components/testsystem/ajax/upload-document.php`
- основная часть POST-action в `assets/components/testsystem/ajax/testsystem.php`

### Выявлены как уязвимые или архитектурно опасные
- `core/elements/snippets/manageUsers.php`
- `core/elements/snippets/userMenu.php`
- `core/elements/snippets/logoutHandler.php`
- `assets/components/testsystem/ajax/testsystem.php`
- `assets/components/testsystem/controllers/QuestionController.php` (через GET-маршрут `deleteQuestion`)
- `assets/components/testsystem/controllers/CategoryController.php` (через exempt actions)
- `assets/components/testsystem/controllers/LearningPathController.php` (через debug/test actions)

---

## Рекомендации по исправлению

## Priority 0 — срочно
1. **Добавить CSRF-проверку в `manageUsers.php`**.
   - На все POST-ветки.
   - Во все формы добавить `CsrfProtection::getTokenField()`.
2. **Убрать `assignCategoryExpert` и `removeCategoryExpert` из `$csrfExemptActions`.**
3. **Запретить state-changing действия по GET.**
   - В первую очередь `deleteQuestion`.
   - Удалить чтение `question_id` из `$_GET` для delete action.
   - Явно требовать `POST` для delete/update/create/grant/revoke/assign/remove.
4. **Удалить/закрыть debug actions в production**:
   - `debugPathProgress`
   - `testCompleteStep`

## Priority 1
5. **Сделать logout строго CSRF-защищенным**.
   - Либо полноценная CSRF-валидация, которая блокирует logout.
   - Либо отдельный одноразовый logout token.
6. **Унифицировать все формы на `CsrfProtection`**, исключив локальные механизмы вроде `$_SESSION['csrf']`.

## Priority 2
7. **Добавить серверные method guards** внутри контроллеров/шлюза:
   - `POST` only для mutating actions;
   - `GET` only для read-only actions.
8. **Автотесты безопасности**:
   - POST без токена → 403/ошибка;
   - GET на mutating action → 405/ошибка;
   - exempt list содержит только read-only endpoints.

---

## Итоговый вывод

Система уже имеет неплохой CSRF-фундамент, но он подорван несколькими опасными исключениями. Главные реальные риски:
- **админский CSRF через `manageUsers.php`;**
- **обход CSRF через GET для `deleteQuestion`;**
- **ошибочно exempt state-changing actions для category experts;**
- **debug endpoints, меняющие состояние без CSRF.**

Если моделировать реального атакующего, то наиболее вероятный путь эксплуатации — не взлом “с нуля”, а **принуждение уже авторизованного администратора/эксперта/редактора выполнить опасное действие своим браузером**.

Приоритет устранения: **manageUsers → GET mutation removal → category expert actions → debug actions → logout flow**.

---

## Статус после исправлений (проверка 2026-03-21)

- **Закрыто:** `manageUsers.php` теперь валидирует CSRF и рендерит token fields во все mutating формы.
- **Закрыто:** `assignCategoryExpert` / `removeCategoryExpert` больше не должны обходить CSRF и дополнительно ограничены `POST`.
- **Закрыто:** `deleteQuestion` больше не должен вызываться через `GET`; mutating actions ограничены `POST`.
- **Закрыто:** `debugPathProgress` / `testCompleteStep` переведены в `POST-only` и больше не должны обходить CSRF как read-only endpoints.
- **Закрыто:** logout flow в `userMenu.php` и `logoutHandler.php` больше не должен выполняться без валидного CSRF.
- **Закрыто:** `userProfile.php` переведен на общий `CsrfProtection`, локальный `$_SESSION['csrf']` больше не используется.

### Остаточный риск

На момент этой сверки из пунктов данного аудита остаются в основном **архитектурные и регрессионные риски**, а не явные открытые bypass:
- возможны новые mutating actions в будущем, если их добавят в gateway без обновления `postOnlyActions`;
- полезно добавить автоматические security-smoke tests на `GET` vs `POST` и на отсутствие опасных CSRF exemptions.
