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
use Symfony\Component\Console\Input\ArgvInput;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Thanks implements EventSubscriberInterface, PluginInterface
{
    private $io;
    private $displayReminder = 0;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->io = $io;

        foreach (debug_backtrace() as $trace) {
            if (!isset($trace['object']) || !isset($trace['args'][0])) {
                continue;
            }

            if (!$trace['object'] instanceof Application || !$trace['args'][0] instanceof ArgvInput) {
                continue;
            }

            $input = $trace['args'][0];
            $app = $trace['object'];

            try {
                $command = $input->getFirstArgument();
                $command = $command ? $app->find($command)->getName() : null;
            } catch (\InvalidArgumentException $e) {
            }

            if ('update' === $command) {
                $this->displayReminder = 1;
            }

            $app->add(new Command\ThanksCommand());
            break;
        }
    }

    public function enableReminder()
    {
        if (1 === $this->displayReminder) {
            $this->displayReminder = version_compare('1.1.0', PluginInterface::PLUGIN_API_VERSION, '<=') ? 2 : 0;
        }
    }

    public function displayReminder(ScriptEvent $event)
    {
        if (2 !== $this->displayReminder) {
            return;
        }

        $love = '\\' === DIRECTORY_SEPARATOR ? 'love' : '💖';
        $star = '\\' === DIRECTORY_SEPARATOR ? 'star' : '★';

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
