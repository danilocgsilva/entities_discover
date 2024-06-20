<?php

declare(strict_types= 1);

namespace Danilocgsilva\EntitiesDiscover;

class PdoReceipt
{
    public readonly string $host;

    public readonly string $user;

    public readonly string $password;

    public readonly string $database;

    public function setHost(string $host): self
    {
        $this->host = $host;
        return $this;
    }

    public function setUser(string $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function setDatabase(string $database): self
    {
        $this->database = $database;
        return $this;
    }
}
