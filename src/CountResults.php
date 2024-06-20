<?php

declare(strict_types=1);

namespace Danilocgsilva\EntitiesDiscover;

class CountResults
{
    private $successes = [];

    private $fails = [];
    
    public function addSucess(string $tableName, int $count): void
    {
        $this->successes[$tableName] = $count;
    }

    public function addFail(string $tableName): void
    {
        $this->fails[$tableName];
    }

    public function getSuccesses(): array
    {
        return $this->successes;
    }

    public function getFails(): array
    {
        return $this->fails;
    }
}
