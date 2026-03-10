{* Чанк: contact_form - Контактная форма обратной связи
   Вызывается из: MODX ресурсов через [[contact_form]]
   Использует сниппеты: AjaxForm, FormIt
   Включает: form.contact_form, tpl.contact_form
*}
{var $form     = $form     ?: 'form.contact_form'}
{var $tpl      = $tpl      ?: 'tpl.contact_form'}
{var $subject  = $subject  ?: 'Сообщение с сайта ' ~ $_modx->config.http_host}
{var $validate = $validate ?: 'name:required,phone:required,check:required'}
{var $success  = $success  ?: '<div class="text-center py-3"><h3>Ваше сообщение отправлено</h3><p class="mx-auto">Наши специалисты свяжутся с вами<br>в ближайшее время.</p></div>'}
{var $error    = $error    ?: 'В форме содержатся ошибки!'}
{var $emailto  = $emailto  ?: $_modx->config.emailto}

{'!AjaxForm' | snippet : [
    'snippet' => 'FormIt',
    'hooks' => 'email',
    'form' => $form,
    'emailFrom' => $_modx->config.emailsender,
    'emailSubject' => $subject,
    'emailTo' => $emailto,
    'emailTpl' => $tpl,
    'successMessage' => $success,
    'validate' => $validate,
    'validationErrorMessage' => $error,
    'name.vTextRequired' => 'Пожалуйста, укажите, как к вам обращаться',
    'phone.vTextRequired' => 'Оставьте свой номер телефона, чтобы мы могли с вами связаться',
    'check.vTextRequired' => 'Вы должны дать разрешение на обработку своих персональных данных',
]}
