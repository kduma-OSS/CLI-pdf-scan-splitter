<?php

namespace App\Commands;

use App\Actions\ImageToPdfConvertAction;
use App\Actions\PdfPagesJoinerAction;
use App\Actions\PdfPagesSplitterAction;
use App\Actions\ScanBarcodes;
use App\Actions\Tools\TemporaryDirectoryCreatorAction;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
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
    protected $signature = 'process {output_dir} {pdf*} {--dpi=200}';

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
    public function handle(
        PdfPagesSplitterAction $extractor,
        ScanBarcodes $scanner,
        TemporaryDirectoryCreatorAction $tempDirMaker,
        PdfPagesJoinerAction $joiner,
        ImageToPdfConvertAction $converter
    )
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

        $inputs = $inputs->map(function(SplFileInfo $input_file) use ($converter, $temporaryInputsDirectory) {
            if($input_file->getExtension() === 'pdf')
                return $input_file;

            switch ($input_file->getExtension()) {
                case 'jpg':
                case 'jpeg':
                case 'png':
                    $output_file = $temporaryInputsDirectory->path(Str::random(10).'.pdf');
                    $converter->execute($input_file, $output_file);
                    $this->info("File converted to PDF: {$input_file->getFilename()}");
                    return new SplFileInfo($output_file);
                default:
                    $this->error("Unsupported file type: {$input_file->getFilename()}");
                    return null;
            }
        })->filter();

        $outputs = [];
        $this->info("Extracting pages from PDF files");
        $this->withProgressBar($inputs, function (SplFileInfo $file) use ($tempDirMaker, $temporaryInputsDirectory, $extractor, &$outputs) {
            $temporaryDirectory = $tempDirMaker->create();
            $pages = $extractor->execute(
                input_file: $file,
                output_dir: $temporaryDirectory->path(),
            );

            $file_hash = sha1($file->getRealPath()).'_'.Str::random(8);
            foreach ($pages as $page) {
                $outputs[] = $output = $temporaryInputsDirectory->path($file_hash.'-'.basename($page));
                rename($page, $output);
            }

            $temporaryDirectory->delete();
        });

        $final = [];
        $tagged = collect([]);
        $outputs = collect($outputs)->map(fn($path) => new SplFileInfo($path));
        $this->newLine(2);
        $this->info("Scanning pages for barcodes");
        $this->withProgressBar($outputs, function (SplFileInfo $page) use ($temporaryOutputDirectory, $scanner, &$final, &$tagged) {
            $scanned = $scanner->execute(
                input_file: $page,
                dpi: $this->option('dpi'),
            );

            $bc = $scanned->filter(fn($barcode) => $barcode['type'] == 'CODE-128');
            if($bc->count() > 0) {
                $bc = $bc->first();
                $bc = $bc['value'];
            } else {
                $bc = 'UNKNOWN';
            }

            $page_tags = $scanned
                ->filter(fn($barcode) => $barcode['type'] == 'QR-Code')
                ->map(function ($barcode) {
                    if(!preg_match('/^([0-9A-Za-z]+(@[0-9A-Za-z]+)?):(\\d+)(:(\\d+))?$/um', $barcode['value'])) {
                        return null;
                    }
                    [$id, $page, $count] = explode(':', $barcode['value'].':::');

                    $barcode['tag'] = [
                        'id' => $id != "" ? $id : null,
                        'page' => $page != "" ? $page : null,
                        'count' => $count != "" ? $count : null,
                    ];

                    return $barcode;
                })
                ->filter()
                ->map(function ($barcode) use ($bc) {
                    if(is_null($barcode['tag']['id'])) {
                        $barcode['tag']['id'] = $bc;
                    }

                    return $barcode;
                });

            if($page_tags->count() > 0) {
                $t = $page_tags->first();
                $id = $t['tag']['id'];
                $page_no = $t['tag']['page'];

                if(!isset($tagged[$id])) {
                    $tagged[$id] = collect();
                }

                if(!isset($tagged[$id][$page_no])) {
                    $tagged[$id][$page_no] = collect();
                }

                $output = $this->getOutputPath($temporaryOutputDirectory, 'TG_'.$bc);

                if($bc == 'UNKNOWN' && preg_match('/^((T[0-9A-Za-z]{14}T)[0-9A-Za-z]*|([0-9A-Za-z]{14})[0-9A-Za-z]*|([0-9A-Za-z]*)@[0-9A-Za-z]*)$/usm', $id, $matches)) {
                    $bc = $matches[4] ?? $matches[3] ?? $matches[2] ?? $matches[1];
                }

                $tagged[$id][$page_no][] = [
                    'file' => $output,
                    'tag' => $t['tag'],
                    'barcode' => $bc,
                ];


            } else {
                $final[] = $output = $this->getOutputPath($temporaryOutputDirectory, $bc);
            }
            rename($page, $output);
        });

        if($tagged->count()) {
            $this->newLine(2);
            $errors = [];
            $this->info("Processing multi-page documents");
            $this->withProgressBar($tagged, function (Collection $tag_pages) use ($joiner, $temporaryOutputDirectory, &$errors, &$final) {
                $tag_pages = $tag_pages->sortKeys();
                $tag_name = $tag_pages->first()->first()['tag']['id'];

                if($tag_pages->keys()->max() != $tag_pages->keys()->count()) {
                    $errors[] = $tag_name.' has missing pages - last page is '.$tag_pages->keys()->max(). ' but there are '.$tag_pages->keys()->count().' pages!';
                }

                $tag_pages = $tag_pages->map(function (Collection $tag_page, $page_number) use ($tag_name, &$errors, &$final) {
                    if($tag_page->count() > 1) {
                        $errors[] = $tag_name.':'.$page_number.' has been scanned multiple times!';
                    }

                    $used = $tag_page->pop();

                    foreach ($tag_page as $p) {
                        $file = $p['file'];
                        $new_file = pathinfo($file, PATHINFO_DIRNAME).DIRECTORY_SEPARATOR.'IGNORED_'.pathinfo($file, PATHINFO_FILENAME).'_'.$p['tag']['page'].'.'.pathinfo($file, PATHINFO_EXTENSION);
                        $errors[] = $tag_name.':'.$page_number.' - ignored file placed at '.basename($new_file);

                        $final[] = $new_file;
                        rename($file, $new_file);
                    }

                    return $used;
                });

                $barcode = $tag_pages->first()['barcode'];


                $joiner->execute(
                    input_files: $tag_pages->pluck('file'),
                    output_file: $output = $this->getOutputPath($temporaryOutputDirectory, $barcode),
                );
                $final[] = $output;
            });
            $this->newLine(2);
            foreach ($errors as $error) {
                $this->error($error);
            }
        }

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
