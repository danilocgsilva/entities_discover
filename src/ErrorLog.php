<?php

namespace Danilocgsilva\EntitiesDiscover;

use Danilocgsilva\EntitiesDiscover\ErrorLogInterface;

class ErrorLog implements ErrorLogInterface
{
    public function message(string $message)
    {
        print($message . "\n");
    }
}
