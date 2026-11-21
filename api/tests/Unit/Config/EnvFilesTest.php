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

    /**
     * @return iterable<string, array{string}>
     */
    public static function corsOrigins(): iterable
    {
        yield 'shop' => ['CORS_SHOP_ORIGIN'];
        yield 'back-office' => ['CORS_ADMIN_ORIGIN'];
    }

    /**
     * Without a value set for production, no front is allowed: the API fails closed.
     */
    #[DataProvider('corsOrigins')]
    public function testTheBaseEnvFileAllowsNoFront(string $name): void
    {
        $values = $this->parse('.env');

        self::assertArrayHasKey($name, $values);
        self::assertSame('', $values[$name]);
    }

    /**
     * An origin is compared exactly with the Origin header the browser sends: a
     * trailing slash or a path would never match, and the front would be refused.
     */
    #[DataProvider('corsOrigins')]
    public function testDevAndTestAllowTheirFrontsByTheirExactOrigin(string $name): void
    {
        foreach (['.env.dev', '.env.test'] as $file) {
            self::assertMatchesRegularExpression('#^https?://[a-z0-9.-]+(:[0-9]+)?$#', $this->parse($file)[$name] ?? '', \sprintf('%s in %s is not an origin.', $name, $file));
        }
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
