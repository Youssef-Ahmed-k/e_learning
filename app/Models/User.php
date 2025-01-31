<?php

namespace App\Models;

//use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;


class User extends Authenticatable implements JWTSubject
{
    use  HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'is_suspended',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
    public function setPasswordAttribute($password)
    {
        $this->attributes['password'] = bcrypt($password);
    }
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function courses()
    {
        return $this->hasMany(Course::class, 'ProfessorID', 'id');
    }

    public function materials()
    {
        return $this->hasMany(Material::class, 'ProfessorID', 'id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'RecipientID', 'id');
    }

    public function studentAnswers()
    {
        return $this->hasMany(StudentAnswer::class, 'StudentID', 'id');
    }

    public function quizResults()
    {
        return $this->hasMany(QuizResult::class, 'StudentID', 'id');
    }

    public function courseRegistrations()
    {
        return $this->hasMany(CourseRegistration::class, 'StudentID', 'id');
    }

    public function suspensions()
    {
        return $this->hasMany(Suspension::class, 'StudentID', 'id');
    }

    public function cheatingLogs()
    {
        return $this->hasMany(CheatingLog::class, 'StudentID', 'id');
    }

    public function isSuspended(): bool
    {
        return $this->is_suspended;
    }
}