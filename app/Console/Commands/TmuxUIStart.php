<?php

namespace App\Console\Commands;

use App\Models\Settings;
use App\Support\UpdatePerformanceHelper;
use Blacklight\Tmux;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class TmuxUIStart extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tmux-ui:start
                            {--timeout=300 : Timeout for tmux operations}
                            {--force : Force start even if session exists}
                            {--monitor : Enable monitoring mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the processing of tmux scripts with improved performance';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->info('🚀 Starting Tmux UI...');

            $tmux = new Tmux;
            $tmuxSession = Settings::settingValue('tmux_session') ?? 0;
            $timeout = (int) $this->option('timeout');

            // Check system resources before starting
            $this->checkSystemResources();

            // Check if session already exists
            if (! $this->option('force') && $this->isSessionRunning($tmuxSession)) {
                $this->error("❌ Tmux session '$tmuxSession' is already running");

                if (! $this->confirm('Would you like to restart the session?')) {
                    return Command::FAILURE;
                }

                $this->call('tmux-ui:stop', ['--kill' => true]);
                sleep(2); // Brief pause to ensure clean shutdown
            }

            // Set running state
            $tmux->startRunning();
            $this->line('  ✓ Tmux running state activated');

            // Start tmux session
            $this->startTmuxSession($tmuxSession, $timeout);

            $this->info('✅ Tmux UI started successfully');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Failed to start Tmux UI: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Check system resources before starting
     */
    private function checkSystemResources(): void
    {
        $recommendations = UpdatePerformanceHelper::checkSystemResources();

        if (! empty($recommendations)) {
            $this->warn('⚠️ System resource information:');
            foreach ($recommendations as $recommendation) {
                $this->line("  • $recommendation");
            }
            $this->line(''); // Add empty line for better readability
        }
    }

    /**
     * Check if tmux session is running
     */
    private function isSessionRunning(string $sessionName): bool
    {
        $process = Process::timeout(10)->run("tmux list-session 2>/dev/null | grep -q '^$sessionName:'");

        return $process->successful();
    }

    /**
     * Start the tmux session
     */
    private function startTmuxSession(string $sessionName, int $timeout): void
    {
        $this->info('🔧 Initializing tmux session...');

        $runScript = base_path('misc/update/tmux/run.php');

        if (! file_exists($runScript)) {
            throw new \Exception("Tmux run script not found: $runScript");
        }

        // Ensure the run script is executable
        chmod($runScript, 0755);

        // Start the tmux session with better error handling
        $command = "php $runScript";

        if ($this->option('monitor')) {
            // Start in monitoring mode
            $this->info('📊 Starting in monitoring mode...');
            $process = Process::timeout($timeout)->tty()->run($command);

            if (! $process->successful()) {
                throw new \Exception('Tmux session failed to start: '.$process->errorOutput());
            }
        } else {
            // Start in background
            $this->info('🔄 Starting tmux session in background...');
            $process = Process::timeout(30)->start($command);

            // Give it a moment to start
            sleep(3);

            if (! $this->isSessionRunning($sessionName)) {
                throw new \Exception('Failed to start tmux session');
            }
        }

        $this->line('  ✓ Tmux session started successfully');
    }
}
