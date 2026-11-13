<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Base class of the tests that need a real PostgreSQL database.
 *
 * The database is the `database_test` service of compose.override.yaml
 * (`docker compose up -d`). Its schema is built from the migrations, once
 * per PHPUnit run, so the migrations are exercised too. Each test then runs
 * in a transaction that is rolled back: tests do not see each other's data.
 *
 * Without the database these tests fail, on purpose: a skipped test would
 * hide a regression.
 */
abstract class DatabaseTestCase extends KernelTestCase
{
    private const string START_HINT = 'Start the test database with "docker compose up -d" from api/.';

    private static bool $schemaIsUpToDate = false;

    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        if (!self::$schemaIsUpToDate) {
            $this->migrate();
            self::$schemaIsUpToDate = true;
        }

        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $connection = $this->entityManager->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }

        parent::tearDown();
    }

    private function migrate(): void
    {
        $connection = $this->entityManager->getConnection();

        try {
            $database = (string) $connection->getDatabase();
        } catch (ConnectionException $e) {
            self::fail('The test database is not reachable. '.self::START_HINT.' ('.$e->getMessage().')');
        }

        // Last line of defence: never migrate anything but a test database.
        if (!str_ends_with($database, '_test')) {
            self::fail(\sprintf('Refusing to run the migrations on "%s": not a test database.', $database));
        }

        \assert(null !== self::$kernel);
        $application = new Application(self::$kernel);
        $application->setAutoExit(false);
        $output = new BufferedOutput();

        $exitCode = $application->run(new ArrayInput([
            'command' => 'doctrine:migrations:migrate',
            '--no-interaction' => true,
            '--allow-no-migration' => true,
        ]), $output);

        if (0 !== $exitCode) {
            self::fail("The migrations failed on the test database:\n".$output->fetch());
        }
    }
}
