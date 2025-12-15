<?php

namespace AnikNinja\MailMapper\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\EmailMapping;
use App\Services\EmailMappingService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use App\Services\DataTableCommonService;
use Illuminate\Http\JsonResponse;
use Modules\Admin\Transformers\EmailMappingDataTransformer;
use Illuminate\Database\Eloquent\Builder;

class EmailMappingController extends Controller
{
    protected $dataTableCommonService;

    public function __construct(DataTableCommonService $dataTableCommonService)
    {
        $this->dataTableCommonService = $dataTableCommonService;

        // Restrict access based on permissions
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            $route = $request->route()->getActionMethod();
            // Allow access if user has super-admin-only permission
            if ($user && $user->can('super-admin-only')) {
                return $next($request);
            }
            // Otherwise, for index, edit, update: require email-mapping-configure
            if (in_array($route, ['index', 'edit', 'update'])) {
                if (!$user || !$user->can('email-mapping-configure')) {
                    abort(403, 'Unauthorized! You do not have permission to access this resource.');
                }
                return $next($request);
            }
            // For all other methods, deny access
            abort(403, 'Unauthorized! Permission denied. Contact your administrator.');
        });
    }
    public function index(Request $request): View | JsonResponse
    {
        if ($request->has('searchableColumns')) {
            return $this->fetchData($request);
        }

        return view('admin::email_mapping.index');
    }

    /**
     * Fetch data for DataTables.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchData(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'searchableColumns' => 'required|array',
                'sortableColumns' => 'nullable|array',
                'draw' => 'nullable|integer',
                'start' => 'nullable|integer|min:0',
                'length' => 'nullable|integer|min:1',
            ]);

            $query = $this->buildQuery();

            $searchableColumns = $request->searchableColumns;
            $sortableColumns = $request->sortableColumns ?? [];
            $userPermissionRoles = ['Admin', 'Super-Admin'];
            $dateField = 'date';
            $createdByField = 'last_updated_by';

            $dataTableResult = $this->dataTableCommonService->handle(
                $request,
                $query,
                $searchableColumns,
                $sortableColumns,
                $userPermissionRoles,
                $dateField,
                $createdByField
            );

            $data = $dataTableResult['data']->map(
                fn($item, $key) => EmailMappingDataTransformer::transform($item, $key)
            );

            return response()->json([
                'draw' => (int) $request->input('draw', 0),
                'recordsTotal' => $dataTableResult['total'],
                'recordsFiltered' => $dataTableResult['total'],
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'data' => [],
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'draw' => (int) $request->input('draw', 0),
            ], 500);
        }
    }

    /**
     * Build the base query for feasibility requirements.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function buildQuery(): Builder
    {
        $modelClass = request()->model ?? EmailMapping::class;

        if (!class_exists($modelClass)) {
            throw new \Exception("Model {$modelClass} does not exist.");
        }

        // Always eager load updatedBy for DataTables
        $withRelations = request()->withRelations ?? [];
        if (!in_array('updatedBy', $withRelations)) {
            $withRelations[] = 'updatedBy';
        }

        return $modelClass::query()->with($withRelations);
    }

    public function create(): View
    {
        return view('admin::email_mapping.form');
    }

    public function store(Request $request, EmailMappingService $svc): RedirectResponse
    {
        $data = $request->validate([
            'module' => 'required|string',
            'menu' => 'required|string',
            'task' => 'required|string',
            'to' => 'required|string',
            'cc' => 'nullable|string',
            'subject' => 'required|string',
            'body' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $data['to'] = $request->input('to');
        $data['cc'] = $request->input('cc');
        $data['last_updated_by'] = auth()->id();

        $mapping = EmailMapping::create($data);
        $svc->clearCacheForMapping($mapping);

        return redirect()->route('email-mappings.index')->with('success', 'Saved');
    }

    public function edit(EmailMapping $email_mapping): View
    {
        return view('admin::email_mapping.form', ['mapping' => $email_mapping]);
    }

    public function update(Request $request, EmailMapping $email_mapping, EmailMappingService $svc): RedirectResponse
    {
        $data = $request->validate([
            'to' => 'required|string',
            'cc' => 'nullable|string',
            'subject' => 'required|string',
            'body' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $data['to'] = $request->input('to');
        $data['cc'] = $request->input('cc');
        $data['last_updated_by'] = auth()->id();

        $email_mapping->update($data);
        $svc->clearCacheForMapping($email_mapping);

        return redirect()->route('email-mappings.index')->with('success', 'Updated');
    }

    public function destroy(EmailMapping $email_mapping, EmailMappingService $svc): RedirectResponse
    {
        $svc->clearCacheForMapping($email_mapping);
        $email_mapping->delete();
        return back()->with('success', 'Deleted');
    }
}
