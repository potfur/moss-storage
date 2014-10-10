<?php

/*
 * This file is part of the Storage package
 *
 * (c) Michal Wachowski <wachowski.michal@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Moss\Storage;

class PgSQLSerializableTransactionTest extends \PHPUnit_Framework_TestCase
{
    public function testTransactionStart()
    {
        $storage = new PgSQLSerializableTransaction($this->mockDriver(), $this->mockBuilder());
        $this->assertInstanceOf('Moss\Storage\Driver\DriverInterface', $storage->getDriver());
        $this->assertTrue($storage->transactionCheck());
    }

    protected function mockDriver()
    {
        $mock = $this->getMock('Moss\Storage\Driver\DriverInterface');
        return $mock;
    }

    protected function mockBuilder()
    {
        $mock = $this->getMock('Moss\Storage\Builder\QueryBuilderInterface');

        return $mock;
    }
} 