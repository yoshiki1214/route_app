<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    protected static function booted()
    {
        static::deleting(function ($appointment) {
            // アポイントメント削除時に、関連する訪問履歴も削除
            $appointment->visits()->delete();
        });
    }

    protected $fillable = [
        'client_id',
        'user_id',
        'title',
        'visit_type',
        'start_datetime',
        'end_datetime',
        'memo',
        'google_calendar_event_id',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
    ];

    /**
     * クライアントとのリレーション
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * ユーザーとのリレーション
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 関連する訪問記録を取得
     */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }
}
