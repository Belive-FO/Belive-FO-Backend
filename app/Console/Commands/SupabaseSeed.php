<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Exception;

class SupabaseSeed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'supabase:seed {file?} {--force : Force execution even if dangerous operations detected}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed Supabase database using SQL files from database/seeds/sql/';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sqlPath = database_path('seeds/sql');

        // Check if directory exists
        if (!File::isDirectory($sqlPath)) {
            $this->error("SQL seeds directory not found: {$sqlPath}");
            $this->info("Creating directory...");
            File::makeDirectory($sqlPath, 0755, true);
            $this->info("✅ Directory created. Please add SQL files to {$sqlPath}");
            return Command::FAILURE;
        }

        // Production environment warning
        if (app()->environment('production')) {
            if (!$this->confirm('⚠️  You are in PRODUCTION environment. Continue?', false)) {
                $this->warn('Seeding cancelled.');
                return Command::FAILURE;
            }
        }

        if ($this->argument('file')) {
            // Seed specific file
            $file = $sqlPath . '/' . $this->argument('file');
            if (!File::exists($file)) {
                $this->error("SQL file not found: {$file}");
                return Command::FAILURE;
            }
            return $this->executeSqlFile($file);
        } else {
            // Seed all SQL files
            $files = File::glob($sqlPath . '/*.sql');
            if (empty($files)) {
                $this->warn("No SQL files found in {$sqlPath}");
                $this->info("Add SQL files with .sql extension to seed the database.");
                return Command::SUCCESS;
            }

            // Sort files by name (for ordered execution)
            sort($files);

            $this->info("Found " . count($files) . " SQL file(s) to execute:");
            foreach ($files as $file) {
                $this->line("  - " . basename($file));
            }
            $this->newLine();

            $successCount = 0;
            $failureCount = 0;

            foreach ($files as $file) {
                $result = $this->executeSqlFile($file);
                if ($result === Command::SUCCESS) {
                    $successCount++;
                } else {
                    $failureCount++;
                    if (!$this->confirm('Continue with remaining files?', true)) {
                        break;
                    }
                }
            }

            $this->newLine();
            if ($failureCount === 0) {
                $this->info("✅ All SQL files executed successfully! ({$successCount} file(s))");
                return Command::SUCCESS;
            } else {
                $this->warn("⚠️  Completed with errors: {$successCount} succeeded, {$failureCount} failed");
                return Command::FAILURE;
            }
        }
    }

    /**
     * Execute a single SQL file.
     */
    private function executeSqlFile(string $file): int
    {
        $this->info("📄 Executing: " . basename($file));

        try {
            $sql = File::get($file);

            // Check for dangerous operations
            if ($this->containsDangerousOperations($sql)) {
                if (!$this->option('force')) {
                    $this->error('⚠️  DANGER: SQL file contains DELETE/TRUNCATE/DROP operations!');
                    $this->warn('This command is designed to PREVENT data loss.');
                    $this->newLine();
                    $this->line('If you are absolutely certain you want to proceed:');
                    $this->line('  php artisan supabase:seed ' . basename($file) . ' --force');
                    $this->newLine();
                    return Command::FAILURE;
                } else {
                    $this->warn('⚠️  FORCE MODE: Executing file with dangerous operations...');
                    if (!$this->confirm('Are you absolutely sure?', false)) {
                        $this->warn('Execution cancelled.');
                        return Command::FAILURE;
                    }
                }
            }

            // Execute SQL within a transaction
            DB::beginTransaction();

            try {
                // Split SQL by semicolon and execute each statement
                $statements = $this->parseSqlStatements($sql);

                foreach ($statements as $index => $statement) {
                    if (!empty(trim($statement))) {
                        DB::statement($statement);
                        $this->line("  ✓ Statement " . ($index + 1) . " executed");
                    }
                }

                DB::commit();
                $this->info("  ✅ " . basename($file) . " executed successfully");
                return Command::SUCCESS;

            } catch (Exception $e) {
                DB::rollBack();
                $this->error("  ❌ Error executing " . basename($file) . ":");
                $this->error("     " . $e->getMessage());
                $this->warn("     Transaction rolled back. No changes were made.");
                return Command::FAILURE;
            }

        } catch (Exception $e) {
            $this->error("  ❌ Error reading file: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Check if SQL contains dangerous operations.
     */
    private function containsDangerousOperations(string $sql): bool
    {
        // Normalize SQL (remove comments and extra whitespace)
        $normalized = preg_replace('/--.*$/m', '', $sql); // Remove single-line comments
        $normalized = preg_replace('/\/\*.*?\*\//s', '', $normalized); // Remove multi-line comments
        $normalized = strtoupper(trim($normalized));

        // Check for dangerous keywords
        $dangerousPatterns = [
            '/\bDELETE\s+FROM\b/i',
            '/\bTRUNCATE\s+(TABLE\s+)?/i',
            '/\bDROP\s+(TABLE|SCHEMA|DATABASE|INDEX|VIEW|FUNCTION|TRIGGER)\b/i',
            '/\bALTER\s+TABLE\s+.*\s+DROP\b/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $normalized)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse SQL file into individual statements.
     */
    private function parseSqlStatements(string $sql): array
    {
        // Remove comments
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

        // Split by semicolon, but preserve semicolons inside strings
        $statements = [];
        $current = '';
        $inString = false;
        $stringChar = '';

        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];

            if (!$inString && ($char === '"' || $char === "'")) {
                $inString = true;
                $stringChar = $char;
                $current .= $char;
            } elseif ($inString && $char === $stringChar) {
                // Check if it's escaped
                if ($i > 0 && $sql[$i - 1] !== '\\') {
                    $inString = false;
                    $stringChar = '';
                }
                $current .= $char;
            } elseif (!$inString && $char === ';') {
                $statements[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }

        // Add remaining statement if any
        if (!empty(trim($current))) {
            $statements[] = trim($current);
        }

        // Filter out empty statements
        return array_filter($statements, fn($stmt) => !empty(trim($stmt)));
    }
}

