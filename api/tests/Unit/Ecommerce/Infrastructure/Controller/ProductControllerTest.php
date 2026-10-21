<?php

namespace App\Tests\Integration\Ecommerce\Infrastructure\Controller\Catalog;

use App\Ecommerce\Application\DTO\Catalog\CreateProductDto;
use App\Ecommerce\Application\DTO\Catalog\UpdateProductDto;
use App\Ecommerce\Application\UseCase\Catalog\CreateProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\GetProductsUseCase;
use App\Ecommerce\Application\UseCase\Catalog\GetProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\UpdateProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\UploadProductImageUseCase;
use App\Ecommerce\Domain\Model\Catalog\Picture;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Infrastructure\Presenter\Catalog\ProductPresenter;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ProductControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testGetProducts(): void
    {
        // 1. Mocks
        $useCaseMock = $this->createMock(GetProductsUseCase::class);
        $presenterMock = $this->createMock(ProductPresenter::class);

        $useCaseMock->expects($this->once())->method('execute')->willReturn([]);
        $presenterMock->expects($this->once())->method('presentCollection')->with([])->willReturn(['products' => []]);

        // Injection des mocks dans le conteneur de Symfony
        static::getContainer()->set(GetProductsUseCase::class, $useCaseMock);
        static::getContainer()->set(ProductPresenter::class, $presenterMock);

        // 2. Act
        $this->client->request('GET', '/products');

        // 3. Assert
        $response = $this->client->getResponse();
        $this->assertSame(JsonResponse::HTTP_OK, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode(['products' => []]), $response->getContent());
    }

    public function testGetProductByIdSuccess(): void
    {
        $useCaseMock = $this->createMock(GetProductUseCase::class);
        $presenterMock = $this->createMock(ProductPresenter::class);
        $productMock = $this->createMock(Product::class);

        $useCaseMock->expects($this->once())->method('execute')->with('prod-123')->willReturn($productMock);
        $presenterMock->expects($this->once())->method('present')->with($productMock)->willReturn(['id' => 'prod-123', 'name' => 'Parfum']);

        static::getContainer()->set(GetProductUseCase::class, $useCaseMock);
        static::getContainer()->set(ProductPresenter::class, $presenterMock);

        $this->client->request('GET', '/product/prod-123');

        $response = $this->client->getResponse();
        $this->assertSame(JsonResponse::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('prod-123', $response->getContent());
    }

    public function testGetProductByIdNotFound(): void
    {
        $useCaseMock = $this->createMock(GetProductUseCase::class);
        // Le cas où le cas d'usage retourne null (produit inexistant)
        $useCaseMock->expects($this->once())->method('execute')->with('unknown')->willReturn(null);

        static::getContainer()->set(GetProductUseCase::class, $useCaseMock);

        $this->client->request('GET', '/product/unknown');

        $response = $this->client->getResponse();
        $this->assertSame(JsonResponse::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(json_encode(['message' => 'Product not found']), $response->getContent());
    }

    public function testCreateProduct(): void
    {
        $useCaseMock = $this->createMock(CreateProductUseCase::class);

        // On s'assure que le UseCase reçoit un DTO construit à partir du JSON envoyé
        $useCaseMock->expects($this->once())
            ->method('execute')
            ->with($this->isInstanceOf(CreateProductDto::class))
            ->willReturn('new-uuid'); // Simule le retour d'un ID de produit créé

        static::getContainer()->set(CreateProductUseCase::class, $useCaseMock);

        $payload = [
            'name' => 'Oud Intense',
            'price' => 120.50,
            'category_id' => 'cat-789',
            'attributes' => ['capacity' => '100ml']
        ];

        $this->client->request('POST', '/create-product', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));

        $response = $this->client->getResponse();
        $this->assertSame(JsonResponse::HTTP_CREATED, $response->getStatusCode());
        $this->assertStringContainsString('new-uuid', $response->getContent());
    }

    public function testUpdateProduct(): void
    {
        $useCaseMock = $this->createMock(UpdateProductUseCase::class);
        $productMock = $this->createMock(Product::class);
        $productMock->method('getId')->willReturn('prod-123');

        $useCaseMock->expects($this->once())
            ->method('execute')
            ->with($this->isInstanceOf(UpdateProductDto::class), 'prod-123')
            ->willReturn($productMock);

        static::getContainer()->set(UpdateProductUseCase::class, $useCaseMock);

        $payload = ['name' => 'Nouveau Nom', 'price' => 95.00];

        $this->client->request('PUT', '/edit-product/prod-123', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));

        $response = $this->client->getResponse();
        $this->assertSame(JsonResponse::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Product updated successfully', $data['result']);
        $this->assertSame('prod-123', $data['data']);
    }

    public function testUploadPictureSuccess(): void
    {
        $useCaseMock = $this->createMock(UploadProductImageUseCase::class);
        $imageMock = $this->createMock(Picture::class);
        $imageMock->method('getId')->willReturn('img-999');

        $useCaseMock->expects($this->once())
            ->method('execute')
            ->with('prod-123', $this->isInstanceOf(UploadedFile::class), 'Belle image')
            ->willReturn($imageMock);

        static::getContainer()->set(UploadProductImageUseCase::class, $useCaseMock);

        // Simulation d'un fichier temporaire pour l'upload multipart
        $tempFile = tempnam(sys_get_temp_dir(), 'test_img_');
        file_put_contents($tempFile, 'fake image content');
        $uploadedFile = new UploadedFile($tempFile, 'perfume.jpg', 'image/jpeg', null, true);

        // Envoi en format Multipart Form (les fichiers vont dans le 5ème argument de request())
        $this->client->request(
            'POST',
            '/upload-picture/prod-123',
            ['alt' => 'Belle image'], // $_POST
            ['file' => $uploadedFile] // $_FILES
        );

        $response = $this->client->getResponse();
        $this->assertSame(JsonResponse::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Image uploaded successfully', $data['result']);
        $this->assertSame('img-999', $data['data']);
    }

    public function testUploadPictureBadRequest(): void
    {
        // On teste le cas d'erreur de validation du contrôleur (pas de fichier fourni)
        $this->client->request('POST', '/upload-picture/prod-123');

        $response = $this->client->getResponse();
        $this->assertSame(JsonResponse::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString('Product ID and file are required', $response->getContent());
    }
    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }
}
