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
use Composer\Json\JsonFile;
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
    // This is a list of projects that should get a star on their main repository
    // (when there is one) whenever you use any of their other repositories.
    // When a project's main repo is also a dependency of their other repos (like amphp/amp),
    // there is no need to list it here, as starring will transitively happen anyway.
    private static $mainRepositories = [
        'api-platform' => [
            'name' => 'api-platform/api-platform',
            'url' => 'https://github.com/api-platform/api-platform',
        ],
        'cakephp' => [
            'name' => 'cakephp/cakephp',
            'url' => 'https://github.com/cakephp/cakephp',
        ],
        'drupal' => [
            'name' => 'drupal/drupal',
            'url' => 'https://github.com/drupal/drupal',
        ],
        'laravel' => [
            'name' => 'laravel/laravel',
            'url' => 'https://github.com/laravel/laravel',
        ],
        'illuminate' => [
            'name' => 'laravel/laravel',
            'url' => 'https://github.com/laravel/laravel',
        ],
        'nette' => [
            'name' => 'nette/nette',
            'url' => 'https://github.com/nette/nette',
        ],
        'phpDocumentor' => [
            'name' => 'phpDocumentor/phpDocumentor2',
            'url' => 'https://github.com/phpDocumentor/phpDocumentor2',
        ],
        'piwik' => [
            'name' => 'piwik/piwik',
            'url' => 'https://github.com/piwik/piwik',
        ],
        'reactphp' => [
            'name' => 'reactphp/react',
            'url' => 'https://github.com/reactphp/react',
        ],
        'sebastianbergmann' => [
            'name' => 'phpunit/phpunit',
            'url' => 'https://github.com/sebastianbergmann/phpunit',
        ],
        'slimphp' => [
            'name' => 'slimphp/Slim',
            'url' => 'https://github.com/slimphp/Slim',
        ],
        'Sylius' => [
            'name' => 'Sylius/Sylius',
            'url' => 'https://github.com/Sylius/Sylius',
        ],
        'symfony' => [
            'name' => 'symfony/symfony',
            'url' => 'https://github.com/symfony/symfony',
        ],
        'yiisoft' => [
            'name' => 'yiisoft/yii2',
            'url' => 'https://github.com/yiisoft/yii2',
        ],
        'zendframework' => [
            'name' => 'zendframework/zendframework',
            'url' => 'https://github.com/zendframework/zendframework',
        ],
    ];

    protected function configure()
    {
        $this->setName('thanks')
            ->setDescription('Give thanks (in the form of a GitHub ⭐) to your fellow PHP package maintainers.')
            ->setDefinition([
                new InputOption('dry-run', null, InputOption::VALUE_NONE, 'Don\'t actually send the stars'),
            ])
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $composer = $this->getComposer();
        $repo = $composer->getRepositoryManager()->getLocalRepository();

        $urls = [
            'composer/composer' => 'https://github.com/composer/composer',
            'php/php-src' => 'https://github.com/php/php-src',
        ];

        $directPackages = $this->getDirectlyRequiredPackageNames();
        // symfony/thanks shouldn't trigger thanking symfony/symfony
        unset($directPackages['symfony/thanks']);
        foreach ($repo->getPackages() as $package) {
            $extra = $package->getExtra();

            if (isset($extra['thanks']['name']) && isset($extra['thanks']['url'])) {
                $urls += [$extra['thanks']['name'] => $extra['thanks']['url']];
            }

            if (!$url = $package->getSourceUrl()) {
                continue;
            }

            $urls[$package->getName()] = $url;

            if (!preg_match('#^https://github.com/([^/]++)#', $url, $url)) {
                continue;
            }
            $owner = $url[1];

            // star the main repository, but only if this package is directly
            // being required by the user's composer.json
            if (isset(self::$mainRepositories[$owner]) && isset($directPackages[$package->getName()])) {
                $urls[self::$mainRepositories[$owner]['name']] = self::$mainRepositories[$owner]['url'];
            }
        }

        ksort($urls);

        $rfs = Factory::createRemoteFilesystem($this->getIo(), $composer->getConfig());

        $i = 0;
        $template ='_%d: repository(owner:"%s",name:"%s"){id,viewerHasStarred}'."\n";
        $graphql = '';

        foreach ($urls as $package => $url) {
            if (preg_match('#^https://github.com/([^/]++)/([^./]++)#', $url, $url)) {
                $graphql .= sprintf($template, ++$i, $url[1], $url[2]);
                $aliases['_'.$i] = [$package, $url[0]];
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
                $notStarred = $this->callGithub($rfs, sprintf("mutation{\n%s}", $graphql));
            }

            $output->writeln('Stars <comment>sent</> to:');
            foreach ($repos as $alias => $repo) {
                $output->writeln(sprintf(' ⭐  %s - %s', sprintf(isset($notStarred[$alias]) ? '<comment>%s</>' : '%s', $aliases[$alias][0]), $aliases[$alias][1]));
            }
        }

        $output->writeln("\nThanks to you! 💖");

        return 0;
    }

    private function callGitHub(RemoteFilesystem $rfs, $graphql)
    {
        $result = $rfs->getContents('github.com', 'https://api.github.com/graphql', false, [
            'http' => [
                'method' => 'POST',
                'content' => json_encode(['query' => $graphql]),
            ],
        ]);
        $result = json_decode($result, true);

        return $result['data'];
    }

    private function getDirectlyRequiredPackageNames()
    {
        $file = new JsonFile(Factory::getComposerFile(), null, $this->getIO());

        if (!$file->exists()) {
            throw new \Exception('Could not find your composer.json file!');
        }

        $data = $file->read() + array('require' => array(), 'require-dev' => array());
        $data = array_keys($data['require'] + $data['require-dev']);

        return array_combine($data, $data);
    }
}
