<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\QuoteAnalysisResource;
use App\Models\Quote;
use App\Services\AI\QuoteAnalysisService;

class QuoteAnalysisController extends Controller
{
    public function __construct(
        private readonly QuoteAnalysisService $quoteAnalysisService
    ) {
    }

    public function analyze(Quote $quote): QuoteAnalysisResource
    {
        $this->authorize('analyze', $quote);

        $analysis = $this->quoteAnalysisService->analyze($quote);

        return new QuoteAnalysisResource($analysis);
    }

    public function show(Quote $quote): QuoteAnalysisResource
    {
        $this->authorize('view', $quote);

        $analysis = $quote->analysis()->firstOrFail();

        return new QuoteAnalysisResource($analysis);
    }
}
