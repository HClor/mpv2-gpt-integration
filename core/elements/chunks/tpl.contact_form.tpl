{* Чанк: tpl.contact_form - Email шаблон для контактной формы
   Вызывается из: contact_form.tpl через FormIt email hook
   Содержит: данные заявки для отправки на email
*}
<p>Пользователь оставил заявку на сайте {'http_host' | config}</p>
<br>
<p><strong>Контакты</strong></p>
<p>Имя: {$name | escape}</p>
<p>Телефон: {$phone | escape}</p>
<br>
<p><strong>Сообщение</strong></p>
<p>{$message | escape | nl2br}</p>