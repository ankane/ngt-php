<?php

use PHPUnit\Framework\TestCase;

final class IndexTest extends TestCase
{
    public function testWorks()
    {
        $objects = [
            [1, 1, 2, 1],
            [5, 4, 6, 5],
            [1, 2, 1, 2]
        ];
        $index = new Ngt\Index(4);
        $this->assertEquals([1, 2, 3], $index->batchInsert($objects));

        $path = tempnam(sys_get_temp_dir(), 'index');
        unlink($path);
        $this->assertTrue($index->save($path));

        $query = $objects[0];
        $result = $index->search($query, size: 3);
        $this->assertCount(3, $result);
        $this->assertEquals([1, 3, 2], array_map(fn ($r) => $r['id'], $result));
        $this->assertEquals(0, $result[0]['distance']);
        $this->assertEqualsWithDelta(1.732050776481628, $result[1]['distance'], 0.00001);
        $this->assertEqualsWithDelta(7.549834251403809, $result[2]['distance'], 0.00001);

        $index = Ngt\Index::load($path);
        $result = $index->search($query, size: 3);
        $this->assertEquals([1, 3, 2], array_map(fn ($r) => $r['id'], $result));
    }

    public function testRemove()
    {
        $objects = [
            [1, 1, 2, 1],
            [5, 4, 6, 5],
            [1, 2, 1, 2]
        ];
        $index = new Ngt\Index(4);
        $index->batchInsert($objects);

        $this->assertTrue($index->remove(3));
        $this->assertFalse($index->remove(3));
        $this->assertFalse($index->remove(4));

        $result = $index->search($objects[0]);
        $this->assertCount(2, $result);
    }

    public function testObjectTypeFloat16()
    {
        $this->expectException(Ngt\Exception::class);
        $this->expectExceptionMessage('Method not supported for this object type');

        $object = [1.5, 2.5, 3.5];
        $index = new Ngt\Index(3, objectType: Ngt\ObjectType::Float16);
        $this->assertEquals(1, $index->insert($object));
        $this->assertTrue($index->buildIndex());
        $this->assertEquals($object, $index->object(1));
    }

    public function testObjectTypeInteger()
    {
        $object = [1, 2, 3];
        $index = new Ngt\Index(3, objectType: Ngt\ObjectType::Integer);
        $this->assertEquals(1, $index->insert($object));
        $this->assertTrue($index->buildIndex());
        $this->assertEquals($object, $index->object(1));
    }

    public function testEmpty()
    {
        $index = new Ngt\Index(3);
        $this->assertEmpty($index->batchInsert([]));
    }

    public function testBadObjectType()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown object type');

        new Ngt\Index(3, objectType: 'bad');
    }

    public function testBadDistanceType()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown distance type');

        new Ngt\Index(3, distanceType: 'bad');
    }

    public function testInsertBadDimensions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bad dimensions');

        $index = new Ngt\Index(3);
        $index->insert([1, 2]);
    }

    public function testBatchInsertBadDimensions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bad dimensions');

        $index = new Ngt\Index(3);
        $index->batchInsert([[1, 2]]);
    }

    public function testSearchBadDimensions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bad dimensions');

        $index = new Ngt\Index(3);
        $index->insert([1, 2, 3]);
        $index->search([1, 2]);
    }
}
