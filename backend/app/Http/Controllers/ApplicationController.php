<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Http\Requests\StoreApplicationRequest;
use App\Http\Requests\UpdateApplicationRequest;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ApplicationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(ApplicationStatus::class)],
        ]);

        $applications = Application::query()
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest('updated_at')
            ->latest('id')
            ->get();

        return ApplicationResource::collection($applications);
    }

    public function store(StoreApplicationRequest $request): ApplicationResource
    {
        $application = Application::create($request->validated());

        return new ApplicationResource($application->refresh());
    }

    public function show(Application $application): ApplicationResource
    {
        return new ApplicationResource($application);
    }

    public function update(UpdateApplicationRequest $request, Application $application): ApplicationResource
    {
        $application->update($request->validated());

        return new ApplicationResource($application);
    }
}
