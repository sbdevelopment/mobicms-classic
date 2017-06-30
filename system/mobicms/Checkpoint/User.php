<?php
/**
 * mobiCMS (https://mobicms.org/)
 * This file is part of mobiCMS Content Management System.
 *
 * @license     https://opensource.org/licenses/GPL-3.0 GPL-3.0 (see the LICENSE.md file)
 * @link        http://mobicms.org mobiCMS Project
 * @copyright   Copyright (C) mobiCMS Community
 */

namespace Mobicms\Checkpoint;

use Mobicms\Api\UserInterface;
use Zend\Stdlib\ArrayObject;
use Psr\Container\ContainerInterface;
use Mobicms\Http\Request;

class User extends ArrayObject implements UserInterface
{
	
	/**
	 * @var \PDO
	 */
	private $db;
	
    private $userConfigObject;
	
	/**
	 * @var ContainerInterface
	 */
	private $container;
	
	/**
	 * @var UserInterface
	 */
	private $systemUser;
	
	/**
	 * @var Request
	 */
	private $request;
	
    /**
     * User constructor.
     *
     * @param array $input
     * @param $container
     */
    public function __construct(array $input, ContainerInterface $container)
    {
        parent::__construct($input, parent::ARRAY_AS_PROPS);
        
	    $this->container = $container;
	    $this->db = $this->container->get(\PDO::class);
	    $this->request = $this->container->get(Request::class);
	    $this->systemUser = $this->container->get(UserInterface::class);
    }

    /**
     * User validation
     *
     * @return bool
     */
    public function isValid()
    {
        if ($this->offsetGet('id') > 0
            && $this->offsetGet('preg') == 1
        ) {
            return true;
        }

        return false;
    }

    /**
     * Get User config
     *
     * @return UserConfig
     */
    public function getConfig()
    {
        if (null === $this->userConfigObject) {
            $this->userConfigObject = new UserConfig($this);
        }

        return $this->userConfigObject;
    }
    
    public function setUserPosition()
    {
	    $head_mod = isset($head_mod) ? $head_mod : '';
	    $sql = '';
	
	    if ($this->systemUser->isValid()) {
		    // Фиксируем местоположение авторизованных
		    $movings = $this->systemUser->movings;
		
		    if ($this->systemUser->lastdate < (time() - 300)) {
			    $movings = 0;
			    $sql .= " `sestime` = " . time() . ", ";
		    }
		
		    if ($this->systemUser->place != $head_mod) {
			    ++$movings;
			    $sql .= " `place` = " . $this->db->quote($head_mod) . ", ";
		    }
		
		    if ($this->systemUser->browser != $this->request->userAgent()) {
			    $sql .= " `browser` = " . $this->db->quote($this->request->userAgent()) . ", ";
		    }
		
		    $totalonsite = $this->systemUser->total_on_site;
		
		    if ($this->systemUser->lastdate > (time() - 300)) {
			    $totalonsite = $totalonsite + time() - $this->systemUser->lastdate;
		    }
		
		    $this->db->query("UPDATE `users` SET $sql
		        `movings` = '$movings',
		        `total_on_site` = '$totalonsite',
		        `lastdate` = '" . time() . "'
		        WHERE `id` = " . $this->systemUser->id);
	    } else {
		    // Фиксируем местоположение гостей
		    
		    $session = md5($this->request->ip() . $this->request->ipViaProxy() . $this->request->userAgent());
		    $req = $this->db->query("SELECT * 
				FROM `cms_sessions` 
				WHERE `session_id` = '" . $this->db->quote($session) . "' 
				LIMIT 1");
		
		    if ($req->rowCount()) {
			    // Если есть в базе, то обновляем данные
			    $res = $req->fetch();
			    $movings = ++$res['movings'];
			
			    if ($res['sestime'] < (time() - 300)) {
				    $movings = 1;
				    $sql .= " `sestime` = '" . time() . "', ";
			    }
			
			    if ($res['place'] != $head_mod) {
				    $sql .= " `place` = " . $this->db->quote($head_mod) . ", ";
			    }
			
			    $this->db->exec("UPDATE `cms_sessions` SET $sql
		            `movings` = '$movings',
		            `lastdate` = '" . time() . "'
		            WHERE `session_id` = " . $this->db->quote($session) . "
		        ");
		    } else {
			    // Если еще небыло в базе, то добавляем запись
			    $this->db->exec("INSERT INTO `cms_sessions` SET
		            `session_id` = '" . $session . "',
		            `ip` = '" . $this->request->ip() . "',
		            `ip_via_proxy` = '" . $this->request->ipViaProxy() . "',
		            `browser` = " . $this->db->quote($this->request->userAgent()) . ",
		            `lastdate` = '" . time() . "',
		            `sestime` = '" . time() . "',
		            `place` = " . $this->db->quote($head_mod));
		    }
	    }
    }
}
