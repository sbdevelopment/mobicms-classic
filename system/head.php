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

/** @var Mobicms\Api\ToolsInterface $tools */
$tools = $container->get(Mobicms\Api\ToolsInterface::class);

/** @var Mobicms\Http\Request $request */
$request = $container->get(Mobicms\Http\Request::class);

/** @var Mobicms\Api\UserInterface $systemUser */
$systemUser = $container->get(Mobicms\Api\UserInterface::class);

/** @var Mobicms\Api\ConfigInterface $config */
$config = $container->get(Mobicms\Api\ConfigInterface::class);

$act = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : '';
$headmod = isset($headmod) ? $headmod : '';
$textl = isset($textl) ? $textl : $config['copyright'];
$keywords = isset($keywords) ? htmlspecialchars($keywords) : $config->meta_key;
$descriptions = isset($descriptions) ? htmlspecialchars($descriptions) : $config->meta_desc;
$header_params = [];
echo '<!DOCTYPE html>' .
    "\n" . '<html lang="' . $config->lng . '">' .
    "\n" . '<head>' .
    "\n" . '<meta charset="utf-8">' .
    "\n" . '<meta http-equiv="X-UA-Compatible" content="IE=edge">' .
    "\n" . '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=2.0, user-scalable=yes">' .
    "\n" . '<meta name="HandheldFriendly" content="true">' .
    "\n" . '<meta name="MobileOptimized" content="width">' .
    "\n" . '<meta content="yes" name="apple-mobile-web-app-capable">' .
    "\n" . '<meta name="Generator" content="mobiCMS, https://mobicms.org">' .
    "\n" . '<meta name="keywords" content="' . $keywords . '">' .
    "\n" . '<meta name="description" content="' . $descriptions . '">' .
    "\n" . '<link rel="stylesheet" href="' . $config->homeurl . '/theme/' . $tools->getSkin() . '/style.css">' .
    "\n" . '<link rel="shortcut icon" href="' . $config->homeurl . '/favicon.ico">' .
    "\n" . '<link rel="alternate" type="application/rss+xml" title="RSS | ' . _t('Site News', 'system') . '" href="' . $config->homeurl . '/rss/">' .
    "\n" . '<title>' . $textl . '</title>' .
    "\n" . '</head><body>';

// Рекламный модуль
$cms_ads = [];

if (!isset($_GET['err']) && $act != '404' && $headmod != 'admin') {
    $view = $systemUser->id ? 2 : 1;
    $layout = ($headmod == 'mainpage' && !$act) ? 1 : 2;
    $req = $db->query("SELECT * FROM `cms_ads` WHERE `to` = '0' AND (`layout` = '$layout' or `layout` = '0') AND (`view` = '$view' or `view` = '0') ORDER BY  `mesto` ASC");

    if ($req->rowCount()) {
        while ($res = $req->fetch()) {
            $name = explode("|", $res['name']);
            $name = htmlentities($name[mt_rand(0, (count($name) - 1))], ENT_QUOTES, 'UTF-8');

            if (!empty($res['color'])) {
                $name = '<span style="color:#' . $res['color'] . '">' . $name . '</span>';
            }

            // Если было задано начертание шрифта, то применяем
            $font = $res['bold'] ? 'font-weight: bold;' : false;
            $font .= $res['italic'] ? ' font-style:italic;' : false;
            $font .= $res['underline'] ? ' text-decoration:underline;' : false;

            if ($font) {
                $name = '<span style="' . $font . '">' . $name . '</span>';
            }

            @$cms_ads[$res['type']] .= '<a href="' . ($res['show'] ? $tools->checkout($res['link']) : $config['homeurl'] . '/go.php?id=' . $res['id']) . '">' . $name . '</a><br>';

            if (($res['day'] != 0 && time() >= ($res['time'] + $res['day'] * 3600 * 24))
                || ($res['count_link'] != 0 && $res['count'] >= $res['count_link'])
            ) {
                $db->exec('UPDATE `cms_ads` SET `to` = 1  WHERE `id` = ' . $res['id']);
            }
        }
    }
}

// Рекламный блок сайта
if (isset($cms_ads[0])) {
    echo $cms_ads[0];
}

// Выводим логотип и переключатель языков
echo '<table style="width: 100%;" class="logo"><tr>' .
    '<td valign="bottom"><a href="' . $config['homeurl'] . '">' . $tools->image('images/logo.png', ['class' => '']) . '</a></td>';

if ($headmod == 'mainpage' && count($config->lng_list) > 1) {
    $locale = App::getTranslator()->getLocale();
    echo '<td align="right"><a href="' . $config->homeurl . '/go.php?lng"><b>' . strtoupper($locale) . '</b></a>&#160;<a href="' . $config->homeurl . '/go.php?lng">' . $tools->getFlag($locale) . '</a></td>';
}

echo '</tr></table>';

// Выводим верхний блок с приветствием
//echo '<div class="header"> ' . _t('Hi', 'system') . ', ' . ($systemUser->id ? '<b>' . $systemUser->name . '</b>!' : _t('Guest', 'system') . '!') . '</div>';

// Главное меню пользователя
echo '<div class="tmn">' .
    (isset($_GET['err']) || $headmod != "mainpage" || ($headmod == 'mainpage' && $act) ? '<a href=\'' . $config['homeurl'] . '\'>' . $tools->image('images/menu_home.png') . _t('Home', 'system') . '</a><br>' : '') .
    ($systemUser->id && $headmod != 'office' ? '<a href="' . $config['homeurl'] . '/profile/?act=office">' . $tools->image('images/menu_cabinet.png') . $systemUser->name . ' <small style="color: #a8b5c4">(' . _t('Personal', 'system') . ')</small></a><br>' : '') .
    (!$systemUser->id && $headmod != 'login' ? $tools->image('images/menu_login.png') . '<a href="' . $config['homeurl'] . '/login/">' . _t('Login', 'system') . '</a>' : '') .
    '</div><div class="maintxt">';

