<?php

namespace App\Console\Commands;

use App\Services\Import\HistoryImporter;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportHistory extends Command
{
    protected $signature = 'kuncia:import-history {file : JSON file with the past records} {--dry-run : Show what would happen and save nothing}';

    protected $description = 'Import past rent records (tenants, tenancies, paid invoices) from a JSON file';

    public function handle(HistoryImporter $importer): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            $this->error('The file is not valid JSON.');

            return self::FAILURE;
        }

        try {
            $lines = $importer->import($data, (bool) $this->option('dry-run'));
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }
            $this->warn('Nothing was saved.');

            return self::FAILURE;
        }

        foreach ($lines as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }
}
