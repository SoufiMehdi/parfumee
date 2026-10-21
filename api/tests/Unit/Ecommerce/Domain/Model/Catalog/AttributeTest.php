<?php

namespace Tests\Unit\Ecommerce\Domain\Model\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Attribute;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Ecommerce\Domain\Model\Catalog\Attribute
 */
final class AttributeTest extends TestCase
{
    public function testItCanBeInstantiatedWithValues(): void
    {
        $attribute = new Attribute(
            1.5,
            'Vanilla',
            'Large',
            'A description',
            'Glass bottle'
        );

        $this->assertSame(1.5, $attribute->getWeight());
        $this->assertSame('Vanilla', $attribute->getFragrance());
        $this->assertSame('Large', $attribute->getSize());
        $this->assertSame('A description', $attribute->getDescription());
        $this->assertSame('Glass bottle', $attribute->getPresentation());
    }

    public function testItCanBeInstantiatedWithDefaultValues(): void
    {
        $attribute = new Attribute();

        $this->assertNull($attribute->getWeight());
        $this->assertNull($attribute->getFragrance());
        $this->assertNull($attribute->getSize());
        $this->assertNull($attribute->getDescription());
        $this->assertNull($attribute->getPresentation());
    }

    public function testSettersUpdatePropertiesCorrectly(): void
    {
        $attribute = new Attribute();

        $attribute->setWeight(2.5);
        $attribute->setFragrance('Citrus');
        $attribute->setSize('Small');
        $attribute->setDescription('New description');
        $attribute->setPresentation('Plastic tube');

        $this->assertSame(2.5, $attribute->getWeight());
        $this->assertSame('Citrus', $attribute->getFragrance());
        $this->assertSame('Small', $attribute->getSize());
        $this->assertSame('New description', $attribute->getDescription());
        $this->assertSame('Plastic tube', $attribute->getPresentation());
    }
}