<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        'nif',
        'payment_type',
        'payment_ref',
    ];
    public $incrementing = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id');
    }


    public function purchases(): HasMany
    {
        return $this->hasMany(
            Purchase::class,
        );
    }

    public function userD(): BelongsTo {
        return $this->belongsTo(User::class, 'id')->withTrashed();
    }
}
