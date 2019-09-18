<?php
declare(strict_types = 1);

namespace TYPO3\CMS\SiteTools\Command;

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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\SiteTools\Utility\SiteToolsUtility;

/**
 * Command to generate a route enhancer configuration for a plugin
 */
class GenerateRouteEnhancerCommand extends Command
{
    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * Defines the allowed options for this command
     *
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setDescription('Generates a route enhancer configuration for the specified plugin')
            ->setHelp('The extension must be present and activated.')
            ->setAliases(['site:tools:generaterouteenhancer', 'sitetools:generaterouteenhancer'])
            ->addArgument(
                'extension',
                InputArgument::OPTIONAL,
                'The extension key of a extension which the plugin is part of.'
            )
            ->addArgument(
                'plugin',
                InputArgument::OPTIONAL,
                'The plugin name of a the plugin to generate the route enhancer configuration.'
            );
    }

    /**
     * Shows the route enhancer configuration for the plugin
     *
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->io = new SymfonyStyle($input, $output);

        $extensionName = $this->getArgument('extension');
        $pluginName = $this->getArgument('plugin');

        if (!$this->checkPlugin($extensionName, $pluginName)) {
            $this->io->error("The plugin `$extensionName / $pluginName` was not found!");
            return;
        }

        // Create configuration
        $routeEnhancer = SiteToolsUtility::generateRouteEnhancer($extensionName, $pluginName);

        // Output configuration
        $this->io->title("Route Enhancer configuration for `$extensionName / $pluginName`");
        $this->io->write($routeEnhancer['configuration'], $this->io::OUTPUT_RAW);
    }

    /**
     * Returns the argument value for a given argument name. If the argument
     * does not exist the user will be asked for an input.
     *
     * @param string $name The argument name
     *
     * @return string|string[]|null The argument value
     */
    protected function getArgument(string $name)
    {
        $value = $this->input->getArgument($name);

        if (empty($value)) {
            $question = new Question("Please enter a value for '$name'");
            $value = $this->io->askQuestion($question);
        }

        return $value;
    }

    /**
     * Checks if the plugin is known.
     *
     * @param string $extensionName
     * @param string $pluginName
     *
     * @return bool Returns true if the plugin is known
     */
    protected function checkPlugin(string $extensionName, string $pluginName): bool
    {
        return isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions'][$extensionName]) &&
            isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions'][$extensionName]['plugins'][$pluginName]);
    }
}
