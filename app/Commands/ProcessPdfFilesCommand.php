<?php

namespace App\Commands;

use App\Actions\PdfPagesSplitterAction;
use App\Actions\ScanBarcodes;
use App\Actions\Tools\TemporaryDirectoryCreatorAction;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use SplFileInfo;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use function Termwind\{render};

class ProcessPdfFilesCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'process {output_dir} {pdf*}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Reads and sorts PDF files based on barcodes';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(PdfPagesSplitterAction $extractor, ScanBarcodes $scanner, TemporaryDirectoryCreatorAction $tempDirMaker)
    {
        $inputs = collect($this->argument('pdf'))
            ->map(fn($path) => realpath($path))
            ->filter()
            ->map(fn($path) => new SplFileInfo($path))
            ->filter(fn(SplFileInfo $file) => $file->isFile());

        $output_dir = realpath($this->argument('output_dir'));
        if(!$output_dir) {
            $this->error("Output directory does not exist");
            return SymfonyCommand::FAILURE;
        }

        $output_dir = new SplFileInfo($output_dir);
        if(!$output_dir->isDir()) {
            $this->error("Output directory is not a directory");
            return SymfonyCommand::FAILURE;
        }

        $temporaryInputsDirectory = $tempDirMaker->create();
        $temporaryOutputDirectory = $tempDirMaker->create();

        $outputs = [];
        $this->info("Extracting pages from PDF files");
        $this->withProgressBar($inputs, function (SplFileInfo $file) use ($tempDirMaker, $temporaryInputsDirectory, $extractor, &$outputs) {
            $temporaryDirectory = $tempDirMaker->create();
            $pages = $extractor->execute(
                input_file: $file,
                output_dir: $temporaryDirectory->path(),
            );

            $file_hash = sha1($file->getRealPath());
            foreach ($pages as $page) {
                $outputs[] = $output = $temporaryInputsDirectory->path($file_hash.'-'.basename($page));
                rename($page, $output);
            }

            $temporaryDirectory->delete();
        });

        $final = [];
        $outputs = collect($outputs)->map(fn($path) => new SplFileInfo($path));
        $this->newLine(2);
        $this->info("Scanning pages for barcodes");
        $this->withProgressBar($outputs, function (SplFileInfo $page) use ($temporaryOutputDirectory, $scanner, &$final) {
            $bc = $scanner->execute(
                input_file: $page
            );

            $bc = $bc->filter(fn($barcode) => $barcode['type'] == 'CODE-128');
            if($bc->count() > 0) {
                $bc = $bc->first();
                $bc = $bc['value'];
            } else {
                $bc = 'UNKNOWN';
            }

            $final[] = $output = $this->getOutputPath($temporaryOutputDirectory, $bc);
            rename($page, $output);
        });

        $this->newLine(2);
        $this->info("Moving files to output directory");
        $final = collect($final)->map(fn($path) => new SplFileInfo($path));
        $final->each(function (SplFileInfo $input) use ($output_dir) {
            $output = Str::of($output_dir)->finish(DIRECTORY_SEPARATOR)->append($input->getFilename());
            rename($input, $output);
            $this->line("Moved {$input->getFilename()} to {$output}");
        });

        $temporaryInputsDirectory->delete();
        $temporaryOutputDirectory->delete();
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    public function schedule(Schedule $schedule)
    {
        // $schedule->command(static::class)->everyMinute();
    }

    public function getOutputPath(TemporaryDirectory $tmp, string $barcode): string
    {
        $counter = 0;

        while (file_exists($tmp->path(sprintf("%s.pdf", $counter == 0 ? $barcode : $barcode . '-' . $counter)))) {
            $counter++;
        }

        return $tmp->path(sprintf("%s.pdf", $counter == 0 ? $barcode : $barcode . '-' . $counter));
    }
}
