<?php

namespace Danilocgsilva\EntitiesDiscover;

class Entity
{
    private string $tableName;

    public function setTable(string $table)
    {
        $this->tableName = $table;
    }

    public function dev()
    {
        return 'dev test';
    }
}
