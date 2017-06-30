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
	 * @param string $path - например, /path/to/namespace/common/
	 * @param string $name - например, common; используйте в twig как {% use '@common/template.twig' %}
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
	 * Возвращает массив с расширениями
	 * @return array
	 */
	public function getExtensions();
	
	/**
	 * Установка пакета рассширений
	 *
	 * @param array $extensions [ new ClassFirst(), new ClassLast() ]
	 *
	 * @return mixed
	 */
	public function setExtensions(array $extensions);
	
	/**
	 * Установка пути для шаблонов
	 *
	 * @param string $dir_path - ex. /path/to/templates/
	 */
	public function setTemplatesDirectory($dir_path);
	
	/**
	 * Установка пути для кэша
	 *
	 * @param string $dir_path - ex. /path/to/cache/
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