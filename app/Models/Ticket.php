<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'screening_id',
        'seat_id',
        'purchase_id',
        'price',
        'qrcpde_url',
        'status',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    public function screening(): BelongsTo
    {
        return $this->belongsTo(Screening::class, 'screening_id');
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class,'seat_id');
    }
}