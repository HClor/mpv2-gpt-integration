package com.lms.testapp.utils

import com.lms.testapp.data.models.*
import com.lms.testapp.data.repository.TestRepository
import kotlinx.coroutines.flow.first

/**
 * Loads demo data for offline testing
 * Contains examples of all 6 question types
 */
object DemoDataLoader {

    suspend fun loadDemoDataIfNeeded(repository: TestRepository) {
        // Check if data already exists
        val existingCategories = repository.getAllCategories().first()
        if (existingCategories.isNotEmpty()) {
            return // Data already loaded
        }

        // Load categories
        val categories = listOf(
            Category(
                id = 1,
                name = "Веб-разработка",
                description = "Тесты по HTML, CSS, JavaScript и другим веб-технологиям",
                sortOrder = 1
            ),
            Category(
                id = 2,
                name = "История",
                description = "Тесты по истории России и мировой истории",
                sortOrder = 2
            ),
            Category(
                id = 3,
                name = "Русский язык",
                description = "Тесты по грамматике и орфографии",
                sortOrder = 3
            )
        )
        repository.insertCategories(categories)

        // Load tests
        val tests = listOf(
            Test(
                id = 1,
                categoryId = 1,
                title = "HTML основы",
                description = "Базовые знания HTML-разметки",
                mode = "training",
                timeLimit = 15,
                passScore = 70,
                randomizeQuestions = true,
                randomizeAnswers = true
            ),
            Test(
                id = 2,
                categoryId = 1,
                title = "JavaScript для начинающих",
                description = "Основы языка JavaScript",
                mode = "exam",
                timeLimit = 20,
                passScore = 60,
                randomizeQuestions = true,
                randomizeAnswers = true
            ),
            Test(
                id = 3,
                categoryId = 2,
                title = "История России XX века",
                description = "События и даты российской истории",
                mode = "training",
                timeLimit = 30,
                passScore = 70,
                randomizeQuestions = true,
                randomizeAnswers = true
            ),
            Test(
                id = 4,
                categoryId = 3,
                title = "Орфография русского языка",
                description = "Правила написания слов",
                mode = "training",
                timeLimit = null,
                passScore = 80,
                randomizeQuestions = true,
                randomizeAnswers = false
            )
        )
        repository.insertTests(tests)

        // Load questions for HTML test
        loadHtmlTestQuestions(repository)

        // Load questions for JavaScript test
        loadJavaScriptTestQuestions(repository)

        // Load questions for History test
        loadHistoryTestQuestions(repository)

        // Load questions for Russian language test
        loadRussianLanguageTestQuestions(repository)
    }

