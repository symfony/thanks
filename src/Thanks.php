<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Thanks;

use Composer\Composer;
use Composer\Console\Application;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event as ScriptEvent;
use Composer\Script\ScriptEvents;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Thanks implements EventSubscriberInterface, PluginInterface
{
    private $io;
    private $displayReminder = false;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->io = $io;

        foreach (debug_backtrace() as $trace) {
            if (isset($trace['object']) && $trace['object'] instanceof Application) {
                $trace['object']->add(new Command\ThanksCommand());
                break;
            }
        }
    }

    public function enableReminder()
    {
        $this->displayReminder = version_compare('1.1.0', PluginInterface::PLUGIN_API_VERSION, '<=');
    }

    public function displayReminder(ScriptEvent $event)
    {
        if (!$this->displayReminder) {
            return;
        }

        $love = '\\' === DIRECTORY_SEPARATOR ? 'love' : '💖 ';
        $star = '\\' === DIRECTORY_SEPARATOR ? 'star' : '★ ';

        $this->io->writeError('');
        $this->io->writeError('What about running <comment>composer thanks</> now?');
        $this->io->writeError(sprintf('This will spread some %s by sending a %s to the GitHub repositories of your fellow package maintainers.', $love, $star));
        $this->io->writeError('');
    }

    public static function getSubscribedEvents()
    {
        return [
            PackageEvents::POST_PACKAGE_UPDATE => 'enableReminder',
            ScriptEvents::POST_UPDATE_CMD => 'displayReminder',
        ];
    }
}
