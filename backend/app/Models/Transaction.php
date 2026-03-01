<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Matikan auto-increment karena kita menggunakan UUID
    public $incrementing = false;
    protected $keyType = 'string';

    // Otomatisasi pembuatan UUID saat baris baru diciptakan
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    // Relasi kembali ke entitas User
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
