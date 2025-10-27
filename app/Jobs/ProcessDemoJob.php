<?php

namespace App\Jobs;

use App\Models\UploadedDemo;
use App\Services\DemoProcessorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessDemoJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes timeout per demo
    public $tries = 3; // Retry 3 times on failure
    public $backoff = [10, 30, 60]; // Backoff delays in seconds
    public $uniqueFor = 600; // Prevent duplicate jobs for 10 minutes

    protected $demo;

    /**
     * Create a new job instance.
     */
    public function __construct(UploadedDemo $demo)
    {
        $this->demo = $demo;
        // Set the queue connection and queue name
        $this->onQueue('demos');
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return (string) $this->demo->id;
    }

    /**
     * Execute the job.
     */
    public function handle(DemoProcessorService $demoProcessor): void
    {
        try {
            Log::info("Starting demo processing", [
                'demo_id' => $this->demo->id,
                'filename' => $this->demo->original_filename,
                'attempt' => $this->attempts()
            ]);

            // Update status to processing
            $this->demo->update([
                'status' => 'processing',
                'processing_output' => 'Processing in background queue...'
            ]);

            // Process the demo
            $demoProcessor->processDemo($this->demo);

            Log::info("Demo processing completed successfully", [
                'demo_id' => $this->demo->id,
                'final_status' => $this->demo->fresh()->status
            ]);

        } catch (Exception $e) {
            Log::error("Demo processing failed", [
                'demo_id' => $this->demo->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            // Update status to failed
            $this->demo->update([
                'status' => 'failed',
                'processing_output' => 'Processing failed: ' . $e->getMessage()
            ]);

            // Re-throw the exception to trigger retry logic
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error("Demo processing job failed permanently", [
            'demo_id' => $this->demo->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        $this->demo->update([
            'status' => 'failed',
            'processing_output' => 'Processing failed after ' . $this->attempts() . ' attempts: ' . $exception->getMessage()
        ]);
    }
}