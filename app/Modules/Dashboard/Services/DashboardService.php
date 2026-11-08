<?php

namespace App\Modules\Dashboard\Services;

use App\Core\User\Models\User;
use App\Modules\HR\Hr\Models\HrPeopleChange;
use App\Modules\HR\Hr\Models\HRPerson;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DashboardService
{


    public function getDashboardData(): array
    {
        $id   = Auth::id();
        $user = User::with('companies')->find($id);

        return [
            'user'          => $user?->person,
            'approvalAlert' => $this->getApprovalAlert($user, $id),
            'profileAlert'  => $this->getProfileAlert($user, $id),
        ];
    }

    private function getApprovalAlert($user, $id)
    {
        if (!$user || !$user->companies()->exists()) {
            return null;
        }

        $pendingCount = HrPeopleChange::where('is_approved', false)->count();
        $sessionKey   = "approval_alert_shown_{$id}";

        if ($pendingCount > 0 && !session()->has($sessionKey)) {
            session()->put($sessionKey, true);

            return [
                'show'         => true,
                'message'      => $pendingCount === 1
                    ? 'An employee submitted a request to modify their profile.'
                    : "{$pendingCount} employees have submitted requests to modify their profiles.",
                'pendingCount' => $pendingCount,
            ];
        }

        return null;
    }

    private function getProfileAlert($user, $id)
    {
        $hr         = $user?->person;
        $completion = $hr?->profile_completion ?? 0;
        $sessionKey = "profile_alert_shown_{$id}";

        if ($completion < 80 && !session()->has($sessionKey)) {
            session()->put($sessionKey, true);

            return [
                'show'       => true,
                'completion' => $completion,
                'message'    => "Your profile is only {$completion}% complete. Please complete it to unlock all features.",
            ];
        }

        return null;
    }



    /*
    |=========================================================================
    | 🎨 CORPORATE BRANDING
    |=========================================================================
    */

    public function getCorporateBranding()
    {
        // Priority 1: global company-wide active branding (no company_unit)
        $global = CorporateBranding::withoutTrashed()
            ->where('is_active', true)
            ->whereNull('company_unit_id')
            ->with(['logoLight', 'logoDark', 'favicon'])
            ->latest('updated_at')
            ->first();

        if ($global) {
            return $global;
        }

        // Fallback: any active branding (most recently updated)
        return CorporateBranding::withoutTrashed()
            ->where('is_active', true)
            ->with(['logoLight', 'logoDark', 'favicon'])
            ->latest('updated_at')
            ->first();
    }

    /*
    |=========================================================================
    | 📁 RESOURCES
    |=========================================================================
    */



    /*
    |=========================================================================
    | 🎂 STAFF ANNIVERSARIES
    |=========================================================================
    */

    /*
    |=========================================================================
    | 🏖️ STAFF LEAVES
    |=========================================================================
    */

}
