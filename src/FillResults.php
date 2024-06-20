<?php

declare(strict_types=1);

namespace Danilocgsilva\EntitiesDiscover;

use Exception;
use PDO;

class FillResults
{
    private TimeDebug|null $timeDebug = null;

    private bool $retry = false;

    public function __construct(private $tableLoop, private $queryField, private $relatedEntityIdentity, private CountResults $countResults, private PDO $pdo)
    {
    }

    public function setTimeDebug(TimeDebug $timeDebug): self
    {
        $this->timeDebug = $timeDebug;
        return $this;
    }

    public function setRetry(): self
    {
        $this->retry = true;
        return $this;
    }

    public function fill(): void
    {
        if ($this->retry) {
            $this->addOnSuccessWithTrials();
        } else {
            $this->addOnSuccess();
        }
    }

    private function addOnSuccess(): void
    {
        $queryCount = sprintf(
            "SELECT COUNT(%s) as occurrences FROM %s WHERE %s = :search;",
            $this->tableLoop->firstField,
            $this->tableLoop->getName(),
            $this->queryField,
            $this->relatedEntityIdentity
        );

        $preResult = $this->pdo->prepare($queryCount);
        $preResult->execute([':search' => $this->relatedEntityIdentity]);
        $row = $preResult->fetch(PDO::FETCH_ASSOC);
        $this->countResults->addSucess(($tableName = $this->tableLoop->getName()), (int) $row["occurrences"]);
        if ($this->timeDebug) {
            $this->timeDebug->message("Success on " . $tableName . ": counted: " . (int) $row["occurrences"]);
        }
    }

    private function addOnSuccessWithTrials(): void
    {
        $maximumTrials = 3;
        $currentTrial = 1;
        while ($currentTrial <= $maximumTrials) {
            try {
                $this->addOnSuccess();
                break;
            } catch (Exception $e) {
                if ($this->timeDebug) {
                    $this->timeDebug->message("Fail in trial " . $currentTrial . ". Message: " . $e->getMessage() . ". Exception class: " . get_class($e));
                }
                if ($currentTrial === $maximumTrials) {
                    throw $e;
                }
                $currentTrial++;
            }
        }
    }
}
