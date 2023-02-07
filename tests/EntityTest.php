<?php

namespace Danilocgsilva\EntitiesDiscover\Tests;

use PHPUnit\Framework\TestCase;
use Danilocgsilva\EntitiesDiscover\{Entity, ErrorLogInterface};
use PDO;
use Exception;

class EntityTest extends TestCase
{
    public function testGetForeignsException(): void
    {
        $this->expectException(Exception::class);
        $entity = new Entity($this->createMock(ErrorLogInterface::class));
        $entity->setPdo(
            new PDO('sqlite:messaging.sqlite3')
        );

        $generator = $entity->getForeigns();
        $generator->next();
    }
}
