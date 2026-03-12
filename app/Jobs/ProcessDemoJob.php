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
use Throwable;

class ProcessDemoJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 0; // Unlimited — error handling is in handle() catch block
    public $maxExceptions = 0;
    public $uniqueFor = 600;
    public $timeout = 600;

    protected $demo;

    public function __construct(UploadedDemo $demo)
    {
        $this->demo = $demo;
        $this->onQueue('demos');
    }

    public function uniqueId(): string
    {
        return (string) $this->demo->id;
    }

    public function handle(DemoProcessorService $demoProcessor): void
    {
        // Reload from DB to get current status (another job may have already processed this)
        $this->demo = $this->demo->fresh();
        $currentStatus = $this->demo->status;

        if (!in_array($currentStatus, ['queued', 'uploaded', 'failed'])) {
            Log::warning("Skipping demo processing — already in final state", [
                'demo_id' => $this->demo->id,
                'status' => $currentStatus,
            ]);
            return;
        }

        try {
            Log::info("Starting demo processing", [
                'demo_id' => $this->demo->id,
                'filename' => $this->demo->original_filename,
                'file_path' => $this->demo->file_path,
                'full_path' => $this->demo->full_path,
                'file_exists' => file_exists($this->demo->full_path) ? 'YES' : 'NO',
            ]);

            $this->demo->update([
                'status' => 'processing',
                'processing_output' => '[' . now()->format('Y-m-d H:i:s') . '] Processing in background queue...'
            ]);

            $demoProcessor->processDemo($this->demo);

            Log::info("Demo processing completed successfully", [
                'demo_id' => $this->demo->id,
                'final_status' => $this->demo->fresh()->status
            ]);

        } catch (Throwable $e) {
            Log::error("Demo processing failed", [
                'demo_id' => $this->demo->id,
                'error' => $e->getMessage(),
            ]);

            $this->demo->update([
                'status' => 'failed',
                'processing_output' => '[' . now()->format('Y-m-d H:i:s') . '] Processing failed: ' . $e->getMessage()
            ]);

            // Do NOT re-throw — job completes as "DONE" from Laravel's perspective
            // The demo status is already set to 'failed' in the database
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error("Demo processing job failed permanently", [
            'demo_id' => $this->demo->id,
            'error' => $exception->getMessage(),
        ]);

        $this->demo->update([
            'status' => 'failed',
            'processing_output' => '[' . now()->format('Y-m-d H:i:s') . '] Processing failed after ' . $this->attempts() . ' attempts: ' . $exception->getMessage()
        ]);
    }
}
