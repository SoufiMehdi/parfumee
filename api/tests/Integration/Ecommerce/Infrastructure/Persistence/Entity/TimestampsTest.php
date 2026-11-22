<?php

declare(strict_types=1);

namespace App\Tests\Integration\Ecommerce\Infrastructure\Persistence\Entity;

use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineCategory;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrinePicture;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineProduct;
use App\Ecommerce\Infrastructure\Persistence\Entity\User\DoctrineAddress;
use App\Ecommerce\Infrastructure\Persistence\Entity\User\DoctrineRefreshToken;
use App\Ecommerce\Infrastructure\Persistence\Entity\User\DoctrineUser;
use App\Tests\Factory\Catalog\DoctrineCategoryFactory;
use App\Tests\Factory\Catalog\DoctrinePictureFactory;
use App\Tests\Factory\Catalog\DoctrineProductFactory;
use App\Tests\Factory\User\DoctrineAddressFactory;
use App\Tests\Factory\User\DoctrineUserFactory;
use App\Tests\Support\DatabaseTestCase;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * created_at and updated_at on every entity, against the real database. Gedmo
 * Timestampable sets them with the time of the clock service, frozen here.
 *
 * @phpstan-type Timestamped DoctrineCategory|DoctrineProduct|DoctrinePicture|DoctrineUser|DoctrineAddress
 */
final class TimestampsTest extends DatabaseTestCase
{
    use ClockSensitiveTrait;

    private const string CREATED = '2026-11-01T09:30:00+00:00';
    private const string CHANGED = '2026-11-21T10:00:00+00:00';

    public function testEveryEntityHasBothDates(): void
    {
        $missing = [];
        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            // refresh_tokens belongs to the refresh token bundle.
            if (!str_starts_with($metadata->getName(), 'App\\') || DoctrineRefreshToken::class === $metadata->getName()) {
                continue;
            }
            foreach (['createdAt', 'updatedAt'] as $field) {
                if (!$metadata->hasField($field) || Types::DATETIMETZ_IMMUTABLE !== $metadata->getTypeOfField($field) || $metadata->isNullable($field)) {
                    $missing[] = $metadata->getName().'::$'.$field;
                }
            }
        }

        self::assertSame([], $missing, 'Every entity uses the Timestamps trait.');
    }

    /**
     * @return iterable<string, array{callable(): Timestamped}>
     */
    public static function entities(): iterable
    {
        yield 'category' => [static fn (): DoctrineCategory => DoctrineCategoryFactory::createOne()];
        yield 'product' => [static fn (): DoctrineProduct => DoctrineProductFactory::createOne()];
        yield 'picture' => [static fn (): DoctrinePicture => DoctrinePictureFactory::createOne()];
        yield 'account' => [static fn (): DoctrineUser => DoctrineUserFactory::createOne()];
        yield 'address' => [static fn (): DoctrineAddress => DoctrineAddressFactory::createOne()];
    }

    /**
     * @param callable(): Timestamped $create
     */
    #[DataProvider('entities')]
    public function testAnInsertedRowGetsTheTimeOfItsCreationForBothDates(callable $create): void
    {
        self::mockTime(new \DateTimeImmutable(self::CREATED));

        $row = $this->reload($create());

        self::assertEquals(new \DateTimeImmutable(self::CREATED), $row->getCreatedAt());
        self::assertEquals(new \DateTimeImmutable(self::CREATED), $row->getUpdatedAt());
    }

    /**
     * @return iterable<string, array{callable(): Timestamped, callable(Timestamped): void}>
     */
    public static function changes(): iterable
    {
        yield 'category' => [
            static fn (): DoctrineCategory => DoctrineCategoryFactory::createOne(),
            static fn (DoctrineCategory $category) => $category->setName('Fondants'),
        ];
        yield 'product' => [
            static fn (): DoctrineProduct => DoctrineProductFactory::createOne(['price' => 20.0]),
            static fn (DoctrineProduct $product) => $product->setPrice(22.5),
        ];
        yield 'picture' => [
            static fn (): DoctrinePicture => DoctrinePictureFactory::createOne(),
            static fn (DoctrinePicture $picture) => $picture->setAlt('Une bougie allumée'),
        ];
        yield 'account' => [
            static fn (): DoctrineUser => DoctrineUserFactory::createOne(),
            static fn (DoctrineUser $user) => $user->setFirstName('Grace'),
        ];
        yield 'address' => [
            static fn (): DoctrineAddress => DoctrineAddressFactory::createOne(),
            static fn (DoctrineAddress $address) => $address->setCity('Lyon'),
        ];
    }

    /**
     * @param callable(): Timestamped     $create
     * @param callable(Timestamped): void $change
     */
    #[DataProvider('changes')]
    public function testAChangeMovesUpdatedAtAndKeepsCreatedAt(callable $create, callable $change): void
    {
        self::mockTime(new \DateTimeImmutable(self::CREATED));
        $row = $this->reload($create());

        self::mockTime(new \DateTimeImmutable(self::CHANGED));
        $change($row);
        $this->entityManager->flush();
        $row = $this->reload($row);

        self::assertEquals(new \DateTimeImmutable(self::CREATED), $row->getCreatedAt());
        self::assertEquals(new \DateTimeImmutable(self::CHANGED), $row->getUpdatedAt());
    }

    public function testAFlushWritesNothingForRowsThatDidNotChange(): void
    {
        self::mockTime(new \DateTimeImmutable(self::CREATED));
        // A round price on purpose: PostgreSQL returns it as "20.00", which a float
        // (20.0) or a careless conversion ("20") would never equal.
        $pictureId = DoctrinePictureFactory::createOne(['product' => DoctrineProductFactory::new(['price' => 20.0])])->getId();
        $addressId = DoctrineAddressFactory::createOne()->getId();
        $this->entityManager->clear();

        // Every kind of row in memory, with its relations, as a request loads them: the
        // signed-in account, the product being edited and its category...
        $picture = $this->entityManager->find(DoctrinePicture::class, $pictureId);
        $address = $this->entityManager->find(DoctrineAddress::class, $addressId);
        self::assertNotNull($picture?->getProduct()?->getCategory()->getName());
        self::assertNotNull($address?->getUser()->getEmail());

        self::mockTime(new \DateTimeImmutable(self::CHANGED));
        $queries = $this->queriesRunBy(fn () => $this->entityManager->flush());

        // ...must not be rewritten when something else is saved: their updated_at would move.
        self::assertSame([], $queries, implode("\n", $queries));
    }

    public function testADateKeepsItsInstantWhateverTheTimeZoneOfTheClock(): void
    {
        // 20:00 in Paris is 19:00 in UTC. A column without time zone would keep the
        // "20:00" and read it back as 20:00 in UTC: one hour off.
        self::mockTime(new \DateTimeImmutable('2026-11-21 20:00:00', new \DateTimeZone('Europe/Paris')));

        $row = $this->reload(DoctrineCategoryFactory::createOne());

        self::assertEquals(new \DateTimeImmutable('2026-11-21T19:00:00+00:00'), $row->getCreatedAt());
    }

    /**
     * Reads the row again from the database, not from the memory of Doctrine.
     *
     * @template T of Timestamped
     *
     * @param T $row
     *
     * @return T
     */
    private function reload(object $row): object
    {
        $id = $row->getId();
        $this->entityManager->clear();

        $reloaded = $this->entityManager->find($row::class, $id);
        self::assertNotNull($reloaded);

        return $reloaded;
    }
}
