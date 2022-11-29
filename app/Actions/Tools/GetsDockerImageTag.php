<?php

namespace App\Actions\Tools;

use Illuminate\Support\Str;

trait GetsDockerImageTag
{
    use DetectsPharArchives;

    protected function getImageTag(string $dockerImageName): string
    {
        return $dockerImageName . ':' . $this->getDockerBranch();
    }

    protected function getDockerBranch(): string
    {
        $version = config('app.version');
        if(!Str::startsWith($version, 'v'))
            $version = 'latest';

        $environment = config('app.env');

        if($this->isPhar() || $environment == 'production')
            return $version;

        try {
            $composer_version = \Composer\InstalledVersions::getPrettyVersion('kduma/pdf-scan-splitter-tool');
            if(Str::startsWith($composer_version, 'dev-'))
                $composer_version = Str::after($composer_version, 'dev-');
        } catch (\OutOfBoundsException $e) {
            return 'master';
        }

        return $composer_version ?? 'master';
    }
}
