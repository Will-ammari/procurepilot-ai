<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use HasFactory;

    public const EVENT_PURCHASE_REQUEST_CREATED = 'purchase_request.created';

    public const EVENT_PURCHASE_REQUEST_SUBMITTED = 'purchase_request.submitted';

    public const EVENT_QUOTE_CREATED = 'quote.created';

    public const EVENT_QUOTE_ANALYSIS_COMPLETED = 'quote.analysis_completed';

    public const EVENT_COMPARISON_GENERATED = 'comparison.generated';

    public const EVENT_APPROVAL_APPROVED = 'approval.approved';

    public const EVENT_APPROVAL_REJECTED = 'approval.rejected';

    public const EVENT_INVOICE_RECEIVED = 'invoice.received';

    public const EVENT_INVOICE_PAID = 'invoice.paid';

    public const EVENT_VENDOR_SCORECARD_CALCULATED = 'vendor_scorecard.calculated';

    protected $fillable = [
        'organization_id',
        'user_id',
        'event',
        'subject_type',
        'subject_id',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
