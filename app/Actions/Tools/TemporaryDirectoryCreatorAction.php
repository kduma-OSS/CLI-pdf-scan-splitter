<?php

namespace App\Actions\Tools;

use Spatie\TemporaryDirectory\TemporaryDirectory;

class TemporaryDirectoryCreatorAction
{
    use DetectsPharArchives;

    public function get(): TemporaryDirectory
    {
        return new TemporaryDirectory($this->isPhar() ? sys_get_temp_dir() : base_path('tmp'));
    }

    public function create(): TemporaryDirectory
    {
        return $this->get()->create();
    }
}
