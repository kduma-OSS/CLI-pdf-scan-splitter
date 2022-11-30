<?php

namespace App\Actions\Tools;

use Spatie\TemporaryDirectory\TemporaryDirectory;

class RunDockerContainerAction
{
    use GetsDockerImageTag;

    private TemporaryDirectory|string|null $temporaryDirectory = null;
    private ?string $name = null;


    public function withTemporaryDirectory(TemporaryDirectory|string|null $withTemporaryDirectory): self
    {
        $action = clone $this;
        $action->temporaryDirectory = $withTemporaryDirectory;

        return $action;
    }

    public function withName(?string $name): self
    {
        $action = clone $this;
        $action->name = $name;

        return $action;
    }

    public function execute(string $dockerImageName, string $arguments = '', array &$output = null, int &$return = null): string
    {
        $command = $this->getCommand($dockerImageName, arguments: $arguments, interactive: false);

        return exec($command, $output, $return);
    }

    public function getCommand(string $dockerImageName, string $arguments = '', bool $interactive = true): string
    {
        $options = collect([
            '--rm'          => true,
            '--tty'         => true,
        ]);

        if ($interactive) {
            $options->put('--interactive', true);
        }

        if ($this->name) {
            $options->put('--name', $this->name);
        }

        if ($this->temporaryDirectory) {
            if($this->temporaryDirectory instanceof TemporaryDirectory && !$this->temporaryDirectory->exists())
                $this->temporaryDirectory = $this->temporaryDirectory->create();

            $path = $this->temporaryDirectory instanceof TemporaryDirectory ? $this->temporaryDirectory->path() : $this->temporaryDirectory;
            $options->put('--volume', $path . ':' . '/data');
        }

        $options = $options->map(function ($value, $key) {
            if ($value === true)
                return $key;

            return sprintf('%s %s', $key, escapeshellarg($value));
        })->implode(' ');

        $command = sprintf(
            'docker run %s %s %s 2>&1',
            $options,
            escapeshellarg($this->getImageTag($dockerImageName)),
            $arguments
        );
        return $command;
    }
}
