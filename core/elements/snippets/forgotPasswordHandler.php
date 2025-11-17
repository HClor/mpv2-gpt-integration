<?php
/* TS FORGOT PASSWORD v1.1 - Added CSRF Protection */

require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

$errors = [];
$success = false;

if ($_POST && isset($_POST['reset_request'])) {
    // CSRF защита
    if (!CsrfProtection::validateRequest($_POST)) {
        $errors[] = 'Ошибка безопасности. Обновите страницу и попробуйте снова.';
    } else {
        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            $errors[] = 'Введите email';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Неверный формат email';
        } else {
        $profile = $modx->getObject('modUserProfile', ['email' => $email]);
        
        if ($profile) {
            $user = $profile->getOne('User');
            if ($user) {
                $resetToken = bin2hex(random_bytes(32));
                $resetExpiry = time() + 3600;
                
                $extended = $profile->get('extended') ?: [];
                $extended['reset_token'] = $resetToken;
                $extended['reset_expiry'] = $resetExpiry;
                $profile->set('extended', $extended);
                $profile->save();
                
                $siteUrl = rtrim($modx->getOption('site_url'), '/');
                $resetId = $modx->getOption('lms.reset_page', null, 0);
                $resetLink = $resetId ? $modx->makeUrl($resetId, 'web', 'token=' . $resetToken, 'full') : $siteUrl . '/reset-password?token=' . $resetToken;
                
                $emailBody = "Здравствуйте, " . $user->username . "!\n\n";
                $emailBody .= "Вы запросили восстановление пароля.\n";
                $emailBody .= "Для установки нового пароля перейдите по ссылке:\n";
                $emailBody .= "$resetLink\n\n";
                $emailBody .= "Ссылка действительна в течение 1 часа.\n\n";
                $emailBody .= "Если вы не запрашивали восстановление пароля — проигнорируйте это письмо.";
                
                $mail = $modx->getService('mail', 'mail.modPHPMailer');
                $mail->set(modMail::MAIL_BODY, $emailBody);
                $mail->set(modMail::MAIL_FROM, $modx->getOption('emailsender'));
                $mail->set(modMail::MAIL_FROM_NAME, $modx->getOption('site_name'));
                $mail->set(modMail::MAIL_SUBJECT, 'Восстановление пароля');
                $mail->address('to', $email);
                
                if ($mail->send()) {
                    $success = true;
                } else {
                    $errors[] = 'Ошибка отправки письма: ' . $mail->mailer->ErrorInfo;
                }
                $mail->reset();
            } else {
                $success = true;
            }
            } else {
                $success = true;
            }
        }
    }
}

if ($success) {
    $authId = $modx->getOption('lms.auth_page', null, 0);
    return '<div class="alert alert-success">
        <h4>✅ Письмо отправлено</h4>
        <p>Если аккаунт с таким email существует, на него отправлена ссылка для восстановления пароля.</p>
        <p>Проверьте почту и перейдите по ссылке.</p>
        <p><a href="' . $modx->makeUrl($authId ?: $modx->getOption('site_start')) . '">Вернуться на страницу входа</a></p>
    </div>';
}

$errorMsg = '';
if (!empty($errors)) {
    $errorMsg = '<div class="alert alert-danger"><ul class="mb-0">';
    foreach ($errors as $error) {
        $errorMsg .= '<li>' . htmlspecialchars($error) . '</li>';
    }
    $errorMsg .= '</ul></div>';
}

$emailValue = htmlspecialchars($_POST['email'] ?? '');
$authId = $modx->getOption('lms.auth_page', null, 0);

return $errorMsg . '<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>Восстановление пароля</h3>
                </div>
                <div class="card-body">
                    <p>Введите email, указанный при регистрации. На него будет отправлена ссылка для установки нового пароля.</p>
                    <form method="post">
                        <input type="hidden" name="reset_request" value="1">
                        ' . CsrfProtection::getTokenField() . '
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="' . $emailValue . '" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Отправить ссылку</button>
                    </form>
                    <div class="text-center mt-3">
                        <a href="' . $modx->makeUrl($authId ?: $modx->getOption('site_start')) . '">← Вернуться к входу</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>';