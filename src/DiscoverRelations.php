<?php

declare(strict_types=1);

namespace Danilocgsilva\EntitiesDiscover;

use PDO;
use PDOException;
use Danilocgsilva\Database\Table;
use Exception;
use Danilocgsilva\Database\Discover;
use Generator;
use Danilocgsilva\EntitiesDiscover\CountResults;

class DiscoverRelations
{
    private LogInterface $debugMessages;

    private PDO $pdo;

    private array $skipTables = [];

    private string $tableIdFieldName;

    public function setPdo(PDO $pdo): self
    {
        $this->pdo = $pdo;
        return $this;
    }

    public function setSkipTables(array $skipingTables): self
    {
        $this->skipTables = $skipingTables;
        return $this;
    }

    public function setDebugMessages(LogInterface $debugMessages): self
    {
        $this->debugMessages = $debugMessages;
        return $this;
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
        if (isset($this->debugMessages)) {
            $this->debugMessages->message('Staring fetches occurrences from table ' . $tableName);
        }

        $queryField = $this->getTableIdFieldName($tableName);

        /** @var \Danilocgsilva\Database\Table[] $tables */
        $tables = [];
        foreach ($this->getTablesWithField($queryField) as $table) {
            $table->fetchFirstField($this->pdo);
            $tables[] = $table;

            if (isset($this->debugMessages)) {
                $this->debugMessages->message("Fetched occurrence in table " . $table . " for table " . $tableName);
            }
        }

        $countResults = new CountResults();
        foreach ($tables as $tableLoop) {
            if ($this->isLoopFieldTheSameFromTableOrigin($tableLoop, $queryField)) {
                continue;
            }
            if (in_array($tableLoop->getName(), $this->skipTables)) {
                if (isset($this->debugMessages)) {
                    $this->debugMessages->message(sprintf("Table %s skiped.", $tableLoop->getName()));
                }
                continue;
            }

            $fillResults = new FillResults($tableLoop, $queryField, $relatedEntityIdentity, $countResults, $this->pdo);
            try {
                if (isset($this->debugMessages)) {
                    $fillResults->setTimeDebug($this->debugMessages);
                }

                $fillResults->add();
                
            } catch (PDOException $pdoe) {
                $countResults->addFail(
                    $tableLoop->getName(), 
                    ($exceptionMessage = $pdoe->getMessage()), 
                    ($exceptionClass = get_class($pdoe))
                );
                if (isset($this->debugMessages)) {
                    $this->debugMessages->message("Fail counting occurrences in " . $tableName. ", exeception message: " . $exceptionMessage . ", class: " . $exceptionClass . ".");
                }
            } catch (Exception $e) {
                if (isset($this->debugMessages)) {
                    $this->debugMessages->message("Exception not expected in " . $tableName. ", exeception message: " . $e->getMessage() . ", class: " . get_class($e) . ".");
                }
                throw $e;
            }
        }

        return $countResults;
    }

    public function getTableIdFieldName(string|null $tableName = null): string
    {
        if (!isset($this->tableIdFieldName) && $tableName) {
            $this->tableIdFieldName = (new Table())
            ->setName($tableName)
            ->fetchFirstField($this->pdo)
            ->firstField;
        }
        return $this->tableIdFieldName;
    }

    private function isLoopFieldTheSameFromTableOrigin($tableLoop, $queryField): bool
    {
        return $tableLoop->firstField === $queryField;
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
}
