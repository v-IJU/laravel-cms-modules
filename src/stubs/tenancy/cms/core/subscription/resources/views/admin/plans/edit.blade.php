@extends('layouts.master')

@section('title', 'Edit plan')
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
            Edit Plan
        @endslot
    @endcomponent

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Edit Plan: {{ $plan->name }}</h4>
                    <a href="{{ route('subscription.plans.index') }}" class="btn btn-secondary btn-sm">
                        <i class="bx bx-arrow-back me-1"></i> Back
                    </a>
                </div>
                <div class="card-body">
                    <form action="{{ route('subscription.plans.update', $plan->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" name="name"
                                    class="form-control @error('name') is-invalid @enderror"
                                    value="{{ old('name', $plan->name) }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Slug</label>
                                <input type="text" class="form-control" value="{{ $plan->slug }}" readonly>
                                <small class="text-muted">Slug cannot be changed after creation</small>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3">{{ old('description', $plan->description) }}</textarea>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Price</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="price" step="0.01" min="0" class="form-control"
                                        value="{{ old('price', $plan->price) }}" required>
                                </div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Billing Cycle</label>
                                <select name="billing_cycle" class="form-select">
                                    @foreach (['monthly', 'yearly', 'weekly', 'lifetime'] as $cycle)
                                        <option value="{{ $cycle }}"
                                            {{ $plan->billing_cycle == $cycle ? 'selected' : '' }}>
                                            {{ ucfirst($cycle) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Max Users (-1 = unlimited)</label>
                                <input type="number" name="max_users" min="-1" class="form-control"
                                    value="{{ old('max_users', $plan->max_users) }}" required>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Max Modules (-1 = unlimited)</label>
                                <input type="number" name="max_modules" min="-1" class="form-control"
                                    value="{{ old('max_modules', $plan->max_modules) }}" required>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Trial Days (0 = no trial)</label>
                                <input type="number" name="trial_days" min="0" class="form-control"
                                    value="{{ old('trial_days', $plan->trial_days) }}">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Order</label>
                                <input type="number" name="order" min="0" class="form-control"
                                    value="{{ old('order', $plan->order) }}">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Status</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                                        {{ $plan->is_active ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                            </div>
                        </div>

                        {{-- Module Features --}}
                        <div class="card border mt-3">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">
                                    <i class="bx bx-puzzle me-2"></i>Module Access
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="all_modules" id="all_modules"
                                        onchange="toggleAllModules(this)" {{ $hasAllAccess ? 'checked' : '' }}>
                                    <label class="form-check-label fw-bold" for="all_modules">
                                        <span class="badge bg-warning me-1">Enterprise</span>
                                        Grant ALL modules access
                                    </label>
                                </div>

                                <hr>

                                <div id="modules-list" class="row" style="{{ $hasAllAccess ? 'opacity:0.5' : '' }}">
                                    @foreach ($modules as $module)
                                        <div class="col-md-3 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input module-check" type="checkbox"
                                                    name="modules[{{ $module->id }}]" value="true"
                                                    id="module_{{ $module->id }}"
                                                    {{ $features->get('module_' . $module->name) === 'true' ? 'checked' : '' }}
                                                    {{ $hasAllAccess ? 'disabled' : '' }}>
                                                <label class="form-check-label" for="module_{{ $module->id }}">
                                                    {{ ucfirst($module->name) }}
                                                    <span
                                                        class="badge {{ $module->type == 1 ? 'bg-primary' : 'bg-success' }} ms-1"
                                                        style="font-size:9px">
                                                        {{ $module->type == 1 ? 'core' : 'local' }}
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-save me-1"></i> Update Plan
                            </button>
                            <a href="{{ route('subscription.plans.index') }}" class="btn btn-secondary ms-2">Cancel</a>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')
    <script>
        function toggleAllModules(checkbox) {
            const moduleChecks = document.querySelectorAll('.module-check');
            const modulesList = document.getElementById('modules-list');

            moduleChecks.forEach(function(check) {
                check.disabled = checkbox.checked;
                if (checkbox.checked) check.checked = false;
            });

            modulesList.style.opacity = checkbox.checked ? '0.5' : '1';
        }
    </script>
@endsection
