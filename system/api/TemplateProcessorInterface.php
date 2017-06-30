<?php
/**
 * mobiCMS (https://mobicms.org/)
 * This file is part of mobiCMS Content Management System.
 *
 * @license     https://opensource.org/licenses/GPL-3.0 GPL-3.0 (see the LICENSE.md file)
 * @link        http://mobicms.org mobiCMS Project
 * @copyright   Copyright (C) mobiCMS Community
 */

namespace Mobicms\Api;

/**
 * Interface TemplateProcessorInterface
 *
 * @package Mobicms\Api
 */
interface TemplateProcessorInterface
{
	
	/**
	 * Установка неймспейсов, для работы с ними в шаблонах
	 *
	 * @param string $path
	 * @param string $name
	 *
	 */
	public function setNamespace($path, $name);
	
	/**
	 * Добавление расширения в шаблонизатор
	 *
	 * @param $class - Extension className, ex.: new Class()
	 */
	public function addExtension($class);
	
	/**
	 * Установка пути для шаблонов
	 *
	 * @param string $dir_path
	 */
	public function setTemplatesDirectory($dir_path);
	
	/**
	 * Установка пути для кэша
	 *
	 * @param string $dir_path
	 */
	public function setCacheDirectory($dir_path);
	
	/**
	 * Рендеринг шаблона
	 * @param string $template
	 * @param array $data
	 *
	 * @return string HTML
	 */
	public function templateRender($template, $data);
}