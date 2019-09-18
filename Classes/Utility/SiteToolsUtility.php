<?php
declare(strict_types = 1);
namespace TYPO3\CMS\SiteTools\Utility;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Symfony\Component\Yaml\Yaml;

/**
 * Creates route enhancer configurations for a selected plugin
 */
class SiteToolsUtility
{
    /**
     * Creates the route enhancer config for the given plugin
     * @param string $extensionName
     * @param string $pluginName
     * @return array
     */
    public static function generateRouteEnhancer(string $extensionName, string $pluginName): array
    {
        $info = '';
        $routeEnhancer = ['routeEnhancers' => []];

        if (!empty($extensionName) && !empty($pluginName)) {
            $pluginControllers = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions']
                [$extensionName]['plugins'][$pluginName]['controllers'];

            $routeEnhancer['routeEnhancers'][$extensionName . $pluginName] = [
                'type' => 'Extbase',
                'limitToPages' => [1, 2, 3],
                'extension' => $extensionName,
                'plugin' => $pluginName,
                'routes' => [],
                'defaultController' => ''
            ];

            foreach ($pluginControllers as $pluginController => $pluginControllerConfig) {
                $info .= $pluginControllerConfig['alias'] . " ($pluginController)\r\n";

                foreach ($pluginControllerConfig['actions'] as $pluginActionKey => $pluginActionName) {
                    $info .= '->' . $pluginActionName . "\r\n";

                    if (empty($routeEnhancer['routeEnhancers'][$extensionName . $pluginName]['defaultController'])) {
                        $routeEnhancer['routeEnhancers'][$extensionName . $pluginName]['defaultController'] =
                            $pluginControllerConfig['alias'] . '::' . $pluginActionName;
                    }

                    $routeEnhancer['routeEnhancers'][$extensionName . $pluginName]['routes'][] = [
                        'routePath' => "/$pluginActionName/{page}",
                        '_controller' => $pluginControllerConfig['alias'] . '::' . $pluginActionName
                    ];
                }

                $info .= "\r\n";
            }
        }

        return [
            'extension' => $extensionName,
            'plugin' => $pluginName,
            'information' => $info,
            'configuration' => Yaml::dump($routeEnhancer, 99, 2)
        ];
    }
}
