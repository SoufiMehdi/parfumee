<?php

declare(strict_types=1);

namespace App\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Dotenv\Dotenv;

/**
 * .env is loaded in every environment, production included. A secret left in it
 * would silently become the production value (S4).
 */
final class EnvFilesTest extends TestCase
{
    private const string PROJECT_DIR = __DIR__.'/../../..';

    /**
     * @return iterable<string, array{string}>
     */
    public static function secrets(): iterable
    {
        yield 'APP_SECRET' => ['APP_SECRET'];
        yield 'JWT_PASSPHRASE' => ['JWT_PASSPHRASE'];
    }

    #[DataProvider('secrets')]
    public function testTheBaseEnvFileHoldsNoSecret(string $name): void
    {
        $values = $this->parse('.env');

        self::assertArrayHasKey($name, $values, 'The variable must stay declared, so that its absence is not a surprise.');
        self::assertSame('', $values[$name], \sprintf('%s must be empty in .env: put the dev value in .env.dev.', $name));
    }

    #[DataProvider('secrets')]
    public function testDevAndTestDefineTheirOwnValue(string $name): void
    {
        self::assertNotSame('', $this->parse('.env.dev')[$name] ?? '');
        self::assertNotSame('', $this->parse('.env.test')[$name] ?? '');
    }

    public function testTheBaseDatabaseUrlOnlyHoldsThePlaceholderPassword(): void
    {
        self::assertStringContainsString(':!ChangeMe!@127.0.0.1', $this->parse('.env')['DATABASE_URL']);
    }

    public function testTheTestDatabaseIsTheLocalThrowawayOne(): void
    {
        self::assertStringContainsString('test:test@127.0.0.1:54320', $this->parse('.env.test')['DATABASE_URL']);
    }

    /**
     * @return array<string, string>
     */
    private function parse(string $file): array
    {
        $content = file_get_contents(self::PROJECT_DIR.'/'.$file);
        self::assertIsString($content);

        return (new Dotenv())->parse($content, $file);
    }
}
