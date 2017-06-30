<?php
/**
 * mobiCMS (https://mobicms.org/)
 * This file is part of mobiCMS Content Management System.
 *
 * @license     https://opensource.org/licenses/GPL-3.0 GPL-3.0 (see the LICENSE.md file)
 * @link        http://mobicms.org mobiCMS Project
 * @copyright   Copyright (C) mobiCMS Community
 */

namespace Mobicms;

use Psr\Container\ContainerInterface;

class Counters
{
    /**
     * @var \PDO
     */
    private $db;

    /**
     * @var Api\UserInterface::class
     */
    private $systemUser;

    /**
     * @var \Mobicms\Tools\Utilites
     */
    private $tools;

    private $homeurl;

    public function __invoke(ContainerInterface $container)
    {

        $this->db = $container->get(\PDO::class);
        $this->systemUser = $container->get(Api\UserInterface::class);
        $this->tools = $container->get(Api\ToolsInterface::class);
        $this->homeurl = $container->get('config')['mobicms']['homeurl'];

        return $this;
    }

    /**
     * Счетчик Фотоальбомов пользователей
     *
     * @return string
     */
    public function album()
    {
        $file = CACHE_PATH . 'count_album.dat';

        if (file_exists($file) && filemtime($file) > (time() - 600)) {
            $res = unserialize(file_get_contents($file));
            $album = $res['album'];
            $photo = $res['photo'];
            $new = $res['new'];
            $new_adm = $res['new_adm'];
        } else {
            $album = $this->db->query('SELECT COUNT(DISTINCT `user_id`) FROM `cms_album_files`')->fetchColumn();
            $photo = $this->db->query('SELECT COUNT(*) FROM `cms_album_files`')->fetchColumn();
            $new = $this->db->query('SELECT COUNT(*) FROM `cms_album_files` WHERE `time` > ' . (time() - 259200) . ' AND `access` = 4')->fetchColumn();
            $new_adm = $this->db->query('SELECT COUNT(*) FROM `cms_album_files` WHERE `time` > ' . (time() - 259200) . ' AND `access` > 1')->fetchColumn();
            file_put_contents($file, serialize(['album' => $album, 'photo' => $photo, 'new' => $new, 'new_adm' => $new_adm]), LOCK_EX);
        }

        $newcount = 0;
        if ($this->systemUser->rights >= 6 && $new_adm) {
            $newcount = $new_adm;
        } elseif ($new) {
            $newcount = $new;
        }

        return $album . '&#160;/&#160;' . $photo .
            ($newcount ? '&#160;/&#160;<span class="red"><a href="' . $this->homeurl . '/album/index.php?act=top">+' . $newcount . '</a></span>' : '');
    }

    /**
     * Счетчик загруз центра
     *
     * @return string
     */
    public function downloads()
    {
        $file = CACHE_PATH . 'count_downloads.dat';

        if (file_exists($file) && filemtime($file) > (time() - 600)) {
            $res = unserialize(file_get_contents($file));
            $total = isset($res['total']) ? $res['total'] : 0;
            $new = isset($res['new']) ? $res['new'] : 0;
            $mod = isset($res['mod']) ? $res['mod'] : 0;
        } else {
            $old = time() - (3 * 24 * 3600);
            $total = $this->db->query("SELECT COUNT(*) FROM `download__files` WHERE `type` = '2'")->fetchColumn();
            $new = $this->db->query("SELECT COUNT(*) FROM `download__files` WHERE `type` = '2' AND `time` > '$old'")->fetchColumn();
            $mod = $this->db->query("SELECT COUNT(*) FROM `download__files` WHERE `type` = '3'")->fetchColumn();

            file_put_contents($file, serialize(['total' => $total, 'new' => $new, 'mod' => $mod]), LOCK_EX);
        }

        if ($new > 0) {
            $total .= '&nbsp;/&nbsp;<span class="red"><a href="downloads/?act=new_files">+' . $new . '</a></span>';
        }

        if ($this->systemUser->rights == 4 || $this->systemUser->rights >= 6) {
            if ($mod) {
                $total .= '&nbsp;/&nbsp;<span class="red"><a href="downloads/?act=mod_files">м. ' . $mod . '</a></span>';
            }
        }

        return $total;
    }

