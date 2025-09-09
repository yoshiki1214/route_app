<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'address',
        'latitude',
        'longitude',
        'phone',
        'fax',
        'email',
        'contact_person',
        'department',
        'position',
        'notes',
    ];

    /**
     * 訪問履歴を取得
     */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    /**
     * メモを取得
     */
    public function memos(): HasMany
    {
        return $this->hasMany(Memo::class);
    }

    /**
     * 最後の訪問日を取得
     */
    public function getLastVisitAttribute()
    {
        return $this->visits()->latest('visited_at')->first()?->visited_at;
    }
}
