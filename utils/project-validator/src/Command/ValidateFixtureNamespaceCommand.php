<?php

declare(strict_types=1);

namespace Rector\Utils\ProjectValidator\Command;

use Nette\Utils\Strings;
use const PATHINFO_DIRNAME;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symplify\PackageBuilder\Console\ShellCode;
use Symplify\SmartFileSystem\Finder\FinderSanitizer;
use Symplify\SmartFileSystem\SmartFileInfo;

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
        $fixtureFiles = $this->getFixtureFiles();
        $incorrectNamespaceFiles = [];

        foreach ($fixtureFiles as $key => $fixtureFile) {
            // 1. geting expected namespace ...
            [, $relativePath] = explode(getcwd(), (string) $fixtureFile);
            $relativePath = ltrim(pathinfo($relativePath, PATHINFO_DIRNAME), '\/');
            $backslashedPath = str_replace('/', '\\', $relativePath);
            $expectedNamespace = $this->getExpectedNamespace($backslashedPath);

            if ($expectedNamespace === null) {
                continue;
            }

            // 2. reading file contents
            $fileContent = (string) file_get_contents((string) $fixtureFile);
            // @see https://regex101.com/r/5KtBi8/2
            $matchAll = Strings::matchAll($fileContent, '#^namespace (.*);$#msU');

            if (! $matchAll) {
                // 3. collect files with no namespace
                $incorrectNamespaceFiles[] = (string) $fixtureFile;
                continue;
            }

            if ($this->isFoundIncorrectNamespace($matchAll, $expectedNamespace)) {
                continue;
            }

            // 3. collect files with incorrect namespace
            $incorrectNamespaceFiles[] = (string) $fixtureFile;
        }

        if ($incorrectNamespaceFiles !== []) {
            $this->symfonyStyle->listing($incorrectNamespaceFiles);
            $message = sprintf(
                'Found %d files with invalid namespace. Please follow psr-4 defined in composer.json',
                count($incorrectNamespaceFiles)
            );

            $this->symfonyStyle->error($message);

            return ShellCode::ERROR;
        }

        $this->symfonyStyle->success('All fixtures are correct');
        return ShellCode::SUCCESS;
    }

    private function getExpectedNamespace(string $backslashedPath): ?string
    {
        if (strpos($backslashedPath, 'tests\\') === 0) {
            return 'Rector\Core\Tests' . substr($backslashedPath, 5);
        }

        if (strpos($backslashedPath, 'rules\\') === 0) {
            $namespaces     = explode('\\', $backslashedPath);
            unset($namespaces[0]);
            $namespaces[1]  = ucfirst($namespaces[1]);
            $namespaces[1]  = preg_replace_callback('#-(\w)#', function ($value) {
                if (is_array($value)) {
                    return strtoupper($value[1]);
                }

                return strtoupper($value);
            }, $namespaces[1]);
            $namespaces[2]  = 'Tests';

            return 'Rector\\' . implode('\\', $namespaces);
        }

        return null;
    }

    /**
     * @param array<int, array<int, string>> $matchAll
     */
    private function isFoundIncorrectNamespace(array $matchAll, string $expectedNamespace): bool
    {
        $countMatchAll = count($matchAll);
        if ($countMatchAll === 1 && $matchAll[0][1] === $expectedNamespace) {
            return true;
        }

        return $countMatchAll === 2 && $matchAll[0][1] === $expectedNamespace && $matchAll[1][1] === $expectedNamespace;
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
