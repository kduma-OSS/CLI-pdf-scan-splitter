<?php

namespace App\Actions;

use App\Actions\Tools\RunDockerContainerAction;
use App\Actions\Tools\Exceptions\PdfPageContentsExtractorException;
use App\Actions\Tools\TemporaryDirectoryCreatorAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use SplFileInfo;

class PdfPagesJoinerAction
{
    public function __construct(
        protected RunDockerContainerAction $runDockerContainerAction,
        protected TemporaryDirectoryCreatorAction $tempDirMaker
    ) {}

    public function execute(array|Collection $input_files, string|SplFileInfo $output_file): void
    {
        if(is_string($output_file))
            $output_file = new SplFileInfo($output_file);

        if(is_array($input_files))
            $input_files = collect($input_files);

        $input_files = $input_files
            ->map(function($input_file) {
                if(is_string($input_file))
                    return new SplFileInfo($input_file);

                return $input_file;
            })
            ->values()
            ->mapWithKeys(fn(SplFileInfo $file, $index) => ['input-'.str_pad($index, 5, '0', STR_PAD_LEFT).'.pdf' => $file]);

        $temporaryDirectory = $this->tempDirMaker->create();

        $input_files->each(function(SplFileInfo $input_file, $name) use ($temporaryDirectory) {
            copy($input_file->getPathname(), $temporaryDirectory->path($name));
        });

        $action = $this
            ->runDockerContainerAction
            ->withTemporaryDirectory($temporaryDirectory);

        $action->execute(dockerImageName: 'ghcr.io/kduma-oss/cli-pdf-scan-splitter/pdf-page-joiner',output: $output, return: $return);

        if (0 != $return) {
            $temporaryDirectory->delete();
            throw new PdfPageContentsExtractorException(
                command: $action->getCommand(dockerImageName: 'ghcr.io/kduma-oss/cli-pdf-scan-splitter/pdf-page-joiner'),
                code: $return,
                output: $output,
            );
        }

        copy($temporaryDirectory->path('output.pdf'), $output_file->getPathname());

        $temporaryDirectory->delete();
    }
}
