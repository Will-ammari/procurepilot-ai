<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Quote extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_ANALYSIS_PENDING = 'analysis_pending';
    public const STATUS_ANALYZED = 'analyzed';

    protected $fillable = [
        'organization_id',
        'purchase_request_id',
        'vendor_id',
        'total_amount',
        'currency',
        'delivery_days',
        'payment_terms',
        'warranty_months',
        'valid_until',
        'notes',
        'status',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'delivery_days' => 'integer',
        'warranty_months' => 'integer',
        'valid_until' => 'date',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function analysis(): HasOne
    {
        return $this->hasOne(QuoteAnalysis::class);
    }

    public function attachments(): MorphMany
{
    return $this->morphMany(Attachment::class, 'attachable');
}
}
