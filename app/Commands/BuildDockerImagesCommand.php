<?php

namespace App\Commands;

use App\Actions\Tools\BuildDockerImageAction;
use App\Actions\Tools\GetsDockerImageTag;
use App\Actions\Tools\RunDockerContainerAction;
use Illuminate\Console\Command;

class BuildDockerImagesCommand extends Command
{
    use GetsDockerImageTag;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'docker:build';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Build docker images for tools used in project';

    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = true;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(BuildDockerImageAction $builder, RunDockerContainerAction $runner)
    {
        $this->info('Docker tag: '.$this->getDockerBranch());
        if($this->isPhar()) {
            $this->error('This command is not available when run from phar archive');
            return 1;
        }

        collect([
            'ghcr.io/kduma-oss/cli-pdf-scan-splitter/pdf-page-extractor' => base_path('bin/pdf-page-extractor/'),
            'ghcr.io/kduma-oss/cli-pdf-scan-splitter/pdf-page-joiner' => base_path('bin/pdf-page-joiner/'),
            'ghcr.io/kduma-oss/cli-pdf-scan-splitter/barcode-scanner' => base_path('bin/barcode-scanner/'),
            'ghcr.io/kduma-oss/cli-pdf-scan-splitter/image-pdf-converter' => base_path('bin/image-pdf-converter/'),
        ])->each(function ($path, $tag) use ($builder, $runner) {
            $this->info($builder->getCommand($tag, $path));
            $builder->execute($tag, $path);
        });

        return Command::SUCCESS;
    }
}
