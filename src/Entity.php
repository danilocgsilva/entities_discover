<?php

namespace Danilocgsilva\EntitiesDiscover;

use PDO;
use Generator;
use ReflectionProperty;
use Exception;

class Entity
{
    private string $tableName;
    private PDO $pdo;
    private ErrorLogInterface $errorLog;
    private int $foreignsFound = 0;

    public function __construct(ErrorLogInterface $errorLog)
    {
        $this->errorLog = $errorLog;
    }

    public function setTable(string $table): self
    {
        $this->tableName = $table;
        return $this;
    }

    public function setPdo(PDO $pdo): self
    {
        $this->pdo = $pdo;
        return $this;
    }

    public function getForeignsFound(): int
    {
        return $this->foreignsFound;
    }

    public function getForeigns(): Generator
    {
        if (!(new ReflectionProperty($this, 'tableName'))->isInitialized($this)) {
            $message = "You still have not setted the table.";
            $this->errorLog->message($message);
            throw new Exception($message);
        }
        
        $queryBaseString = 'SELECT * FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE ' .
        'WHERE TABLE_SCHEMA = :db_name AND TABLE_NAME = :table_name ' .
        ' AND REFERENCED_TABLE_NAME IS NOT NULL;';
        $toQuery = $this->pdo->prepare($queryBaseString, [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY]);
        $toQuery->execute([
            ':db_name' => $this->pdo->query('SELECT database()')->fetchColumn(), 
            ':table_name' => $this->tableName
        ]);
        while ($row = $toQuery->fetch(PDO::FETCH_ASSOC)) {
            $this->foreignsFound++;
            yield $row['COLUMN_NAME'];
        }
    }
}
