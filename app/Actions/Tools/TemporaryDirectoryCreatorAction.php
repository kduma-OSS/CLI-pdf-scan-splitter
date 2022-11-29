<?php

namespace App\Actions\Tools;

use Spatie\TemporaryDirectory\TemporaryDirectory;

class TemporaryDirectoryCreatorAction
{
    public function get(): TemporaryDirectory
    {
        return new TemporaryDirectory(base_path('tmp'));
    }

    public function create(): TemporaryDirectory
    {
        return $this->get()->create();
    }
}
