<?php

namespace App\Modules\HR\Hr\Models;

use App\Core\Position\Models\HrPosition;
use App\Core\User\Models\User;
use App\Shared\Casts\EncryptedPhi;
use App\Shared\Traits\HasPhiAudit;
use App\Shared\Traits\IsDataSubject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Core\Role\Models\Role;

class HRPerson extends Model
{

    use HasFactory, LogsActivity, HasPhiAudit, IsDataSubject;

    protected int    $retentionYears = 5;
    protected string $retentionFrom  = 'updated_at';

    // PHI fields for HasPhiAudit::getPhiFieldNames()
    protected array $phiFields = ['id_number'];

    protected $table = 'hr_people';


    public $timestamps = true;
    protected $dateFormat = 'Y-m-d H:i:s';

    protected $fillable = [
        'user_id',
        'title',
        'first_name',
        'surname',
        'initial',
        'position',
        'email',
        'cell_number',
        'phone_number',
        'id_number',
        'profile_pic',
        'profile_pic_path',
        'status',
        'division_level_1',
        'division_level_2',
        'division_level_3',
        'division_level_4',
        'division_level_5',
        'manager_id',
        'date_joined',
        'date_left',
        'role_id',
        'second_manager_id',
        'uuid',
        'known_as',
        'position_id',
    ];

    protected $casts = [
        'id_number'         => EncryptedPhi::class,
        'user_id' => 'integer',
        'position' => 'integer',
        'status' => 'integer',
        'division_level_1' => 'integer',
        'division_level_2' => 'integer',
        'division_level_3' => 'integer',
        'division_level_4' => 'integer',
        'division_level_5' => 'integer',
        'manager_id' => 'integer',
        'date_joined' => 'integer',
        'date_left' => 'integer',
        'role_id' => 'integer',
        'second_manager_id' => 'integer',
        'uuid' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    public function companyUnitHeritage(): string
{
    if ($this->user_id) {
        $units = DB::table('company_unit_user_pivots as p')
            ->join('company_units as u', 'u.id', '=', 'p.company_id')
            ->where('p.user_id', $this->user_id)
            ->orderBy('u.sort_order')
            ->pluck('u.name', 'u.type');

        if ($units->isNotEmpty()) {
            return $units->map(fn($name, $type) => "{$name} ({$type})")->implode(', ');
        }
    }

    return '—';
}

    // Accessors for Unix timestamps
    public function getDateJoinedAttribute($value)
    {
        return $value ? \Carbon\Carbon::createFromTimestamp($value) : null;
    }

    public function setDateJoinedAttribute($value)
    {
        $this->attributes['date_joined'] = $value instanceof \Carbon\Carbon ? $value->timestamp : $value;
    }

    public function getDateLeftAttribute($value)
    {
        return $value ? \Carbon\Carbon::createFromTimestamp($value) : null;
    }

    public function setDateLeftAttribute($value)
    {
        $this->attributes['date_left'] = $value instanceof \Carbon\Carbon ? $value->timestamp : $value;
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function manager()
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function secondManager()
    {
        return $this->belongsTo(self::class, 'second_manager_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
    public function hrPosition()
    {

        return $this->belongsTo(HrPosition::class, 'position_id', 'id');
    }

    // Accessor: department name from company_unit_user_pivots → company_units, falls back to division_level_1
    public function getDepartmentAttribute(): string
    {
        if ($this->relationLoaded('user') && $this->user?->relationLoaded('companyUnits')) {
            $unit = $this->user->companyUnits->firstWhere('type', 'department');
            if ($unit) return $unit->name;
        }

        if ($this->user_id) {
            $name = DB::table('company_unit_user_pivots as p')
                ->join('company_units as u', 'u.id', '=', 'p.company_id')
                ->where('p.user_id', $this->user_id)
                ->where('u.type', 'department')
                ->value('u.name');
            if ($name) return $name;
        }

        return $this->division_level_1 ?? '—';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('HRPerson')
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

        $name = $this->first_name . ' ' . $this->surname;

        return match ($eventName) {
            'created' => "Person '{$name}' created by " . ($user?->name ?? 'System'),
            'updated' => "Person '{$name}' updated by " . ($user?->name ?? 'System'),
            'deleted' => "Person '{$name}' deleted by " . ($user?->name ?? 'System'),
            default => "Person {$eventName} event",
        };
    }

    public function getProfileCompletionAttribute(): int
    {
        $fields = [
            'title',
            'first_name',
            'surname',
            'initial',
            'email',
            'cell_number',
            'phone_number',
            'id_number',
            'profile_pic',
            'manager_id',
            'position_id',
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
