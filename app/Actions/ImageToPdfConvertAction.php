<?php

namespace App\Actions;

use App\Actions\Tools\RunDockerContainerAction;
use App\Actions\Tools\Exceptions\PdfPageContentsExtractorException;
use App\Actions\Tools\TemporaryDirectoryCreatorAction;
use Illuminate\Support\Str;
use SplFileInfo;

class ImageToPdfConvertAction
{
    public function __construct(
        protected RunDockerContainerAction $runDockerContainerAction,
        protected TemporaryDirectoryCreatorAction $tempDirMaker
    ) {}

    public function execute(string|SplFileInfo $input_file, string|SplFileInfo $output_file): void
    {
        if(is_string($input_file))
            $input_file = new SplFileInfo($input_file);

        if(is_string($output_file))
            $output_file = new SplFileInfo($output_file);

        $temporaryDirectory = $this->tempDirMaker->create();

        copy($input_file->getPathname(), $temporaryDirectory->path('input.'.$input_file->getExtension()));

        $action = $this
            ->runDockerContainerAction
            ->withTemporaryDirectory($temporaryDirectory);

        $action->execute(dockerImageName: 'ghcr.io/kduma-oss/cli-pdf-scan-splitter/image-pdf-converter',output: $output, return: $return);

        if (0 != $return) {
            $temporaryDirectory->delete();
            throw new PdfPageContentsExtractorException(
                command: $action->getCommand(dockerImageName: 'ghcr.io/kduma-oss/cli-pdf-scan-splitter/image-pdf-converter'),
                code: $return,
                output: $output,
            );
        }

        rename($temporaryDirectory->path('output.pdf'), $output_file->getPathname());
        $temporaryDirectory->delete();
    }
}
