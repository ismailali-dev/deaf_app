<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Resource;

class ImportResourcesCsv extends Command
{
    protected $signature = 'import:resources {file}';
    protected $description = 'Import resources from CSV file with encoding cleaning';

    public function handle()
    {
        $file = $this->argument('file');
        if (!file_exists($file)) {
            $this->error("File not found: $file");
            return 1;
        }

        $handle = fopen($file, 'rb');
        if (!$handle) {
            $this->error("Cannot open file: $file");
            return 1;
        }

        // Read and clean header row
        $rawHeader = fgetcsv($handle);
        if (!$rawHeader) {
            $this->error("Empty CSV or missing header");
            fclose($handle);
            return 1;
        }
        $header = array_map([$this, 'cleanString'], $rawHeader);

        // Optional: show detected columns for debugging
        $this->line("Detected columns: " . implode(' | ', $header));

        // Define mapping from CSV headers to Resource model fields
        // (keys are model fields, values are possible CSV header names)
        $fieldMapping = [
            'title'          => ['Organization Name', 'Organization', 'Name'],
            'country'        => ['State / Territory', 'State', 'Territory'],
            'contact_number' => ['Phone', 'Phone / TTY / VP', 'Phone Number', 'Contact'],
            'email'          => ['Email', 'E-mail'],
            'address'        => ['Address', 'Location'],
            'about'          => ['Services / Notes', 'Services', 'Notes', 'Description'],
            'source_link'    => ['Website', 'Web', 'URL'],
        ];

        // Build a quick lookup: which CSV column index corresponds to each model field?
        $columnIndex = [];
        foreach ($fieldMapping as $modelField => $possibleHeaders) {
            foreach ($possibleHeaders as $possible) {
                $idx = array_search($possible, $header);
                if ($idx !== false) {
                    $columnIndex[$modelField] = $idx;
                    break;
                }
            }
        }

        // Verify required fields exist
        if (!isset($columnIndex['title'])) {
            $this->error("Required column 'Organization Name' not found in CSV header.");
            fclose($handle);
            return 1;
        }

        $inserted = 0;
        $errors = [];
        $rowNumber = 1; // for error reporting

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            // Normalise row length
            if (count($row) < count($header)) {
                $row = array_pad($row, count($header), '');
            }

            // Combine header with row values
            $rawData = array_combine($header, $row);
            // Clean all values
            $cleaned = [];
            foreach ($rawData as $key => $value) {
                $cleaned[$key] = $this->cleanString($value ?? '');
            }

            // Extract data using the column mapping
            $title = $cleaned[$header[$columnIndex['title']]] ?? '';
            if (empty($title)) {
                continue; // skip rows without a title
            }

            $resourceData = [
                'title'          => $title,
                'country'        => $this->determineCountry($cleaned[$header[$columnIndex['country']]] ?? ''),
                'contact_number' => $cleaned[$header[$columnIndex['contact_number']]] ?? '',
                'email'          => $cleaned[$header[$columnIndex['email']]] ?? '',
                'address'        => $cleaned[$header[$columnIndex['address']]] ?? '',
                'about'          => $cleaned[$header[$columnIndex['about']]] ?? '',
                'source_link'    => $cleaned[$header[$columnIndex['source_link']]] ?? '',
                'status'         => 1,
            ];

            try {
                Resource::updateOrCreate(
                    ['title' => $resourceData['title'], 'source_link' => $resourceData['source_link']],
                    $resourceData
                );
                $inserted++;
                if ($inserted % 100 == 0) {
                    $this->info("Processed $inserted records...");
                }
            } catch (\Exception $e) {
                $errors[] = "Row $rowNumber (title: '$title'): " . $e->getMessage();
                $this->error("Error on row $rowNumber: " . $e->getMessage());
            }
        }

        fclose($handle);
        $this->info("Done! Inserted/updated $inserted records.");
        if (!empty($errors)) {
            $this->warn("Encountered " . count($errors) . " errors. First error: " . $errors[0]);
        }

        return 0;
    }

    /**
     * Determine country value from State/Territory column.
     */
    private function determineCountry($state)
    {
        $state = trim($state);
        if (stripos($state, 'nationwide') !== false || $state === 'N/A – Nationwide') {
            return 'USA (nationwide)';
        }
        if (in_array($state, ['Puerto Rico', 'Guam', 'U.S. Virgin Islands', 'American Samoa', 'Northern Mariana Islands', 'Washington D.C.'])) {
            return $state . ' (USA territory)';
        }
        return $state;
    }

    /**
     * Clean string: fix invalid UTF-8, replace Windows-1252 en dash/em dash, remove control chars.
     */
    private function cleanString($str)
    {
        if ($str === null || $str === '') {
            return '';
        }
        // Remove invalid byte sequences and convert to UTF-8
        $str = @mb_convert_encoding($str, 'UTF-8', 'UTF-8');
        // Replace Windows-1252 en dash (0x96) and similar
        $str = str_replace("\x96", '-', $str);
        $str = str_replace("\x97", '-', $str);
        // Replace Unicode en dash and em dash
        $str = str_replace(['–', '—'], '-', $str);
        // Remove non-printable characters (except newlines)
        $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $str);
        return trim($str);
    }
}