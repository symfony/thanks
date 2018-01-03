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
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\CommandEvent;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event as ScriptEvent;
use Composer\Script\ScriptEvents;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Thanks implements Capable, CommandProvider, EventSubscriberInterface, PluginInterface
{
    private $io;
    private $displayReminder = false;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->io = $io;
    }

    public function getCapabilities()
    {
        return [
            CommandProvider::class => __CLASS__,
        ];
    }

    public function getCommands()
    {
        return [
            new Command\ThanksCommand(),
        ];
    }

    public function inspectCommand(CommandEvent $event)
    {
        if ('update' === $event->getCommandName()) {
            $this->displayReminder = true;
        }
    }

    public function displayReminder(ScriptEvent $event)
    {
        if (!$this->displayReminder) {
            return;
        }

        $love = '\\' === DIRECTORY_SEPARATOR ? 'love' : '💖 ';
        $star = '\\' === DIRECTORY_SEPARATOR ? 'star' : '⭐ ';

        $this->io->writeError('');
        $this->io->writeError('What about running <comment>composer thanks</> now?');
        $this->io->writeError(sprintf('This will spread some %s by sending a %s to the GitHub repositories of your fellow package maintainers.', $love, $star));
        $this->io->writeError('');
    }

    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::POST_UPDATE_CMD => 'displayReminder',
            PluginEvents::COMMAND => 'inspectCommand',
        ];
    }
}
