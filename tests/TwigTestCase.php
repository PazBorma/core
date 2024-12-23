<?php

declare(strict_types=1);

namespace Stu;

use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\Migrations\Configuration\Migration\PhpFile;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Tools\Console\Command\DiffCommand;
use Doctrine\Migrations\Tools\Console\Command\ExecuteCommand;
use Doctrine\Migrations\Tools\Console\Command\MigrateCommand;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Console\Command\SchemaTool\UpdateCommand;
use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\SingleManagerProvider;
use JetBrains\PhpStorm\Deprecated;
use Override;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Spatie\Snapshots\MatchesSnapshots;
use Stu\Config\Init;
use Stu\Lib\SessionInterface;
use Stu\Module\Control\GameControllerInterface;
use Stu\Module\Control\Render\GameTwigRendererInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;

abstract class TwigTestCase extends StuTestCase
{
    use MatchesSnapshots;

    private static string $INTTEST_MIGRATIONS_CONFIG_PATH = 'dist/db/migrations/testdata.php';

    private static bool $isSchemaCreated = false;

    #[Override]
    public function setUp(): void
    {
        $this->initializeSchema();
    }

    protected function renderSnapshot(): void
    {
        $dic = Init::getContainer();

        $this->setupSession($dic);

        $game = $dic->get(GameControllerInterface::class);
        $twigRenderer = $dic->get(GameTwigRendererInterface::class);
        $subject = $dic->get($this->getViewController());

        // execute ViewController and render
        $subject->handle($game);
        $renderResult = $twigRenderer->render($game, $game->getUser());

        $this->assertMatchesHtmlSnapshot($renderResult);
    }

    protected abstract function getViewController(): string;

    protected function loadTestData(TestDataInterface $testData): int
    {
        return $testData->insertTestData();
    }

    #[Deprecated()]
    protected function loadTestDataMigration(string $className): void
    {
        $inputString = str_replace('\\', '\\\\', sprintf("execute --configuration=\"%s\" --quiet %s --up", $className));

        $this->runCommandWithDependecyFactory(ExecuteCommand::class, new StringInput($inputString));
    }

    private function initializeSchema(): void
    {
        if (!static::$isSchemaCreated) {

            // add inttest configuration
            Init::$configFiles[] = '%s/config.intttest.json';

            //$this->createInitialDiff($dic);
            //$this->forceSchemaUpdate($dic);
            $this->initializeTestData();

            static::$isSchemaCreated = true;
        }
    }

    private function setupSession(ContainerInterface $dic): void
    {
        $_SESSION['uid'] = 1;
        $_SESSION['login'] = 1;
        $session = $dic->get(SessionInterface::class);
        $session->createSession();
    }

    private function createInitialDiff(ContainerInterface $dic): void
    {
        $this->runCommandWithDependecyFactory(
            DiffCommand::class,
            new StringInput(sprintf("diff --configuration=\"%s\"", self::$INTTEST_MIGRATIONS_CONFIG_PATH))
        );
    }

    private function forceSchemaUpdate(ContainerInterface $dic): void
    {
        $entityManagerProvider = new SingleManagerProvider($dic->get(EntityManagerInterface::class));

        $application = ConsoleRunner::createApplication(
            $entityManagerProvider,
            [new UpdateCommand($entityManagerProvider)]
        );

        // create schema
        $application->setAutoExit(false);
        $exitCode = $application->run(new StringInput('orm:schema-tool:update --force'));

        if ($exitCode != 0) {
            throw new RuntimeException('Could not force schema update!');
        }
    }

    private function initializeTestData(): void
    {
        $this->runCommandWithDependecyFactory(
            MigrateCommand::class,
            new StringInput(sprintf(
                "migrate --configuration=\"%s\" --all-or-nothing --allow-no-migration --no-interaction",
                self::$INTTEST_MIGRATIONS_CONFIG_PATH
            ))
        );
    }

    private function runCommandWithDependecyFactory(string $command, InputInterface $input): void
    {
        $dic = Init::getContainer();
        $entityManager = $dic->get(EntityManagerInterface::class);

        $entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use ($command, $input) {

            $entityManagerProvider = new SingleManagerProvider($entityManager);
            $config = new PhpFile(self::$INTTEST_MIGRATIONS_CONFIG_PATH);
            $dependencyFactory = DependencyFactory::fromEntityManager(
                $config,
                new ExistingEntityManager($entityManager)
            );
            $application = ConsoleRunner::createApplication(
                $entityManagerProvider,
                [new $command($dependencyFactory)]
            );

            $application->setAutoExit(false);
            if ($application->run($input) != 0) {
                throw new RuntimeException(sprintf('Could not execute %s!', $command));
            }
        });
    }
}
