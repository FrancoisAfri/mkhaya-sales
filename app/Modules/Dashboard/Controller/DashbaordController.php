<?php

namespace App\Modules\Dashboard\Controller;

use App\Core\CompanyUnits\Models\CompanyUnit;
use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Requests\QuickAccessRequest;
use App\Modules\Dashboard\Services\DashboardService;
use App\Modules\Dashboard\Services\QuickAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashbaordController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
        private QuickAccessService $quickAccessService

    ) {}

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD
    |--------------------------------------------------------------------------
    */

    public function index(): Response|RedirectResponse
    {
        if ($uid = Auth::id()) {
            if (\App\Modules\Tenancy\Models\Reseller::where('user_id', $uid)->where('status', 'active')->exists()) {
                return redirect()->route('reseller.dashboard');
            }
        }

        $dashboardData = $this->dashboardService->getDashboardData();
        $userId = Auth::id();

        return Inertia::render('Modules/Dashboard/Pages/Index', [
            'user'              => $dashboardData['user'],
            'approvalAlert'     => $dashboardData['approvalAlert'],
            'profileAlert'      => $dashboardData['profileAlert'],
            'capabilities'      => ['content_management' => false, 'resources' => false],
            'moduleWidgets'     => app(\App\Modules\Dashboard\Services\ModuleWidgetService::class)->widgets(),
            'ceoMessage'        => null,
            'news'              => [],
            'events'            => [],
            'announcements'     => [],
            'internalNotices'   => [],
            'companyUnits'      => $this->getCompanyUnits(),
            'spotlights'        => [],
            'staffAnniversaries'=> [],
            'staffLeaves'       => [],
            'reactionsSummary'  => [],
            'accessibleRibbons' => $this->quickAccessService->getAccessibleRibbonsForUser($userId),
            'userQuickAccess'   => $this->quickAccessService->getForUser($userId),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CONTENT MANAGEMENT
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | CORPORATE BRANDING
    |--------------------------------------------------------------------------
    */

    public function corporateBranding(): Response
    {
        return Inertia::render(
            'Modules/Communication/Resources/CorporateBrandings/Index',
            [
                'branding' => $this->dashboardService->getCorporateBranding(),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | RESOURCES
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | EMPLOYEE SPOTLIGHTS
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | PRIVATE HELPERS
    |--------------------------------------------------------------------------
    */

    private function renderResourcePage(string $view, mixed $resources, string $key = 'resources',): Response
    {
        return Inertia::render($view, [
            $key            => $resources,
            'companyUnits'  => $this->getCompanyUnits(),
        ]);
    }

    private function getCompanyUnits(): array
    {
        $types = [
            'division',
            'department',
            'team',
            'section',
            'unit',
        ];

        $companyUnits = [];

        foreach ($types as $type) {
            $companyUnits["{$type}s"] = CompanyUnit::query()
                ->where('type', $type)
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'parent_id',
                    'type',
                ]);
        }

        return $companyUnits;
    }

    /*
    |--------------------------------------------------------------------------
    | QUICK ACCESS
    |--------------------------------------------------------------------------
    */


    public function storeQuickAccess(QuickAccessRequest $request): RedirectResponse
    {
        $this->quickAccessService->create($request->validated());

        return redirect()->back();
    }

    public function updateQuickAccess(QuickAccessRequest $request, int $id): RedirectResponse
    {
        $this->quickAccessService->update($id, $request->validated());

        return redirect()->back();
    }

    public function destroyQuickAccess(int $id): RedirectResponse
    {
        $this->quickAccessService->delete($id);

        return redirect()->back();
    }

    public function addToUser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ribbon_ids'   => ['required', 'array'],
            'ribbon_ids.*' => ['integer', 'exists:security_module_ribbons,id'],
            'names'        => ['sometimes', 'array'],
            'names.*'      => ['nullable', 'string', 'max:100'],
        ]);
        $userId = Auth::id();

        $this->quickAccessService->addToUser(
            $userId,
            $data['ribbon_ids'],
            $data['names'] ?? []
        );

        return response()->json(['message' => 'Quick access updated.']);
    }

    public function removeFromUser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ribbon_ids'   => ['required'],
            'ribbon_ids.*' => ['integer', 'exists:security_module_ribbons,id'],
        ]);
        $userId = Auth::id();

        $this->quickAccessService->removeFromUser($userId, $data['ribbon_ids']);

        return response()->json(['message' => 'Quick access removed.']);
    }

}
