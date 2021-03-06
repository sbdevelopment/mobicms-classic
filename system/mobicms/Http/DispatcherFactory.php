<?php
/**
 * mobiCMS (https://mobicms.org/)
 * This file is part of mobiCMS Content Management System.
 *
 * @license     https://opensource.org/licenses/GPL-3.0 GPL-3.0 (see the LICENSE.md file)
 * @link        http://mobicms.org mobiCMS Project
 * @copyright   Copyright (C) mobiCMS Community
 */

namespace Mobicms\Http;

use Psr\Container\ContainerInterface;
use FastRoute\Dispatcher\GroupCountBased;
use FastRoute\RouteCollector;

class DispatcherFactory
{
    public function __invoke(ContainerInterface $container)
    {
        return new GroupCountBased($container->get(RouteCollector::class)->getData());
    }
}
