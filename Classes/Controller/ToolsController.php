<?php
declare(strict_types = 1);
namespace TYPO3\CMS\SiteTools\Controller;

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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\SiteTools\Utility\SiteToolsUtility;
use TYPO3Fluid\Fluid\View\ViewInterface;

/**
 * Creates route enhancer configurations for a selected plugin
 * @internal This class is a specific TYPO3 Backend controller implementation and is not part of the Public TYPO3 API.
 */
class ToolsController
{
    /**
     * The name of the module
     *
     * @var string
     */
    protected $moduleName = 'site_tools';

    /**
     * ModuleTemplate object
     *
     * @var ModuleTemplate
     */
    protected $moduleTemplate;

    /**
     * @var ViewInterface
     */
    protected $view;

    /**
     * @var ServerRequestInterface
     */
    protected $request;

    /**
     * @var IconFactory
     */
    protected $iconFactory;

    /**
     * @var UriBuilder
     */
    protected $uriBuilder;

    /**
     * The tools menu items array. Each key represents a key for which values
     * can range between the items in the array of that key.
     *
     * @see setupToolsMenu()
     * @var array
     */
    protected $tools = [
        'actions' => []
    ];

    /**
     * Instantiate the form protection before a simulated user is initialized.
     */
    public function __construct()
    {
        $this->getLanguageService()->includeLLFile(
            'EXT:sitetools/Resources/Private/Language/locallang_module.xlf'
        );
        $this->moduleTemplate = GeneralUtility::makeInstance(ModuleTemplate::class);
        $this->moduleTemplate->getPageRenderer()->loadRequireJsModule('TYPO3/CMS/Backend/Modal');
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $this->uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $this->setupToolsMenu();
    }

    /**
     * Injects the request object for the current request, and renders the overview of all redirects
     *
     * @param ServerRequestInterface $request the current request
     * @return ResponseInterface the response with the content
     */
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->getLanguageService()->includeLLFile(
            'EXT:sitetools/Resources/Private/Language/locallang_module.xlf'
        );
        $this->request = $request;
        $action = $this->getSelectedAction();
        $this->initializeView($action);
        $this->getToolsMenu();
        $this->getButtons();

        $result = call_user_func_array([$this, $action . 'Action'], [$request]);
        if ($result instanceof ResponseInterface) {
            return $result;
        }
        $this->moduleTemplate->setContent($this->view->render());
        return new HtmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * Show all available actions
     * @param ServerRequestInterface $request
     */
    protected function overviewAction(ServerRequestInterface $request)
    {
        $modules = [];
        foreach ($this->tools['actions'] as $controller => $title) {
            if ($controller == 'overview') {
                continue;
            }

            $modules[$controller] = (string)$this->uriBuilder->buildUriFromRoute(
                $this->moduleName,
                [
                    'action' => $controller
                ]
            );
        }
        $this->view->assign('availableFunctions', $modules);
    }

    /**
     * Show all available actions
     * @param ServerRequestInterface $request
     */
    protected function routeEnhancerGeneratorAction(ServerRequestInterface $request)
    {
        $this->getPluginMenu();

        $extensionName = $this->getSelectedPlugin()['extension'];
        $pluginName = $this->getSelectedPlugin()['plugin'];

        $routeEnhancer = SiteToolsUtility::generateRouteEnhancer($extensionName, $pluginName);

        $this->view->assignMultiple(array_merge(
            ['pluginSelected' => !empty($extensionName) && !empty($pluginName)],
            $routeEnhancer
        ));
    }

    /**
     * Create tools menu
     */
    protected function setupToolsMenu()
    {
        $this->tools = [
            'actions' => [
                'overview' => htmlspecialchars($this->getLanguageService()->getLL('overview')),
                'routeEnhancerGenerator' => htmlspecialchars(
                    $this->getLanguageService()->getLL('routeEnhancerGenerator')
                )
            ]
        ];
    }

    /**
     * @param string $templateName
     */
    protected function initializeView(string $templateName)
    {
        $this->view = GeneralUtility::makeInstance(StandaloneView::class);
        $this->view->setTemplate($templateName);
        $this->view->setTemplateRootPaths(['EXT:sitetools/Resources/Private/Templates']);
        $this->view->setPartialRootPaths(['EXT:sitetools/Resources/Private/Partials']);
        $this->view->setLayoutRootPaths(['EXT:sitetools/Resources/Private/Layouts']);
    }