    private suspend fun loadHtmlTestQuestions(repository: TestRepository) {
        val questions = listOf(
            // Single choice
            Question(
                id = 101,
                testId = 1,
                questionText = "Какой тег используется для создания заголовка первого уровня?",
                questionType = "single",
                explanation = "Тег <h1> используется для главного заголовка страницы. Заголовки идут от h1 до h6.",
                sortOrder = 1
            ),
            // Multiple choice
            Question(
                id = 102,
                testId = 1,
                questionText = "Какие из перечисленных тегов являются блочными элементами?",
                questionType = "multiple",
                explanation = "Блочные элементы занимают всю доступную ширину и начинаются с новой строки.",
                sortOrder = 2
            ),
            // Single choice
            Question(
                id = 103,
                testId = 1,
                questionText = "Какой атрибут используется для указания URL ссылки?",
                questionType = "single",
                explanation = "Атрибут href (hypertext reference) указывает URL-адрес ссылки.",
                sortOrder = 3
            ),
            // Matching
            Question(
                id = 104,
                testId = 1,
                questionText = "Сопоставьте HTML-теги с их назначением:",
                questionType = "matching",
                explanation = "Важно знать назначение основных HTML-тегов.",
                sortOrder = 4
            ),
            // Single choice
            Question(
                id = 105,
                testId = 1,
                questionText = "Какой DOCTYPE используется для HTML5?",
                questionType = "single",
                explanation = "В HTML5 используется упрощенный DOCTYPE.",
                sortOrder = 5
            )
        )
        repository.insertQuestions(questions)

        // Answers for question 101 (single)
        val answers101 = listOf(
            Answer(id = 1001, questionId = 101, answerText = "<h1>", isCorrect = true, sortOrder = 1),
            Answer(id = 1002, questionId = 101, answerText = "<header>", isCorrect = false, sortOrder = 2),
            Answer(id = 1003, questionId = 101, answerText = "<head>", isCorrect = false, sortOrder = 3),
            Answer(id = 1004, questionId = 101, answerText = "<title>", isCorrect = false, sortOrder = 4)
        )

        // Answers for question 102 (multiple)
        val answers102 = listOf(
            Answer(id = 1005, questionId = 102, answerText = "<div>", isCorrect = true, sortOrder = 1),
            Answer(id = 1006, questionId = 102, answerText = "<span>", isCorrect = false, sortOrder = 2),
            Answer(id = 1007, questionId = 102, answerText = "<p>", isCorrect = true, sortOrder = 3),
            Answer(id = 1008, questionId = 102, answerText = "<a>", isCorrect = false, sortOrder = 4),
            Answer(id = 1009, questionId = 102, answerText = "<section>", isCorrect = true, sortOrder = 5)
        )

        // Answers for question 103 (single)
        val answers103 = listOf(
            Answer(id = 1010, questionId = 103, answerText = "href", isCorrect = true, sortOrder = 1),
            Answer(id = 1011, questionId = 103, answerText = "src", isCorrect = false, sortOrder = 2),
            Answer(id = 1012, questionId = 103, answerText = "link", isCorrect = false, sortOrder = 3),
            Answer(id = 1013, questionId = 103, answerText = "url", isCorrect = false, sortOrder = 4)
        )

        // Answers for question 105 (single)
        val answers105 = listOf(
            Answer(id = 1014, questionId = 105, answerText = "<!DOCTYPE html>", isCorrect = true, sortOrder = 1),
            Answer(id = 1015, questionId = 105, answerText = "<!DOCTYPE HTML PUBLIC>", isCorrect = false, sortOrder = 2),
            Answer(id = 1016, questionId = 105, answerText = "<html version=\"5\">", isCorrect = false, sortOrder = 3),
            Answer(id = 1017, questionId = 105, answerText = "<!HTML5>", isCorrect = false, sortOrder = 4)
        )

        repository.insertAnswers(answers101 + answers102 + answers103 + answers105)

        // Matching pairs for question 104
        val matchingPairs = listOf(
            MatchingPair(id = 1, questionId = 104, leftItem = "<a>", rightItem = "Ссылка", sortOrder = 1),
            MatchingPair(id = 2, questionId = 104, leftItem = "<img>", rightItem = "Изображение", sortOrder = 2),
            MatchingPair(id = 3, questionId = 104, leftItem = "<table>", rightItem = "Таблица", sortOrder = 3),
            MatchingPair(id = 4, questionId = 104, leftItem = "<ul>", rightItem = "Маркированный список", sortOrder = 4)
        )
        repository.insertMatchingPairs(matchingPairs)
    }

