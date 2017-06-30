<?php
/**
 * mobiCMS (https://mobicms.org/)
 * This file is part of mobiCMS Content Management System.
 *
 * @license     https://opensource.org/licenses/GPL-3.0 GPL-3.0 (see the LICENSE.md file)
 * @link        http://mobicms.org mobiCMS Project
 * @copyright   Copyright (C) mobiCMS Community
 */

namespace Controllers;

use Mobicms\Api\AdsInterface;
use Mobicms\Api\ConfigInterface;
use Mobicms\Api\ToolsInterface;
use Mobicms\Api\UserInterface;
use Mobicms\Http\Request;
use Psr\Container\ContainerInterface;

class BaseController
{
	/**
	 * @var ContainerInterface
	 */
	private $container;
	
	/**
	 * @var \PDO
	 */
	private $db;
	
	/**
	 * @var Request
	 */
	private $request;
	
	/**
	 * @var ConfigInterface
	 */
	private $config;
	
	/**
	 * @var ToolsInterface
	 */
	private $tools;
	
	/**
	 * @var AdsInterface
	 */
	private $ads;
	
	/**
	 * @var UserInterface
	 */
	private $systemUser;
	
	public function __construct(ContainerInterface $container)
	{
		defined('MOBICMS') or die('Error: restricted access');
		
		$this->container = $container;
		
		$this->db = $this->container->get(\PDO::class);
		
		$this->tools = $this->container->get(ToolsInterface::class);
		
		$this->request = $this->container->get(Request::class);
		
		$this->systemUser = $this->container->get(UserInterface::class);
		
		$this->config = $this->container->get(ConfigInterface::class);
		
		$this->ads = $this->container->get(AdsInterface::class);
		
	}
	
	public function getHeaderData()
	{
		$act = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : '';
		$headmod = isset($headmod) ? $headmod : '';
		$textl = isset($textl) ? $textl : $this->config['copyright'];
		$keywords = isset($keywords) ? htmlspecialchars($keywords) : $this->config->meta_key;
		$descriptions = isset($descriptions) ? htmlspecialchars($descriptions) : $this->config->meta_desc;
		
		$header_params = [];
		
		$header_params['seo'] = [
			'keywords' => $keywords,
			'description' => $descriptions
		];
		$header_params['config'] = [
			'lng' => $this->config->lng,
			'home_url' => $this->config->homeurl,
			'get_skin' => $this->tools->getSkin()
		];
		$header_params['titles'] = [
			'body' => $textl,
			'rss' =>  _t('Site News', 'system')
		];
		
		if($this->systemUser->id) {
			$header_params['user'] = [
				'id' => $this->systemUser->id,
				'name' => $this->systemUser->name
			];
		}
		
		// Рекламный модуль
		$cms_ads = $this->ads->returnAds();
		
		// Рекламный блок сайта
		if (isset($cms_ads[0])) {
			$header_params['cms_ads']['header'] = $cms_ads[0];
		}
		
		// Логотип и переключатель языков
		$header_params['logo'] = $this->tools->image('images/logo.png', ['class' => '']);
		
		if ($headmod == 'mainpage' && count($this->config->lng_list) > 1) {
			$locale = \App::getTranslator()->getLocale();
			$header_params['config']['locale'] = [
				'name' => strtoupper($locale),
				'flag' => $this->tools->getFlag($locale)
			];
		}
		
		// Верхний блок с приветствием
		$header_params['hi_message'] = _t('Hi', 'system') . ', ' . ($this->systemUser->id ? '<b>' . $this->systemUser->name . '</b>!' : _t('Guest', 'system') . '!');
		
		// Главное меню пользователя
		$header_params['personal_links'] = [
			'home' => (isset($_GET['err']) || $headmod != "mainpage" || ($headmod == 'mainpage' && $act) ? [
				'image' => $this->tools->image('images/menu_home.png'),
				'text' => _t('Home', 'system')
			] : false),
			'cabinet' => ($this->systemUser->id && $headmod != 'office' ? [
				'image' => $this->tools->image('images/menu_cabinet.png'),
				'text' => _t('Personal', 'system'),
			] : false),
			'auth' => (!$this->systemUser->id && $headmod != 'login' ? [
				'image' => $this->tools->image('images/menu_login.png'),
				'text' => _t('Login', 'system')
			] : false)
		];
		// Рекламный блок сайта
		if (!empty($cms_ads[1])) {
			$header_params['cms_ads']['page'] = $cms_ads[1];
		}
		
		// Фиксация местоположений посетителей
		$this->systemUser->setUserPosition();
		
		// Выводим сообщение о Бане
		if (!empty($this->systemUser->ban)) {
			$header_params['ban'] = [
				'text' => _t('Ban', 'system'),
				'details' => _t('Details', 'system')
			];
		}
		
		// Непрочитанное
		if ($this->systemUser->id) {
			$header_params['unread_mails'] = [];
			$i = 0;
			
			$new_sys_mail = $this->container->get('counters')->unread('sys_mail');
			
			if ($new_sys_mail) {
				$header_params['unread_mails']['unread'][$i++] = [
					'name' => _t('System', 'system'),
					'count' => $new_sys_mail,
					'link' => '/mail/index.php?act=systems'
				];
			}
			
			$new_mail = $this->container->get('counters')->unread('mail');
			
			if ($new_mail) {
				$header_params['unread_mails']['unread'][$i++] = [
					'name' => _t('Mail', 'system'),
					'count' => $new_mail,
					'link' => '/mail/index.php?act=new'
				];
			}
			
			if ($this->systemUser->comm_count > $this->systemUser->comm_old) {
				$header_params['unread_mails']['unread'][$i++] = [
					'name' => _t('Guestbook', 'system'),
					'count' => ($this->systemUser->comm_count - $this->systemUser->comm_old),
					'link' => '/profile/?act=guestbook&amp;user=' . $this->systemUser->id
				];
			}
			
			$new_album_comm = $this->container->get('counters')->unread('album_comm');
			
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
		return $header_params;
	}
	
	public function getFooterData()
	{
		
	}
}