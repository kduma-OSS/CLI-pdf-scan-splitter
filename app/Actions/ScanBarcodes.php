<?php

namespace App\Actions;

use App\Actions\Tools\RunDockerContainerAction;
use App\Actions\Tools\Exceptions\PdfPageContentsExtractorException;
use App\Actions\Tools\TemporaryDirectoryCreatorAction;
use Illuminate\Support\Collection;
use SplFileInfo;

class ScanBarcodes
{
    public function __construct(
        protected RunDockerContainerAction $runDockerContainerAction,
        protected TemporaryDirectoryCreatorAction $tempDirMaker
    ) {}

    public function execute(string|SplFileInfo $input_file, int $dpi): Collection
    {
        if(is_string($input_file))
            $input_file = new SplFileInfo($input_file);

        $temporaryDirectory = $this->tempDirMaker->create();

        copy($input_file->getPathname(), $temporaryDirectory->path('input.pdf'));

        $action = $this
            ->runDockerContainerAction
            ->withTemporaryDirectory($temporaryDirectory);

        $action->execute(dockerImageName: 'ghcr.io/kduma-oss/cli-pdf-scan-splitter/barcode-scanner', arguments: $dpi, output: $output, return: $return);

        if (0 != $return) {
            $temporaryDirectory->delete();

            if (5 == $return)
                return collect();

            throw new PdfPageContentsExtractorException(
                command: $action->getCommand(dockerImageName: 'ghcr.io/kduma-oss/cli-pdf-scan-splitter/barcode-scanner', arguments: $dpi),
                code: $return,
                output: $output,
            );
        }

        $barcodes = file_get_contents($temporaryDirectory->path('output.txt'));
        $barcodes = collect(explode("\n", $barcodes))
            ->map(fn($barcode) => trim($barcode))
            ->filter(fn($barcode) => !empty($barcode))
            ->map(fn($barcode) => explode(':', $barcode, 2))
            ->map(fn($barcode) => [
                'type' => $barcode[0],
                'value' => $barcode[1],
            ]);

        $temporaryDirectory->delete();

        return $barcodes;
    }
}
