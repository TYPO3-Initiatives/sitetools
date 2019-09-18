<?php

defined('TYPO3_MODE') or die();

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
    'site',
    'tools',
    '',
    '',
    [
        'routeTarget' => \TYPO3\CMS\SiteTools\Controller\ToolsController::class . '::handleRequest',
        'access' => 'group,user',
        'name' => 'site_tools',
        'icon' => 'EXT:sitetools/Resources/Public/Icons/Extension.svg',
        'labels' => 'LLL:EXT:sitetools/Resources/Private/Language/locallang_module.xlf'
    ]
);
