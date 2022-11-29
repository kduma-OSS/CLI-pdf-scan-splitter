<?php

namespace App\Actions;

use App\Actions\Tools\RunDockerContainerAction;
use App\Actions\Tools\Exceptions\PdfPageContentsExtractorException;
use App\Actions\Tools\TemporaryDirectoryCreatorAction;
use Illuminate\Support\Str;
use SplFileInfo;

class PdfPagesSplitterAction
{
    public function __construct(
        protected RunDockerContainerAction $runDockerContainerAction,
        protected TemporaryDirectoryCreatorAction $tempDirMaker
    ) {}

    public function execute(string|SplFileInfo $input_file, string|SplFileInfo $output_dir)
    {
        if(is_string($input_file))
            $input_file = new SplFileInfo($input_file);

        if(is_string($output_dir))
            $output_dir = new SplFileInfo($output_dir);

        $temporaryDirectory = $this->tempDirMaker->create();

        copy($input_file->getPathname(), $temporaryDirectory->path('input.pdf'));

        $action = $this
            ->runDockerContainerAction
            ->withTemporaryDirectory($temporaryDirectory);

        $action->execute(dockerImageName: 'ghcr.io/kduma-oss/cli-pdf-scan-splitter/pdf-page-extractor',output: $output, return: $return);

        if (0 != $return) {
            $temporaryDirectory->delete();
            throw new PdfPageContentsExtractorException(
                command: $action->getCommand(dockerImageName: 'ghcr.io/kduma-oss/cli-pdf-scan-splitter/pdf-page-extractor'),
                code: $return,
                output: $output,
            );
        }

        $list = glob($temporaryDirectory->path('output-*.pdf'));
        $output_dir_str = Str::of($output_dir->getPathname())->finish(DIRECTORY_SEPARATOR);
        $outputs = [];
        foreach ($list as $item) {
            $page = Str::of(basename($item))->replace('output-', '')->replace('.pdf', '')->toInteger();
            $outputs[] = $output = $output_dir_str->append(sprintf("%s.pdf", str_pad($page, 4, '0', STR_PAD_LEFT)))->toString();
            copy($item, $output);
        }

        $temporaryDirectory->delete();

        return $outputs;
    }
}
