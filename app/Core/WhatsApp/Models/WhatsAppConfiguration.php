<?php

namespace App\Core\WhatsApp\Models;

use Illuminate\Database\Eloquent\Model;

/** The company's WhatsApp Business account; see WhatsAppConfig for how modules read it. */
class WhatsAppConfiguration extends Model
{
    use \App\Modules\Tenancy\Traits\BelongsToCompany;

    protected $table = 'whatsapp_configurations';

    protected $fillable = [
        'company_id', 'display_number', 'phone_number_id', 'business_account_id', 'access_token',
        'api_version', 'default_country_code', 'is_active', 'last_tested_at', 'last_test_result', 'updated_by',
    ];

    protected $casts = ['is_active' => 'boolean', 'last_tested_at' => 'datetime'];

    protected $hidden = ['access_token'];

    public function setAccessTokenAttribute(?string $value): void
    {
        $this->attributes['access_token'] = $value ? encrypt($value) : null;
    }

    public function getAccessTokenAttribute(?string $value): ?string
    {
        try {
            return $value ? decrypt($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** The current company's row (there is at most one). */
    public static function current(): ?self
    {
        return static::query()->first();
    }

    public function isComplete(): bool
    {
        return $this->is_active && filled($this->phone_number_id) && filled($this->access_token);
    }
}
