<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Thanks\Command;

use Composer\Command\BaseCommand;
use Composer\Composer;
use Composer\Util\RemoteFilesystem;
use Composer\Factory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ThanksCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('thanks')
            ->setDescription('Give thanks (in the form of github ★) to your fellow PHP package maintainers.')
            ->setDefinition([
                new InputOption('dry-run', null, InputOption::VALUE_NONE, 'Don\'t send the stars actually'),
            ])
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $composer = $this->getComposer();
        $repo = $composer->getRepositoryManager()->getLocalRepository();

        $urls = [];
        foreach ($repo->getPackages() as $package) {
            if ($url = $package->getSourceUrl()) {
                $urls[$package->getName()] = $url;
            }
        }
        ksort($urls);

        $rfs = Factory::createRemoteFilesystem($this->getIo(), $composer->getConfig());

        $i = 0;
        $template ='_%d: repository(owner:"%s",name:"%s"){id,viewerHasStarred}'."\n";
        $graphql = sprintf($template, ++$i, 'symfony', 'symfony');
        $aliases = ['_1' => 'symfony/symfony'];

        foreach ($urls as $package => $url) {
            if (preg_match('#^https://github.com/([^/]++)/([^./]++)#', $url, $url)) {
                $graphql .= sprintf($template, ++$i, $url[1], $url[2]);
                $aliases['_'.$i] = $package;
            }
        }

        $repos = $this->callGithub($rfs, sprintf("query{\n%s}", $graphql));

        $template ='%1$s: addStar(input:{clientMutationId:"%s",starrableId:"%s"}){clientMutationId}'."\n";
        $graphql = '';
        $notStarred = [];

        foreach ($repos as $alias => $repo) {
            if (!$repo['viewerHasStarred']) {
                $graphql .= sprintf($template, $alias, $repo['id']);
                $notStarred[$alias] = $repo;
            }
        }

        if (!$notStarred) {
            $output->writeln('You already starred all your GitHub dependencies.');
        } else {
            if (!$input->getOption('dry-run')) {
                $repos = $this->callGithub($rfs, sprintf("mutation{\n%s}", $graphql));
            } else {
                $repos = $notStarred;
            }

            foreach ($repos as $alias => $mutation) {
                $output->writeln(sprintf('★ %s', $aliases[$alias]));
            }
        }

        $output->writeln('Thanks to you! 💖');

        return 0;
    }

    private function callGitHub(RemoteFilesystem $rfs, string $graphql): array
    {
        $result = $rfs->getContents('github.com', 'https://api.github.com/graphql', false, [
            'http' => [
                'method' => 'POST',
                'content' => json_encode(['query' => $graphql]),
            ],
        ]);

        return json_decode($result, true)['data'];
    }
}
