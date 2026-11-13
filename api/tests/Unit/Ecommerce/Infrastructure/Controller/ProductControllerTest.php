<?php

namespace App\Tests\Integration\Ecommerce\Infrastructure\Controller\Catalog;

use App\Ecommerce\Application\DTO\Catalog\CreateProductDto;
use App\Ecommerce\Application\DTO\Catalog\UpdateProductDto;
use App\Ecommerce\Application\UseCase\Catalog\CreateProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\GetProductsUseCase;
use App\Ecommerce\Application\UseCase\Catalog\GetProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\UpdateProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\UploadProductImageUseCase;
use App\Ecommerce\Domain\Exception\Catalog\ProductNotFoundException;
use App\Ecommerce\Domain\Model\Catalog\Picture;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Infrastructure\Presenter\Catalog\ProductPresenter;
use App\Tests\Support\AuthenticatesRequests;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ProductControllerTest extends WebTestCase
{
    use AuthenticatesRequests;

    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Catalogue writes are admin-only; the access rules themselves are covered by AccessControlTest.
        $this->authenticateAs($this->client, '99999999-9999-4999-8999-999999999999', ['ROLE_ADMIN']);
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

        $useCaseMock->expects($this->once())->method('execute')->with('11111111-1111-4111-8111-111111111111')->willReturn($productMock);
        $presenterMock->expects($this->once())->method('present')->with($productMock)->willReturn(['id' => '11111111-1111-4111-8111-111111111111', 'name' => 'Parfum']);

        static::getContainer()->set(GetProductUseCase::class, $useCaseMock);
        static::getContainer()->set(ProductPresenter::class, $presenterMock);

        $this->client->request('GET', '/product/11111111-1111-4111-8111-111111111111');

        $response = $this->client->getResponse();
        $this->assertSame(JsonResponse::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('11111111-1111-4111-8111-111111111111', $response->getContent());
    }

    public function testGetProductByIdNotFound(): void
    {
        $useCaseMock = $this->createMock(GetProductUseCase::class);
        $useCaseMock->expects($this->once())->method('execute')->with('33333333-3333-4333-8333-333333333333')
            ->willThrowException(new ProductNotFoundException('33333333-3333-4333-8333-333333333333'));

        static::getContainer()->set(GetProductUseCase::class, $useCaseMock);

        $this->client->request('GET', '/product/33333333-3333-4333-8333-333333333333');

        $response = $this->client->getResponse();
        $this->assertSame(JsonResponse::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(
            json_encode(['error' => 'Product with id "33333333-3333-4333-8333-333333333333" not found.']),
            $response->getContent()
        );
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
            'category_id' => '22222222-2222-4222-8222-222222222222',
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
        $productMock->method('getId')->willReturn('11111111-1111-4111-8111-111111111111');

        $useCaseMock->expects($this->once())
            ->method('execute')
            ->with($this->isInstanceOf(UpdateProductDto::class), '11111111-1111-4111-8111-111111111111')
            ->willReturn($productMock);

        static::getContainer()->set(UpdateProductUseCase::class, $useCaseMock);

        $payload = ['name' => 'Nouveau Nom', 'price' => 95.00, 'category_id' => '22222222-2222-4222-8222-222222222222'];

        $this->client->request('PUT', '/edit-product/11111111-1111-4111-8111-111111111111', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));

        $response = $this->client->getResponse();
        $this->assertSame(JsonResponse::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Product updated successfully', $data['result']);
        $this->assertSame('11111111-1111-4111-8111-111111111111', $data['data']);
    }

    public function testUploadPictureSuccess(): void
    {
        $useCaseMock = $this->createMock(UploadProductImageUseCase::class);
        $imageMock = $this->createMock(Picture::class);
        $imageMock->method('getId')->willReturn('img-999');

        $useCaseMock->expects($this->once())
            ->method('execute')
            ->with('11111111-1111-4111-8111-111111111111', $this->isInstanceOf(UploadedFile::class), 'Belle image')
            ->willReturn($imageMock);

        static::getContainer()->set(UploadProductImageUseCase::class, $useCaseMock);

        // Simulation d'un fichier temporaire pour l'upload multipart
        $tempFile = tempnam(sys_get_temp_dir(), 'test_img_');
        // Un vrai JPEG : le contenu est validé avant d'atteindre le contrôleur (P0-02).
        imagejpeg(imagecreatetruecolor(8, 8), $tempFile);
        $uploadedFile = new UploadedFile($tempFile, 'perfume.jpg', 'image/jpeg', null, true);

        // Envoi en format Multipart Form (les fichiers vont dans le 5ème argument de request())
        $this->client->request(
            'POST',
            '/upload-picture/11111111-1111-4111-8111-111111111111',
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
        $this->client->request('POST', '/upload-picture/11111111-1111-4111-8111-111111111111');

        $response = $this->client->getResponse();
        $this->assertSame(JsonResponse::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertSame(['error' => 'The "file" field is required.'], json_decode($response->getContent(), true));
    }
    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }
}
