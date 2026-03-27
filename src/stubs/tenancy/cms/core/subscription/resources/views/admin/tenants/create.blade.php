@extends('layouts.master')

@section('title', 'add tenant')
@section('style')
    <!-- Datatables -->
    @include('layout::admin.head.list_head')
    <style>
        .table-div table {
            width: 100% !important;
        }
    </style>
@endsection
@section('content')

    @component('components.breadcrumb')
        @slot('li_1')
            Dashboard
        @endslot
        @slot('title')
            Add Tenant
        @endslot
    @endcomponent

    <div class="row justify-content-center">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">
                        <i class="bx bx-building me-2"></i>Add New Tenant
                    </h4>
                    <a href="{{ route('tenants.index') }}" class="btn btn-secondary btn-sm">
                        <i class="bx bx-arrow-back me-1"></i> Back
                    </a>
                </div>
                <div class="card-body">
                    <form action="{{ route('tenants.store') }}" method="POST">
                        @csrf

                        {{-- Tenant Info --}}
                        <div class="card border mb-4">
                            <div class="card-header bg-light py-2">
                                <h6 class="mb-0"><i class="bx bx-user me-2"></i>Tenant Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">
                                            Tenant ID <span class="text-danger">*</span>
                                            <small class="text-muted">(slug, e.g. acme)</small>
                                        </label>
                                        <input type="text" name="id" id="tenant-id"
                                            class="form-control @error('id') is-invalid @enderror"
                                            value="{{ old('id') }}" placeholder="acme-corp" pattern="[a-z0-9\-]+"
                                            required>
                                        @error('id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="text-muted">
                                            Subdomain: <span id="subdomain-preview" class="text-primary fw-bold">
                                                acme.{{ env('APP_DOMAIN', 'localhost') }}
                                            </span>
                                        </small>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">
                                            Company Name <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" name="name"
                                            class="form-control @error('name') is-invalid @enderror"
                                            value="{{ old('name') }}" placeholder="Acme Corporation" required>
                                        @error('name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">
                                            Admin Email <span class="text-danger">*</span>
                                        </label>
                                        <input type="email" name="email"
                                            class="form-control @error('email') is-invalid @enderror"
                                            value="{{ old('email') }}" placeholder="admin@acme.com" required>
                                        @error('email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Plan & Trial --}}
                        <div class="card border mb-4">
                            <div class="card-header bg-light py-2">
                                <h6 class="mb-0">
                                    <i class="bx bx-credit-card me-2"></i>Plan & Trial Setup
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">
                                            Plan <span class="text-danger">*</span>
                                        </label>
                                        <select name="plan_id" class="form-select" required>
                                            <option value="">Select Plan</option>
                                            @foreach ($plans as $plan)
                                                <option value="{{ $plan->id }}"
                                                    {{ old('plan_id') == $plan->id ? 'selected' : '' }}>
                                                    {{ $plan->name }}
                                                    — ${{ $plan->price }}/{{ $plan->billing_cycle }}
                                                    @if ($plan->trial_days > 0)
                                                        ({{ $plan->trial_days }}d trial)
                                                    @endif
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">
                                            Trial Days
                                            <small class="text-muted">(0 = no trial, activate immediately)</small>
                                        </label>
                                        <input type="number" name="trial_days" min="0" class="form-control"
                                            value="{{ old('trial_days', 14) }}">
                                    </div>
                                </div>

                                {{-- Plan cards preview --}}
                                <div class="row mt-2">
                                    @foreach ($plans as $plan)
                                        <div class="col-md-4">
                                            <div class="card border plan-card" style="cursor:pointer"
                                                onclick="selectPlan({{ $plan->id }}, {{ $plan->trial_days }})">
                                                <div class="card-body p-3 text-center">
                                                    <h6 class="mb-1">{{ $plan->name }}</h6>
                                                    <h4 class="text-primary mb-1">
                                                        ${{ $plan->price }}
                                                        <small class="fs-12 text-muted">
                                                            /{{ $plan->billing_cycle }}
                                                        </small>
                                                    </h4>
                                                    <small class="text-muted">
                                                        {{ $plan->max_users == -1 ? 'Unlimited' : $plan->max_users }} users
                                                        · {{ $plan->max_modules == -1 ? 'All' : $plan->max_modules }}
                                                        modules
                                                    </small>
                                                    @if ($plan->trial_days > 0)
                                                        <div class="mt-1">
                                                            <span class="badge bg-info">
                                                                {{ $plan->trial_days }}d trial
                                                            </span>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        {{-- Submit --}}
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-plus me-1"></i> Create Tenant
                            </button>
                            <a href="{{ route('tenants.index') }}" class="btn btn-secondary">
                                Cancel
                            </a>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')
    <script>
        // Auto format tenant ID
        document.getElementById('tenant-id').addEventListener('input', function() {
            this.value = this.value.toLowerCase().replace(/[^a-z0-9\-]/g, '');
            document.getElementById('subdomain-preview').textContent =
                (this.value || 'acme') + '.{{ env('APP_DOMAIN', 'localhost') }}';
        });

        function selectPlan(planId, trialDays) {
            document.querySelector('select[name="plan_id"]').value = planId;
            document.querySelector('input[name="trial_days"]').value = trialDays;

            // Highlight selected plan card
            document.querySelectorAll('.plan-card').forEach(c => {
                c.classList.remove('border-primary', 'bg-soft-primary');
            });
            event.currentTarget.classList.add('border-primary', 'bg-soft-primary');
        }
    </script>
@endsection
