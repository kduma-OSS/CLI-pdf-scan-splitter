<?php

namespace App\Actions\Tools;

use Phar;

trait DetectsPharArchives
{
    protected function isPhar(): bool
    {
        return strlen(Phar::running()) > 0;
    }
}
