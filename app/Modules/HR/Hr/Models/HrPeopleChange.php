<?php

nameSpace  App\Modules\HR\Hr\Models;
use App\Core\Position\Models\HrPosition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Core\Role\Models\Role;

class HrPeopleChange extends Model
{
    use LogsActivity;
    public $table = 'hr_people_changes';

    protected $fillable = [
        'uuid',
        'first_name',
        'surname',
        'initial',
        'email',
        'cell_number',
        'phone_number',
        'id_number',
        'profile_pic',
        'status',
        'division_level_1',
        'division_level_2',
        'division_level_3',
        'division_level_4',
        'division_level_5',
        'manager_id',
        'second_manager_id',
        'date_joined',
        'date_left',
        'role_id',
        'position',
        'title',
        'known_as',
        'hr_main_id',
        'is_approved',
    ];

    protected $casts = [
        'date_joined' => 'integer',
        'date_left' => 'integer',
        'is_approved' => 'boolean',
    ];

    public function getDateOfBirthAttribute($value)
    {
        return $value ? \Carbon\Carbon::createFromTimestamp($value)->format('Y-m-d') : null;
    }

    public function getDateJoinedAttribute($value)
    {
        return $value ? \Carbon\Carbon::createFromTimestamp($value)->format('Y-m-d') : null;
    }

    public function getDateLeftAttribute($value)
    {
        return $value ? \Carbon\Carbon::createFromTimestamp($value)->format('Y-m-d') : null;
    }

    public function getMedStartDateAttribute($value)
    {
        return $value ? \Carbon\Carbon::createFromTimestamp($value)->format('Y-m-d') : null;
    }

    public function getProvidentStartDateAttribute($value)
    {
        return $value ? \Carbon\Carbon::createFromTimestamp($value)->format('Y-m-d') : null;
    }

    // Relations
    public function hrPerson()
    {
        return $this->belongsTo(HRPerson::class, 'hr_main_id');
    }

    public function position()
    {
        return $this->belongsTo(HrPosition::class, 'position');
    }

    public function manager()
    {
        return $this->belongsTo(HRPerson::class, 'manager_id');
    }

    public function secondManager()
    {
        return $this->belongsTo(HRPerson::class, 'second_manager_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }



    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('HrPeopleChange')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(
                fn(string $eventName) =>
                $this->getDescriptionForEvent($eventName)
            );
    }

    protected function getDescriptionForEvent(string $eventName): string
    {
        $user = Auth::user();

        $name = trim(($this->first_name ?? '') . ' ' . ($this->surname ?? ''));

        return match ($eventName) {
            'created' => "HR change record for '{$name}' created by " . ($user?->name ?? 'System'),
            'updated' => "HR change record for '{$name}' updated by " . ($user?->name ?? 'System'),
            'deleted' => "HR change record for '{$name}' deleted by " . ($user?->name ?? 'System'),
            default => "HR change {$eventName} event",
        };
    }

    public function getProfileCompletionAttribute(): int
    {
        $fields = [
            'first_name',
            'surname',
            'initial',
            'email',
            'cell_number',
            'phone_number',
            'id_number',
            'profile_pic',
            'status',
            'division_level_1',
            'division_level_2',
            'division_level_3',
            'division_level_4',
            'division_level_5',
            'manager_id',
            'second_manager_id',
            'date_joined',
            'date_left',
            'role_id',
            'position',
            'title',
            'known_as',
            'hr_main_id',
        ];

        $filled = 0;
        $total = count($fields);

        foreach ($fields as $field) {
            if (!empty($this->$field)) {
                $filled++;
            }
        }

        $percentage = ($filled / $total) * 100;

        $steps = [5, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100];

        foreach ($steps as $step) {
            if ($percentage <= $step) {
                return $step;
            }
        }

        return 100;
    }

}
