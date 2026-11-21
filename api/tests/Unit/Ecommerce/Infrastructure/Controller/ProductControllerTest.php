<?php

declare(strict_types=1);

namespace App\Tests\Integration\Ecommerce\Infrastructure\Controller\Catalog;

use App\Ecommerce\Application\DTO\Catalog\CreateProductDto;
use App\Ecommerce\Application\DTO\Catalog\UpdateProductDto;
use App\Ecommerce\Application\UseCase\Catalog\CreateProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\GetProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\ListProductsUseCase;
use App\Ecommerce\Application\UseCase\Catalog\UpdateProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\UploadProductImageUseCase;
use App\Ecommerce\Domain\Exception\Catalog\ProductNotFoundException;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Model\Catalog\Picture;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Repository\Page;
use App\Ecommerce\Domain\Repository\Pagination;
use App\Ecommerce\Infrastructure\Presenter\Catalog\ProductPresenter;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\AttributesView;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\CategorySummaryView;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\PictureView;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\ProductView;
use App\Tests\Support\AuthenticatesRequests;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;

class ProductControllerTest extends WebTestCase
{
    use AuthenticatesRequests;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Catalogue writes are admin-only; the access rules themselves are covered by AccessControlTest.
        $this->authenticateAs($this->client, '99999999-9999-4999-8999-999999999999', ['ROLE_ADMIN']);
    }

    public function testGetProducts(): void
    {
        $category = new Category('22222222-2222-4222-8222-222222222222', 'Bougies', 'bougies');
        $useCaseMock = $this->createMock(ListProductsUseCase::class);
        $useCaseMock->expects($this->once())->method('execute')->willReturn(new Page(
            [new Product('11111111-1111-4111-8111-111111111111', 'Ambre', 'ambre', 12.5, $category)],
            total: 21,
            pagination: new Pagination(page: 1, perPage: 1),
        ));
        static::getContainer()->set(ListProductsUseCase::class, $useCaseMock);

        $this->client->request('GET', '/api/v1/shop/products?per_page=1');

        $response = $this->client->getResponse();
        $this->assertSame(JsonResponse::HTTP_OK, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertSame(['11111111-1111-4111-8111-111111111111'], array_column($data['items'], 'id'));
        $this->assertSame(['page' => 1, 'per_page' => 1, 'total' => 21, 'total_pages' => 21], $data['pagination']);
        $this->assertSame('21', $response->headers->get('X-Total-Count'));
    }

    public function testGetProductByIdSuccess(): void
    {
        $useCaseMock = $this->createMock(GetProductUseCase::class);
        $presenterMock = $this->createMock(ProductPresenter::class);
        $productMock = $this->createStub(Product::class);

        $useCaseMock->expects($this->once())->method('execute')->with('11111111-1111-4111-8111-111111111111')->willReturn($productMock);
        $presenterMock->expects($this->once())->method('present')->with($productMock)->willReturn(new ProductView(
            '11111111-1111-4111-8111-111111111111',
            'Parfum',
            'parfum',
            12.5,
            new CategorySummaryView('22222222-2222-4222-8222-222222222222', 'Bougies'),
            new AttributesView(180.0, null, null, null, null),
            [new PictureView('img-1', 'products/a.jpg', null, 2)],
        ));

        static::getContainer()->set(GetProductUseCase::class, $useCaseMock);
        static::getContainer()->set(ProductPresenter::class, $presenterMock);

        $this->client->request('GET', '/api/v1/shop/products/11111111-1111-4111-8111-111111111111');

        $response = $this->client->getResponse();
        $this->assertSame(JsonResponse::HTTP_OK, $response->getStatusCode());
        // The exact body: keys are snake_case, and a null stays in the response.
        $this->assertSame([
            'id' => '11111111-1111-4111-8111-111111111111',
            'name' => 'Parfum',
            'slug' => 'parfum',
            'price' => 12.5,
            'category' => ['id' => '22222222-2222-4222-8222-222222222222', 'name' => 'Bougies'],
            // 180, not 180.0: JSON has a single number type.
            'attributes' => ['weight' => 180, 'fragrance' => null, 'size' => null, 'description' => null, 'presentation' => null],
            'pictures' => [['id' => 'img-1', 'url' => 'products/a.jpg', 'alt' => null, 'sort_order' => 2]],
        ], json_decode($response->getContent(), true));
    }

    public function testGetProductByIdNotFound(): void
    {
        $useCaseMock = $this->createMock(GetProductUseCase::class);
        $useCaseMock->expects($this->once())->method('execute')->with('33333333-3333-4333-8333-333333333333')
            ->willThrowException(new ProductNotFoundException('33333333-3333-4333-8333-333333333333'));

        static::getContainer()->set(GetProductUseCase::class, $useCaseMock);

        $this->client->request('GET', '/api/v1/shop/products/33333333-3333-4333-8333-333333333333');

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
            'attributes' => ['capacity' => '100ml'],
        ];

        $this->client->request('POST', '/api/v1/admin/products', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));

        $response = $this->client->getResponse();
        $this->assertSame(JsonResponse::HTTP_CREATED, $response->getStatusCode());
        $this->assertSame(['id' => 'new-uuid'], json_decode($response->getContent(), true));
    }

    public function testUpdateProduct(): void
    {
        $useCaseMock = $this->createMock(UpdateProductUseCase::class);
        $product = new Product(
            '11111111-1111-4111-8111-111111111111',
            'Nouveau Nom',
            'nouveau-nom',
            95.0,
            new Category('22222222-2222-4222-8222-222222222222', 'Bougies', 'bougies'),
        );

        $useCaseMock->expects($this->once())
            ->method('execute')
            ->with($this->isInstanceOf(UpdateProductDto::class), '11111111-1111-4111-8111-111111111111')
            ->willReturn($product);

        static::getContainer()->set(UpdateProductUseCase::class, $useCaseMock);

        $payload = ['name' => 'Nouveau Nom', 'price' => 95.00, 'category_id' => '22222222-2222-4222-8222-222222222222'];

        $this->client->request('PUT', '/api/v1/admin/products/11111111-1111-4111-8111-111111111111', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));

        $response = $this->client->getResponse();
        $this->assertSame(JsonResponse::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        // The updated product is returned, as an account update returns the account.
        $this->assertSame('11111111-1111-4111-8111-111111111111', $data['id']);
        $this->assertSame('Nouveau Nom', $data['name']);
        $this->assertSame('nouveau-nom', $data['slug']);
        $this->assertEquals(95.0, $data['price']);
    }

    public function testUploadPictureSuccess(): void
    {
        $useCaseMock = $this->createMock(UploadProductImageUseCase::class);
        $imageMock = $this->createStub(Picture::class);
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
            '/api/v1/admin/products/11111111-1111-4111-8111-111111111111/pictures',
            ['alt' => 'Belle image'], // $_POST
            ['file' => $uploadedFile] // $_FILES
        );

        $response = $this->client->getResponse();
        $this->assertSame(JsonResponse::HTTP_CREATED, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame(['id' => 'img-999'], $data);
    }

    public function testUploadPictureBadRequest(): void
    {
        // On teste le cas d'erreur de validation du contrôleur (pas de fichier fourni)
        $this->client->request('POST', '/api/v1/admin/products/11111111-1111-4111-8111-111111111111/pictures');

        $response = $this->client->getResponse();
        $this->assertSame(JsonResponse::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertSame(['error' => 'The "file" field is required.'], json_decode($response->getContent(), true));
    }

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }
}
