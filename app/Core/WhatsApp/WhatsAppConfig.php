<?php

namespace App\Core\WhatsApp;

use App\Core\WhatsApp\Models\WhatsAppConfiguration;
use Illuminate\Support\Facades\Schema;

/**
 * Where every module gets the company's WhatsApp credentials.
 *
 * In order, the first complete source wins:
 *   1. central   — Settings → Structure → WhatsApp (whatsapp_configurations), the company-wide
 *                  account every module uses;
 *   2. crm       — CRM → Integrations (meta_whatsapp) or CRM → Settings, for a company that set
 *                  WhatsApp up in CRM before the central page existed, or uses only CRM;
 *   3. env       — config('services.whatsapp') / .env, for a single-company self-hosted install.
 *
 * A source counts only when it has both a token and a phone number id: half a configuration in the
 * central page must not hide a working one in CRM.
 */
final class WhatsAppConfig
{
    public function __construct(
        public readonly string $token,
        public readonly string $phoneNumberId,
        public readonly string $baseUrl,
        public readonly string $countryCode,
        public readonly string $source,        // central | crm | env | none
        public readonly ?string $displayNumber = null,
    ) {
    }

    public static function resolve(): self
    {
        $central = Schema::hasTable('whatsapp_configurations') ? WhatsAppConfiguration::current() : null;
        $country = (string) ($central?->default_country_code ?: config('services.whatsapp.country_code', '27'));

        if ($central?->isComplete()) {
            return new self((string) $central->access_token, (string) $central->phone_number_id,
                'https://graph.facebook.com/' . ($central->api_version ?: 'v21.0'), $country, 'central', $central->display_number);
        }

        $envToken = (string) (config('services.whatsapp.token') ?: config('services.whatsapp.access_token') ?: '');
        $envPhone = (string) (config('services.whatsapp.phone_number_id') ?: '');
        if ($envToken !== '' && $envPhone !== '') {
            return new self($envToken, $envPhone, rtrim((string) (config('services.whatsapp.base_url') ?: 'https://graph.facebook.com/v21.0'), '/'), $country, 'env');
        }

        return new self('', '', 'https://graph.facebook.com/v21.0', $country, 'none');
    }

    public function isConfigured(): bool
    {
        return $this->token !== '' && $this->phoneNumberId !== '';
    }

    /** The Cloud API endpoint for sending from this number. */
    public function messagesUrl(): string
    {
        return $this->baseUrl . '/' . $this->phoneNumberId . '/messages';
    }

    /** A phone number as WhatsApp wants it: digits only, with the country code (082… → 2782…). */
    public function normalise(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '00')) {
            return substr($digits, 2);
        }

        return str_starts_with($digits, '0') ? $this->countryCode . substr($digits, 1) : $digits;
    }
}
