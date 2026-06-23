<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexDepartmentRequest;
use App\Http\Requests\Api\V1\StoreDepartmentRequest;
use App\Http\Requests\Api\V1\UpdateDepartmentRequest;
use App\Http\Resources\Api\V1\DepartmentResource;
use App\Models\Department;
use App\Services\Procurement\DepartmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DepartmentController extends Controller
{
    public function __construct(
        private readonly DepartmentService $departmentService
    ) {
    }

    public function index(IndexDepartmentRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Department::class);

        $departments = $this->departmentService->paginatedForUser(
            user: $request->user(),
            filters: $request->validated()
        );

        return DepartmentResource::collection($departments);
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $this->authorize('create', Department::class);

        $department = $this->departmentService->create(
            user: $request->user(),
            data: $request->validated()
        );

        return (new DepartmentResource($department))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Department $department): DepartmentResource
    {
        $this->authorize('view', $department);

        return new DepartmentResource(
            $department->loadCount(['users', 'purchaseRequests'])
        );
    }

    public function update(UpdateDepartmentRequest $request, Department $department): DepartmentResource
    {
        $this->authorize('update', $department);

        $department = $this->departmentService->update(
            department: $department,
            data: $request->validated()
        );

        return new DepartmentResource($department);
    }

    public function destroy(Department $department): JsonResponse
    {
        $this->authorize('delete', $department);

        $this->departmentService->delete($department);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
