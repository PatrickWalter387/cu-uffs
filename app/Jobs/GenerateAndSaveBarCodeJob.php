<?php

namespace App\Jobs;

use App\Enums\UserOperationStatus;
use App\Helpers\StorageHelper;
use App\Services\BarcodeService;
use App\Services\UserPayloadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use phpDocumentor\Reflection\Types\This;

class GenerateAndSaveBarCodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    private string $uid;
    private string $className;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($uid)
    {
        $this->className = get_class((object)This::class);
        $this->uid = $uid;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(UserPayloadService $userPayloadService, BarcodeService $barcodeService)
    {
        try {
            Log::info("Starting job {$this->className}");

            $userPayloadService->updateStatusAndMessageByUid($this->uid, UserOperationStatus::GeneratingBarCode);

            $user = $userPayloadService->getByUid($this->uid)->payload;

            $barcodePath = StorageHelper::saveBarCode($this->uid, $barcodeService->generateBase64($user["enrollment_id"]));

            $user["bar_code"] = $barcodePath;

            $userPayloadService->updatePayloadByUid($this->uid, $user);

            FinishUserCreationJob::dispatch($this->uid);
            Log::info("Finished job {$this->className}");
        } catch (\Exception $e) {
            Log::error("Error on job {$this->className}");

            $userPayloadService->updateStatusAndMessageByUid($this->uid, UserOperationStatus::Failed, "Failed at {$this->className} with message: {$e->getMessage()}");
        }
    }
}