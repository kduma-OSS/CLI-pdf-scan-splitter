<?php

namespace App\Actions\Tools;

use Phar;
use Spatie\TemporaryDirectory\TemporaryDirectory;

class TemporaryDirectoryCreatorAction
{
    protected function isPhar(): bool
    {
        return strlen(Phar::running()) > 0;
    }

    public function get(): TemporaryDirectory
    {
        return new TemporaryDirectory($this->isPhar() ? sys_get_temp_dir() : base_path('tmp'));
    }

    public function create(): TemporaryDirectory
    {
        return $this->get()->create();
    }
}
