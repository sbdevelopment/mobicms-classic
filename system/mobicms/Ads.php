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

use Mobicms\Api\AdsInterface;
use Mobicms\Api\ConfigInterface;
use Mobicms\Api\ToolsInterface;
use Mobicms\Api\UserInterface;
use Psr\Container\ContainerInterface;

class Ads implements AdsInterface
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
	 * @var ConfigInterface
	 */
	private $config;
	
	/**
	 * @var ToolsInterface
	 */
	private $tools;
	
	/**
	 * @var UserInterface
	 */
	private $systemUser;
	
	public function __construct(ContainerInterface $container) {
		
		defined('MOBICMS') or die('Error: restricted access');
		
		$this->container = $container;
		
		$this->db = $this->container->get(\PDO::class);
		
		$this->tools = $this->container->get(ToolsInterface::class);
		
		$this->systemUser = $this->container->get(UserInterface::class);
		
		$this->config = $this->container->get(ConfigInterface::class);
	}
	
	public function returnAds()
	{
		$act = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : '';
		$head_mod = isset($head_mod) ? $head_mod : '';
		
		$cms_ads = [];
		
		if (!isset($_GET['err']) && $act != '404' && $head_mod != 'admin') {
			$view = $this->systemUser->id ? 2 : 1;
			$layout = ($head_mod == 'mainpage' && !$act) ? 1 : 2;
			$req = $this->db->query("SELECT * 
				FROM `cms_ads` 
				WHERE `to` = '0' 
				AND (`layout` = '$layout' or `layout` = '0') 
				AND (`view` = '$view' or `view` = '0') 
				ORDER BY  `mesto` 
				ASC");
			
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
						'link' => ($res['show'] ? $this->tools->checkout($res['link']) : $this->config['homeurl'] . '/go.php?id=' . $res['id'])
					];
					
					if (($res['day'] != 0 && time() >= ($res['time'] + $res['day'] * 3600 * 24))
					    || ($res['count_link'] != 0 && $res['count'] >= $res['count_link'])
					) {
						$this->db->exec('UPDATE `cms_ads` 
							SET `to` = 1  
							WHERE `id` = ' . $res['id']);
					}
				}
			}
		}
		
		return $cms_ads;
	}
}