    private suspend fun loadJavaScriptTestQuestions(repository: TestRepository) {
        val questions = listOf(
            Question(
                id = 201,
                testId = 2,
                questionText = "Какое ключевое слово используется для объявления переменной в современном JavaScript?",
                questionType = "single",
                explanation = "let и const - современные способы объявления переменных в ES6+.",
                sortOrder = 1
            ),
            Question(
                id = 202,
                testId = 2,
                questionText = "Расположите этапы выполнения JavaScript кода в правильном порядке:",
                questionType = "ordering",
                explanation = "JavaScript выполняется последовательно: парсинг, компиляция, выполнение.",
                sortOrder = 2
            ),
            Question(
                id = 203,
                testId = 2,
                questionText = "Какой результат выполнения: typeof null",
                questionType = "single",
                explanation = "Это известный баг JavaScript - typeof null возвращает 'object'.",
                sortOrder = 3
            ),
            Question(
                id = 204,
                testId = 2,
                questionText = "Выберите все примитивные типы данных в JavaScript:",
                questionType = "multiple",
                explanation = "Примитивные типы: string, number, boolean, undefined, null, symbol, bigint.",
                sortOrder = 4
            )
        )
        repository.insertQuestions(questions)

        // Answers for question 201
        val answers201 = listOf(
            Answer(id = 2001, questionId = 201, answerText = "let", isCorrect = true, sortOrder = 1),
            Answer(id = 2002, questionId = 201, answerText = "var", isCorrect = false, sortOrder = 2),
            Answer(id = 2003, questionId = 201, answerText = "int", isCorrect = false, sortOrder = 3),
            Answer(id = 2004, questionId = 201, answerText = "define", isCorrect = false, sortOrder = 4)
        )

        // Answers for question 203
        val answers203 = listOf(
            Answer(id = 2005, questionId = 203, answerText = "\"object\"", isCorrect = true, sortOrder = 1),
            Answer(id = 2006, questionId = 203, answerText = "\"null\"", isCorrect = false, sortOrder = 2),
            Answer(id = 2007, questionId = 203, answerText = "\"undefined\"", isCorrect = false, sortOrder = 3),
            Answer(id = 2008, questionId = 203, answerText = "null", isCorrect = false, sortOrder = 4)
        )

        // Answers for question 204 (multiple)
        val answers204 = listOf(
            Answer(id = 2009, questionId = 204, answerText = "string", isCorrect = true, sortOrder = 1),
            Answer(id = 2010, questionId = 204, answerText = "number", isCorrect = true, sortOrder = 2),
            Answer(id = 2011, questionId = 204, answerText = "array", isCorrect = false, sortOrder = 3),
            Answer(id = 2012, questionId = 204, answerText = "boolean", isCorrect = true, sortOrder = 4),
            Answer(id = 2013, questionId = 204, answerText = "object", isCorrect = false, sortOrder = 5),
            Answer(id = 2014, questionId = 204, answerText = "symbol", isCorrect = true, sortOrder = 6)
        )

        repository.insertAnswers(answers201 + answers203 + answers204)

        // Ordering items for question 202
        val orderingItems = listOf(
            OrderingItem(id = 1, questionId = 202, itemText = "Парсинг кода", correctPosition = 1),
            OrderingItem(id = 2, questionId = 202, itemText = "Создание AST", correctPosition = 2),
            OrderingItem(id = 3, questionId = 202, itemText = "Компиляция в байт-код", correctPosition = 3),
            OrderingItem(id = 4, questionId = 202, itemText = "Выполнение кода", correctPosition = 4)
        )
        repository.insertOrderingItems(orderingItems)
    }

    private suspend fun loadHistoryTestQuestions(repository: TestRepository) {
        val questions = listOf(
            Question(
                id = 301,
                testId = 3,
                questionText = "В каком году произошла Октябрьская революция?",
                questionType = "single",
                explanation = "Октябрьская революция произошла 25-26 октября (7-8 ноября) 1917 года.",
                sortOrder = 1
            ),
            Question(
                id = 302,
                testId = 3,
                questionText = "Расположите события в хронологическом порядке:",
                questionType = "ordering",
                explanation = "Важно знать последовательность ключевых событий XX века.",
                sortOrder = 2
            ),
            Question(
                id = 303,
                testId = 3,
                questionText = "Сопоставьте исторических деятелей с их достижениями:",
                questionType = "matching",
                explanation = "Знание вклада исторических личностей важно для понимания истории.",
                sortOrder = 3
            ),
            Question(
                id = 304,
                testId = 3,
                questionText = "Когда был подписан пакт Молотова-Риббентропа?",
                questionType = "single",
                explanation = "Договор о ненападении между СССР и Германией был подписан 23 августа 1939 года.",
                sortOrder = 4
            )
        )
        repository.insertQuestions(questions)

        // Answers for question 301
        val answers301 = listOf(
            Answer(id = 3001, questionId = 301, answerText = "1917", isCorrect = true, sortOrder = 1),
            Answer(id = 3002, questionId = 301, answerText = "1918", isCorrect = false, sortOrder = 2),
            Answer(id = 3003, questionId = 301, answerText = "1905", isCorrect = false, sortOrder = 3),
            Answer(id = 3004, questionId = 301, answerText = "1914", isCorrect = false, sortOrder = 4)
        )

        // Answers for question 304
        val answers304 = listOf(
            Answer(id = 3005, questionId = 304, answerText = "23 августа 1939", isCorrect = true, sortOrder = 1),
            Answer(id = 3006, questionId = 304, answerText = "1 сентября 1939", isCorrect = false, sortOrder = 2),
            Answer(id = 3007, questionId = 304, answerText = "22 июня 1941", isCorrect = false, sortOrder = 3),
            Answer(id = 3008, questionId = 304, answerText = "15 августа 1938", isCorrect = false, sortOrder = 4)
        )

        repository.insertAnswers(answers301 + answers304)

        // Ordering items for question 302
        val orderingItems = listOf(
            OrderingItem(id = 10, questionId = 302, itemText = "Октябрьская революция", correctPosition = 1),
            OrderingItem(id = 11, questionId = 302, itemText = "Образование СССР", correctPosition = 2),
            OrderingItem(id = 12, questionId = 302, itemText = "Начало Великой Отечественной войны", correctPosition = 3),
            OrderingItem(id = 13, questionId = 302, itemText = "Победа в ВОВ", correctPosition = 4),
            OrderingItem(id = 14, questionId = 302, itemText = "Распад СССР", correctPosition = 5)
        )
        repository.insertOrderingItems(orderingItems)

        // Matching pairs for question 303
        val matchingPairs = listOf(
            MatchingPair(id = 10, questionId = 303, leftItem = "Ленин", rightItem = "Октябрьская революция", sortOrder = 1),
            MatchingPair(id = 11, questionId = 303, leftItem = "Гагарин", rightItem = "Первый полет в космос", sortOrder = 2),
            MatchingPair(id = 12, questionId = 303, leftItem = "Жуков", rightItem = "Победа в ВОВ", sortOrder = 3),
            MatchingPair(id = 13, questionId = 303, leftItem = "Горбачев", rightItem = "Перестройка", sortOrder = 4)
        )
        repository.insertMatchingPairs(matchingPairs)
    }

