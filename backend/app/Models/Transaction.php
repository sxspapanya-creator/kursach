<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'amount', 'type', 'category_id', 'description', 'date', 'payment_method'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}