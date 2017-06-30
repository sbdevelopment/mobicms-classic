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

use Twig_Environment;
use Twig_Loader_Filesystem;

class TemplateProcessor implements Api\TemplateProcessorInterface
{
	
	/**
	 * @var Twig_Loader_Filesystem
	 */
	private $tplLoader;
	
	/**
	 * @var Twig_Environment
	 */
	private $tplProcessor;
	
	private $tpl_path;
	
	private $cache_path;
	
	public function __construct(Twig_Loader_Filesystem $loader_filesystem) {
		
		$this->tplLoader = $loader_filesystem;
		
		$this->tplProcessor = new Twig_Environment($this->tplLoader, array(
			'cache' => $this->setCacheDirectory('/system/cache/Twig/'),
			'debug' => true
		));
		
		$this->tplProcessor->addExtension(new \Twig_Extension_Debug());
	}
	
	public function setTemplatesDirectory($dir_path)
	{
		$this->tpl_path = $_SERVER['DOCUMENT_ROOT'] . $dir_path;
	}
	
	private function setCacheDirectory($dir_path){
		$this->cache_path = $_SERVER['DOCUMENT_ROOT'] . $dir_path;
	}
	
	public function setNamespace($path, $name)
	{
		$this->tplLoader->addPath($_SERVER['DOCUMENT_ROOT'] . $path, $name);
	}
	
	public function templateRender($template,$data){
		return $this->tplProcessor->render($template.'.twig',$data);
	}
	
}
