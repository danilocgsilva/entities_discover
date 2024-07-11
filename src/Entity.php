<?php

declare(strict_types=1);

namespace Danilocgsilva\EntitiesDiscover;

use PDO;
use Generator;
use ReflectionProperty;
use Exception;

class Entity
{
    private string $tableName;

    private PDO $pdo;

    private LogInterface $errorLog;

    private LogInterface $debugMessages;

    private LogInterface $timeDebug;
    
    private int $foreignsFound = 0;

    private array $skipTables = [];


    private DiscoverRelations $discoverRelations;

    public function setDebugMessages(LogInterface $debugMessages): self
    {
        $this->debugMessages = $debugMessages;
        return $this;
    }

    public function setTimeDebug(LogInterface $timeDebug): self
    {
        $this->timeDebug = $timeDebug;
        return $this;
    }

    public function setSkipTables(array $skipingTables): self
    {
        $this->skipTables = $skipingTables;
        return $this;
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

    /**
     * Returns an associative array, givin the table name as a key and an integer as the occurrences count.
     *
     * @param string $tableName
     * @param string|integer $relatedEntityIdentity
     */
    public function discoverEntitiesOccurrencesByIdentitySync(
        string $tableName, 
        string|int $relatedEntityIdentity
    ): CountResults
    {
        if (isset($this->timeDebug)) {
            $this->timeDebug->message('Staring fetches occurrences from table ' . $tableName);
        }

        if (!isset($this->pdo)) {
            throw new Exception("This methods requires a pdo setted in the class.");
        }

        $this->discoverRelations = (new DiscoverRelations())
        ->setPdo($this->pdo)
        ->setSkipTables($this->skipTables);

        if (isset($this->debugMessages)) {
            $this->debugMessages->message("Checks successfully meeted.");
            $this->discoverRelations->setDebugMessages($this->debugMessages);
        }

        /**
         * @var CountResults
         */
        $results = $this
        ->discoverRelations
        ->discoverEntitiesOccurrencesByIdentitySync($tableName, $relatedEntityIdentity);

        if (isset($this->timeDebug)) {
            $this->timeDebug->message('Finished with ' . $tableName);
        }

        return $results;
    }

    public function getTableIdFieldName(): string
    {
        return $this->discoverRelations->getTableIdFieldName();
    }
}
