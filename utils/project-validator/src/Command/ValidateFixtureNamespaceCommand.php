<?php

declare(strict_types=1);

namespace Rector\Utils\ProjectValidator\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symplify\PackageBuilder\Console\ShellCode;
use Symplify\SmartFileSystem\Finder\FinderSanitizer;
use Symplify\SmartFileSystem\SmartFileInfo;
use Nette\Utils\Strings;

final class ValidateFixtureNamespaceCommand extends Command
{
    /**
     * @var FinderSanitizer
     */
    private $finderSanitizer;

    /**
     * @var SymfonyStyle
     */
    private $symfonyStyle;

    public function __construct(FinderSanitizer $finderSanitizer, SymfonyStyle $symfonyStyle)
    {
        $this->finderSanitizer = $finderSanitizer;
        $this->symfonyStyle = $symfonyStyle;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('[CI] Validate tests fixtures namespace');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fixtureFiles            = $this->getFixtureFiles();
        $incorrectNamespaceFiles = [];

        foreach ($fixtureFiles as $key => $fixtureFile) {
            // 1. geting expected namespace ...
            list(, $relativePath) = explode(getcwd(), (string) $fixtureFile);
            $relativePath         = ltrim(pathinfo($relativePath, \PATHINFO_DIRNAME), '\/');
            $backslashedPath      = str_replace('/', '\\', $relativePath);

            if (strpos($backslashedPath, 'tests\\') === 0) {
                $expectedNamespace = 'Rector\Core\Tests' . substr($backslashedPath, 5);
            } else {
                continue; // temporary only check for tests/
            }

            // 2. reading file contents
            $fileContent = (string) file_get_contents((string) $fixtureFile);
            // @see https://regex101.com/r/5KtBi8/2
            $matchAll       = Strings::matchAll($fileContent, '#^namespace (.*);$#msU');

            if (! $matchAll) {
                // 3. collect files with no namespace
                $incorrectNamespaceFiles[] = (string) $fixtureFile;
                continue;
            }

            if (count($matchAll) === 1 && $matchAll[0][1] === $expectedNamespace) {
                continue;
            }

            if (count($matchAll) === 2 && $matchAll[0][1] === $expectedNamespace && $matchAll[1][1] === $expectedNamespace) {
                continue;
            }

            // 3. collect files with incorrect namespace
            $incorrectNamespaceFiles[] = (string) $fixtureFile;
        }

        if ($incorrectNamespaceFiles !== []) {
            $this->symfonyStyle->listing($incorrectNamespaceFiles);
            $message = sprintf('Found %d files with invalid namespace. Please follow psr-4 defined in composer.json', count($incorrectNamespaceFiles));

            $this->symfonyStyle->error($message);

            return ShellCode::ERROR;
        }

        $this->symfonyStyle->success('All fixtures are correct');
        return ShellCode::SUCCESS;
    }

    /**
     * @return SmartFileInfo[]
     */
    private function getFixtureFiles(): array
    {
        $finder = new Finder();
        $finder = $finder->files()
            ->name('#\.php\.inc$#')
            ->notName('#empty_file\.php\.inc$#')
            ->path('#/Fixture/#')
            ->notPath('#/blade-template/#')
            ->in(__DIR__ . '/../../../../tests')
            ->in(__DIR__ . '/../../../../packages/*/tests')
            ->in(__DIR__ . '/../../../../rules/*/tests');

        return $this->finderSanitizer->sanitize($finder);
    }
}
