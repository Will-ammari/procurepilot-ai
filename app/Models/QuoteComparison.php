<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteComparison extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'purchase_request_id',
        'recommended_quote_id',
        'generated_by_user_id',
        'currency',
        'reason',
        'quotes',
        'weights',
    ];

    protected $casts = [
        'quotes' => 'array',
        'weights' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function recommendedQuote(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'recommended_quote_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }
}
