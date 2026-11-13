<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog;

use App\Ecommerce\Application\UseCase\Catalog\UploadProductImageUseCase;
use App\Ecommerce\Domain\Model\Catalog\Picture;
use App\Tests\Support\AuthenticatesRequests;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Covers S2: only real JPEG, PNG and WebP images of at most 5 MB are stored.
 *
 * The use case is replaced by a mock, so nothing is written to disk: a
 * refused file must never reach it.
 */
final class UploadPictureValidationTest extends WebTestCase
{
    use AuthenticatesRequests;

    private const string URI = '/upload-picture/11111111-1111-4111-8111-111111111111';
    private const string SVG = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

    private KernelBrowser $client;

    /** @var string[] */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->authenticateAs($this->client, '99999999-9999-4999-8999-999999999999', ['ROLE_ADMIN']);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function acceptedImages(): iterable
    {
        yield 'jpeg' => [self::image('jpeg'), 'candle.jpg'];
        yield 'png' => [self::image('png'), 'candle.png'];
        yield 'webp' => [self::image('webp'), 'candle.webp'];
    }

    #[DataProvider('acceptedImages')]
    public function testRealImageIsAccepted(string $content, string $name): void
    {
        $picture = $this->createStub(Picture::class);
        $picture->method('getId')->willReturn('picture-id');
        $useCase = $this->createMock(UploadProductImageUseCase::class);
        $useCase->expects(self::once())->method('execute')->willReturn($picture);
        static::getContainer()->set(UploadProductImageUseCase::class, $useCase);

        $this->upload($content, $name, 'image/jpeg');

        self::assertResponseStatusCodeSame(200);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function refusedFiles(): iterable
    {
        // Name and Content-Type are chosen by the client: they must not be trusted.
        yield 'svg' => [self::SVG, 'candle.svg', 'image/svg+xml'];
        yield 'svg disguised as jpeg' => [self::SVG, 'candle.jpg', 'image/jpeg'];
        yield 'html disguised as jpeg' => ['<html><script>alert(1)</script></html>', 'candle.jpg', 'image/jpeg'];
        yield 'php disguised as png' => ['<?php echo 1;', 'candle.png', 'image/png'];
        yield 'gif' => [self::image('gif'), 'candle.gif', 'image/gif'];
        yield 'text' => ['fake image content', 'candle.jpg', 'image/jpeg'];
    }

    #[DataProvider('refusedFiles')]
    public function testOtherFileIsRefused(string $content, string $name, string $clientMimeType): void
    {
        $this->forbidUseCase();

        $this->upload($content, $name, $clientMimeType);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            '{"error":"The picture must be a JPEG, PNG or WebP image."}',
            $this->client->getResponse()->getContent(),
        );
    }

    public function testImageOverFiveMegabytesIsRefused(): void
    {
        $this->forbidUseCase();

        // A valid PNG followed by padding is still detected as a PNG.
        $this->upload(self::image('png').str_repeat("\0", 5 * 1024 * 1024), 'candle.png', 'image/png');

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('too large', (string) $this->client->getResponse()->getContent());
    }

    private function forbidUseCase(): void
    {
        $useCase = $this->createMock(UploadProductImageUseCase::class);
        $useCase->expects(self::never())->method('execute');
        static::getContainer()->set(UploadProductImageUseCase::class, $useCase);
    }

    private function upload(string $content, string $name, string $clientMimeType): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'upload_test_');
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        $this->client->request('POST', self::URI, files: [
            'file' => new UploadedFile($path, $name, $clientMimeType, test: true),
        ]);
    }

    /**
     * @param 'jpeg'|'png'|'webp'|'gif' $format
     */
    private static function image(string $format): string
    {
        $image = imagecreatetruecolor(8, 8);
        \assert(false !== $image);

        ob_start();
        match ($format) {
            'jpeg' => imagejpeg($image),
            'png' => imagepng($image),
            'webp' => imagewebp($image),
            'gif' => imagegif($image),
        };

        return (string) ob_get_clean();
    }
}
