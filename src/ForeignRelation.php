<?php

namespace Danilocgsilva\EntitiesDiscover;

class ForeignRelation
{
    public function __construct(
        private string $localField,
        private string $foreighTable,
        private int $foreignId
    ) {}

    public function getLocalField(): string
    {
        return $this->localField;
    }

    public function getForeignTable(): string
    {
        return $this->foreighTable;
    }

    public function getForeignIf(): int
    {
        return $this->foreignId;
    }
}
