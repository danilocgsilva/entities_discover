<?php

namespace Danilocgsilva\EntitiesDiscover;

use PDO;
use Generator;
use ReflectionProperty;
use Exception;
use Danilocgsilva\Database\Discover;
use Danilocgsilva\Database\Table;

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
            yield new ForeignRelation(
                $row['COLUMN_NAME'], 
                $row['REFERENCED_TABLE_NAME'], 
                $row['REFERENCED_COLUMN_NAME']
            );
        }
    }

    public function discoverEntitiesOccurrencesByIdentity(string $tableName, string|int $relatedEntityIdentity): array
    {
        $queryField = (new Table())
            ->setName($tableName)
            ->fetchFirstField($this->pdo)
            ->firstField;

        $tables = $this->getTablesWithField($queryField);

        $occurrences = [];
        foreach ($tables as $tableLoop) {

            $tableLoop->fetchFirstField($this->pdo);
            if ($tableLoop->firstField === $queryField) {
                continue;
            }
            
            $queryCount = sprintf(
                "SELECT COUNT(%s) as occurrences FROM %s WHERE %s = :search;", 
                $tableLoop->firstField,
                $tableLoop->getName(),
                $queryField
            );
            $preResult = $this->pdo->prepare($queryCount);
            $preResult->execute([':search' => $relatedEntityIdentity]);
            $row = $preResult->fetch(PDO::FETCH_ASSOC);
            $occurrences[$tableLoop->getName()] = $row['occurrences'];
        }
        return $occurrences;
    }

    private function getTablesWithField(string $field)
    {
        $databaseDiscover = new Discover($this->pdo);
        $generator = $databaseDiscover->tablesWithEqualFieldName($field);
        $tables = iterator_to_array($generator);
        return $tables;
    }
}
