<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use HasFactory;

    protected $primaryKey = 'MaterialID';

    protected $fillable = [
        'Title',
        'Description',
        'FilePath',
        'VideoPath',
        'MaterialType',
        'CourseID',
        'ProfessorID',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'CourseID', 'CourseID');
    }

    public function professor()
    {
        return $this->belongsTo(User::class, 'ProfessorID', 'id');
    }
}