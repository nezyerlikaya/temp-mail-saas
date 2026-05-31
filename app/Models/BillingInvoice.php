<?php

namespace App\Models;

use App\Enums\BillingInvoiceStatus;
use App\Enums\BillingProvider;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid',
    'billing_customer_id',
    'provider',
    'provider_invoice_id',
    'status',
    'currency',
    'amount_due',
    'amount_paid',
    'hosted_invoice_url',
    'pdf_url',
    'metadata',
    'issued_at',
    'paid_at',
])]
#[Hidden(['provider_invoice_id'])]
class BillingInvoice extends Model
{
    public function customer(): BelongsTo
    {
        return $this->belongsTo(BillingCustomer::class, 'billing_customer_id');
    }

    protected function casts(): array
    {
        return [
            'provider' => BillingProvider::class,
            'status' => BillingInvoiceStatus::class,
            'amount_due' => 'integer',
            'amount_paid' => 'integer',
            'metadata' => 'array',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }
}
