<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaceData extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'face_embedding',
        'face_image_path',
        'is_registered'
    ];

    protected $casts = [
        'face_embedding' => 'array',
        'is_registered' => 'boolean'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}