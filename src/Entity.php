<?php

declare(strict_types=1);

namespace Danilocgsilva\EntitiesDiscover;

use PDO;
use Generator;
use ReflectionProperty;
use Exception;
use PDOException;
use Danilocgsilva\Database\Discover;
use Danilocgsilva\Database\Table;
use function Amp\async;
use Amp\Future;

class Entity
{
    private string $tableName;
    private PDO|null $pdo;
    private ErrorLogInterface $errorLog;
    private int $foreignsFound = 0;
    private TimeDebug|null $timeDebug = null;

    private PdoReceipt|null $pdoReceipt;

    public function __construct(ErrorLogInterface $errorLog)
    {
        $this->errorLog = $errorLog;
    }

    public function setTable(string $table): self
    {
        $this->tableName = $table;
        return $this;
    }

    public function setTimeDebug(TimeDebug $timeDebug): self
    {
        $this->timeDebug = $timeDebug;
        return $this;
    }

    public function setPdo(PDO $pdo): self
    {
        $this->pdo = $pdo;
        return $this;
    }

    public function setPdoReceipt(PdoReceipt $pdoReceipt): self
    {
        $this->pdoReceipt = $pdoReceipt;
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
     * @return array
     */
    public function discoverEntitiesOccurrencesByIdentityAsync(string $tableName, string|int $relatedEntityIdentity): array
    {
        if ($this->timeDebug) {
            $this->timeDebug->message('Staring fetches occurrences from table ' . $tableName);
        }

        $queryField = (new Table())
            ->setName($tableName)
            ->fetchFirstField($this->pdo)
            ->firstField;

        /** @var \Danilocgsilva\Database\Table[] $tables */
        $tables = [];
        foreach ($this->getTablesWithField($queryField) as $table) {
            $table->fetchFirstField($this->pdo);
            $tables[] = $table;

            if ($this->timeDebug) {
                $this->timeDebug->message("Fetched occurrence in table " . $table . " for table " . $tableName);
            }
        }

        /** @var array $occurrences */
        $occurrences = [];
        $future = [];

        foreach ($tables as $tableLoop) {
            if ($this->isLoopFieldTheSameFromTableOrigin($tableLoop, $queryField)) {
                continue;
            }

            $future[] = async(function () use ($tableLoop, $queryField, $relatedEntityIdentity, &$occurrences) {
                try {
                    $queryCount = sprintf(
                        "SELECT COUNT(%s) as occurrences FROM %s WHERE %s = :search;",
                        $tableLoop->firstField,
                        $tableLoop->getName(),
                        $queryField,
                        $relatedEntityIdentity
                    );

                    $preResult = $this->pdo->prepare($queryCount);
                    $preResult->execute([':search' => $relatedEntityIdentity]);
                    $row = $preResult->fetch(PDO::FETCH_ASSOC);
                    $occurrences[$tableLoop->getName()] = $row["occurrences"];
                } catch (Exception $e) {
                    $occurrences[$tableLoop->getName()] = "0";
                }
            });
        }

        Future\await($future);

        return $occurrences;
    }


    /**
     * Returns an associative array, givin the table name as a key and an integer as the occurrences count.
     *
     * @param string $tableName
     * @param string|integer $relatedEntityIdentity
     */
    public function discoverEntitiesOccurrencesByIdentitySync(string $tableName, string|int $relatedEntityIdentity): CountResults
    {
        if ($this->timeDebug) {
            $this->timeDebug->message('Staring fetches occurrences from table ' . $tableName);
        }

        $queryField = (new Table())
            ->setName($tableName)
            ->fetchFirstField($this->pdo)
            ->firstField;

        /** @var \Danilocgsilva\Database\Table[] $tables */
        $tables = [];
        foreach ($this->getTablesWithField($queryField) as $table) {
            $table->fetchFirstField($this->pdo);
            $tables[] = $table;

            if ($this->timeDebug) {
                $this->timeDebug->message("Fetched occurrence in table " . $table . " for table " . $tableName);
            }
        }

        $countResults = new CountResults();
        foreach ($tables as $tableLoop) {
            if ($this->isLoopFieldTheSameFromTableOrigin($tableLoop, $queryField)) {
                continue;
            }

            try {
                $queryCount = sprintf(
                    "SELECT COUNT(%s) as occurrences FROM %s WHERE %s = :search;",
                    $tableLoop->firstField,
                    ($tableName = $tableLoop->getName()),
                    $queryField,
                    $relatedEntityIdentity
                );

                $preResult = $this->pdo->prepare($queryCount);
                $preResult->execute([':search' => $relatedEntityIdentity]);
                $row = $preResult->fetch(PDO::FETCH_ASSOC);
                $countResults->addSucess($tableName, (int) $row["occurrences"]);
                if ($this->timeDebug) {
                    $this->timeDebug->message("Success on " . $tableName . ": counted: " . (int) $row["occurrences"]);
                }
            } catch (PDOException $pdoe) {
                $countResults->addFail(
                    $tableLoop->getName(), 
                    ($exceptionMessage = $pdoe->getMessage()), 
                    ($exceptionClass = get_class($pdoe))
                );
                if ($this->timeDebug) {
                    $this->timeDebug->message("Fail counting occurrences in " . $tableName. ", exeception message: " . $exceptionMessage . ", class: " . $exceptionClass . ".");
                }
            } catch (Exception $e) {
                if ($this->timeDebug) {
                    $this->timeDebug->message("Exception not expected in " . $tableName. ", exeception message: " . $e->getMessage() . ", class: " . get_class($e) . ".");
                }
                throw $e;
            }
        }

        return $countResults;
    }

    /**
     * @param string $field
     * @return Generator|\Danilocgsilva\Database\Table[]
     */
    private function getTablesWithField(string $field): Generator
    {
        $databaseDiscover = new Discover($this->pdo);
        return $databaseDiscover->tablesWithEqualFieldName($field);
    }

    private function isLoopFieldTheSameFromTableOrigin($tableLoop, $queryField): bool
    {
        return $tableLoop->firstField === $queryField;
    }
}
