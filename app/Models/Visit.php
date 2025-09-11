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

    protected static function booted()
    {
        static::deleting(function ($visit) {
            // 訪問記録が削除される場合、関連するアポイントメントも削除
            if ($visit->appointment_id) {
                $visit->appointment()->delete();
            }
        });
    }

    protected $fillable = [
        'client_id',
        'user_id',
        'appointment_id',
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

    /**
     * 関連するアポイントメントを取得
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
