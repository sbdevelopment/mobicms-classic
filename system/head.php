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

/** @var Mobicms\Api\TemplateProcessorInterface $tplProcessor */
$tplProcessor = $container->get(Mobicms\Api\TemplateProcessorInterface::class);

$act = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : '';
$headmod = isset($headmod) ? $headmod : '';
$textl = isset($textl) ? $textl : $config['copyright'];
$keywords = isset($keywords) ? htmlspecialchars($keywords) : $config->meta_key;
$descriptions = isset($descriptions) ? htmlspecialchars($descriptions) : $config->meta_desc;
$header_params = [];

$header_params['seo'] = [
	'keywords' => $keywords,
	'description' => $descriptions
];
$header_params['config'] = [
	'lng' => $config->lng,
	'home_url' => $config->homeurl,
	'get_skin' => $tools->getSkin()
];
$header_params['titles'] = [
	'body' => $textl,
	'rss' =>  _t('Site News', 'system')
];

if($systemUser->id) {
	$header_params['user'] = [
		'id' => $systemUser->id,
		'name' => $systemUser->name
	];
}

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

            // Если было задано начертание шрифта:
            $font = $res['bold'] ? 'font-weight: bold;' : false;
            $font .= $res['italic'] ? ' font-style:italic;' : false;
            $font .= $res['underline'] ? ' text-decoration:underline;' : false;

            @$cms_ads[$res['type']] = [
				'name' => $name,
				'style' => 'color:'.$res['color'].';'.$font,
				'link' => ($res['show'] ? $tools->checkout($res['link']) : $config['homeurl'] . '/go.php?id=' . $res['id'])
			];
            
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
	$header_params['cms_ads']['header'] = $cms_ads[0];
}
// Логотип и переключатель языков
$header_params['logo'] = $tools->image('images/logo.png', ['class' => '']);

if ($headmod == 'mainpage' && count($config->lng_list) > 1) {
    $locale = App::getTranslator()->getLocale();
    $header_params['config']['locale'] = [
    	'name' => strtoupper($locale),
	    'flag' => $tools->getFlag($locale)
    ];
}

// Верхний блок с приветствием
$header_params['hi_message'] = _t('Hi', 'system') . ', ' . ($systemUser->id ? '<b>' . $systemUser->name . '</b>!' : _t('Guest', 'system') . '!');

// Главное меню пользователя
$header_params['personal_links'] = [
	'home' => (isset($_GET['err']) || $headmod != "mainpage" || ($headmod == 'mainpage' && $act) ? [
		'image' => $tools->image('images/menu_home.png'),
		'text' => _t('Home', 'system')
	] : false),
	'cabinet' => ($systemUser->id && $headmod != 'office' ? [
		'image' => $tools->image('images/menu_cabinet.png'),
		'text' => _t('Personal', 'system'),
	] : false),
	'auth' => (!$systemUser->id && $headmod != 'login' ? [
		'image' => $tools->image('images/menu_login.png'),
		'text' => _t('Login', 'system')
	] : false)
];
// Рекламный блок сайта
if (!empty($cms_ads[1])) {
	$header_params['cms_ads']['page'] = $cms_ads[1];
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
    $req = $db->query("SELECT * 
		FROM `cms_sessions` 
		WHERE `session_id` = '" . $db->quote($session) . "' 
		LIMIT 1");

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
	$header_params['ban'] = [
		'text' => _t('Ban', 'system'),
		'details' => _t('Details', 'system')
	];
}

// Непрочитанное
if ($systemUser->id) {
	$header_params['unread_mails'] = [];
	$i = 0;
	
    $new_sys_mail = $db->query("SELECT COUNT(*) 
		FROM `cms_mail` 
		WHERE `from_id`='" . $systemUser->id . "' 
		AND `read`='0' 
		AND `sys`='1' 
		AND `delete`!='" . $systemUser->id . "'")->fetchColumn();
	
    if ($new_sys_mail) {
    	$header_params['unread_mails']['unread'][$i++] = [
    		'name' => _t('System', 'system'),
		    'count' => $new_sys_mail,
		    'link' => '/mail/index.php?act=systems'
	    ];
    }

    $new_mail = $db->query("SELECT COUNT(*) FROM `cms_mail`
                            	LEFT JOIN `cms_contact` 
                            	ON `cms_mail`.`user_id`=`cms_contact`.`from_id` 
                            	AND `cms_contact`.`user_id`='" . $systemUser->id . "'
                            WHERE `cms_mail`.`from_id`='" . $systemUser->id . "'
                            AND `cms_mail`.`sys`='0'
                            AND `cms_mail`.`read`='0'
                            AND `cms_mail`.`delete`!='" . $systemUser->id . "'
                            AND `cms_contact`.`ban`!='1'")->fetchColumn();

    if ($new_mail) {
	    $header_params['unread_mails']['unread'][$i++] = [
		    'name' => _t('Mail', 'system'),
		    'count' => $new_mail,
		    'link' => '/mail/index.php?act=new'
	    ];
    }

    if ($systemUser->comm_count > $systemUser->comm_old) {
        $header_params['unread_mails']['unread'][$i++] = [
		    'name' => _t('Guestbook', 'system'),
		    'count' => ($systemUser->comm_count - $systemUser->comm_old),
	        'link' => '/profile/?act=guestbook&amp;user=' . $systemUser->id
	    ];
    }

    $new_album_comm = $db->query("SELECT COUNT(*) 
		FROM `cms_album_files` 
		WHERE `user_id` = '" . $systemUser->id . "' 
		AND `unread_comments` = 1")->fetchColumn();

    if ($new_album_comm) {
	    $header_params['unread_mails']['unread'][$i++] = [
		    'name' => _t('Comments', 'album_comments'),
		    'link' => '/album/index.php?act=top&amp;mod=my_new_comm'
	    ];
    }

    if (count($header_params['unread_mails'])) {
	    $header_params['unread_mails']['title'] = _t('Unread', 'system');
    }
}

$tplProcessor->templateRender('header',$header_params);
