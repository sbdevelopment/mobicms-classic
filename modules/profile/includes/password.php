<?php
/**
 * mobiCMS (https://mobicms.org/)
 * This file is part of mobiCMS Content Management System.
 *
 * @license     https://opensource.org/licenses/GPL-3.0 GPL-3.0 (see the LICENSE.md file)
 * @link        http://mobicms.org mobiCMS Project
 * @copyright   Copyright (C) mobiCMS Community
 */

defined('MOBICMS') or die('Error: restricted access');

/** @var Psr\Container\ContainerInterface $container */
$container = App::getContainer();

/** @var PDO $db */
$db = $container->get(PDO::class);

/** @var Mobicms\Api\UserInterface $systemUser */
$systemUser = $container->get(Mobicms\Api\UserInterface::class);

/** @var Mobicms\Api\ToolsInterface $tools */
$tools = $container->get(Mobicms\Api\ToolsInterface::class);

// Проверяем права доступа
if ($user['id'] != $systemUser->id && ($systemUser->rights < 7 || $user['rights'] > $systemUser->rights)) {
    echo $tools->displayError(_t('Access forbidden'));
    require ROOT_PATH . 'system/end.php';
    exit;
}

$textl = htmlspecialchars($user['name']) . ': ' . _t('Change Password');
require ROOT_PATH . 'system/head.php';

switch ($mod) {
    case 'change':
        // Меняем пароль
        $error = [];
        $oldpass = isset($_POST['oldpass']) ? trim($_POST['oldpass']) : '';
        $newpass = isset($_POST['newpass']) ? trim($_POST['newpass']) : '';
        $newconf = isset($_POST['newconf']) ? trim($_POST['newconf']) : '';
        $autologin = isset($_POST['autologin']) ? 1 : 0;

        if ($user['id'] != $systemUser->id) {
            if (!$newpass || !$newconf) {
                $error[] = _t('It is necessary to fill in all fields');
            }
        } else {
            if (!$oldpass || !$newpass || !$newconf) {
                $error[] = _t('It is necessary to fill in all fields');
            }
        }

        if (!$error && $user['id'] == $systemUser->id && md5(md5($oldpass)) !== $user['password']) {
            $error[] = _t('Old password entered incorrectly');
        }

        if ($newpass != $newconf) {
            $error[] = _t('The password confirmation you entered is wrong');
        }

        if (!$error && (strlen($newpass) < 3)) {
            $error[] = _t('The password must contain at least 3 characters');
        }

        if (!$error) {
            // Записываем в базу
            $db->prepare('UPDATE `users` SET `password` = ? WHERE `id` = ?')->execute([
                md5(md5($newpass)),
                $user['id'],
            ]);

            // Проверяем и записываем COOKIES
            if (isset($_COOKIE['cuid']) && isset($_COOKIE['cups'])) {
                setcookie('cups', md5($newpass), time() + 3600 * 24 * 365);
            }

            echo '<div class="gmenu"><p><b>' . _t('Password successfully changed') . '</b><br />' .
                '<a href="' . ($systemUser->id == $user['id'] ? '../login/' : '?user=' . $user['id']) . '">' . _t('Continue') . '</a></p>';
            echo '</div>';
        } else {
            echo $tools->displayError($error,
                '<a href="?act=password&amp;user=' . $user['id'] . '">' . _t('Repeat') . '</a>');
        }
        break;

    default:
        // Форма смены пароля
        echo '<div class="phdr"><b>' . _t('Change Password') . ':</b> ' . $user['name'] . '</div>';
        echo '<form action="?act=password&amp;mod=change&amp;user=' . $user['id'] . '" method="post">';

        if ($user['id'] == $systemUser->id) {
            echo '<div class="menu"><p>' . _t('Enter old password') . ':<br /><input type="password" name="oldpass" /></p></div>';
        }

        echo '<div class="gmenu"><p>' . _t('Enter new password') . ':<br />' .
            '<input type="password" name="newpass" /><br />' . _t('Repeat password') . ':<br />' .
            '<input type="password" name="newconf" /></p>' .
            '<p><input type="submit" value="' . _t('Save') . '" name="submit" />' .
            '</p></div></form>' .
            '<p><a href="?user=' . $user['id'] . '">' . _t('Profile') . '</a></p>';
}