// Рекламный блок сайта
if (!empty($cms_ads[1])) {
    echo '<div class="gmenu">' . $cms_ads[1] . '</div>';
}

// Фиксация местоположений посетителей
$sql = '';

if ($systemUser->isValid()) {
    // Фиксируем местоположение авторизованных
    $movings = $systemUser->movings;

    if ($systemUser->lastdate < (time() - 300)) {
        $movings = 0;
        $sql .= " `sestime` = " . time() . ", ";
    }

    if ($systemUser->place != $headmod) {
        ++$movings;
        $sql .= " `place` = " . $db->quote($headmod) . ", ";
    }

    if ($systemUser->browser != $request->userAgent()) {
        $sql .= " `browser` = " . $db->quote($request->userAgent()) . ", ";
    }

    $totalonsite = $systemUser->total_on_site;

    if ($systemUser->lastdate > (time() - 300)) {
        $totalonsite = $totalonsite + time() - $systemUser->lastdate;
    }

    $db->query("UPDATE `users` SET $sql
        `movings` = '$movings',
        `total_on_site` = '$totalonsite',
        `lastdate` = '" . time() . "'
        WHERE `id` = " . $systemUser->id);
} else {
    // Фиксируем местоположение гостей
    $movings = 0;
    $session = md5($request->ip() . $request->ipViaProxy() . $request->userAgent());
    $req = $db->query("SELECT * FROM `cms_sessions` WHERE `session_id` = " . $db->quote($session) . " LIMIT 1");

    if ($req->rowCount()) {
        // Если есть в базе, то обновляем данные
        $res = $req->fetch();
        $movings = ++$res['movings'];

        if ($res['sestime'] < (time() - 300)) {
            $movings = 1;
            $sql .= " `sestime` = '" . time() . "', ";
        }

        if ($res['place'] != $headmod) {
            $sql .= " `place` = " . $db->quote($headmod) . ", ";
        }

        $db->exec("UPDATE `cms_sessions` SET $sql
            `movings` = '$movings',
            `lastdate` = '" . time() . "'
            WHERE `session_id` = " . $db->quote($session) . "
        ");
    } else {
        // Если еще небыло в базе, то добавляем запись
        $db->exec("INSERT INTO `cms_sessions` SET
            `session_id` = '" . $session . "',
            `ip` = '" . $request->ip() . "',
            `ip_via_proxy` = '" . $request->ipViaProxy() . "',
            `browser` = " . $db->quote($request->userAgent()) . ",
            `lastdate` = '" . time() . "',
            `sestime` = '" . time() . "',
            `place` = " . $db->quote($headmod) . "
        ");
    }
}

// Выводим сообщение о Бане
if (!empty($systemUser->ban)) {
    echo '<div class="alarm">' . _t('Ban', 'system') . '&#160;<a href="' . $config['homeurl'] . '/profile/?act=ban">' . _t('Details', 'system') . '</a></div>';
}

// Ссылки на непрочитанное
if ($systemUser->id) {
    $list = [];
    $new_sys_mail = $db->query("SELECT COUNT(*) FROM `cms_mail` WHERE `from_id`='" . $systemUser->id . "' AND `read`='0' AND `sys`='1' AND `delete`!='" . $systemUser->id . "'")->fetchColumn();

    if ($new_sys_mail) {
        $list[] = '<a href="' . $config['homeurl'] . '/mail/index.php?act=systems">' . _t('System', 'system') . '</a> (+' . $new_sys_mail . ')';
    }

    $new_mail = $db->query("SELECT COUNT(*) FROM `cms_mail`
                            LEFT JOIN `cms_contact` ON `cms_mail`.`user_id`=`cms_contact`.`from_id` AND `cms_contact`.`user_id`='" . $systemUser->id . "'
                            WHERE `cms_mail`.`from_id`='" . $systemUser->id . "'
                            AND `cms_mail`.`sys`='0'
                            AND `cms_mail`.`read`='0'
                            AND `cms_mail`.`delete`!='" . $systemUser->id . "'
                            AND `cms_contact`.`ban`!='1'")->fetchColumn();

    if ($new_mail) {
        $list[] = '<a href="' . $config['homeurl'] . '/mail/index.php?act=new">' . _t('Mail', 'system') . '</a> (+' . $new_mail . ')';
    }

    if ($systemUser->comm_count > $systemUser->comm_old) {
        $list[] = '<a href="' . $config['homeurl'] . '/profile/?act=guestbook&amp;user=' . $systemUser->id . '">' . _t('Guestbook', 'system') . '</a> (' . ($systemUser->comm_count - $systemUser->comm_old) . ')';
    }

    $new_album_comm = $db->query('SELECT COUNT(*) FROM `cms_album_files` WHERE `user_id` = ' . $systemUser->id . ' AND `unread_comments` = 1')->fetchColumn();

    if ($new_album_comm) {
        $list[] = '<a href="' . $config['homeurl'] . '/album/index.php?act=top&amp;mod=my_new_comm">' . _t('Comments', 'system') . '</a>';
    }

    if (!empty($list)) {
        echo '<div class="rmenu">' . _t('Unread', 'system') . ': ' . implode(', ', $list) . '</div>';
    }
}

$tools->templateRender('header',$header_params);
