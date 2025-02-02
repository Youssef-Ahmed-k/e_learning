<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $primaryKey = 'CourseID';

    protected $fillable = [
        'CourseCode',
        'CourseName',
        'ProfessorID',
    ];

    /**
     * Get the professor (user) who owns the course.
     */
    public function professor()
    {
        return $this->belongsTo(User::class, 'ProfessorID', 'id');
    }

    /**
     * Get the materials associated with the course.
     */
    public function materials()
    {
        return $this->hasMany(Material::class, 'CourseID', 'CourseID');
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class, 'CourseID', 'CourseID');
    }

    public function courseRegistrations()
    {
        return $this->hasMany(CourseRegistration::class, 'CourseID', 'CourseID');
    }
}