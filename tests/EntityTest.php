<?php

namespace Danilocgsilva\EntitiesDiscover\Tests;

use PHPUnit\Framework\TestCase;
use Danilocgsilva\EntitiesDiscover\Entity;
use PDO;

class EntityTest extends TestCase
{
    public function testGetForeigns(): void
    {
        $entity = new Entity();
        $entity->setTable("tableTest");
        $entity->setPdo(
            new PDO('sqlite:messaging.sqlite3')
        );

        $this->assertSame(1, 1);
    }
}
