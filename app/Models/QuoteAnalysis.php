<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteAnalysis extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'quote_id',
        'raw_text',
        'summary',
        'extracted_terms',
        'hidden_costs',
        'risk_notes',
        'confidence_score',
        'model_name',
        'status',
    ];

    protected $casts = [
        'extracted_terms' => 'array',
        'hidden_costs' => 'array',
        'risk_notes' => 'array',
        'confidence_score' => 'decimal:2',
    ];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
