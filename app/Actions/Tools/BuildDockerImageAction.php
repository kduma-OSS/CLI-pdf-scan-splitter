<?php

namespace App\Actions\Tools;

class BuildDockerImageAction
{
    public function execute(string $tag, string $path)
    {
        $command = $this->getCommand($tag, $path);

        shell_exec($command);
    }

    public function getCommand(string $tag, string $path): string
    {
        return sprintf(
            'docker build --tag %s --file %s %s',
            escapeshellarg($tag),
            escapeshellarg($path . '/Dockerfile'),
            escapeshellarg($path)
        );
    }
}
