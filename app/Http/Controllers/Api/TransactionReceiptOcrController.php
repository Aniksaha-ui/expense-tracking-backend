<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Transactions\ScanTransactionReceiptRequest;
use App\Models\Category;
use App\Services\TransactionReceiptOcrService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class TransactionReceiptOcrController extends Controller
{
    public function scan(ScanTransactionReceiptRequest $request, TransactionReceiptOcrService $receiptOcr): JsonResponse
    {
        try {
            return $this->successResponse(
                $receiptOcr->scan(
                    $request->file('receipt'),
                    Category::query()
                        ->where('user_id', auth()->id())
                        ->where('type', 'EXPENSE')
                        ->orderBy('name')
                        ->pluck('name')
                        ->all(),
                ),
                'Receipt scanned. Review the extracted details before saving.'
            );
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 422);
        } catch (\Throwable $exception) {
            report($exception);

            return $this->errorResponse('Receipt could not be scanned. Please try again.', status: 500);
        }
    }
}
