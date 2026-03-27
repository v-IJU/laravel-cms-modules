@extends('layouts.master')

@section('title', 'create plan')
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
            Create Plan
        @endslot
    @endcomponent
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Create Plan</h4>
                    <a href="{{ route('subscription.plans.index') }}" class="btn btn-secondary btn-sm">
                        <i class="bx bx-arrow-back me-1"></i> Back
                    </a>
                </div>
                <div class="card-body">
                    <form action="{{ route('subscription.plans.store') }}" method="POST">
                        @csrf

                        {{-- Basic Info --}}
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" name="name"
                                    class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}"
                                    required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Slug <span class="text-danger">*</span></label>
                                <input type="text" name="slug" id="slug"
                                    class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug') }}"
                                    required>
                                @error('slug')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Price <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="price" step="0.01" min="0"
                                        class="form-control @error('price') is-invalid @enderror"
                                        value="{{ old('price', '0.00') }}" required>
                                </div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Billing Cycle</label>
                                <select name="billing_cycle" class="form-select">
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="lifetime">Lifetime</option>
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Max Users
                                    <small class="text-muted">(-1 = unlimited)</small>
                                </label>
                                <input type="number" name="max_users" min="-1" class="form-control"
                                    value="{{ old('max_users', 5) }}" required>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Max Modules
                                    <small class="text-muted">(-1 = unlimited)</small>
                                </label>
                                <input type="number" name="max_modules" min="-1" class="form-control"
                                    value="{{ old('max_modules', 3) }}" required>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Trial Days
                                    <small class="text-muted">(0 = no trial)</small>
                                </label>
                                <input type="number" name="trial_days" min="0" class="form-control"
                                    value="{{ old('trial_days', 0) }}">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Order</label>
                                <input type="number" name="order" min="0" class="form-control"
                                    value="{{ old('order', 0) }}">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Status</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                                        {{ old('is_active', true) ? 'checked' : '' }}>
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

                                {{-- All access toggle --}}
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="all_modules" id="all_modules"
                                        onchange="toggleAllModules(this)">
                                    <label class="form-check-label fw-bold" for="all_modules">
                                        <span class="badge bg-warning me-1">Enterprise</span>
                                        Grant access to ALL modules (wildcard)
                                    </label>
                                </div>

                                <hr>

                                {{-- Individual modules --}}
                                <div id="modules-list" class="row">
                                    @foreach ($modules as $module)
                                        <div class="col-md-3 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input module-check" type="checkbox"
                                                    name="modules[{{ $module->id }}]" value="true"
                                                    id="module_{{ $module->id }}"
                                                    {{ old('modules.' . $module->id) ? 'checked' : '' }}>
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
                                <i class="bx bx-save me-1"></i> Create Plan
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
        // Auto generate slug from name
        document.querySelector('input[name="name"]').addEventListener('keyup', function() {
            document.getElementById('slug').value = this.value
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        });

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