    private suspend fun loadRussianLanguageTestQuestions(repository: TestRepository) {
        val questions = listOf(
            Question(
                id = 401,
                testId = 4,
                questionText = "Заполните пропуски в предложении правильными окончаниями:",
                questionType = "fill_blank",
                explanation = "Окончания существительных зависят от падежа и склонения.",
                sortOrder = 1
            ),
            Question(
                id = 402,
                testId = 4,
                questionText = "В каком слове пишется НН?",
                questionType = "single",
                explanation = "НН пишется в прилагательных, образованных от существительных с основой на -н.",
                sortOrder = 2
            ),
            Question(
                id = 403,
                testId = 4,
                questionText = "Какие слова пишутся раздельно с НЕ?",
                questionType = "multiple",
                explanation = "НЕ пишется раздельно с глаголами (кроме исключений) и причастиями с зависимыми словами.",
                sortOrder = 3
            ),
            Question(
                id = 404,
                testId = 4,
                questionText = "Опишите правило написания -тся/-ться в глаголах:",
                questionType = "essay",
                explanation = "Если глагол отвечает на вопрос 'что делает?' - пишется -тся, если 'что делать?' - -ться.",
                sortOrder = 4
            )
        )
        repository.insertQuestions(questions)

        // Fill blank for question 401
        val fillBlank = FillBlank(
            id = 1,
            questionId = 401,
            templateText = "Мальчик шел по {{1}} и смотрел на {{2}}.",
            caseSensitive = false
        )
        repository.insertFillBlank(fillBlank)

        val fillBlankAnswers = listOf(
            FillBlankAnswer(
                id = 1,
                fillBlankId = 1,
                blankNumber = 1,
                correctAnswer = "дороге",
                alternativeAnswers = "[\"Дороге\", \"ДОРОГЕ\"]"
            ),
            FillBlankAnswer(
                id = 2,
                fillBlankId = 1,
                blankNumber = 2,
                correctAnswer = "небо",
                alternativeAnswers = "[\"Небо\", \"НЕБО\"]"
            )
        )
        repository.insertFillBlankAnswers(fillBlankAnswers)

        // Answers for question 402
        val answers402 = listOf(
            Answer(id = 4001, questionId = 402, answerText = "каменный", isCorrect = true, sortOrder = 1),
            Answer(id = 4002, questionId = 402, answerText = "кожаный", isCorrect = false, sortOrder = 2),
            Answer(id = 4003, questionId = 402, answerText = "серебряный", isCorrect = false, sortOrder = 3),
            Answer(id = 4004, questionId = 402, answerText = "глиняный", isCorrect = false, sortOrder = 4)
        )

        // Answers for question 403 (multiple)
        val answers403 = listOf(
            Answer(id = 4005, questionId = 403, answerText = "не знаю", isCorrect = true, sortOrder = 1),
            Answer(id = 4006, questionId = 403, answerText = "невозможно", isCorrect = false, sortOrder = 2),
            Answer(id = 4007, questionId = 403, answerText = "не прочитанная книга", isCorrect = true, sortOrder = 3),
            Answer(id = 4008, questionId = 403, answerText = "неинтересный", isCorrect = false, sortOrder = 4),
            Answer(id = 4009, questionId = 403, answerText = "не хочу", isCorrect = true, sortOrder = 5)
        )

        repository.insertAnswers(answers402 + answers403)
    }
}
