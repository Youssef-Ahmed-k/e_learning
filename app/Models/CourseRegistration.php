<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseRegistration extends Model
{
    use HasFactory;

    protected $primaryKey = 'RegistrationID';

    protected $fillable = [
        'StudentID',
        'CourseID',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'StudentID', 'id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'CourseID', 'CourseID');
    }
}