    /**
     * Create document header tools menu
     */
    protected function getToolsMenu()
    {
        $menuRegistry = $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry();
        $menu = $menuRegistry->makeMenu();
        $menu->setIdentifier('actions');

        foreach ($this->tools['actions'] as $controller => $title) {
            $menuItem = $menu
                ->makeMenuItem()
                ->setHref((string)$this->uriBuilder->buildUriFromRoute(
                    $this->moduleName,
                    [
                        'action' => $controller
                    ]
                ))
                ->setTitle($title);

            if ($this->getSelectedAction() === $controller) {
                $menuItem->setActive(true);
            }

            $menu->addMenuItem($menuItem);
        }

        $menuRegistry->addMenu($menu);
    }

    /**
     * Create document header buttons
     */
    protected function getButtons()
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();

        // Reload
        $reloadButton = $buttonBar->makeLinkButton()
            ->setHref(GeneralUtility::getIndpEnv('REQUEST_URI'))
            ->setTitle($this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.reload'))
            ->setIcon($this->iconFactory->getIcon('actions-refresh', Icon::SIZE_SMALL));
        $buttonBar->addButton($reloadButton, ButtonBar::BUTTON_POSITION_RIGHT);

        // Shortcut
        $mayMakeShortcut = $this->getBackendUserAuthentication()->mayMakeShortcut();
        if ($mayMakeShortcut) {
            $getVars = ['id', 'route'];

            $shortcutButton = $buttonBar->makeShortcutButton()
                ->setModuleName('site_tools')
                ->setGetVariables($getVars);
            $buttonBar->addButton($shortcutButton, ButtonBar::BUTTON_POSITION_RIGHT);
        }
    }

    /**
     * Create document header plugin menu
     */
    protected function getPluginMenu()
    {
        $menuRegistry = $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry();

        // Plugin drop down
        $menu = $menuRegistry->makeMenu();
        $menu->setIdentifier('plugin');
        $extensions = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions'];

        if (is_array($extensions)) {
            // Add select item
            $menuItem = $menu
                ->makeMenuItem()
                ->setHref((string)$this->uriBuilder->buildUriFromRoute(
                    $this->moduleName,
                    [
                        'action' => $this->getSelectedAction()
                    ]
                ))
                ->setTitle($this->getLanguageService()->sL(
                    'LLL:EXT:sitetools/Resources/Private/Language/locallang_module.xlf:select_plugin'
                ));
            $menu->addMenuItem($menuItem);

            // Add plugin items
            foreach ($extensions as $extensionName => $extensionConfig) {
                if (!is_array($extensionConfig['plugins'])) {
                    continue;
                }

                foreach ($extensionConfig['plugins'] as $pluginName => $pluginConfig) {
                    //$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions'][$extensionName]['plugins'][$pluginName] = [];
                    $pluginSignature = strtolower($extensionName . '_' . $pluginName);

                    $menuItem = $menu
                        ->makeMenuItem()
                        ->setHref((string)$this->uriBuilder->buildUriFromRoute(
                            $this->moduleName,
                            [
                                'action' => $this->getSelectedAction(),
                                'extension' => $extensionName,
                                'plugin' => $pluginName
                            ]
                        ))
                        ->setTitle($extensionName . ' => ' . $pluginName);

                    if ($this->getSelectedPlugin()['extension'] === $extensionName &&
                        $this->getSelectedPlugin()['plugin'] === $pluginName) {
                        $menuItem->setActive(true);
                    }

                    $menu->addMenuItem($menuItem);
                }
            }
        } else {
            // Add no plugins item
            $menuItem = $menu
                ->makeMenuItem()
                ->setHref((string)$this->uriBuilder->buildUriFromRoute(
                    $this->moduleName,
                    [
                        'action' => $this->getSelectedAction()
                    ]
                ))
                ->setTitle($this->getLanguageService()->sL(
                    'LLL:EXT:sitetools/Resources/Private/Language/locallang_module.xlf:no_plugins'
                ));
            $menu->addMenuItem($menuItem);
        }
        $menuRegistry->addMenu($menu);
    }

    /**
     * @return string
     */
    protected function getSelectedAction(): string
    {
        $action = $this->request->getQueryParams()['action'] ?? $this->request->getParsedBody()['action'] ?? 'overview';
        return (string)isset($this->tools['actions'][$action]) ? $action : 'overview';
    }

    /**
     * @return array
     */
    protected function getSelectedPlugin(): array
    {
        return [
            'extension' => (string)$this->request->getQueryParams()['extension'] ?? $this->request->getParsedBody()['extension'] ?? '',
            'plugin' => (string)$this->request->getQueryParams()['plugin'] ?? $this->request->getParsedBody()['plugin'] ?? '',
        ];
    }

    /**
     * @return LanguageService
     */
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**
     * @return BackendUserAuthentication
     */
    protected function getBackendUserAuthentication(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
