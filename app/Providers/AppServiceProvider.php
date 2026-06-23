<?php

namespace App\Providers;

use App\Models\Department;
use App\Models\PurchaseRequest;
use App\Models\Vendor;
use App\Policies\DepartmentPolicy;
use App\Policies\PurchaseRequestPolicy;
use App\Policies\VendorPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(PurchaseRequest::class, PurchaseRequestPolicy::class);
        Gate::policy(Vendor::class, VendorPolicy::class);
    }
}
