-- phpMyAdmin SQL Dump
-- version 4.9.7
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Окт 21 2025 г., 13:30
-- Версия сервера: 5.7.21-20-beget-5.7.21-20-1-log
-- Версия PHP: 5.6.40

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `lmixru_mpv2`
--

-- --------------------------------------------------------

--
-- Структура таблицы `modx_site_content`
--
-- Создание: Окт 21 2025 г., 07:53
-- Последнее обновление: Окт 21 2025 г., 07:53
--

DROP TABLE IF EXISTS `modx_site_content`;
CREATE TABLE `modx_site_content` (
  `id` int(10) UNSIGNED NOT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'document',
  `contentType` varchar(50) NOT NULL DEFAULT 'text/html',
  `pagetitle` varchar(191) NOT NULL DEFAULT '',
  `longtitle` varchar(191) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `alias` varchar(191) DEFAULT '',
  `alias_visible` tinyint(1) UNSIGNED NOT NULL DEFAULT '1',
  `link_attributes` varchar(191) NOT NULL DEFAULT '',
  `published` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `pub_date` int(20) NOT NULL DEFAULT '0',
  `unpub_date` int(20) NOT NULL DEFAULT '0',
  `parent` int(10) NOT NULL DEFAULT '0',
  `isfolder` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `introtext` text,
  `content` mediumtext,
  `richtext` tinyint(1) UNSIGNED NOT NULL DEFAULT '1',
  `template` int(10) NOT NULL DEFAULT '0',
  `menuindex` int(10) NOT NULL DEFAULT '0',
  `searchable` tinyint(1) UNSIGNED NOT NULL DEFAULT '1',
  `cacheable` tinyint(1) UNSIGNED NOT NULL DEFAULT '1',
  `createdby` int(10) NOT NULL DEFAULT '0',
  `createdon` int(20) NOT NULL DEFAULT '0',
  `editedby` int(10) NOT NULL DEFAULT '0',
  `editedon` int(20) NOT NULL DEFAULT '0',
  `deleted` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `deletedon` int(20) NOT NULL DEFAULT '0',
  `deletedby` int(10) NOT NULL DEFAULT '0',
  `publishedon` int(20) NOT NULL DEFAULT '0',
  `publishedby` int(10) NOT NULL DEFAULT '0',
  `menutitle` varchar(191) NOT NULL DEFAULT '',
  `donthit` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `privateweb` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `privatemgr` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `content_dispo` tinyint(1) NOT NULL DEFAULT '0',
  `hidemenu` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `class_key` varchar(100) NOT NULL DEFAULT 'modDocument',
  `context_key` varchar(100) NOT NULL DEFAULT 'web',
  `content_type` int(11) UNSIGNED NOT NULL DEFAULT '1',
  `uri` text,
  `uri_override` tinyint(1) NOT NULL DEFAULT '0',
  `hide_children_in_tree` tinyint(1) NOT NULL DEFAULT '0',
  `show_in_tree` tinyint(1) NOT NULL DEFAULT '1',
  `properties` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `modx_site_content`
--

INSERT INTO `modx_site_content` (`id`, `type`, `contentType`, `pagetitle`, `longtitle`, `description`, `alias`, `alias_visible`, `link_attributes`, `published`, `pub_date`, `unpub_date`, `parent`, `isfolder`, `introtext`, `content`, `richtext`, `template`, `menuindex`, `searchable`, `cacheable`, `createdby`, `createdon`, `editedby`, `editedon`, `deleted`, `deletedon`, `deletedby`, `publishedon`, `publishedby`, `menutitle`, `donthit`, `privateweb`, `privatemgr`, `content_dispo`, `hidemenu`, `class_key`, `context_key`, `content_type`, `uri`, `uri_override`, `hide_children_in_tree`, `show_in_tree`, `properties`) VALUES
(1, 'document', 'text/html', 'Главная', 'Поздравляем!', '', 'index', 1, '', 1, 0, 0, 0, 0, '', '<p>Текст про тесты и обучение</p>', 1, 3, 0, 1, 1, 1, 1722803141, 1, 1760858787, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 1, 'modDocument', 'web', 1, 'index', 0, 0, 1, '{\"autoredirector\":{\"old_uri\":\"index\"}}'),
(2, 'document', 'text/plain', 'robots.txt', '', '', 'robots', 1, '', 1, 0, 0, 0, 0, NULL, 'User-agent: *\nAllow: /\n\nHost: {$_modx->config.http_host}\n\nSitemap: {$_modx->config.site_url}sitemap.xml\n', 0, 3, 16, 0, 1, 1, 1760679652, 0, 0, 0, 0, 0, 1760680337, 0, '', 0, 0, 0, 0, 1, 'modDocument', 'web', 3, 'robots.txt', 0, 0, 1, '{\"autoredirector\":{\"old_uri\":\"robots.txt\"}}'),
(3, 'document', 'text/html', 'Информация о нас', '', '', 'about', 1, '', 1, 0, 0, 23, 1, NULL, '<p>Медиабизнес слабо допускает конструктивный формирование имиджа, учитывая результат предыдущих медиа-кампаний. Конвесия покупателя, конечно, по-прежнему востребована. Рыночная информация стабилизирует пресс-клиппинг, полагаясь на инсайдерскую информацию. План размещения без оглядки на авторитеты не так уж очевиден.</p>\n<p>Психологическая среда индуцирует конструктивный стратегический маркетинг, оптимизируя бюджеты. Медиапланирование поддерживает общественный ребрендинг. Медиамикс правомочен. Медиапланирование стабилизирует стратегический рекламоноситель.</p>\n<p>Рекламная площадка усиливает медиабизнес. Эволюция мерчандайзинга притягивает департамент маркетинга и продаж, оптимизируя бюджеты. Поэтому таргетирование стремительно усиливает целевой трафик. Потребление, вопреки мнению П.Друкера, редко соответствует рыночным ожиданиям. Имидж, следовательно, программирует медиамикс.</p>\n', 1, 3, 0, 1, 1, 1, 1760679652, 0, 0, 0, 0, 0, 1760680337, 0, 'О компании', 0, 0, 0, 0, 0, 'modDocument', 'web', 1, 'arhiv/about', 0, 0, 1, '{\"autoredirector\":{\"old_uri\":\"arhiv\\/about\"}}'),
(4, 'document', 'text/html', 'Наши сотрудники', '', '', 'specialists', 1, '', 1, 0, 0, 23, 1, NULL, '<p></p>\n', 0, 3, 4, 1, 1, 1, 1760679652, 0, 0, 0, 0, 0, 1760680337, 0, 'Специалисты', 0, 0, 0, 0, 0, 'modDocument', 'web', 1, 'arhiv/specialists', 0, 0, 1, '{\"autoredirector\":{\"old_uri\":\"arhiv\\/specialists\"}}'),
(5, 'document', 'text/html', 'Сотрудник 1', '', '', 'spec-1', 1, '', 1, 0, 0, 4, 0, NULL, '<p></p>\n', 1, 3, 1, 1, 1, 1, 1760679652, 0, 0, 0, 0, 0, 1760676052, 0, '', 0, 0, 0, 0, 1, 'modDocument', 'web', 1, 'arhiv/specialists/spec-1', 0, 0, 1, NULL),
(6, 'document', 'text/html', 'Сотрудник 2', '', '', 'spec-2', 1, '', 1, 0, 0, 4, 0, NULL, '<p></p>\n', 1, 3, 2, 1, 1, 1, 1760679652, 0, 0, 0, 0, 0, 1760672452, 0, '', 0, 0, 0, 0, 1, 'modDocument', 'web', 1, 'arhiv/specialists/spec-2', 0, 0, 1, NULL),
(7, 'document', 'text/html', 'Сотрудник 3', '', '', 'spec-3', 1, '', 1, 0, 0, 4, 0, NULL, '<p></p>\n', 1, 3, 3, 1, 1, 1, 1760679652, 0, 0, 0, 0, 0, 1760668852, 0, '', 0, 0, 0, 0, 1, 'modDocument', 'web', 1, 'arhiv/specialists/spec-3', 0, 0, 1, NULL),
(8, 'document', 'text/html', 'Сотрудник 4', '', '', 'spec-4', 1, '', 1, 0, 0, 4, 0, NULL, '<p></p>\n', 1, 3, 4, 1, 1, 1, 1760679652, 0, 0, 0, 0, 0, 1760665252, 0, '', 0, 0, 0, 0, 1, 'modDocument', 'web', 1, 'arhiv/specialists/spec-4', 0, 0, 1, NULL),
(9, 'document', 'text/html', 'Сотрудник 5', '', '', 'spec-5', 1, '', 1, 0, 0, 4, 0, NULL, '<p></p>\n', 1, 3, 5, 1, 1, 1, 1760679652, 0, 0, 0, 0, 0, 1760661652, 0, '', 0, 0, 0, 0, 1, 'modDocument', 'web', 1, 'arhiv/specialists/spec-5', 0, 0, 1, NULL),
(10, 'document', 'text/html', 'Отзывы наших клиентов', '', '', 'reviews', 1, '', 1, 0, 0, 23, 1, NULL, '<p></p>\n', 1, 3, 1, 1, 1, 1, 1760679652, 0, 0, 0, 0, 0, 1760680337, 0, 'Отзывы', 0, 0, 0, 0, 0, 'CollectionContainer', 'web', 1, 'arhiv/reviews', 0, 0, 1, '{\"autoredirector\":{\"old_uri\":\"arhiv\\/reviews\"}}'),
(11, 'document', 'text/html', 'Отзыв 1', '', '', 'review-1', 1, '', 1, 0, 0, 10, 0, NULL, '<p>Восприятие, на первый взгляд, отражает гендерный стимул. Чем больше люди узнают друг друга, тем больше воспитание иллюстрирует коллективный импульс. Придерживаясь жестких принципов социального Дарвинизма, предсознательное отражает страх, также это подчеркивается в труде Дж.Морено \"Театр Спонтанности\". Ригидность отчуждает групповой эгоцентризм.</p>\n<p>Рефлексия, как справедливо считает Ф.Энгельс, представляет собой экзистенциальный тест. Идентификация, по определению, отчуждает инсайт. Акцентуированная личность выбирает эмпирический страх. Страх, согласно традиционным представлениям, теоретически возможен.</p>\n<p>Бессознательное, в представлении Морено, однородно выбирает кризис, это обозначено Ли Россом как фундаментальная ошибка атрибуции, которая прослеживается во многих экспериментах. Действие осознаёт гештальт. Как отмечает Д.Майерс, у нас есть некоторое чувство конфликта, которое возникает с ситуации несоответствия желаемого и действительного, поэтому бессознательное просветляет инсайт. Самоактуализация осознаёт филосовский объект. Гендер, по определению, изящно отталкивает латентный интеллект, в частности, \"психозы\", индуцируемые при различных психопатологических типологиях.</p>', 1, 3, 1, 1, 1, 1, 1760679652, 0, 0, 0, 0, 0, 1760676052, 0, '', 0, 0, 0, 0, 0, 'modDocument', 'web', 1, 'arhiv/reviews/review-1', 0, 0, 0, NULL),
(12, 'document', 'text/html', 'Отзыв 2', '', '', 'review-2', 1, '', 1, 0, 0, 10, 0, NULL, '<p>Самонаблюдение аннигилирует индивидуальный интеллект, следовательно основной закон психофизики: ощущение изменяется пропорционально логарифму раздражителя . Код начинает потребительский импульс, что вызвало развитие функционализма и сравнительно-психологических исследований поведения. Как отмечает Жан Пиаже, субъект традиционен.</p>\n<p>Сновидение существенно отражает стимул. Роль, иcходя из того, что мгновенно отчуждает индивидуальный аутизм. Установка отталкивает групповой эгоцентризм, таким образом, стратегия поведения, выгодная отдельному человеку, ведет к коллективному проигрышу. Чувство, в представлении Морено, косвенно.</p>\n<p>Психосоматика выбирает конвергентный филогенез, как и предсказывают практические аспекты использования принципов гештальпсихологии в области восприятия, обучения, развития психики, социальных взаимоотношений. В заключении добавлю, контраст концептуально понимает контраст. Компульсивность, например, притягивает психоз. Самость психологически аннигилирует автоматизм. Психосоматика фундаментально притягивает когнитивный объект. Аномия представляет собой концептуальный гомеостаз.</p>', 1, 3, 2, 1, 1, 1, 1760679652, 0, 0, 0, 0, 0, 1760672452, 0, '', 0, 0, 0, 0, 0, 'modDocument', 'web', 1, 'arhiv/reviews/review-2', 0, 0, 0, NULL),
(13, 'document', 'text/html', 'Отзыв 3', '', '', 'review-3', 1, '', 1, 0, 0, 10, 0, NULL, '<p>Акцентуированная личность интегрирует психоанализ. Чувство, как справедливо считает Ф.Энгельс, важно отталкивает девиантный филогенез. Всякая психическая функция в культурном развитии ребенка появляется на сцену дважды, в двух планах,— сперва социальном, потом — психологическом, следовательно рефлексия вероятна. Установка вызывает позитивистский психоз.</p>\n<p>Сознание, конечно, начинает институциональный интеракционизм. Онтогенез речи, например, представляет собой конфликтный код. Когнитивная составляющая, в первом приближении, просветляет эгоцентризм. Наши исследования позволяют сделать вывод о том, что гештальт отталкивает ассоцианизм. Психе, в первом приближении, семантически представляет собой коллективный субъект. Л.С.Выготский понимал тот факт, что эскапизм начинает материалистический контраст.</p>\n<p>Предсознательное иллюстрирует автоматизм, также это подчеркивается в труде Дж.Морено \"Театр Спонтанности\". Стратификация гомогенно отражает архетип, следовательно тенденция к конформизму связана с менее низким интеллектом. Социализация отражает культурный гомеостаз, что вызвало развитие функционализма и сравнительно-психологических исследований поведения. В связи с этим нужно подчеркнуть, что сновидение выбирает социометрический эриксоновский гипноз. Но так как книга Фридмана адресована руководителям и работникам образования, то есть сознание вызывает конформизм. Инсайт интегрирует экспериментальный интеллект.</p>', 1, 3, 3, 1, 1, 1, 1760679652, 0, 0, 0, 0, 0, 1760668852, 0, '', 0, 0, 0, 0, 0, 'modDocument', 'web', 1, 'arhiv/reviews/review-3', 0, 0, 0, NULL),
(14, 'document', 'text/html', 'Галерея', '', '', 'gallery', 1, '', 1, 0, 0, 23, 1, NULL, '<p></p>\n', 1, 3, 5, 1, 1, 1, 1760679652, 0, 0, 0, 0, 0, 1760680337, 0, '', 0, 0, 0, 0, 0, 'modDocument', 'web', 1, 'arhiv/gallery', 0, 0, 1, '{\"autoredirector\":{\"old_uri\":\"arhiv\\/gallery\"}}'),
(15, 'document', 'text/html', 'Новости компании', '', '', 'news', 1, '', 1, 0, 0, 23, 1, NULL, '<p></p>\n', 1, 3, 2, 1, 1, 1, 1760679652, 0, 0, 0, 0, 0, 1760680337, 0, 'Новости', 0, 0, 0, 0, 0, 'CollectionContainer', 'web', 1, 'arhiv/news', 0, 0, 1, '{\"autoredirector\":{\"old_uri\":\"arhiv\\/news\"}}'),
(16, 'document', 'text/html', 'Новость 1', '', '', 'news-1', 1, '', 1, 0, 0, 15, 0, NULL, '<p>Кризис жанра дает фузз, потому что современная музыка не запоминается. Очевидно, что нота заканчивает самодостаточный контрапункт контрастных фактур. Показательный пример – хамбакер неустойчив. Аллюзийно-полистилистическая композиция иллюстрирует дискретный шоу-бизнес. Как было показано выше, хамбакер продолжает звукоряд, таким образом объектом имитации является число длительностей в каждой из относительно автономных ритмогрупп ведущего голоса.</p>', 1, 3, 1, 1, 1, 1, 1760679652, 0, 0, 0, 0, 0, 1760676052, 0, '', 0, 0, 0, 0, 0, 'modDocument', 'web', 1, 'arhiv/news/news-1', 0, 0, 0, NULL),
(17, 'document', 'text/html', 'Новость 2', '', '', 'news-2', 1, '', 1, 0, 0, 15, 0, NULL, '<p>В заключении добавлю, open-air дает конструктивный флажолет. Гипнотический рифф вызывает рок-н-ролл 50-х, благодаря быстрой смене тембров (каждый инструмент играет минимум звуков). Процессуальное изменение имеет определенный эффект \"вау-вау\". В заключении добавлю, процессуальное изменение выстраивает изоритмический цикл. Микрохроматический интервал, на первый взгляд, использует open-air, это понятие создано по аналогии с термином Ю.Н.Холопова \"многозначная тональность\".</p>', 1, 3, 2, 1, 1, 1, 1760679652, 0, 0, 0, 0, 0, 1760672452, 0, '', 0, 0, 0, 0, 0, 'modDocument', 'web', 1, 'arhiv/news/news-2', 0, 0, 0, NULL),
(18, 'document', 'text/html', 'Новость 3', '', '', 'news-3', 1, '', 1, 0, 0, 15, 0, NULL, '<p>Соноропериод многопланово трансформирует длительностный голос. Серпантинная волна иллюстрирует разнокомпонентный сет. Иными словами, фишка всекомпонентна. Микрохроматический интервал неустойчив. Процессуальное изменение представляет собой мнимотакт. Как было показано выше, адажио продолжает флажолет.</p>', 1, 3, 3, 1, 1, 1, 1760679652, 0, 0, 0, 0, 0, 1760668852, 0, '', 0, 0, 0, 0, 0, 'modDocument', 'web', 1, 'arhiv/news/news-3', 0, 0, 0, NULL),
(19, 'document', 'text/html', 'Контактная информация', '', '', 'contacts', 1, '', 1, 0, 0, 23, 1, NULL, '<p>Адрес: {\"address\" | config}</p>\n<p>Телефон: {\"phone\" | config}</p>\n<p>E-mail: {\"email\" | config}</p>\n{\'contact_form\' | chunk : [\n\'form\' => \'form.contact_form\',\n\'tpl\' => \'tpl.contact_form\',\n\'subject\' => \'Заявка с сайта \' ~ $_modx->config.http_host,\n\'validate\' => \'name:required,phone:required,check:required\'\n]}\n', 0, 3, 3, 1, 1, 1, 1760679652, 0, 0, 0, 0, 0, 1760680337, 0, '', 0, 0, 0, 0, 0, 'modDocument', 'web', 1, 'arhiv/contacts', 0, 0, 1, '{\"autoredirector\":{\"old_uri\":\"arhiv\\/contacts\"}}'),
(20, 'document', 'text/html', 'Страница не найдена', '&nbsp;', '', '404', 1, '', 1, 0, 0, 0, 1, NULL, '<div style=\'width: 500px; margin: -30px auto 0; overflow: hidden;padding-top: 25px;\'>\n<div style=\'float: left; width: 100px; margin-right: 50px; font-size: 75px;margin-top: 45px;\'>404</div>\n<div style=\'float: left; width: 350px; padding-top: 30px; font-size: 14px;\'>\n<h2>Страница не найдена</h2>\n<p style=\'margin: 8px 0 0;\'>Страница, на которую вы зашли, вероятно, была удалена с сайта, либо ее здесь никогда не было.</p>\n<p style=\'margin: 8px 0 0;\'>Возможно, вы ошиблись при наборе адреса или перешли по неверной ссылке.</p>\n<h3 style=\'margin: 15px 0 0;\'>Что делать?</h3>\n<ul style=\'margin: 5px 0 0 15px;\'>\n<li>проверьте правильность написания адреса,</li>\n<li>перейдите на <a href=\'{$_modx->config.site_url}\'>главную страницу</a> сайта,</li>\n<li>или <a href=\'javascript:history.go(-1);\'>вернитесь на предыдущую страницу</a>.</li>\n</ul>\n</div>\n</div>\n', 0, 3, 15, 1, 1, 1, 1760679652, 0, 0, 0, 0, 0, 1760680337, 0, '', 0, 0, 0, 0, 1, 'modDocument', 'web', 1, '404', 0, 0, 1, '{\"autoredirector\":{\"old_uri\":\"404\"}}'),
(21, 'document', 'text/html', 'Карта сайта', '', '', 'site-map', 1, '', 1, 0, 0, 0, 1, NULL, '{\'pdoMenu\' | snippet : [\n\'startId\' => 0,\n\'ignoreHidden\' => 1,\n\'resources\' => \'-20,-\' ~ $_modx->resource.id,\n\'level\' => 2,\n\'outerClass\' => \'\',\n\'firstClass\' => \'\',\n\'lastClass\' => \'\',\n\'hereClass\' => \'\',\n\'where\' => \'{\"searchable\":1}\'\n]}\n', 0, 3, 14, 1, 1, 1, 1760679652, 0, 0, 0, 0, 0, 1760680337, 0, '', 0, 0, 0, 0, 1, 'modDocument', 'web', 1, 'site-map', 0, 0, 1, '{\"autoredirector\":{\"old_uri\":\"site-map\"}}'),
(22, 'document', 'text/xml', 'sitemap.xml', '', '', 'sitemap', 1, '', 1, 0, 0, 0, 0, NULL, '{\'pdoSitemap\' | snippet : [ \'showHidden\' => 1, \'resources\' => \'-20\' ]}\n', 0, 3, 17, 0, 1, 1, 1760679652, 0, 0, 0, 0, 0, 1760680337, 0, '', 0, 0, 0, 0, 1, 'modDocument', 'web', 2, 'sitemap.xml', 0, 0, 1, '{\"autoredirector\":{\"old_uri\":\"sitemap.xml\"}}'),
(23, 'document', 'text/html', 'Архив', '', '', 'arhiv', 1, '', 0, 0, 0, 0, 0, '', '', 1, 3, 13, 1, 1, 1, 1760679705, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 1, 'modDocument', 'web', 1, 'arhiv', 0, 0, 1, '{\"autoredirector\":{\"old_uri\":\"arhiv\"}}'),
(24, 'document', 'text/html', 'Авторизация', '', '', 'auth', 1, '', 1, 0, 0, 0, 0, '', '<h1>Вход и регистрация</h1>[[!authHandler]]', 1, 3, 0, 1, 1, 1, 1760699322, 1, 1760703609, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 'modDocument', 'web', 1, 'auth', 0, 0, 1, '{\"autoredirector\":{\"old_uri\":\"auth\"}}'),
(25, 'document', 'text/html', 'Активация аккаунта', '', '', 'activate', 1, '', 1, 0, 0, 0, 0, '', '<h1>Активация аккаунта</h1>[[!activateAccount]]', 1, 3, 0, 1, 1, 1, 1760699322, 1, 1760702109, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 1, 'modDocument', 'web', 1, 'activate', 0, 0, 1, '{\"autoredirector\":{\"old_uri\":\"activate\"}}'),
(26, 'document', 'text/html', 'Забыли пароль', '', '', 'forgot-password', 1, '', 1, 0, 0, 0, 0, '', '<h1>Восстановление пароля</h1>[[!forgotPasswordHandler]]', 1, 3, 0, 1, 1, 1, 1760699322, 1, 1760702118, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 1, 'modDocument', 'web', 1, 'forgot-password', 0, 0, 1, '{\"autoredirector\":{\"old_uri\":\"forgot-password\"}}'),
(27, 'document', 'text/html', 'Сброс пароля', '', '', 'reset-password', 1, '', 1, 0, 0, 0, 0, '', '<h1>Установка нового пароля</h1>[[!resetPasswordHandler]]', 1, 3, 0, 1, 1, 1, 1760699322, 1, 1760702128, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 1, 'modDocument', 'web', 1, 'reset-password', 0, 0, 1, '{\"autoredirector\":{\"old_uri\":\"reset-password\"}}'),
(28, 'document', 'text/html', 'Профиль', '', '', 'profile', 1, '', 1, 0, 0, 0, 0, '', '<h1>Мой профиль</h1>\r\n[[!userProfile]]', 1, 3, 0, 1, 1, 1, 1760699322, 1, 1760702140, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 'modDocument', 'web', 1, 'profile', 0, 0, 1, '{\"autoredirector\":{\"old_uri\":\"profile\"}}'),
(29, 'document', 'text/html', 'Импорт вопросов', '', '', 'import-csv', 1, '', 1, 0, 0, 0, 0, NULL, '<h1>Импорт вопросов из CSV</h1>[[!csvImportForm]]', 1, 3, 0, 1, 1, 1, 1760702179, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 1, 'modDocument', 'web', 1, 'import-csv', 0, 0, 1, NULL),
(34, 'document', 'text/html', 'Рейтинг', '', '', 'leaderboard', 1, '', 1, 0, 0, 0, 0, NULL, '<h1>Рейтинг учеников</h1>\r\n[[!leaderboard]]', 1, 3, 0, 1, 1, 1, 1760705022, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 'modDocument', 'web', 1, 'leaderboard', 0, 0, 1, NULL),
(35, 'document', 'text/html', 'Тесты', '', '', 'tests', 1, '', 1, 0, 0, 0, 0, NULL, '<h1 class=\"mb-4\">Все тесты</h1>\r\n[[!categoriesAndTests]]', 1, 3, 0, 1, 1, 1, 1760718219, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 'modDocument', 'web', 1, 'tests', 0, 0, 1, NULL),
(36, 'document', 'text/html', 'Создать тест', '', '', 'add-test', 1, '', 1, 0, 0, 0, 0, NULL, '<h1 class=\"mb-4\">Создание нового теста</h1>\r\n<p class=\"lead text-muted mb-4\">Процесс состоит из 2 шагов: создание теста и импорт вопросов</p>\r\n[[!addTestForm]]', 1, 3, 0, 1, 0, 1, 1760718340, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 1, 'modDocument', 'web', 1, 'add-test', 0, 0, 1, NULL),
(38, 'document', 'text/html', 'Управление категориями', '', '', 'manage-categories', 1, '', 1, 0, 0, 0, 0, NULL, '<h1>Управление категориями</h1>\r\n<p class=\"lead\">Добавление, редактирование и удаление категорий тестов</p>\r\n[[!manageCategories]]', 1, 3, 0, 1, 1, 1, 1760720922, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 1, 'modDocument', 'web', 1, 'manage-categories', 0, 0, 1, NULL),
(43, 'document', 'text/html', 'Управление пользователями', '', '', 'manage-users', 1, '', 0, 0, 0, 0, 0, '', '<h1>Управление пользователями</h1>\r\n<p class=\"lead\">Назначение ролей и управление доступом</p>\r\n[[!manageUsers]]', 1, 3, 0, 1, 1, 1, 1760723661, 1, 1760956643, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 1, 'modDocument', 'web', 1, 'manage-users', 0, 0, 1, '{\"autoredirector\":{\"old_uri\":\"manage-users\"}}'),
(56, 'document', 'text/html', 'Программирование', '', '', 'cat-1', 1, '', 1, 0, 0, 35, 1, NULL, '<h1>Программирование</h1><p>Тесты категории</p>', 1, 3, 0, 1, 1, 1, 1760783115, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 'modDocument', 'web', 1, 'tests/cat-1', 0, 0, 1, NULL),
(58, 'document', 'text/html', 'Видеонаблюдение', '', '', 'cat-3', 1, '', 1, 0, 0, 35, 1, NULL, '<h1>Видеонаблюдение</h1><p>Тесты категории</p>', 1, 3, 0, 1, 1, 1, 1760783115, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 'modDocument', 'web', 1, 'tests/cat-3', 0, 0, 1, '{\"autoredirector\":{\"old_uri\":\"tests\\/cat-3\"}}'),
(59, 'document', 'text/html', 'Системы контроля доступа', '', '', 'cat-4', 1, '', 1, 0, 0, 35, 1, NULL, '<h1>Системы контроля доступа</h1><p>Тесты категории</p>', 1, 3, 0, 1, 1, 1, 1760783115, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 'modDocument', 'web', 1, 'tests/cat-4', 0, 0, 1, NULL),
(68, 'document', 'text/html', 'Тест: Основы SQL', '', '', 'test-1', 1, '', 1, 0, 0, 0, 0, NULL, '<h1>Основы SQL</h1>[[!testRunner? &testId=`1`]]', 1, 3, 0, 1, 1, 2, 1760785155, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 1, 'modDocument', 'web', 1, 'test-1', 0, 0, 1, NULL),
(85, 'document', 'text/html', 'SQL', '', '', 'test-24', 1, '', 1, 0, 0, 56, 0, NULL, '[[!testRunner? &test_id=`24`]]', 1, 3, 0, 1, 1, 2, 1760857576, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 'modDocument', 'web', 1, 'tests/cat-1/test-24', 0, 0, 1, NULL),
(86, 'document', 'text/html', 'jhlkhlkjhlkhkllkhljk', '', '', 'test-25', 1, '', 1, 0, 0, 56, 0, NULL, '[[!testRunner? &test_id=`25`]]', 1, 3, 0, 1, 1, 5, 1760956848, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 'modDocument', 'web', 1, 'tests/cat-1/test-25', 0, 0, 1, NULL),
(89, 'document', 'text/html', 'SQL', '', '', 'test-28', 1, '', 1, 0, 0, 56, 0, NULL, '[[!testRunner? &test_id=`28`]]', 1, 3, 0, 1, 1, 2, 1760962528, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 'modDocument', 'web', 1, 'tests/cat-1/test-28', 0, 0, 1, NULL),
(90, 'document', 'text/html', 'ДИ РП', '', '', 'test-29', 1, '', 1, 0, 0, 58, 0, NULL, '[[!testRunner? &test_id=`29`]]', 1, 3, 0, 1, 1, 5, 1760963075, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 'modDocument', 'web', 1, 'tests/cat-3/test-29', 0, 0, 1, NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `modx_site_tmplvars`
--
-- Создание: Окт 21 2025 г., 07:53
-- Последнее обновление: Окт 21 2025 г., 07:53
--

DROP TABLE IF EXISTS `modx_site_tmplvars`;
CREATE TABLE `modx_site_tmplvars` (
  `id` int(10) UNSIGNED NOT NULL,
  `source` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `property_preprocess` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `type` varchar(20) NOT NULL DEFAULT '',
  `name` varchar(50) NOT NULL DEFAULT '',
  `caption` varchar(80) NOT NULL DEFAULT '',
  `description` varchar(191) NOT NULL DEFAULT '',
  `editor_type` int(11) NOT NULL DEFAULT '0',
  `category` int(11) NOT NULL DEFAULT '0',
  `locked` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `elements` text,
  `rank` int(11) NOT NULL DEFAULT '0',
  `display` varchar(20) NOT NULL DEFAULT '',
  `default_text` mediumtext,
  `properties` text,
  `input_properties` text,
  `output_properties` text,
  `static` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `static_file` varchar(191) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `modx_site_tmplvars`
--

INSERT INTO `modx_site_tmplvars` (`id`, `source`, `property_preprocess`, `type`, `name`, `caption`, `description`, `editor_type`, `category`, `locked`, `elements`, `rank`, `display`, `default_text`, `properties`, `input_properties`, `output_properties`, `static`, `static_file`) VALUES
(1, 0, 0, 'fastuploadtv', 'img', 'Изображение', '', 0, 1, 0, NULL, 0, '', NULL, NULL, 'a:5:{s:4:\"path\";s:26:\"assets/images/{d}-{m}-{y}/\";s:6:\"prefix\";s:7:\"{rand}-\";s:4:\"MIME\";s:0:\"\";s:9:\"showValue\";b:0;s:11:\"showPreview\";b:1;}', NULL, 0, ''),
(2, 0, 0, 'checkbox', 'show_on_page', 'Отображать на странице', '', 0, 1, 0, 'Дочерние ресурсы==children||Контент==content||Галерею==gallery||Код под Содержимым==raw_content||Правую колонку==aside', 0, 'delim', 'children||content||gallery||raw_content||aside', 'a:0:{}', 'a:2:{s:10:\"allowBlank\";s:4:\"true\";s:7:\"columns\";s:1:\"1\";}', 'a:1:{s:9:\"delimiter\";s:2:\"||\";}', 0, ''),
(3, 0, 0, 'text', 'keywords', 'Keywords', '', 0, 1, 0, NULL, 0, '', NULL, NULL, NULL, NULL, 0, ''),
(4, 0, 0, 'text', 'subtitle', 'Подпись', '', 0, 1, 0, NULL, 0, '', NULL, NULL, NULL, NULL, 0, ''),
(5, 0, 0, 'migx', 'elements', 'Элементы', '', 0, 1, 0, NULL, 0, '', NULL, NULL, 'a:2:{s:8:\"formtabs\";s:287:\"[{\"caption\":\"Элемент\",\"fields\":[{\"field\":\"title\",\"caption\":\"Заголовок\"},{\"field\":\"subtitle\",\"caption\":\"Подзаголовок\"},{\"field\":\"img\",\"caption\":\"Изображение\",\"inputTV\":\"img\"},{\"field\":\"content\",\"caption\":\"Контент\",\"inputTVtype\":\"richtext\"}]}]\";s:7:\"columns\";s:163:\"[{\"header\":\"Изображение\",\"dataIndex\":\"img\",\"width\":200,\"renderer\":\"this.renderImage\"},{\"header\":\"Содержимое\",\"dataIndex\":\"title\",\"width\":400}]\";}', NULL, 0, '');

-- --------------------------------------------------------

--
-- Структура таблицы `modx_site_tmplvar_contentvalues`
--
-- Создание: Окт 21 2025 г., 07:53
-- Последнее обновление: Окт 21 2025 г., 07:53
--

DROP TABLE IF EXISTS `modx_site_tmplvar_contentvalues`;
CREATE TABLE `modx_site_tmplvar_contentvalues` (
  `id` int(10) UNSIGNED NOT NULL,
  `tmplvarid` int(10) NOT NULL DEFAULT '0',
  `contentid` int(10) NOT NULL DEFAULT '0',
  `value` mediumtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `modx_site_tmplvar_contentvalues`
--

INSERT INTO `modx_site_tmplvar_contentvalues` (`id`, `tmplvarid`, `contentid`, `value`) VALUES
(1, 2, 4, 'content||gallery'),
(2, 1, 5, '/assets/components/siteextra/web/img/spec1.png'),
(3, 4, 5, 'Маркетолог'),
(4, 1, 6, '/assets/components/siteextra/web/img/spec2.png'),
(5, 4, 6, 'Маркетолог'),
(6, 1, 7, '/assets/components/siteextra/web/img/spec3.png'),
(7, 4, 7, 'PR-менеджер'),
(8, 1, 8, '/assets/components/siteextra/web/img/spec4.png'),
(9, 4, 8, 'Директор'),
(10, 1, 9, '/assets/components/siteextra/web/img/spec5.png'),
(11, 4, 9, 'Оператор колл-центра'),
(12, 5, 14, '[{\"MIGX_id\":1,\"img\":\"\\/assets\\/components\\/siteextra\\/web\\/img\\/gal1.jpg\",\"title\":\"\\u0424\\u043e\\u0442\\u043e 1\"},{\"MIGX_id\":2,\"img\":\"\\/assets\\/components\\/siteextra\\/web\\/img\\/gal2.jpg\",\"title\":\"\\u0424\\u043e\\u0442\\u043e 2\"},{\"MIGX_id\":3,\"img\":\"\\/assets\\/components\\/siteextra\\/web\\/img\\/gal3.jpg\",\"title\":\"\\u0424\\u043e\\u0442\\u043e 3\"},{\"MIGX_id\":4,\"img\":\"\\/assets\\/components\\/siteextra\\/web\\/img\\/gal4.jpg\",\"title\":\"\\u0424\\u043e\\u0442\\u043e 4\"},{\"MIGX_id\":5,\"img\":\"\\/assets\\/components\\/siteextra\\/web\\/img\\/gal5.jpg\",\"title\":\"\\u0424\\u043e\\u0442\\u043e 5\"},{\"MIGX_id\":6,\"img\":\"\\/assets\\/components\\/siteextra\\/web\\/img\\/gal6.jpg\",\"title\":\"\\u0424\\u043e\\u0442\\u043e 6\"}]');

-- --------------------------------------------------------

--
-- Структура таблицы `modx_test_answers`
--
-- Создание: Окт 21 2025 г., 07:53
-- Последнее обновление: Окт 21 2025 г., 07:53
--

DROP TABLE IF EXISTS `modx_test_answers`;
CREATE TABLE `modx_test_answers` (
  `id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer_text` text NOT NULL,
  `is_correct` tinyint(1) DEFAULT '0',
  `explanation` text,
  `sort_order` int(11) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Дамп данных таблицы `modx_test_answers`
--

INSERT INTO `modx_test_answers` (`id`, `question_id`, `answer_text`, `is_correct`, `explanation`, `sort_order`) VALUES
(1, 1, 'Язык программирования', 0, NULL, 0),
(2, 1, 'Язык запросов к БД', 1, NULL, 1),
(3, 1, 'Система управления БД', 0, NULL, 2),
(4, 1, 'Протокол передачи данных', 0, NULL, 3),
(5, 2, 'INSERT', 0, NULL, 0),
(6, 2, 'UPDATE', 0, NULL, 1),
(7, 2, 'SELECT', 1, NULL, 2),
(8, 2, 'DELETE', 0, NULL, 3),
(9, 3, 'Удаляет записи', 0, NULL, 0),
(10, 3, 'Обновляет записи', 0, NULL, 1),
(11, 3, 'Объединяет таблицы', 1, NULL, 2),
(12, 3, 'Создаёт таблицу', 0, NULL, 3),
(13, 4, 'INNER JOIN', 1, NULL, 0),
(14, 4, 'OUTER JOIN', 1, NULL, 1),
(15, 4, 'LEFT JOIN', 1, NULL, 2),
(16, 4, 'MIDDLE JOIN', 0, NULL, 3),
(17, 5, 'Любое поле таблицы', 0, NULL, 0),
(18, 5, 'Уникальный идентификатор записи', 1, NULL, 1),
(19, 5, 'Внешняя ссылка', 0, NULL, 2),
(20, 5, 'Индекс для быстрого поиска', 0, NULL, 3),
(21, 6, 'MAKE TABLE', 0, NULL, 0),
(22, 6, 'BUILD TABLE', 0, NULL, 1),
(23, 6, 'CREATE TABLE', 1, NULL, 2),
(24, 6, 'NEW TABLE', 0, NULL, 3),
(25, 7, 'Создаёт условие выборки', 1, NULL, 0),
(26, 7, 'Сортирует результаты', 0, NULL, 1),
(27, 7, 'Группирует данные', 0, NULL, 2),
(28, 7, 'Объединяет таблицы', 0, NULL, 3),
(29, 8, 'COUNT', 1, NULL, 0),
(30, 8, 'SUM', 1, NULL, 1),
(31, 8, 'AVG', 1, NULL, 2),
(32, 8, 'JOIN', 0, NULL, 3),
(33, 9, 'Копия таблицы', 0, NULL, 0),
(34, 9, 'Структура для ускорения поиска', 1, NULL, 1),
(35, 9, 'Связь между таблицами', 0, NULL, 2),
(36, 9, 'Резервная копия', 0, NULL, 3),
(37, 10, 'Удаления дубликатов', 1, NULL, 0),
(38, 10, 'Сортировки данных', 0, NULL, 1),
(39, 10, 'Группировки данных', 0, NULL, 2),
(40, 10, 'Объединения таблиц', 0, NULL, 3),
(41, 11, 'Язык программирования', 0, NULL, 0),
(42, 11, 'Язык запросов к БД', 1, NULL, 1),
(43, 11, 'Операционная система', 0, NULL, 2),
(44, 11, 'Браузер', 0, NULL, 3),
(45, 12, 'INSERT', 0, NULL, 0),
(46, 12, 'SELECT', 1, NULL, 1),
(47, 12, 'UPDATE', 0, NULL, 2),
(48, 12, 'DELETE', 0, NULL, 3),
(49, 13, 'Удаляет данные', 0, NULL, 0),
(50, 13, 'Обновляет таблицу', 0, NULL, 1),
(51, 13, 'Объединяет таблицы', 1, NULL, 2),
(52, 13, 'Создаёт индекс', 0, NULL, 3),
(53, 14, 'INNER JOIN', 1, NULL, 0),
(54, 14, 'LEFT JOIN', 1, NULL, 1),
(55, 14, 'MIDDLE JOIN', 0, NULL, 2),
(56, 14, 'RIGHT JOIN', 1, NULL, 3),
(57, 15, 'Любое поле', 0, NULL, 0),
(58, 15, 'Уникальный идентификатор записи', 1, NULL, 1),
(59, 15, 'Внешний ключ', 0, NULL, 2),
(60, 15, 'Индекс', 0, NULL, 3),
(61, 16, 'INSERT TABLE', 0, NULL, 0),
(62, 16, 'MAKE TABLE', 0, NULL, 1),
(63, 16, 'CREATE TABLE', 1, NULL, 2),
(64, 16, 'NEW TABLE', 0, NULL, 3),
(65, 17, 'Сортирует данные', 0, NULL, 0),
(66, 17, 'Фильтрует данные', 1, NULL, 1),
(67, 17, 'Группирует данные', 0, NULL, 2),
(68, 17, 'Удаляет данные', 0, NULL, 3),
(69, 18, 'COUNT', 1, NULL, 0),
(70, 18, 'SUM', 1, NULL, 1),
(71, 18, 'JOIN', 0, NULL, 2),
(72, 18, 'AVG', 1, NULL, 3),
(73, 19, 'Резервная копия', 0, NULL, 0),
(74, 19, 'Структура для ускорения поиска', 1, NULL, 1),
(75, 19, 'Внешний ключ', 0, NULL, 2),
(76, 19, 'Таблица', 0, NULL, 3),
(77, 20, 'Для сортировки', 0, NULL, 0),
(78, 20, 'Для удаления дубликатов', 1, NULL, 1),
(79, 20, 'Для подсчёта', 0, NULL, 2),
(80, 20, 'Для группировки', 0, NULL, 3),
(81, 21, 'Язык программирования', 0, NULL, 0),
(82, 21, 'Язык запросов к БД', 1, NULL, 1),
(83, 21, 'Операционная система', 0, NULL, 2),
(84, 21, 'Браузер', 0, NULL, 3),
(85, 22, 'INSERT', 0, NULL, 0),
(86, 22, 'SELECT', 1, NULL, 1),
(87, 22, 'UPDATE', 0, NULL, 2),
(88, 22, 'DELETE', 0, NULL, 3),
(89, 23, 'Удаляет данные', 0, NULL, 0),
(90, 23, 'Обновляет таблицу', 0, NULL, 1),
(91, 23, 'Объединяет таблицы', 1, NULL, 2),
(92, 23, 'Создаёт индекс', 0, NULL, 3),
(93, 24, 'INNER JOIN', 1, NULL, 0),
(94, 24, 'LEFT JOIN', 1, NULL, 1),
(95, 24, 'MIDDLE JOIN', 0, NULL, 2),
(96, 24, 'RIGHT JOIN', 1, NULL, 3),
(97, 25, 'Любое поле', 0, NULL, 0),
(98, 25, 'Уникальный идентификатор записи', 1, NULL, 1),
(99, 25, 'Внешний ключ', 0, NULL, 2),
(100, 25, 'Индекс', 0, NULL, 3),
(101, 26, 'INSERT TABLE', 0, NULL, 0),
(102, 26, 'MAKE TABLE', 0, NULL, 1),
(103, 26, 'CREATE TABLE', 1, NULL, 2),
(104, 26, 'NEW TABLE', 0, NULL, 3),
(105, 27, 'Сортирует данные', 0, NULL, 0),
(106, 27, 'Фильтрует данные', 1, NULL, 1),
(107, 27, 'Группирует данные', 0, NULL, 2),
(108, 27, 'Удаляет данные', 0, NULL, 3),
(109, 28, 'COUNT', 1, NULL, 0),
(110, 28, 'SUM', 1, NULL, 1),
(111, 28, 'JOIN', 0, NULL, 2),
(112, 28, 'AVG', 1, NULL, 3),
(113, 29, 'Резервная копия', 0, NULL, 0),
(114, 29, 'Структура для ускорения поиска', 1, NULL, 1),
(115, 29, 'Внешний ключ', 0, NULL, 2),
(116, 29, 'Таблица', 0, NULL, 3),
(117, 30, 'Для сортировки', 0, NULL, 0),
(118, 30, 'Для удаления дубликатов', 1, NULL, 1),
(119, 30, 'Для подсчёта', 0, NULL, 2),
(120, 30, 'Для группировки', 0, NULL, 3),
(121, 31, 'Язык программирования', 0, NULL, 0),
(122, 31, 'Язык запросов к БД', 1, NULL, 1),
(123, 31, 'Операционная система', 0, NULL, 2),
(124, 31, 'Браузер', 0, NULL, 3),
(125, 32, 'INSERT', 0, NULL, 0),
(126, 32, 'SELECT', 1, NULL, 1),
(127, 32, 'UPDATE', 0, NULL, 2),
(128, 32, 'DELETE', 0, NULL, 3),
(129, 33, 'Удаляет данные', 0, NULL, 0),
(130, 33, 'Обновляет таблицу', 0, NULL, 1),
(131, 33, 'Объединяет таблицы', 1, NULL, 2),
(132, 33, 'Создаёт индекс', 0, NULL, 3),
(133, 34, 'INNER JOIN', 1, NULL, 0),
(134, 34, 'LEFT JOIN', 1, NULL, 1),
(135, 34, 'MIDDLE JOIN', 0, NULL, 2),
(136, 34, 'RIGHT JOIN', 1, NULL, 3),
(137, 35, 'Любое поле', 0, NULL, 0),
(138, 35, 'Уникальный идентификатор записи', 1, NULL, 1),
(139, 35, 'Внешний ключ', 0, NULL, 2),
(140, 35, 'Индекс', 0, NULL, 3),
(141, 36, 'INSERT TABLE', 0, NULL, 0),
(142, 36, 'MAKE TABLE', 0, NULL, 1),
(143, 36, 'CREATE TABLE', 1, NULL, 2),
(144, 36, 'NEW TABLE', 0, NULL, 3),
(145, 37, 'Сортирует данные', 0, NULL, 0),
(146, 37, 'Фильтрует данные', 1, NULL, 1),
(147, 37, 'Группирует данные', 0, NULL, 2),
(148, 37, 'Удаляет данные', 0, NULL, 3),
(149, 38, 'COUNT', 1, NULL, 0),
(150, 38, 'SUM', 1, NULL, 1),
(151, 38, 'JOIN', 0, NULL, 2),
(152, 38, 'AVG', 1, NULL, 3),
(153, 39, 'Резервная копия', 0, NULL, 0),
(154, 39, 'Структура для ускорения поиска', 1, NULL, 1),
(155, 39, 'Внешний ключ', 0, NULL, 2),
(156, 39, 'Таблица', 0, NULL, 3),
(157, 40, 'Для сортировки', 0, NULL, 0),
(158, 40, 'Для удаления дубликатов', 1, NULL, 1),
(159, 40, 'Для подсчёта', 0, NULL, 2),
(160, 40, 'Для группировки', 0, NULL, 3),
(161, 41, 'Язык программирования', 0, NULL, 0),
(162, 41, 'Язык запросов к БД', 1, NULL, 1),
(163, 41, 'Операционная система', 0, NULL, 2),
(164, 41, 'Браузер', 0, NULL, 3),
(165, 42, 'INSERT', 0, NULL, 0),
(166, 42, 'SELECT', 1, NULL, 1),
(167, 42, 'UPDATE', 0, NULL, 2),
(168, 42, 'DELETE', 0, NULL, 3),
(169, 43, 'Удаляет данные', 0, NULL, 0),
(170, 43, 'Обновляет таблицу', 0, NULL, 1),
(171, 43, 'Объединяет таблицы', 1, NULL, 2),
(172, 43, 'Создаёт индекс', 0, NULL, 3),
(173, 44, 'INNER JOIN', 1, NULL, 0),
(174, 44, 'LEFT JOIN', 1, NULL, 1),
(175, 44, 'MIDDLE JOIN', 0, NULL, 2),
(176, 44, 'RIGHT JOIN', 1, NULL, 3),
(177, 45, 'Любое поле', 0, NULL, 0),
(178, 45, 'Уникальный идентификатор записи', 1, NULL, 1),
(179, 45, 'Внешний ключ', 0, NULL, 2),
(180, 45, 'Индекс', 0, NULL, 3),
(181, 46, 'INSERT TABLE', 0, NULL, 0),
(182, 46, 'MAKE TABLE', 0, NULL, 1),
(183, 46, 'CREATE TABLE', 1, NULL, 2),
(184, 46, 'NEW TABLE', 0, NULL, 3),
(185, 47, 'Сортирует данные', 0, NULL, 0),
(186, 47, 'Фильтрует данные', 1, NULL, 1),
(187, 47, 'Группирует данные', 0, NULL, 2),
(188, 47, 'Удаляет данные', 0, NULL, 3),
(189, 48, 'COUNT', 1, NULL, 0),
(190, 48, 'SUM', 1, NULL, 1),
(191, 48, 'JOIN', 0, NULL, 2),
(192, 48, 'AVG', 1, NULL, 3),
(193, 49, 'Резервная копия', 0, NULL, 0),
(194, 49, 'Структура для ускорения поиска', 1, NULL, 1),
(195, 49, 'Внешний ключ', 0, NULL, 2),
(196, 49, 'Таблица', 0, NULL, 3),
(197, 50, 'Для сортировки', 0, NULL, 0),
(198, 50, 'Для удаления дубликатов', 1, NULL, 1),
(199, 50, 'Для подсчёта', 0, NULL, 2),
(200, 50, 'Для группировки', 0, NULL, 3),
(201, 51, 'Матрица RACI', 1, NULL, 0),
(202, 51, 'Регламенты и шаблоны', 0, NULL, 1),
(203, 51, 'KPI', 0, NULL, 2),
(204, 51, 'single', 0, NULL, 3),
(205, 52, 'Аудит данных и Планирование', 1, NULL, 0),
(206, 52, 'Организация внешних коммуникаций', 0, NULL, 1),
(207, 52, 'Подготовка планировок в работу', 0, NULL, 2),
(208, 52, 'single', 0, NULL, 3),
(209, 53, 'Передача документации Менеджеру', 1, NULL, 0),
(210, 53, 'Утверждение внутренней проверки', 1, NULL, 1),
(211, 53, 'Участие в экспертизе проекта', 1, NULL, 2),
(212, 53, 'multiple', 0, NULL, 3),
(213, 54, 'Руководитель проекта (РП)', 1, NULL, 0),
(214, 54, 'Менеджер (М)', 0, NULL, 1),
(215, 54, 'Руководитель отдела проектирования (РОП)', 0, NULL, 2),
(216, 54, 'single', 0, NULL, 3),
(217, 55, 'До 5 рабочих дней', 1, NULL, 0),
(218, 55, '1 неделя', 0, NULL, 1),
(219, 55, 'Не ограничен', 0, NULL, 2),
(220, 55, 'single', 0, NULL, 3),
(221, 56, 'Немедленно остановить проект', 1, NULL, 0),
(222, 56, 'Игнорировать до официального уведомления', 0, NULL, 1),
(223, 56, 'Перепоручить задачу Заместителю (З)', 0, NULL, 2),
(224, 56, 'single', 0, NULL, 3),
(225, 57, 'Графики проектирования (ГПР)', 1, NULL, 0),
(226, 57, 'KPI', 0, NULL, 1),
(227, 57, 'Реестр проектов', 0, NULL, 2),
(228, 57, 'single', 0, NULL, 3),
(229, 58, 'Комплектация пакета документов в БД', 1, NULL, 0),
(230, 58, 'Проверка проекта', 0, NULL, 1),
(231, 58, 'Доработка планов расположения', 0, NULL, 2),
(232, 58, 'single', 0, NULL, 3),
(233, 59, 'В течение 3 рабочих дней', 1, NULL, 0),
(234, 59, 'В течение недели', 0, NULL, 1),
(235, 59, 'Немедленно, в реальном времени', 0, NULL, 2),
(236, 59, 'single', 0, NULL, 3),
(237, 60, 'Внести отметку в общий ГПР', 1, NULL, 0),
(238, 60, 'Ничего, этап автоматически фиксируется', 0, NULL, 1),
(239, 60, 'Создать рабочий чат в TEAMS', 0, NULL, 2),
(240, 60, 'single', 0, NULL, 3),
(241, 61, 'Доработка планов расположения', 1, NULL, 0),
(242, 61, 'Разработка ОТР', 0, NULL, 1),
(243, 61, 'Проверка проекта', 0, NULL, 2),
(244, 61, 'single', 0, NULL, 3),
(245, 62, 'Telegram', 1, NULL, 0),
(246, 62, 'Email', 0, NULL, 1),
(247, 62, 'Slack', 0, NULL, 2),
(248, 62, 'single', 0, NULL, 3),
(249, 63, 'Передача документации Менеджеру', 1, NULL, 0),
(250, 63, 'Проверка проекта на соответствие стандартам', 0, NULL, 1),
(251, 63, 'Разработка спецификаций', 0, NULL, 2),
(252, 63, 'single', 0, NULL, 3),
(253, 64, 'Инициализация проекта', 1, NULL, 0),
(254, 64, 'Аудит данных', 0, NULL, 1),
(255, 64, 'Общие данные', 0, NULL, 2),
(256, 64, 'multiple', 1, NULL, 3),
(257, 65, 'answer_1', 0, NULL, 0),
(258, 65, 'answer_2', 0, NULL, 1),
(259, 65, 'answer_3', 0, NULL, 2),
(260, 65, 'answer_4', 0, NULL, 3),
(261, 66, 'На этапе инициализации', 1, NULL, 0),
(262, 66, 'На этапе планирования', 0, NULL, 1),
(263, 66, 'На этапе разработки ОТР', 0, NULL, 2),
(264, 66, 'На этапе проверки проекта', 0, NULL, 3),
(265, 67, 'Графики проектирования (ГПР)', 1, NULL, 0),
(266, 67, 'Матрица RACI', 0, NULL, 1),
(267, 67, 'KPI', 0, NULL, 2),
(268, 67, 'Регламенты и шаблоны', 0, NULL, 3),
(269, 68, 'Матрица RACI', 1, NULL, 0),
(270, 68, 'Графики проектирования (ГПР)', 1, NULL, 1),
(271, 68, 'Регламенты и шаблоны', 1, NULL, 2),
(272, 68, 'KPI', 1, NULL, 3),
(273, 69, 'до 2 рабочих дней', 1, NULL, 0),
(274, 69, 'до 5 рабочих дней', 0, NULL, 1),
(275, 69, 'до 7 рабочих дней', 0, NULL, 2),
(276, 69, 'до 10 рабочих дней', 0, NULL, 3),
(277, 70, 'Структуру папок проекта в БД', 1, NULL, 0),
(278, 70, 'Реестры проекта', 1, NULL, 1),
(279, 70, 'Детальный ГПР', 1, NULL, 2),
(280, 70, 'Рабочий чат в TEAMS', 1, NULL, 3),
(281, 71, 'З (Заместитель)', 1, NULL, 0),
(282, 71, 'РП (Руководитель проекта)', 0, NULL, 1),
(283, 71, 'РОП', 0, NULL, 2),
(284, 71, 'М (Менеджер)', 0, NULL, 3),
(285, 72, 'Аудит данных и Планирование', 1, NULL, 0),
(286, 72, 'Организация внешних коммуникаций', 0, NULL, 1),
(287, 72, 'Разработка ОТР', 0, NULL, 2),
(288, 72, 'Подготовка планировок', 0, NULL, 3),
(289, 73, 'Инициирование запросов на недостающие данные', 1, NULL, 0),
(290, 73, 'Отслеживание поступающей информации', 1, NULL, 1),
(291, 73, 'Заполнение реестров', 1, NULL, 2),
(292, 73, 'Анализ влияния изменений', 1, NULL, 3),
(293, 74, 'ВС (Ведущий специалист)', 1, NULL, 0),
(294, 74, 'РП (Руководитель проекта)', 0, NULL, 1),
(295, 74, 'П (Проектировщик)', 0, NULL, 2),
(296, 74, 'РОП', 0, NULL, 3),
(297, 75, 'Внести отметку о завершении в ГПР объекта', 0, NULL, 0),
(298, 75, 'Проинформировать РОП и М', 0, NULL, 1),
(299, 75, 'Убедиться, что З вносит отметку в общий ГПР', 0, NULL, 2),
(300, 75, 'Все вышеперечисленное', 1, NULL, 3),
(301, 76, 'В течение 1 рабочего дня', 1, NULL, 0),
(302, 76, 'В течение 2 рабочих дней', 0, NULL, 1),
(303, 76, 'В течение 3 рабочих дней', 0, NULL, 2),
(304, 76, 'В течение недели', 0, NULL, 3),
(305, 77, 'Комплектация пакета документов в БД', 1, NULL, 0),
(306, 77, 'Передача документации Менеджеру', 0, NULL, 1),
(307, 77, 'Проверка проекта', 0, NULL, 2),
(308, 77, 'Разработка структурных схем', 0, NULL, 3),
(309, 78, 'РП (Руководитель проекта)', 1, NULL, 0),
(310, 78, 'РОП', 0, NULL, 1),
(311, 78, 'З (Заместитель)', 0, NULL, 2),
(312, 78, 'М (Менеджер)', 0, NULL, 3),
(313, 79, 'Проанализировать влияние изменений', 1, NULL, 0),
(314, 79, 'Оповестить команду', 1, NULL, 1),
(315, 79, 'Разработать план управления изменениями', 1, NULL, 2),
(316, 79, 'Внести корректировки в ГПР', 1, NULL, 3),
(317, 80, 'Матрица RACI', 1, NULL, 0),
(318, 80, 'Графики проектирования (ГПР)', 0, NULL, 1),
(319, 80, 'Реестры проекта', 0, NULL, 2),
(320, 80, 'KPI', 0, NULL, 3);

-- --------------------------------------------------------

--
-- Структура таблицы `modx_test_categories`
--
-- Создание: Окт 21 2025 г., 07:53
-- Последнее обновление: Окт 21 2025 г., 07:53
--

DROP TABLE IF EXISTS `modx_test_categories`;
CREATE TABLE `modx_test_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `sort_order` int(11) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Дамп данных таблицы `modx_test_categories`
--

INSERT INTO `modx_test_categories` (`id`, `name`, `description`, `sort_order`, `created_at`) VALUES
(1, 'Программирование', 'Базовые вопросы по программированию', 1, '2025-10-17 14:54:22'),
(3, 'Видеонаблюдение', 'Системы видеонаблюдения и IP-камеры', 2, '2025-10-17 19:23:39'),
(4, 'Системы контроля доступа', 'СКУД, турникеты, домофоны', 3, '2025-10-17 19:23:39');

-- --------------------------------------------------------

--
-- Структура таблицы `modx_test_questions`
--
-- Создание: Окт 21 2025 г., 07:53
-- Последнее обновление: Окт 21 2025 г., 07:53
--

DROP TABLE IF EXISTS `modx_test_questions`;
CREATE TABLE `modx_test_questions` (
  `id` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('single','multiple','text') DEFAULT 'single',
  `explanation` text,
  `sort_order` int(11) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Дамп данных таблицы `modx_test_questions`
--

INSERT INTO `modx_test_questions` (`id`, `test_id`, `category_id`, `question_text`, `question_type`, `explanation`, `sort_order`) VALUES
(1, 1, NULL, 'Что такое SQL?', 'single', 'SQL (Structured Query Language) — это язык структурированных запросов для работы с реляционными базами данных', 0),
(2, 1, NULL, 'Какая команда используется для выборки данных?', 'single', 'SELECT используется для получения данных из таблиц', 1),
(3, 1, NULL, 'Что делает команда JOIN?', 'single', 'JOIN объединяет строки из двух или более таблиц на основе связанного столбца', 2),
(4, 1, NULL, 'Выберите типы JOIN:', 'multiple', 'Существуют INNER, LEFT, RIGHT, FULL OUTER JOIN. MIDDLE JOIN не существует', 3),
(5, 1, NULL, 'Что такое первичный ключ (PRIMARY KEY)?', 'single', 'PRIMARY KEY — уникальный идентификатор каждой записи в таблице', 4),
(6, 1, NULL, 'Какая команда создаёт таблицу?', 'single', 'CREATE TABLE используется для создания новой таблицы в базе данных', 5),
(7, 1, NULL, 'Что делает команда WHERE?', 'single', 'WHERE задаёт условие для фильтрации строк в запросе', 6),
(8, 1, NULL, 'Выберите агрегатные функции:', 'multiple', 'COUNT, SUM, AVG, MIN, MAX — это агрегатные функции для обработки групп строк', 7),
(9, 1, NULL, 'Что такое индекс в БД?', 'single', 'Индекс — это структура данных, которая ускоряет операции поиска в таблице', 8),
(10, 1, NULL, 'Для чего используется DISTINCT?', 'single', 'DISTINCT удаляет дубликаты из результатов выборки', 9),
(11, 21, NULL, 'Что такое SQL?', 'single', 'SQL - это Structured Query Language', 0),
(12, 21, NULL, 'Какая команда используется для выборки данных?', 'single', 'SELECT используется для выборки данных из таблиц', 1),
(13, 21, NULL, 'Что делает команда JOIN?', 'single', 'JOIN объединяет строки из двух или более таблиц', 2),
(14, 21, NULL, 'Выберите типы JOIN:', 'multiple', 'Существуют: INNER, LEFT, RIGHT, FULL. MIDDLE JOIN - не существует', 3),
(15, 21, NULL, 'Что такое первичный ключ?', 'single', 'PRIMARY KEY уникально идентифирует каждую запись в таблице', 4),
(16, 21, NULL, 'Какая команда создаёт таблицу?', 'single', 'CREATE TABLE используется для создания новых таблиц', 5),
(17, 21, NULL, 'Что делает команда WHERE?', 'single', 'WHERE используется для фильтрации записей по условию', 6),
(18, 21, NULL, 'Выберите агрегатные функции:', 'multiple', 'COUNT, SUM, AVG, MIN, MAX - агрегатные функции. JOIN - это не функция', 7),
(19, 21, NULL, 'Что такое индекс в БД?', 'single', 'Индекс ускоряет поиск данных в таблице', 8),
(20, 21, NULL, 'Для чего используется DISTINCT?', 'single', 'DISTINCT возвращает только уникальные значения', 9),
(21, 22, NULL, 'Что такое SQL?', 'single', 'SQL - это Structured Query Language', 0),
(22, 22, NULL, 'Какая команда используется для выборки данных?', 'single', 'SELECT используется для выборки данных из таблиц', 1),
(23, 22, NULL, 'Что делает команда JOIN?', 'single', 'JOIN объединяет строки из двух или более таблиц', 2),
(24, 22, NULL, 'Выберите типы JOIN:', 'multiple', 'Существуют: INNER, LEFT, RIGHT, FULL. MIDDLE JOIN - не существует', 3),
(25, 22, NULL, 'Что такое первичный ключ?', 'single', 'PRIMARY KEY уникально идентифирует каждую запись в таблице', 4),
(26, 22, NULL, 'Какая команда создаёт таблицу?', 'single', 'CREATE TABLE используется для создания новых таблиц', 5),
(27, 22, NULL, 'Что делает команда WHERE?', 'single', 'WHERE используется для фильтрации записей по условию', 6),
(28, 22, NULL, 'Выберите агрегатные функции:', 'multiple', 'COUNT, SUM, AVG, MIN, MAX - агрегатные функции. JOIN - это не функция', 7),
(29, 22, NULL, 'Что такое индекс в БД?', 'single', 'Индекс ускоряет поиск данных в таблице', 8),
(30, 22, NULL, 'Для чего используется DISTINCT?', 'single', 'DISTINCT возвращает только уникальные значения', 9),
(31, 23, NULL, 'Что такое SQL?', 'single', 'SQL - это Structured Query Language', 0),
(32, 23, NULL, 'Какая команда используется для выборки данных?', 'single', 'SELECT используется для выборки данных из таблиц', 1),
(33, 23, NULL, 'Что делает команда JOIN?', 'single', 'JOIN объединяет строки из двух или более таблиц', 2),
(34, 23, NULL, 'Выберите типы JOIN:', 'multiple', 'Существуют: INNER, LEFT, RIGHT, FULL. MIDDLE JOIN - не существует', 3),
(35, 23, NULL, 'Что такое первичный ключ?', 'single', 'PRIMARY KEY уникально идентифирует каждую запись в таблице', 4),
(36, 23, NULL, 'Какая команда создаёт таблицу?', 'single', 'CREATE TABLE используется для создания новых таблиц', 5),
(37, 23, NULL, 'Что делает команда WHERE?', 'single', 'WHERE используется для фильтрации записей по условию', 6),
(38, 23, NULL, 'Выберите агрегатные функции:', 'multiple', 'COUNT, SUM, AVG, MIN, MAX - агрегатные функции. JOIN - это не функция', 7),
(39, 23, NULL, 'Что такое индекс в БД?', 'single', 'Индекс ускоряет поиск данных в таблице', 8),
(40, 23, NULL, 'Для чего используется DISTINCT?', 'single', 'DISTINCT возвращает только уникальные значения', 9),
(41, 24, NULL, 'Что такое SQL?', 'single', 'SQL - это Structured Query Language', 0),
(42, 24, NULL, 'Какая команда используется для выборки данных?', 'single', 'SELECT используется для выборки данных из таблиц', 1),
(43, 24, NULL, 'Что делает команда JOIN?', 'single', 'JOIN объединяет строки из двух или более таблиц', 2),
(44, 24, NULL, 'Выберите типы JOIN:', 'multiple', 'Существуют: INNER, LEFT, RIGHT, FULL. MIDDLE JOIN - не существует', 3),
(45, 24, NULL, 'Что такое первичный ключ?', 'single', 'PRIMARY KEY уникально идентифирует каждую запись в таблице', 4),
(46, 24, NULL, 'Какая команда создаёт таблицу?', 'single', 'CREATE TABLE используется для создания новых таблиц', 5),
(47, 24, NULL, 'Что делает команда WHERE?', 'single', 'WHERE используется для фильтрации записей по условию', 6),
(48, 24, NULL, 'Выберите агрегатные функции:', 'multiple', 'COUNT, SUM, AVG, MIN, MAX - агрегатные функции. JOIN - это не функция', 7),
(49, 24, NULL, 'Что такое индекс в БД?', 'single', 'Индекс ускоряет поиск данных в таблице', 8),
(50, 24, NULL, 'Для чего используется DISTINCT?', 'single', 'DISTINCT возвращает только уникальные значения', 9),
(51, 27, NULL, 'Что является основным инструментом планирования и контроля сроков для Руководителя проекта?', '', 'Графики проектирования (ГПР) указаны как основной инструмент планирования и контроля сроков.', 0),
(52, 27, NULL, 'На каком этапе жизненного цикла проекта Руководитель проекта (РП) назначается на проект?', '', 'РП назначается на проект после принятия положительного решения РОП на этапе Инициализации.', 1),
(53, 27, NULL, 'Какие действия выполняет РП на этапе \'Аудит данных и Планирование\'?', '', 'На этапе Аудита и Планирования РП создает папки, реестры, проводит аудит данных, формирует ГПР и рабочую группу, организует коммуникации.', 2),
(54, 27, NULL, 'Кто вносит отметку о завершении этапа в общий ГПР?', '', 'В инструкции указано: \'Вы вносите отметки в ГПР объекта, а З — в общий ГПР\'.', 3),
(55, 27, NULL, 'Какой максимальный срок отводится на этап \'Разработка Основных Технических Решений (ОТР)\'?', '', 'Для этапов \'Аудит данных и Планирование\', \'Организация внешних коммуникаций\' и \'Разработка ОТР\' указан срок \'до 2 рабочих дней\'.', 4),
(56, 27, NULL, 'Что должен сделать РП при поступлении изменений, влияющих на проектирование?', '', 'В разделе \'Управление изменениями\' указано: \'анализируйте их влияние, оповещайте команду, разрабатывайте план управления изменениями и вносите корректировки в ГПР\'.', 5),
(57, 27, NULL, 'Какой инструмент используется для четкого распределения зон ответственности?', '', 'Матрица RACI указана в \'Общих принципах работы\' как инструмент для четкого распределения зон ответственности.', 6),
(58, 27, NULL, 'На каком этапе РП лично передает полный комплект документации Менеджеру (М)?', '', 'Этап 19 называется \'Передача документации Менеджеру\', где РП лично производит передачу.', 7),
(59, 27, NULL, 'Каков срок для внесения отметки о завершении этапа в ГПР?', '', 'В \'Сквозных обязанностях\' указано: \'следите, чтобы отметки вносились своевременно (в течение 1 рабочего дня с момента завершения этапа)\'.', 8),
(60, 27, NULL, 'Что должен сделать РП после завершения этапа \'Подготовка планировок в работу\'?', '', 'На этапе 4 указано: \'После завершения подготовки и контроля качества внесите отметку о завершении этапа в ГПР объекта. Проинформируйте РОП и М\'.', 9),
(61, 27, NULL, 'Какой этап следует непосредственно после \'Разработки структурных схем\'?', '', 'Этапы идут в последовательности: 7. Структурные схемы -> 8. Схемы соединений -> 9. Доработка планов.', 10),
(62, 27, NULL, 'Какой формат используется для рабочего чата (РОЧ)?', '', 'В этапе 2 (\'Организуйте коммуникации\') указано: \'Создайте рабочий чат (РОЧ) в TEAMS\'.', 11),
(63, 27, NULL, 'Что является основной целью этапа \'Комплектация пакета документов в БД\'?', '', 'На этапе 18 РП контролирует, чтобы П разместил документацию в папке \'Заказчик\' в БД, и ВС проверил корректность.', 12),
(64, 27, NULL, 'Какие из перечисленных этапов относятся к \'Разработке дополнительной документации\'?', '', 'Этапы 10-16 включают спецификации, общие данные, кабельные журналы, инженерные расчеты и др.', 13),
(65, 29, NULL, 'question_text', '', 'explanation', 0),
(66, 29, NULL, 'На каком этапе жизненного цикла проекта назначается Руководитель проекта (РП)?', 'single', 'РП назначается на проект после принятия положительного решения РОП на этапе инициализации', 1),
(67, 29, NULL, 'Какой основной инструмент планирования и контроля сроков используется РП? ??', 'single', 'ГПР - основной инструмент планирования и контроля сроков согласно общим принципам работы', 2),
(68, 29, NULL, 'Какие элементы системы определяют работу РП?', 'multiple', 'Все перечисленные элементы являются основой системы работы РП согласно общим принципам', 3),
(69, 29, NULL, 'Сколько рабочих дней отводится на этап \'Аудит данных и Планирование\'?', 'single', 'Этап \'Аудит данных и Планирование\' занимает до 2 рабочих дней согласно описанию этапа 2', 4),
(70, 29, NULL, 'Что должен создать РП на этапе \'Аудит данных и Планирование\'?', 'multiple', 'Все перечисленные действия выполняются на этапе аудита данных и планирования', 5),
(71, 29, NULL, 'Кто отвечает за внесение отметок в общий ГПР?', 'single', 'РП вносит отметки в ГПР объекта, а З - в общий ГПР согласно сквозным обязанностям', 6),
(72, 29, NULL, 'На каком этапе РП должен создать рабочий чат (РОЧ) в TEAMS?', 'single', 'Создание рабочего чата осуществляется на этапе \'Аудит данных и Планирование\'', 7),
(73, 29, NULL, 'Какие действия относятся к мониторингу данных на этапах 6-9?', 'multiple', 'Все перечисленные действия входят в мониторинг данных согласно описанию этапов 6-9', 8),
(74, 29, NULL, 'Кто осуществляет внутреннюю проверку проекта на этапе 17?', 'single', 'На этапе проверки ВС выполняет проверку документации на соответствие стандартам', 9),
(75, 29, NULL, 'Что должен сделать РП после завершения каждого этапа?', 'multiple', 'Все перечисленные действия являются обязательными после завершения каждого этапа', 10),
(76, 29, NULL, 'Какой срок установлен для внесения отметок о завершении этапов в ГПР?', 'single', 'Отметки должны вноситься своевременно - в течение 1 рабочего дня с момента завершения этапа', 11),
(77, 29, NULL, 'На каком этапе П подготавливает и конвертирует документацию в папку \'Заказчик\'?', 'single', 'На этапе 18 П подготавливает, конвертирует и размещает документацию в папке \'Заказчик\'', 12),
(78, 29, NULL, 'Кто является центральным узлом коммуникации в проекте?', 'single', 'Согласно сквозным обязанностям, РП является центральным узлом коммуникации', 13),
(79, 29, NULL, 'Что должен сделать РП при поступлении изменений, влияющих на проектирование?', 'multiple', 'Все перечисленные действия входят в управление изменениями согласно сквозным обязанностям', 14),
(80, 29, NULL, 'Какой документ используется для четкого распределения зон ответственности?', 'single', 'Матрица RACI обеспечивает четкое распределение зон ответственности согласно общим принципам работы', 15);

-- --------------------------------------------------------

--
-- Структура таблицы `modx_test_sessions`
--
-- Создание: Окт 21 2025 г., 07:53
-- Последнее обновление: Окт 21 2025 г., 07:53
--

DROP TABLE IF EXISTS `modx_test_sessions`;
CREATE TABLE `modx_test_sessions` (
  `id` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `mode` enum('training','exam') NOT NULL,
  `question_order` text COMMENT 'JSON массив ID вопросов в порядке показа',
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `score` int(11) DEFAULT '0',
  `max_score` int(11) DEFAULT '0',
  `started_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `finished_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Дамп данных таблицы `modx_test_sessions`
--

INSERT INTO `modx_test_sessions` (`id`, `test_id`, `user_id`, `mode`, `question_order`, `status`, `score`, `max_score`, `started_at`, `finished_at`) VALUES
(1, 1, 2, 'training', NULL, 'completed', 6, 10, '2025-10-17 15:25:39', '2025-10-17 15:27:12'),
(2, 1, 2, 'training', NULL, 'completed', 11, 10, '2025-10-17 15:58:19', '2025-10-17 15:59:25'),
(3, 1, 2, 'training', '[10,8,3,1,2,6,5,4,7,9]', 'active', 0, 0, '2025-10-17 19:21:18', NULL),
(4, 1, 2, 'exam', '[5,8,7,10,9,1,6,2,4,3]', 'completed', 1, 10, '2025-10-17 19:21:51', '2025-10-17 19:22:14'),
(5, 1, 2, 'training', '[6,5,1,10,4,8,7,9,3,2]', 'active', 0, 0, '2025-10-17 19:22:18', NULL),
(6, 1, 2, 'training', '[3,1,5,9,10,4,2,7,8,6]', 'active', 0, 0, '2025-10-17 19:57:29', NULL),
(7, 1, 2, 'training', '[7,3,6,10,8,5,4,2,1,9]', 'active', 0, 0, '2025-10-17 19:58:09', NULL),
(8, 1, 2, 'training', '[7,9,3,8,4,6,10,1,2,5]', 'active', 0, 0, '2025-10-17 20:01:37', NULL),
(9, 1, 2, 'training', '[4,9,3,10,7,6,2,5,1,8]', 'active', 0, 0, '2025-10-17 20:01:45', NULL),
(10, 1, 2, 'training', '[7,2,9,6,5,1,3,4,10,8]', 'active', 0, 0, '2025-10-17 20:02:01', NULL),
(11, 1, 2, 'training', '[9,1,6,4,2,8,10,5,7,3]', 'active', 0, 0, '2025-10-17 20:04:44', NULL),
(12, 21, 2, 'training', '[20,15,17,11,18,14,12,19,16,13]', 'active', 0, 0, '2025-10-17 21:03:22', NULL),
(13, 1, 2, 'exam', '[4,5,6,7,1,3,9,10,8,2]', 'active', 0, 0, '2025-10-18 13:38:25', NULL),
(14, 1, 2, 'training', '[9,8,7,1,10,3,6,4,5,2]', 'active', 0, 0, '2025-10-18 14:13:47', NULL),
(15, 1, 2, 'training', '[8,1,10,9,5,3,6,4,2,7]', 'active', 0, 0, '2025-10-18 17:21:34', NULL),
(16, 1, 2, 'training', '[7,8,2,10,9,1,3,5,6,4]', 'active', 0, 0, '2025-10-19 10:25:39', NULL),
(17, 1, 2, 'training', '[3,2,6,10,4,5,1,9,8,7]', 'active', 0, 0, '2025-10-19 10:27:20', NULL),
(18, 1, 5, 'training', '[3,2,8,1,6,10,9,4,7,5]', 'active', 0, 0, '2025-10-20 13:38:06', NULL),
(19, 1, 5, 'training', '[3,9,7,6,5,1,10,4,2,8]', 'active', 0, 0, '2025-10-20 13:38:44', NULL),
(20, 1, 5, 'exam', '[1,5,4,8,3,6,9,10,7,2]', 'active', 0, 0, '2025-10-20 13:39:31', NULL),
(21, 1, 2, 'training', '[10,5,2,7,4,1,3,8,9,6]', 'active', 0, 0, '2025-10-20 14:58:32', NULL),
(22, 1, 2, 'training', '[9,4,10,6,3,7,5,2,8,1]', 'active', 0, 0, '2025-10-20 15:01:24', NULL),
(23, 1, 2, 'exam', '[9,1,4,6,2,7,8,5,10,3]', 'active', 0, 0, '2025-10-20 15:02:30', NULL),
(24, 27, 5, 'training', '[62,63,54,51,55,58,57,64,52,56,53,59,60,61]', 'completed', 10, 14, '2025-10-20 15:06:44', '2025-10-20 15:10:44'),
(25, 29, 5, 'training', '[66,75,78,67,72,73,80,79,77,74,65,69,71,70]', 'completed', 15, 14, '2025-10-20 15:25:16', '2025-10-20 15:29:16');

-- --------------------------------------------------------

--
-- Структура таблицы `modx_test_tests`
--
-- Создание: Окт 21 2025 г., 07:53
-- Последнее обновление: Окт 21 2025 г., 07:53
--

DROP TABLE IF EXISTS `modx_test_tests`;
CREATE TABLE `modx_test_tests` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `mode` enum('training','exam') DEFAULT 'training',
  `time_limit` int(11) DEFAULT '0' COMMENT 'Минуты, 0 = без ограничения',
  `pass_score` int(11) DEFAULT '70' COMMENT 'Процент для зачёта',
  `questions_per_session` int(11) DEFAULT '20' COMMENT 'Сколько вопросов показывать за попытку',
  `randomize_questions` tinyint(1) DEFAULT '1' COMMENT 'Перемешивать вопросы',
  `randomize_answers` tinyint(1) DEFAULT '1' COMMENT 'Перемешивать варианты ответов',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Дамп данных таблицы `modx_test_tests`
--

INSERT INTO `modx_test_tests` (`id`, `category_id`, `title`, `description`, `mode`, `time_limit`, `pass_score`, `questions_per_session`, `randomize_questions`, `randomize_answers`, `is_active`, `created_at`) VALUES
(1, 1, 'Основы SQL', 'Проверка знаний по SQL и реляционным БД', 'training', 30, 70, 10, 1, 1, 1, '2025-10-17 14:54:22'),
(2, NULL, 'Что за блядские мондавошки?', 'тест про животных', 'training', 0, 80, 16, 1, 1, 1, '2025-10-17 19:26:46'),
(3, NULL, 'Чей объектив шире?', '', 'training', 0, 70, 20, 1, 1, 1, '2025-10-17 20:09:50'),
(4, NULL, 'фыафыафыафыа', 'фыафывафыавфыа', 'training', 0, 70, 20, 1, 1, 1, '2025-10-17 20:13:19'),
(5, NULL, 'фыафыа', 'фыафыа', 'training', 0, 70, 20, 1, 1, 1, '2025-10-17 20:18:01'),
(6, NULL, 'фывафыа', 'фыавфыа', 'training', 0, 70, 20, 1, 1, 1, '2025-10-17 20:21:07'),
(7, NULL, 'фывафыа', 'фыавфыа', 'training', 0, 70, 20, 1, 1, 1, '2025-10-17 20:21:10'),
(8, NULL, 'фывафыа', 'фыавфыа', 'training', 0, 70, 20, 1, 1, 1, '2025-10-17 20:22:53'),
(9, NULL, 'asdf', 'asfd', 'training', 0, 70, 20, 1, 1, 1, '2025-10-17 20:23:38'),
(10, NULL, 'фа', 'фаывфыав', 'training', 0, 70, 20, 1, 1, 1, '2025-10-17 20:25:57'),
(11, NULL, 'фыва', 'фа', 'training', 0, 70, 20, 1, 1, 1, '2025-10-17 20:26:29'),
(12, NULL, 'фыва', 'фыав', 'training', 0, 70, 20, 1, 1, 1, '2025-10-17 20:27:52'),
(13, NULL, 'фыав', 'фыва', 'training', 0, 70, 20, 1, 1, 1, '2025-10-17 20:28:35'),
(14, NULL, 'фаы', 'фыва', 'training', 0, 70, 20, 1, 1, 1, '2025-10-17 20:30:33'),
(15, NULL, 'фавыфыаф', 'фыафыафыаа', 'training', 0, 70, 20, 1, 1, 1, '2025-10-17 20:32:06'),
(16, NULL, 'Ыфвыфыва', 'фывафыафыва', 'training', 0, 70, 20, 1, 1, 1, '2025-10-17 20:32:33'),
(17, NULL, 'Ыфвыфыва', 'фывафыафыва', 'training', 0, 70, 20, 1, 1, 1, '2025-10-17 20:32:37'),
(18, 1, 'фыав', 'фывафыафыа', 'training', 0, 70, 20, 1, 1, 1, '2025-10-17 20:33:33'),
(19, 1, 'фывафыа', 'фывафыа', 'training', 0, 70, 20, 1, 1, 1, '2025-10-17 20:37:50'),
(20, 1, 'фыфыа', 'фывафыафыа', 'training', 0, 70, 20, 1, 1, 1, '2025-10-17 20:39:56'),
(21, 1, 'ыфафа', 'фафыа', 'training', 0, 70, 20, 1, 1, 1, '2025-10-17 20:42:20'),
(22, NULL, 'Бабочка сизокрылая', 'описание бабочки', 'training', 0, 70, 20, 1, 1, 1, '2025-10-18 13:34:09'),
(23, 1, 'фаыфыафыаафыа', 'фыа', 'training', 0, 70, 20, 1, 1, 1, '2025-10-18 13:59:07'),
(24, 1, 'SQL', '', 'training', 0, 70, 20, 1, 1, 1, '2025-10-19 10:06:16'),
(25, 1, 'jhlkhlkjhlkhkllkhljk', '', 'training', 0, 70, 20, 1, 1, 1, '2025-10-20 13:40:48'),
(26, 3, 'у', '', 'training', 0, 70, 20, 1, 1, 1, '2025-10-20 14:46:03'),
(27, 3, 'РП', 'Тест для должности РП', 'training', 0, 70, 20, 1, 1, 1, '2025-10-20 14:56:07'),
(28, 1, 'SQL', '', 'training', 0, 70, 20, 1, 1, 1, '2025-10-20 15:15:28'),
(29, 3, 'ДИ РП', 'деятельность РП в проектировании', 'training', 0, 70, 14, 1, 1, 1, '2025-10-20 15:24:35');

-- --------------------------------------------------------

--
-- Структура таблицы `modx_test_user_answers`
--
-- Создание: Окт 21 2025 г., 07:53
-- Последнее обновление: Окт 21 2025 г., 07:53
--

DROP TABLE IF EXISTS `modx_test_user_answers`;
CREATE TABLE `modx_test_user_answers` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer_id` int(11) DEFAULT NULL,
  `answer_text` text,
  `is_correct` tinyint(1) DEFAULT '0',
  `answered_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Дамп данных таблицы `modx_test_user_answers`
--

INSERT INTO `modx_test_user_answers` (`id`, `session_id`, `question_id`, `answer_id`, `answer_text`, `is_correct`, `answered_at`) VALUES
(1, 1, 1, 1, NULL, 0, '2025-10-17 15:25:44'),
(2, 1, 2, 6, NULL, 0, '2025-10-17 15:25:58'),
(3, 1, 3, 11, NULL, 1, '2025-10-17 15:26:03'),
(4, 1, 4, 13, NULL, 0, '2025-10-17 15:26:14'),
(5, 1, 4, 15, NULL, 0, '2025-10-17 15:26:14'),
(6, 1, 5, 18, NULL, 1, '2025-10-17 15:26:24'),
(7, 1, 6, 23, NULL, 1, '2025-10-17 15:26:30'),
(8, 1, 7, 25, NULL, 1, '2025-10-17 15:26:38'),
(9, 1, 8, 29, NULL, 0, '2025-10-17 15:26:48'),
(10, 1, 8, 32, NULL, 0, '2025-10-17 15:26:48'),
(11, 1, 9, 34, NULL, 1, '2025-10-17 15:27:01'),
(12, 1, 10, 37, NULL, 1, '2025-10-17 15:27:10'),
(13, 2, 1, 2, NULL, 1, '2025-10-17 15:58:21'),
(14, 2, 2, 7, NULL, 1, '2025-10-17 15:58:26'),
(15, 2, 3, 11, NULL, 1, '2025-10-17 15:58:32'),
(16, 2, 4, 13, NULL, 0, '2025-10-17 15:58:42'),
(17, 2, 4, 15, NULL, 0, '2025-10-17 15:58:42'),
(18, 2, 4, 16, NULL, 0, '2025-10-17 15:58:42'),
(19, 2, 5, 18, NULL, 1, '2025-10-17 15:58:50'),
(20, 2, 6, 23, NULL, 1, '2025-10-17 15:58:55'),
(21, 2, 7, 25, NULL, 1, '2025-10-17 15:59:02'),
(22, 2, 8, 29, NULL, 1, '2025-10-17 15:59:12'),
(23, 2, 8, 30, NULL, 1, '2025-10-17 15:59:12'),
(24, 2, 8, 31, NULL, 1, '2025-10-17 15:59:12'),
(25, 2, 9, 34, NULL, 1, '2025-10-17 15:59:18'),
(26, 2, 10, 37, NULL, 1, '2025-10-17 15:59:22'),
(27, 4, 5, 20, NULL, 0, '2025-10-17 19:21:54'),
(28, 4, 8, 32, NULL, 0, '2025-10-17 19:21:56'),
(29, 4, 7, 28, NULL, 0, '2025-10-17 19:21:58'),
(30, 4, 10, 39, NULL, 0, '2025-10-17 19:22:00'),
(31, 4, 9, 35, NULL, 0, '2025-10-17 19:22:01'),
(32, 4, 1, 2, NULL, 1, '2025-10-17 19:22:03'),
(33, 4, 6, 22, NULL, 0, '2025-10-17 19:22:05'),
(34, 4, 2, 8, NULL, 0, '2025-10-17 19:22:07'),
(35, 4, 4, 13, NULL, 0, '2025-10-17 19:22:10'),
(36, 4, 4, 14, NULL, 0, '2025-10-17 19:22:10'),
(37, 4, 3, 10, NULL, 0, '2025-10-17 19:22:13'),
(38, 5, 6, 22, NULL, 0, '2025-10-17 19:22:20'),
(39, 6, 3, 9, NULL, 0, '2025-10-17 19:57:43'),
(40, 7, 7, 26, NULL, 0, '2025-10-17 19:58:12'),
(41, 11, 9, 34, NULL, 1, '2025-10-17 20:05:37'),
(42, 11, 1, 1, NULL, 0, '2025-10-17 20:05:40'),
(43, 12, 20, 78, NULL, 1, '2025-10-17 21:03:25'),
(44, 15, 8, 29, NULL, 0, '2025-10-18 17:21:49'),
(45, 15, 8, 31, NULL, 0, '2025-10-18 17:21:49'),
(46, 15, 1, 2, NULL, 1, '2025-10-18 17:22:08'),
(47, 15, 10, 37, NULL, 1, '2025-10-18 17:22:39'),
(48, 18, 3, 11, NULL, 1, '2025-10-20 13:38:13'),
(49, 19, 3, 10, NULL, 0, '2025-10-20 13:38:48'),
(50, 19, 9, 36, NULL, 0, '2025-10-20 13:38:59'),
(51, 20, 1, 4, NULL, 0, '2025-10-20 13:39:34'),
(52, 20, 5, 19, NULL, 0, '2025-10-20 13:39:36'),
(53, 24, 62, 246, NULL, 0, '2025-10-20 15:07:02'),
(54, 24, 63, 249, NULL, 1, '2025-10-20 15:07:15'),
(55, 24, 54, 215, NULL, 0, '2025-10-20 15:07:31'),
(56, 24, 51, 201, NULL, 1, '2025-10-20 15:07:52'),
(57, 24, 55, 217, NULL, 1, '2025-10-20 15:08:23'),
(58, 24, 58, 229, NULL, 1, '2025-10-20 15:08:34'),
(59, 24, 57, 225, NULL, 1, '2025-10-20 15:08:45'),
(60, 24, 64, 254, NULL, 0, '2025-10-20 15:09:10'),
(61, 24, 52, 205, NULL, 1, '2025-10-20 15:09:25'),
(62, 24, 56, 221, NULL, 1, '2025-10-20 15:09:37'),
(63, 24, 53, 210, NULL, 0, '2025-10-20 15:10:02'),
(64, 24, 59, 233, NULL, 1, '2025-10-20 15:10:15'),
(65, 24, 60, 237, NULL, 1, '2025-10-20 15:10:28'),
(66, 24, 61, 241, NULL, 1, '2025-10-20 15:10:42'),
(67, 25, 66, 261, NULL, 1, '2025-10-20 15:25:30'),
(68, 25, 75, 297, NULL, 0, '2025-10-20 15:26:01'),
(69, 25, 75, 298, NULL, 0, '2025-10-20 15:26:01'),
(70, 25, 75, 299, NULL, 0, '2025-10-20 15:26:01'),
(71, 25, 78, 309, NULL, 1, '2025-10-20 15:26:21'),
(72, 25, 67, 266, NULL, 0, '2025-10-20 15:26:34'),
(73, 25, 72, 285, NULL, 1, '2025-10-20 15:27:15'),
(74, 25, 73, 290, NULL, 0, '2025-10-20 15:27:32'),
(75, 25, 73, 292, NULL, 0, '2025-10-20 15:27:32'),
(76, 25, 80, 317, NULL, 1, '2025-10-20 15:27:44'),
(77, 25, 79, 313, NULL, 1, '2025-10-20 15:27:59'),
(78, 25, 79, 314, NULL, 1, '2025-10-20 15:27:59'),
(79, 25, 79, 315, NULL, 1, '2025-10-20 15:27:59'),
(80, 25, 79, 316, NULL, 1, '2025-10-20 15:27:59'),
(81, 25, 77, 305, NULL, 1, '2025-10-20 15:28:12'),
(82, 25, 74, 293, NULL, 1, '2025-10-20 15:28:24'),
(83, 25, 65, 258, NULL, 0, '2025-10-20 15:28:29'),
(84, 25, 69, 273, NULL, 1, '2025-10-20 15:28:41'),
(85, 25, 71, 282, NULL, 0, '2025-10-20 15:28:58'),
(86, 25, 70, 277, NULL, 1, '2025-10-20 15:29:15'),
(87, 25, 70, 278, NULL, 1, '2025-10-20 15:29:15'),
(88, 25, 70, 279, NULL, 1, '2025-10-20 15:29:15'),
(89, 25, 70, 280, NULL, 1, '2025-10-20 15:29:15');

-- --------------------------------------------------------

--
-- Структура таблицы `modx_test_user_stats`
--
-- Создание: Окт 21 2025 г., 07:53
--

DROP TABLE IF EXISTS `modx_test_user_stats`;
CREATE TABLE `modx_test_user_stats` (
  `user_id` int(11) NOT NULL,
  `tests_completed` int(11) DEFAULT '0',
  `tests_passed` int(11) DEFAULT '0',
  `total_score` int(11) DEFAULT '0',
  `avg_score_pct` decimal(5,2) DEFAULT '0.00',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Дамп данных таблицы `modx_test_user_stats`
--

INSERT INTO `modx_test_user_stats` (`user_id`, `tests_completed`, `tests_passed`, `total_score`, `avg_score_pct`, `updated_at`) VALUES
(2, 3, 1, 180, '60.00', '2025-10-18 12:30:33'),
(3, 15, 14, 1425, '95.00', '2025-10-18 12:49:23'),
(4, 15, 13, 1305, '87.00', '2025-10-18 12:49:23'),
(5, 2, 2, 178, '95.00', '2025-10-20 15:29:16');

-- --------------------------------------------------------

--
-- Структура таблицы `modx_users`
--
-- Создание: Окт 21 2025 г., 07:53
-- Последнее обновление: Окт 21 2025 г., 07:53
--

DROP TABLE IF EXISTS `modx_users`;
CREATE TABLE `modx_users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL DEFAULT '',
  `password` varchar(255) NOT NULL DEFAULT '',
  `cachepwd` varchar(255) NOT NULL DEFAULT '',
  `class_key` varchar(100) NOT NULL DEFAULT 'modUser',
  `active` tinyint(1) UNSIGNED NOT NULL DEFAULT '1',
  `remote_key` varchar(191) DEFAULT NULL,
  `remote_data` text,
  `hash_class` varchar(100) NOT NULL DEFAULT 'hashing.modNative',
  `salt` varchar(100) NOT NULL DEFAULT '',
  `primary_group` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `session_stale` text,
  `sudo` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `createdon` int(20) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `modx_users`
--

INSERT INTO `modx_users` (`id`, `username`, `password`, `cachepwd`, `class_key`, `active`, `remote_key`, `remote_data`, `hash_class`, `salt`, `primary_group`, `session_stale`, `sudo`, `createdon`) VALUES
(1, 'lmixru', '7cc831f4acdbdf11602f4ce32e7d44a2', '', 'modUser', 1, NULL, NULL, 'hashing.modMD5', '', 1, NULL, 1, 1722803141),
(2, 'testuser', '$2y$10$Ekv5MbO.lCc4bG0Ah2AZHOMS8JtU8eS5P6oJFlP33EInPK6nrPZte', '', 'modUser', 1, NULL, NULL, 'hashing.modNative', '', 0, NULL, 0, 1760701635),
(3, 'student1', '$2y$10$CHuuV2yEUTUNfwP6GM1wvuDm9M1Rmh6ddV45j3Bsgl8KqmoS7lt8e', '', 'modUser', 1, NULL, NULL, 'hashing.modNative', 'dded7bb1781181ea86279c2b80c778b8', 0, NULL, 0, 1760780962),
(4, 'student2', '$2y$10$c.etGyH4D1lnShbQ6sDSTuD/kIMTGXlkpj1jUmo1ZqtZOx/F9s9WK', '', 'modUser', 1, NULL, NULL, 'hashing.modNative', '57db24ad32f0c4d0901a73cc15b014e8', 0, NULL, 0, 1760780962),
(5, 'expert1', '$2y$10$Ubd6UZnDpXvoRSozI1a4qeiDHE1tob0qVmaWVfrlxSWSLJ7OaOFsm', '', 'modUser', 1, NULL, NULL, 'hashing.modNative', '525fac11bdd573f6b38e54e270d94a2d', 3, NULL, 0, 1760780963),
(6, 'expert2', '$2y$10$FdB6wm7QaRe0Ut6Pxrnk5.ZlNviCbOcF12h0pFQxudkZ8s015TqiW', '', 'modUser', 1, NULL, NULL, 'hashing.modNative', 'b14f2248cef29920703fca2ed8c1dc2e', 0, NULL, 0, 1760780963),
(7, 'admin2', '$2y$10$XVLlkII3I1K4EaX43pFJZOjDMJY/xcVOtElntjo.BgykA8yMB/qFu', '', 'modUser', 1, NULL, NULL, 'hashing.modNative', 'c9e12acbd22316cdc12e7d67cbbdaf45', 0, NULL, 0, 1760780963);

-- --------------------------------------------------------

--
-- Структура таблицы `modx_user_attributes`
--
-- Создание: Окт 21 2025 г., 07:53
-- Последнее обновление: Окт 21 2025 г., 07:53
--

DROP TABLE IF EXISTS `modx_user_attributes`;
CREATE TABLE `modx_user_attributes` (
  `id` int(10) UNSIGNED NOT NULL,
  `internalKey` int(10) NOT NULL,
  `fullname` varchar(100) NOT NULL DEFAULT '',
  `email` varchar(100) NOT NULL DEFAULT '',
  `phone` varchar(100) NOT NULL DEFAULT '',
  `mobilephone` varchar(100) NOT NULL DEFAULT '',
  `blocked` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `blockeduntil` int(11) NOT NULL DEFAULT '0',
  `blockedafter` int(11) NOT NULL DEFAULT '0',
  `logincount` int(11) NOT NULL DEFAULT '0',
  `lastlogin` int(11) NOT NULL DEFAULT '0',
  `thislogin` int(11) NOT NULL DEFAULT '0',
  `failedlogincount` int(10) NOT NULL DEFAULT '0',
  `sessionid` varchar(100) NOT NULL DEFAULT '',
  `dob` int(10) NOT NULL DEFAULT '0',
  `gender` int(1) NOT NULL DEFAULT '0',
  `address` text NOT NULL,
  `country` varchar(191) NOT NULL DEFAULT '',
  `city` varchar(191) NOT NULL DEFAULT '',
  `state` varchar(25) NOT NULL DEFAULT '',
  `zip` varchar(25) NOT NULL DEFAULT '',
  `fax` varchar(100) NOT NULL DEFAULT '',
  `photo` varchar(191) NOT NULL DEFAULT '',
  `comment` text NOT NULL,
  `website` varchar(191) NOT NULL DEFAULT '',
  `extended` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `modx_user_attributes`
--

INSERT INTO `modx_user_attributes` (`id`, `internalKey`, `fullname`, `email`, `phone`, `mobilephone`, `blocked`, `blockeduntil`, `blockedafter`, `logincount`, `lastlogin`, `thislogin`, `failedlogincount`, `sessionid`, `dob`, `gender`, `address`, `country`, `city`, `state`, `zip`, `fax`, `photo`, `comment`, `website`, `extended`) VALUES
(1, 1, 'Администратор по умолчанию', 'ivan.chuvaev@gmail.com', '', '', 0, 0, 0, 43, 1760780561, 1761033207, 0, '38e5c3a4d68a9cd41fe2fa0796976a56', 0, 0, '', '', '', '', '', '', '', '', '', NULL),
(2, 2, 'testuser', 'omskkapital@gmail.com', '', '', 0, 0, 0, 20, 1760780557, 1760797253, 0, 'ec4ea3e637ab8c039222ee1d3c071a76', 0, 0, '', '', '', '', '', '', '', '', '', '[]'),
(3, 3, 'Иван Студентов', 'student1@test.com', '', '', 0, 0, 0, 0, 0, 0, 0, '', 0, 0, '', '', '', '', '', '', '', '', '', NULL),
(4, 4, 'Мария Ученикова', 'student2@test.com', '', '', 0, 0, 0, 0, 0, 0, 0, '', 0, 0, '', '', '', '', '', '', '', '', '', NULL),
(5, 5, 'Виталий Кузлякин', 'expert1@test.com', '', '', 0, 0, 0, 2, 1760956579, 1760967610, 0, 'df2e1db3509f7c6004e15111c6221376', 0, 0, '', '', '', '', '', '', '', '', '', '[]'),
(6, 6, 'Анна Преподавателева', 'expert2@test.com', '', '', 0, 0, 0, 0, 0, 0, 0, '', 0, 0, '', '', '', '', '', '', '', '', '', NULL),
(7, 7, 'Сергей Администраторов', 'admin2@test.com', '', '', 1, 0, 0, 0, 0, 0, 0, '', 0, 0, '', '', '', '', '', '', '', '', '', NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `modx_user_group_roles`
--
-- Создание: Окт 21 2025 г., 07:53
-- Последнее обновление: Окт 21 2025 г., 07:53
--

DROP TABLE IF EXISTS `modx_user_group_roles`;
CREATE TABLE `modx_user_group_roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `description` mediumtext,
  `authority` int(10) UNSIGNED NOT NULL DEFAULT '9999'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `modx_user_group_roles`
--

INSERT INTO `modx_user_group_roles` (`id`, `name`, `description`, `authority`) VALUES
(1, 'Member', NULL, 9999),
(2, 'Super User', NULL, 0);

-- --------------------------------------------------------

--
-- Структура таблицы `modx_user_group_settings`
--
-- Создание: Окт 21 2025 г., 07:53
--

DROP TABLE IF EXISTS `modx_user_group_settings`;
CREATE TABLE `modx_user_group_settings` (
  `group` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `key` varchar(50) NOT NULL,
  `value` text,
  `xtype` varchar(75) NOT NULL DEFAULT 'textfield',
  `namespace` varchar(40) NOT NULL DEFAULT 'core',
  `area` varchar(191) NOT NULL DEFAULT '',
  `editedon` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Структура таблицы `modx_user_messages`
--
-- Создание: Окт 21 2025 г., 07:53
--

DROP TABLE IF EXISTS `modx_user_messages`;
CREATE TABLE `modx_user_messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `type` varchar(15) NOT NULL DEFAULT '',
  `subject` varchar(191) NOT NULL DEFAULT '',
  `message` text NOT NULL,
  `sender` int(10) NOT NULL DEFAULT '0',
  `recipient` int(10) NOT NULL DEFAULT '0',
  `private` tinyint(4) NOT NULL DEFAULT '0',
  `date_sent` datetime DEFAULT NULL,
  `read` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Структура таблицы `modx_user_settings`
--
-- Создание: Окт 21 2025 г., 07:53
--

DROP TABLE IF EXISTS `modx_user_settings`;
CREATE TABLE `modx_user_settings` (
  `user` int(11) NOT NULL DEFAULT '0',
  `key` varchar(50) NOT NULL DEFAULT '',
  `value` text,
  `xtype` varchar(75) NOT NULL DEFAULT 'textfield',
  `namespace` varchar(40) NOT NULL DEFAULT 'core',
  `area` varchar(191) NOT NULL DEFAULT '',
  `editedon` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `modx_site_content`
--
ALTER TABLE `modx_site_content`
  ADD PRIMARY KEY (`id`),
  ADD KEY `alias` (`alias`),
  ADD KEY `published` (`published`),
  ADD KEY `pub_date` (`pub_date`),
  ADD KEY `unpub_date` (`unpub_date`),
  ADD KEY `parent` (`parent`),
  ADD KEY `isfolder` (`isfolder`),
  ADD KEY `template` (`template`),
  ADD KEY `menuindex` (`menuindex`),
  ADD KEY `searchable` (`searchable`),
  ADD KEY `cacheable` (`cacheable`),
  ADD KEY `hidemenu` (`hidemenu`),
  ADD KEY `class_key` (`class_key`),
  ADD KEY `context_key` (`context_key`),
  ADD KEY `uri` (`uri`(191)),
  ADD KEY `uri_override` (`uri_override`),
  ADD KEY `hide_children_in_tree` (`hide_children_in_tree`),
  ADD KEY `show_in_tree` (`show_in_tree`),
  ADD KEY `cache_refresh_idx` (`parent`,`menuindex`,`id`);
ALTER TABLE `modx_site_content` ADD FULLTEXT KEY `content_ft_idx` (`pagetitle`,`longtitle`,`description`,`introtext`,`content`);

--
-- Индексы таблицы `modx_site_tmplvars`
--
ALTER TABLE `modx_site_tmplvars`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `category` (`category`),
  ADD KEY `locked` (`locked`),
  ADD KEY `rank` (`rank`),
  ADD KEY `static` (`static`);

--
-- Индексы таблицы `modx_site_tmplvar_contentvalues`
--
ALTER TABLE `modx_site_tmplvar_contentvalues`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tv_cnt` (`tmplvarid`,`contentid`),
  ADD KEY `tmplvarid` (`tmplvarid`),
  ADD KEY `contentid` (`contentid`);

--
-- Индексы таблицы `modx_test_answers`
--
ALTER TABLE `modx_test_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`);

--
-- Индексы таблицы `modx_test_categories`
--
ALTER TABLE `modx_test_categories`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `modx_test_questions`
--
ALTER TABLE `modx_test_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `test_id` (`test_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Индексы таблицы `modx_test_sessions`
--
ALTER TABLE `modx_test_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `test_id` (`test_id`);

--
-- Индексы таблицы `modx_test_tests`
--
ALTER TABLE `modx_test_tests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Индексы таблицы `modx_test_user_answers`
--
ALTER TABLE `modx_test_user_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `answer_id` (`answer_id`);

--
-- Индексы таблицы `modx_test_user_stats`
--
ALTER TABLE `modx_test_user_stats`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `idx_score` (`total_score`);

--
-- Индексы таблицы `modx_users`
--
ALTER TABLE `modx_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `class_key` (`class_key`),
  ADD KEY `remote_key` (`remote_key`),
  ADD KEY `primary_group` (`primary_group`);

--
-- Индексы таблицы `modx_user_attributes`
--
ALTER TABLE `modx_user_attributes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `internalKey` (`internalKey`);

--
-- Индексы таблицы `modx_user_group_roles`
--
ALTER TABLE `modx_user_group_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `authority` (`authority`);

--
-- Индексы таблицы `modx_user_group_settings`
--
ALTER TABLE `modx_user_group_settings`
  ADD PRIMARY KEY (`group`,`key`);

--
-- Индексы таблицы `modx_user_messages`
--
ALTER TABLE `modx_user_messages`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `modx_user_settings`
--
ALTER TABLE `modx_user_settings`
  ADD PRIMARY KEY (`user`,`key`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `modx_site_content`
--
ALTER TABLE `modx_site_content`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT для таблицы `modx_site_tmplvars`
--
ALTER TABLE `modx_site_tmplvars`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `modx_site_tmplvar_contentvalues`
--
ALTER TABLE `modx_site_tmplvar_contentvalues`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT для таблицы `modx_test_answers`
--
ALTER TABLE `modx_test_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=321;

--
-- AUTO_INCREMENT для таблицы `modx_test_categories`
--
ALTER TABLE `modx_test_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `modx_test_questions`
--
ALTER TABLE `modx_test_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT для таблицы `modx_test_sessions`
--
ALTER TABLE `modx_test_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT для таблицы `modx_test_tests`
--
ALTER TABLE `modx_test_tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT для таблицы `modx_test_user_answers`
--
ALTER TABLE `modx_test_user_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT для таблицы `modx_users`
--
ALTER TABLE `modx_users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT для таблицы `modx_user_attributes`
--
ALTER TABLE `modx_user_attributes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT для таблицы `modx_user_group_roles`
--
ALTER TABLE `modx_user_group_roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `modx_user_messages`
--
ALTER TABLE `modx_user_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `modx_test_answers`
--
ALTER TABLE `modx_test_answers`
  ADD CONSTRAINT `modx_test_answers_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `modx_test_questions` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `modx_test_questions`
--
ALTER TABLE `modx_test_questions`
  ADD CONSTRAINT `modx_test_questions_ibfk_1` FOREIGN KEY (`test_id`) REFERENCES `modx_test_tests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `modx_test_questions_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `modx_test_categories` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `modx_test_sessions`
--
ALTER TABLE `modx_test_sessions`
  ADD CONSTRAINT `modx_test_sessions_ibfk_1` FOREIGN KEY (`test_id`) REFERENCES `modx_test_tests` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `modx_test_tests`
--
ALTER TABLE `modx_test_tests`
  ADD CONSTRAINT `modx_test_tests_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `modx_test_categories` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `modx_test_user_answers`
--
ALTER TABLE `modx_test_user_answers`
  ADD CONSTRAINT `modx_test_user_answers_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `modx_test_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `modx_test_user_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `modx_test_questions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `modx_test_user_answers_ibfk_3` FOREIGN KEY (`answer_id`) REFERENCES `modx_test_answers` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
