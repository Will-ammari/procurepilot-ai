<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorScorecard extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'vendor_id',
        'total_quotes',
        'accepted_quotes',
        'win_rate',
        'average_delivery_days',
        'total_invoices',
        'paid_invoices',
        'invoice_issue_count',
        'total_invoiced_amount',
        'currency',
        'overall_score',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'total_quotes' => 'integer',
            'accepted_quotes' => 'integer',
            'win_rate' => 'decimal:2',
            'average_delivery_days' => 'decimal:2',
            'total_invoices' => 'integer',
            'paid_invoices' => 'integer',
            'invoice_issue_count' => 'integer',
            'total_invoiced_amount' => 'decimal:2',
            'overall_score' => 'decimal:2',
            'calculated_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
