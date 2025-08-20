<?php

namespace App\Console\Commands;

use App\Utilities\RenderMessageSummary;
use Illuminate\Console\Command;
use Exception;

class CheckScreenshotCapability extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'screenshot:check 
                            {--test : Generate a test screenshot}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check if screenshot generation is properly configured for board check exports';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking screenshot generation capability...');
        $this->newLine();

        $hasErrors = false;

        // 1. Check Node.js
        $this->info('1. Checking Node.js installation:');
        exec('node --version 2>&1', $nodeOutput, $nodeReturn);
        if ($nodeReturn === 0) {
            $this->line('   ✓ Node.js installed: ' . implode(' ', $nodeOutput));
        } else {
            $this->error('   ✗ Node.js not found. Please install Node.js v18+');
            $hasErrors = true;
        }

        // 2. Check NPM packages
        $this->info('2. Checking NPM packages:');
        $puppeteerPath = base_path('node_modules/puppeteer/package.json');
        if (file_exists($puppeteerPath)) {
            $package = json_decode(file_get_contents($puppeteerPath), true);
            $this->line('   ✓ Puppeteer installed: v' . ($package['version'] ?? 'unknown'));
        } else {
            $this->error('   ✗ Puppeteer not installed. Run: npm install');
            $hasErrors = true;
        }

        $browsershotPath = base_path('vendor/spatie/browsershot');
        if (is_dir($browsershotPath)) {
            $this->line('   ✓ Browsershot package installed');
        } else {
            $this->error('   ✗ Browsershot not installed. Run: composer install');
            $hasErrors = true;
        }

        // 3. Check Chrome binary
        $this->info('3. Checking Chrome/Chromium installation:');
        $chromeFound = false;
        $chromePaths = [
            getenv('HOME') . '/.cache/puppeteer/chrome-headless-shell',
            '/home/sail/.cache/puppeteer/chrome-headless-shell',
            '/root/.cache/puppeteer/chrome-headless-shell',
        ];

        foreach ($chromePaths as $basePath) {
            if (is_dir($basePath)) {
                $chromeBinaries = glob($basePath . '/*/chrome-headless-shell-linux64/chrome-headless-shell');
                if (!empty($chromeBinaries)) {
                    $this->line('   ✓ Chrome found at: ' . $chromeBinaries[0]);
                    if ($this->output->isVerbose()) {
                        $this->line('     Size: ' . $this->formatBytes(filesize($chromeBinaries[0])));
                        $this->line('     Executable: ' . (is_executable($chromeBinaries[0]) ? 'Yes' : 'No'));
                    }
                    $chromeFound = true;
                    break;
                }
            }
        }

        if (!$chromeFound) {
            $this->error('   ✗ Chrome headless not found.');
            $this->warn('   Run: npx puppeteer browsers install chrome-headless-shell');
            $hasErrors = true;
        }

        // 4. Check system dependencies (verbose mode only)
        if ($this->output->isVerbose()) {
            $this->info('4. Checking system dependencies:');
            $requiredLibs = [
                'libgbm.so' => 'libgbm1',
                'libxss.so' => 'libxss1',
                'libasound.so' => 'libasound2',
                'libatk-1.0.so' => 'libatk1.0-0',
                'libgtk-3.so' => 'libgtk-3-0',
            ];

            foreach ($requiredLibs as $lib => $package) {
                exec("ldconfig -p | grep $lib 2>&1", $output, $return);
                if ($return === 0 && !empty($output)) {
                    $this->line("   ✓ $lib found");
                } else {
                    $this->warn("   ⚠ $lib might be missing (package: $package)");
                }
            }
        }

        // 5. Test screenshot generation
        if ($this->option('test')) {
            $this->newLine();
            $this->info('5. Testing screenshot generation:');
            
            try {
                $testHtml = '
                    <div style="padding: 20px; font-family: Arial, sans-serif;">
                        <h1 style="color: #2563eb;">Screenshot Test</h1>
                        <p>Generated at: ' . now()->format('Y-m-d H:i:s') . '</p>
                        <p>Environment: ' . app()->environment() . '</p>
                        <p>This is a test of the board check screenshot capability.</p>
                    </div>
                ';

                $startTime = microtime(true);
                $result = RenderMessageSummary::htmlToImage($testHtml);
                $duration = round((microtime(true) - $startTime) * 1000, 2);

                if (empty($result)) {
                    $this->error('   ✗ Screenshot generation failed (empty result)');
                    $hasErrors = true;
                } else {
                    $decoded = base64_decode($result);
                    $imageInfo = @getimagesizefromstring($decoded);
                    
                    if ($imageInfo === false) {
                        $this->error('   ✗ Generated data is not a valid image');
                        $hasErrors = true;
                    } else {
                        $this->line('   ✓ Screenshot generated successfully!');
                        $this->line('     Duration: ' . $duration . 'ms');
                        $this->line('     Format: ' . image_type_to_mime_type($imageInfo[2]));
                        $this->line('     Dimensions: ' . $imageInfo[0] . 'x' . $imageInfo[1] . ' pixels');
                        $this->line('     Size: ' . $this->formatBytes(strlen($decoded)));
                        
                        // Save test image
                        $testFile = storage_path('app/screenshot-test-' . time() . '.png');
                        file_put_contents($testFile, $decoded);
                        $this->line('     Test image saved to: ' . $testFile);
                    }
                }
            } catch (Exception $e) {
                $this->error('   ✗ Exception during screenshot generation:');
                $this->error('     ' . $e->getMessage());
                if ($this->output->isVerbose()) {
                    $this->error('     ' . $e->getTraceAsString());
                }
                $hasErrors = true;
            }
        }

        // 6. Check PeoplePraise configuration
        $this->newLine();
        $this->info('6. Checking PeoplePraise configuration:');
        
        $datasource = \App\Models\DataSource::first();
        if ($datasource && $datasource->people_praise_basic_auth_user) {
            $this->line('   ✓ PeoplePraise credentials configured');
        } else {
            $this->warn('   ⚠ PeoplePraise credentials not configured');
            $this->warn('     Board check exports will fail without credentials');
        }

        // Summary
        $this->newLine();
        if ($hasErrors) {
            $this->error('❌ Screenshot capability check failed. Please fix the issues above.');
            return Command::FAILURE;
        } else {
            $this->info('✅ Screenshot capability check passed! Board check exports should work correctly.');
            return Command::SUCCESS;
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}