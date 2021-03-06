<?php

namespace umulmrum\PhpReferenceChecker\Checker;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;
use umulmrum\PhpReferenceChecker\DataModel\MethodRepository;
use umulmrum\PhpReferenceChecker\DataModel\NonReferenceAssignmentWarning;

class ReferenceAssignmentChecker
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param string           $targetPath
     * @param MethodRepository $methodRepository
     *
     * @return NonReferenceAssignmentWarning[]
     */
    public function check($targetPath, MethodRepository $methodRepository)
    {
        $this->init();

        return $this->checkTargetPath($targetPath, $methodRepository);
    }

    private function init()
    {
        $this->logger->info('init: start');
        ini_set('xdebug.max_nesting_level', 3000);
        $this->logger->info('init: end');
    }

    /**
     * @param string           $targetPath
     * @param MethodRepository $repository
     *
     * @return NonReferenceAssignmentWarning[]
     */
    private function checkTargetPath($targetPath, MethodRepository $repository)
    {
        $this->logger->info('checkTargetPath: start');
        if (is_file($targetPath)) {
            $this->logger->info('checkTargetPath: end');
            $fileWarnings = $this->checkFile($targetPath, $repository, '');

            return $this->removePaths($fileWarnings);
        }
        $finder = new Finder();

        $warnings = [];
        $finder
            ->in($targetPath)
            ->files()
            ->name('*.php');

        foreach ($finder as $file) {
            $fileWarnings = $this->checkFile($file->getPathname(), $repository);
            $fileWarnings = $this->shortenPaths($fileWarnings, $targetPath);
            $warnings = array_merge($warnings, $fileWarnings);
        }
        $this->logger->info('checkTargetPath: end');

        return $warnings;
    }

    /**
     * @param string           $path
     * @param MethodRepository $repository
     *
     * @return NonReferenceAssignmentWarning[]
     */
    private function checkFile($path, MethodRepository $repository)
    {
        $warnings = [];
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new AssignByReferenceVisitor($warnings, $repository, $path));
        $nodes = $parser->parse(file_get_contents($path));
        $traverser->traverse($nodes);

        return $warnings;
    }

    /**
     * @param NonReferenceAssignmentWarning[] $warnings
     * @param string                          $basePath
     *
     * @return NonReferenceAssignmentWarning[]
     */
    private function shortenPaths(array $warnings, $basePath)
    {
        $shortenedWarnings = array_map(
            function (NonReferenceAssignmentWarning $warning) use ($basePath) {
                return new NonReferenceAssignmentWarning(
                    substr($warning->getFile(), strlen($basePath)),
                    $warning->getLine(),
                    $warning->getProbability()
                );
            },
            $warnings
        );

        return $shortenedWarnings;
    }

    /**
     * @param NonReferenceAssignmentWarning[] $warnings
     *
     * @return NonReferenceAssignmentWarning[]
     */
    private function removePaths(array $warnings)
    {
        $shortenedWarnings = array_map(
            function (NonReferenceAssignmentWarning $warning) {
                return new NonReferenceAssignmentWarning(
                    '/'.basename($warning->getFile()),
                    $warning->getLine(),
                    $warning->getProbability()
                );
            },
            $warnings
        );

        return $shortenedWarnings;
    }
}
