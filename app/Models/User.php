<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'email_verified_at',
        'password',
        'phone',
        'country_code',
        'birthdate',
        'role',
        'affiliation_type',
        'affiliation_name',
        'is_admin',
        'is_active',
        'progress_percentage',
        'modules_completed_count',
        'exam_status',
        'subscription_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'subscription_expires_at' => 'datetime',
        ];
    }

    public function isSubscribed(): bool
    {
        return $this->subscription_expires_at && $this->subscription_expires_at->isFuture();
    }

    public function enrollments()
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function hasActiveEnrollment(int $courseId): bool
    {
        return $this->enrollments()->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereHas('batch', fn ($query) => $query->where('course_id', $courseId))->exists();
    }

    public function progress()
    {
        return $this->hasMany(UserProgress::class);
    }

    public function quizAttempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function vouchers()
    {
        return $this->hasMany(Voucher::class, 'used_by');
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }
}