    /**
     * Статистика Форума
     *
     * @return string
     */
    public function forum()
    {
        $file = CACHE_PATH . 'count_forum.dat';
        $new = '';

        if (file_exists($file) && filemtime($file) > (time() - 600)) {
            $res = unserialize(file_get_contents($file));
            $top = $res['top'];
            $msg = $res['msg'];
        } else {
            $top = $this->db->query("SELECT COUNT(*) FROM `forum` WHERE `type` = 't' AND `close` != '1'")->fetchColumn();
            $msg = $this->db->query("SELECT COUNT(*) FROM `forum` WHERE `type` = 'm' AND `close` != '1'")->fetchColumn();
            file_put_contents($file, serialize(['top' => $top, 'msg' => $msg]), LOCK_EX);
        }

        if ($this->systemUser->isValid() && ($new_msg = $this->forumNew()) > 0) {
            $new = '&#160;/&#160;<span class="red"><a href="' . $this->homeurl . '/forum/index.php?act=new">+' . $new_msg . '</a></span>';
        }

        return $top . '&#160;/&#160;' . $msg . $new;
    }

    /**
     * Счетчик непрочитанных тем на форуме
     *
     * $mod = 0   Возвращает число непрочитанных тем
     * $mod = 1   Выводит ссылки на непрочитанное
     *
     * @param int $mod
     * @return bool|int|string
     */
    public function forumNew($mod = 0)
    {
        if ($this->systemUser->isValid()) {
            $total = $this->db->query("SELECT COUNT(*) FROM `forum`
                LEFT JOIN `cms_forum_rdm` ON `forum`.`id` = `cms_forum_rdm`.`topic_id` AND `cms_forum_rdm`.`user_id` = '" . $this->systemUser->id . "'
                WHERE `forum`.`type` = 't'" . ($this->systemUser->rights >= 7 ? "" : " AND `forum`.`close` != 1") . "
                AND (`cms_forum_rdm`.`topic_id` IS NULL
                OR `forum`.`time` > `cms_forum_rdm`.`time`)")->fetchColumn();

            if ($mod) {
                return '<a href="index.php?act=new&amp;do=period">' . _t('Show for Period', 'system') . '</a>' .
                    ($total ? '<br><a href="index.php?act=new">' . _t('Unread', 'system') . '</a>&#160;<span class="red">(<b>' . $total . '</b>)</span>' : '');
            } else {
                return $total;
            }
        } else {
            if ($mod) {
                return '<a href="index.php?act=new">' . _t('Last activity', 'system') . '</a>';
            } else {
                return false;
            }
        }
    }

    /**
     * Статистика гостевой
     *
     * $mod = 1    колличество новых в гостевой
     * $mod = 2    колличество новых в Админ-Клубе
     *
     * @param int $mod
     * @return int|string
     */
    public function guestbook($mod = 0)
    {
        $count = 0;

        switch ($mod) {
            case 1:
                $count = $this->db->query('SELECT COUNT(*) FROM `guest` WHERE `adm`=0 AND `time` > ' . (time() - 86400))->fetchColumn();
                break;

            case 2:
                if ($this->systemUser->rights >= 1) {
                    $count = $this->db->query('SELECT COUNT(*) FROM `guest` WHERE `adm`=1 AND `time` > ' . (time() - 86400))->fetchColumn();
                    //$count = mysql_result(mysql_query("SELECT COUNT(*) FROM `guest` WHERE `adm`='1' AND `time` > '" . (time() - 86400) . "'"), 0);
                }
                break;

            default:
                $count = $this->db->query('SELECT COUNT(*) FROM `guest` WHERE `adm` = 0 AND `time` > ' . (time() - 86400))->fetchColumn();

                if ($this->systemUser->rights >= 1) {
                    $adm = $this->db->query('SELECT COUNT(*) FROM `guest` WHERE `adm`=\'1\' AND `time`> ' . (time() - 86400))->fetchColumn();
                    $count = $count . '&#160;/&#160;<span class="red"><a href="guestbook/index.php?act=ga&amp;do=set">' . $adm . '</a></span>';
                }
        }

        return $count;
    }

    /**
     * Статистика библиотеки
     *
     * @return string
     */
    public function library()
    {
        $file = CACHE_PATH . 'count_library.dat';

        if (file_exists($file) && filemtime($file) > (time() - 3200)) {
            $res = unserialize(file_get_contents($file));
            $total = $res['total'];
            $new = $res['new'];
            $mod = $res['mod'];
        } else {
            $total = $this->db->query('SELECT COUNT(*) FROM `library_texts` WHERE `premod` = 1')->fetchColumn();
            $new = $this->db->query('SELECT COUNT(*) FROM `library_texts` WHERE `time` > ' . (time() - 259200) . ' AND `premod` = 1')->fetchColumn();
            $mod = $this->db->query('SELECT COUNT(*) FROM `library_texts` WHERE `premod` = 0')->fetchColumn();

            file_put_contents($file, serialize(['total' => $total, 'new' => $new, 'mod' => $mod]), LOCK_EX);
        }

        if ($new) {
            $total .= '&#160;/&#160;<span class="red"><a href="' . $this->homeurl . '/library/index.php?act=new">+' . $new . '</a></span>';
        }

        if (($this->systemUser->rights == 5 || $this->systemUser->rights >= 6) && $mod) {
            $total .= '&#160;/&#160;<span class="red"><a href="' . $this->homeurl . '/library/index.php?act=premod">M:' . $mod . '</a></span>';
        }

        return $total;
    }

    /**
     * Счетчик посетителей онлайн
     *
     * @return string
     */
    public function online()
    {
        $file = CACHE_PATH . 'count_online.dat';

        if (file_exists($file) && filemtime($file) > (time() - 10)) {
            $res = unserialize(file_get_contents($file));
            $users = $res['users'];
            $guests = $res['guests'];
        } else {
            $users = $this->db->query('SELECT COUNT(*) FROM `users` WHERE `lastdate` > ' . (time() - 300))->fetchColumn();
            $guests = $this->db->query('SELECT COUNT(*) FROM `cms_sessions` WHERE `lastdate` > ' . (time() - 300))->fetchColumn();

            file_put_contents($file, serialize(['users' => $users, 'guests' => $guests]), LOCK_EX);
        }

        return '<a href="' . $this->homeurl . '/users/?act=online">' . $this->tools->image('images/menu_online.png') . $users . ' / ' . $guests . '</a>';
    }

    /**
     * Количество зарегистрированных пользователей
     *
     * @return string
     */
    public function users()
    {
        $file = CACHE_PATH . 'count_users.dat';

        if (file_exists($file) && filemtime($file) > (time() - 600)) {
            $res = unserialize(file_get_contents($file));
            $total = $res['total'];
            $new = $res['new'];
        } else {
            $total = $this->db->query('SELECT COUNT(*) FROM `users`')->fetchColumn();
            $new = $this->db->query('SELECT COUNT(*) FROM `users` WHERE `datereg` > ' . (time() - 86400))->fetchColumn();

            file_put_contents($file, serialize(['total' => $total, 'new' => $new]), LOCK_EX);
        }

        return $total . ($new ? '&#160;/&#160;<span class="red">+' . $new . '</span>' : '');
    }
    
    public function unread($type)
    {
	    $count = 0;
    	switch ($type) {
		    case 'sys_mail':
			    $count = $this->db->query("SELECT COUNT(*)
				FROM `cms_mail` 
				WHERE `from_id`='" . $this->systemUser->id . "' 
				AND `read`='0' 
				AND `sys`='1' 
				AND `delete`!='" . $this->systemUser->id . "'")->fetchColumn();
	        break;
		    case 'mail':
		    	$count = $this->db->query("SELECT COUNT(*) FROM `cms_mail`
	                LEFT JOIN `cms_contact` 
	                ON `cms_mail`.`user_id`=`cms_contact`.`from_id` 
	                AND `cms_contact`.`user_id`='" . $this->systemUser->id . "'
	            WHERE `cms_mail`.`from_id`='" . $this->systemUser->id . "'
	            AND `cms_mail`.`sys`='0'
	            AND `cms_mail`.`read`='0'
	            AND `cms_mail`.`delete`!='" . $this->systemUser->id . "'
	            AND `cms_contact`.`ban`!='1'")->fetchColumn();
		    break;
		    case 'album_comm':
			    $count = $this->db->query("SELECT COUNT(*) 
				FROM `cms_album_files` 
				WHERE `user_id` = '" . $this->systemUser->id . "' 
				AND `unread_comments` = 1")->fetchColumn();
	        break;
	    }
	    return $count;
    }
}
