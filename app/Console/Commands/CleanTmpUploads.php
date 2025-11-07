<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FileService;

class CleanTmpUploads extends Command
{
    protected $signature = 'uploads:clean-tmp {--hours=24}';
    protected $description = 'Remove temporary uploaded files older than specified hours';

    public function handle(FileService $fileService): void
    {
//        $hours = (int) $this->option('hours');
//        $fileService->cleanTmp($hours);
//
//        $this->info("Temporary uploads older than {$hours}h cleaned successfully.");
    }
}

