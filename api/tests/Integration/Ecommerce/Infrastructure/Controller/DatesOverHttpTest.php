<?php

declare(strict_types=1);

namespace App\Tests\Integration\Ecommerce\Infrastructure\Controller;

use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineCategory;
use App\Ecommerce\Infrastructure\Persistence\Entity\User\DoctrineUser;
use App\Tests\Factory\Catalog\DoctrineCategoryFactory;
use App\Tests\Factory\Catalog\DoctrineProductFactory;
use App\Tests\Factory\User\DoctrineUserFactory;
use App\Tests\Support\DatabaseTestCase;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * created_at and updated_at over HTTP, against the real database and with a
 * frozen clock: what the fronts read after a change, and what must not move.
 *
 * Requests go straight through the kernel, so that they share the connection
 * (and the rolled-back transaction) of the test.
 */
final class DatesOverHttpTest extends DatabaseTestCase
{
    use ClockSensitiveTrait;

    private const string CREATED = '2026-11-01T09:30:00+00:00';
    private const string CHANGED = '2026-11-21T10:00:00+00:00';

    public function testAProductUpdateAnswersWithTheDateOfTheChange(): void
    {
        self::mockTime(new \DateTimeImmutable(self::CREATED));
        $editor = DoctrineUserFactory::new()->admin()->create();
        $category = DoctrineCategoryFactory::createOne();
        $product = DoctrineProductFactory::createOne(['name' => 'Ambre', 'price' => 20.0, 'category' => $category]);
        $this->entityManager->clear();

        self::mockTime(new \DateTimeImmutable(self::CHANGED));
        $response = $this->request('PUT', '/api/v1/admin/products/'.$product->getId(), $editor, [
            'name' => 'Ambre doux',
            'price' => 20.0,
            'category_id' => $category->getId(),
        ]);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $body = $this->decode($response);
        self::assertSame(self::CREATED, $body['created_at']);
        self::assertSame(self::CHANGED, $body['updated_at'], 'The response must carry the date of this change, not the previous one.');

        // Loaded by the same request, but not changed: their dates stay.
        $this->entityManager->clear();
        self::assertEquals(new \DateTimeImmutable(self::CREATED), $this->entityManager->find(DoctrineUser::class, $editor->getId())?->getUpdatedAt(), 'The signed-in account did not change.');
        self::assertEquals(new \DateTimeImmutable(self::CREATED), $this->entityManager->find(DoctrineCategory::class, $category->getId())?->getUpdatedAt(), 'The category did not change.');
    }

    public function testAnAccountUpdateAnswersWithTheDateOfTheChange(): void
    {
        self::mockTime(new \DateTimeImmutable(self::CREATED));
        $customer = DoctrineUserFactory::createOne(['firstName' => 'Ada', 'lastName' => 'Lovelace']);
        $this->entityManager->clear();

        self::mockTime(new \DateTimeImmutable(self::CHANGED));
        $response = $this->request('PUT', '/api/v1/shop/me', $customer, ['first_name' => 'Augusta', 'last_name' => 'King']);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $body = $this->decode($response);
        self::assertSame('Augusta', $body['first_name']);
        self::assertSame(self::CREATED, $body['created_at']);
        self::assertSame(self::CHANGED, $body['updated_at']);
    }

    public function testTheBackOfficeListsTheNewestAccountsFirst(): void
    {
        foreach (['first' => '2026-11-01', 'third' => '2026-11-03', 'second' => '2026-11-02'] as $name => $day) {
            self::mockTime(new \DateTimeImmutable($day.'T09:30:00+00:00'));
            DoctrineUserFactory::createOne(['email' => $name.'@example.test']);
        }
        self::mockTime(new \DateTimeImmutable('2026-10-01T09:30:00+00:00'));
        $admin = DoctrineUserFactory::new()->admin()->create(['email' => 'admin@example.test']);

        $response = $this->request('GET', '/api/v1/admin/users?sort=created_at&order=desc', $admin);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $items = $this->decode($response)['items'];
        self::assertIsArray($items);
        self::assertSame(['third@example.test', 'second@example.test', 'first@example.test', 'admin@example.test'], array_column($items, 'email'));
        self::assertSame('2026-11-03T09:30:00+00:00', $items[0]['created_at']);
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function request(string $method, string $uri, DoctrineUser $user, ?array $body = null): Response
    {
        $token = self::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
        \assert(null !== self::$kernel);

        return self::$kernel->handle(Request::create(
            $uri,
            $method,
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'CONTENT_TYPE' => 'application/json'],
            content: null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $data = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
