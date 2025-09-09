<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Visit extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $fillable = [
        'client_id',
        'user_id',
        'visited_at',
        'latitude',
        'longitude',
        'visit_type',
        'status',
        'notes',
    ];

    protected $casts = [
        'visited_at' => 'datetime',
    ];

    /**
     * 訪問先を取得
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * 訪問したユーザーを取得
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
