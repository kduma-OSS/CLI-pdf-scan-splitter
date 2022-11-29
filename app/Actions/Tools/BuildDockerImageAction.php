<?php

namespace App\Actions\Tools;

class BuildDockerImageAction
{
    use GetsDockerImageTag;

    public function execute(string $tag, string $path)
    {
        $command = $this->getCommand($tag, $path);

        shell_exec($command);
    }

    public function getCommand(string $tag, string $path): string
    {
        return sprintf(
            'docker build --tag %s --file %s %s',
            escapeshellarg($this->getImageTag($tag)),
            escapeshellarg($path . '/Dockerfile'),
            escapeshellarg($path)
        );
    }
}
