# LMS Test App - Android

Офлайн Android-приложение для прохождения тестов на базе LMS системы.

## Возможности

- **6 типов вопросов:**
  - Single (один правильный ответ)
  - Multiple (несколько правильных ответов)
  - Matching (сопоставление пар)
  - Ordering (упорядочивание элементов)
  - Fill Blank (заполнение пропусков)
  - Essay (развернутый ответ)

- **Полная офлайн поддержка:**
  - Все данные хранятся в локальной Room базе данных
  - Работает без интернета после первой загрузки
  - История прохождения тестов сохраняется локально

- **Режимы тестирования:**
  - Режим обучения (training) - можно просматривать объяснения
  - Режим экзамена (exam) - ограничение по времени

- **Особенности:**
  - Рандомизация вопросов и ответов
  - Таймер с обратным отсчетом
  - Автоматическая проверка ответов
  - Подробные результаты с объяснениями
  - Material Design 3 интерфейс
  - Поддержка темной темы

## Технологии

- **Kotlin** - основной язык разработки
- **Room Database** - локальное хранение данных
- **Material Design 3** - современный UI
- **Coroutines + Flow** - асинхронность
- **ViewModel + LiveData** - архитектура MVVM
- **ViewBinding** - связывание представлений

## Структура проекта

```
app/src/main/java/com/lms/testapp/
├── TestApplication.kt          # Application класс
├── data/
│   ├── database/
│   │   ├── TestDatabase.kt     # Room Database
│   │   └── TestDao.kt          # DAO интерфейсы
│   ├── models/
│   │   ├── Category.kt         # Категории тестов
│   │   ├── Test.kt             # Тесты
│   │   ├── Question.kt         # Вопросы
│   │   ├── Answer.kt           # Ответы
│   │   ├── MatchingPair.kt     # Пары для сопоставления
│   │   ├── OrderingItem.kt     # Элементы для упорядочивания
│   │   ├── FillBlank.kt        # Пропуски для заполнения
│   │   └── TestSession.kt      # Сессии и ответы пользователя
│   └── repository/
│       └── TestRepository.kt   # Репозиторий с логикой проверки
├── ui/
│   ├── MainActivity.kt         # Список категорий
│   ├── categories/
│   │   └── CategoryAdapter.kt  # Адаптер категорий
│   ├── tests/
│   │   ├── TestListActivity.kt # Список тестов
│   │   └── TestAdapter.kt      # Адаптер тестов
│   ├── quiz/
│   │   ├── QuizActivity.kt     # Прохождение теста
│   │   ├── QuizViewModel.kt    # ViewModel для теста
│   │   ├── AnswerAdapter.kt    # Адаптер single/multiple
│   │   ├── MatchingAdapter.kt  # Адаптер сопоставления
│   │   ├── OrderingAdapter.kt  # Адаптер упорядочивания
│   │   └── FillBlankAdapter.kt # Адаптер заполнения
│   └── results/
│       ├── ResultsActivity.kt  # Результаты теста
│       └── AnswerResultAdapter.kt
└── utils/
    └── DemoDataLoader.kt       # Загрузка демо-данных
```

## Сборка и запуск

### Требования

- Android Studio Arctic Fox или новее
- JDK 17
- Android SDK 34

### Сборка

1. Откройте проект в Android Studio:
   ```bash
   cd android-test-app
   ```

2. Синхронизируйте Gradle:
   ```
   File -> Sync Project with Gradle Files
   ```

3. Запустите на эмуляторе или устройстве:
   ```
   Run -> Run 'app'
   ```

### Сборка APK

```bash
./gradlew assembleDebug
```

APK будет создан в: `app/build/outputs/apk/debug/app-debug.apk`

### Сборка Release версии

```bash
./gradlew assembleRelease
```

## Добавление своих тестов

### 1. Через код

Отредактируйте `DemoDataLoader.kt`:

```kotlin
val questions = listOf(
    Question(
        id = 501,
        testId = 5,
        questionText = "Ваш вопрос",
        questionType = "single", // single, multiple, matching, ordering, fill_blank, essay
        explanation = "Объяснение ответа",
        sortOrder = 1
    )
)
```

### 2. Через JSON (будущая функция)

Положите JSON файл в `assets/tests/` и он будет загружен при первом запуске.

## Структура данных

### Категория
```json
{
  "id": 1,
  "name": "Название категории",
  "description": "Описание",
  "parentId": null
}
```

### Тест
```json
{
  "id": 1,
  "categoryId": 1,
  "title": "Название теста",
  "description": "Описание",
  "mode": "training",
  "timeLimit": 30,
  "passScore": 70,
  "randomizeQuestions": true,
  "randomizeAnswers": true
}
```

### Вопрос
```json
{
  "id": 101,
  "testId": 1,
  "questionText": "Текст вопроса",
  "questionType": "single",
  "explanation": "Объяснение"
}
```

## Лицензия

MIT License
