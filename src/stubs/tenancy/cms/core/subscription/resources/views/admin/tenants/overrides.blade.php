@extends('layouts.master')
@section('title', 'Module Overrides: ' . $tenant->name)
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
            {{ 'Module Overrides: ' . $tenant->name }}
        @endslot
    @endcomponent
    <div class="row">
        <div class="col-12">

            {{-- Header --}}
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">
                        <i class="bx bx-customize me-2"></i>
                        Module Overrides — {{ $tenant->name }}
                    </h4>
                    <p class="text-muted mb-0">
                        Grant or restrict specific modules for this tenant
                        regardless of their plan.
                    </p>
                </div>
                <a href="{{ route('tenants.show', $tenant->id) }}" class="btn btn-secondary btn-sm">
                    <i class="bx bx-arrow-back me-1"></i> Back
                </a>
            </div>

            <div class="row">

                {{-- Add override form --}}
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header py-2">
                            <h6 class="mb-0">
                                <i class="bx bx-plus me-2"></i>Add Override
                            </h6>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('tenants.override.save', $tenant->id) }}" method="POST">
                                @csrf

                                <div class="mb-3">
                                    <label class="form-label">Module</label>
                                    <select name="module_name" class="form-select" required>
                                        <option value="">Select module</option>
                                        @foreach ($allModules as $module)
                                            <option value="{{ $module->name }}">
                                                {{ ucfirst($module->name) }}
                                                ({{ $module->type == 1 ? 'core' : 'local' }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Access</label>
                                    <select name="is_enabled" class="form-select" required>
                                        <option value="1">✅ Enable (grant access)</option>
                                        <option value="0">❌ Disable (restrict access)</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">
                                        Custom Limits
                                        <small class="text-muted">(JSON)</small>
                                    </label>
                                    <textarea name="custom_limits" class="form-control font-monospace" rows="4"
                                        placeholder='{"max_posts": 50, "can_export_pdf": true}'></textarea>
                                    <small class="text-muted">
                                        Overrides plan defaults for this tenant only
                                    </small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Notes</label>
                                    <input type="text" name="notes" class="form-control"
                                        placeholder="Reason for override...">
                                </div>

                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bx bx-save me-1"></i> Save Override
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Current overrides --}}
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header py-2">
                            <h6 class="mb-0">
                                <i class="bx bx-list-ul me-2"></i>
                                Current Overrides
                                <span class="badge bg-primary ms-2">{{ $overrides->count() }}</span>
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            @forelse($overrides as $override)
                                <div class="p-3 border-bottom">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">
                                                {{ ucfirst($override->module_name) }}
                                                @if ($override->is_enabled)
                                                    <span class="badge bg-success ms-1">Enabled</span>
                                                @else
                                                    <span class="badge bg-danger ms-1">Disabled</span>
                                                @endif
                                            </h6>

                                            @if ($override->custom_limits)
                                                <div class="mb-1">
                                                    <small class="text-muted">Custom limits:</small>
                                                    @php
                                                        $limits = json_decode($override->custom_limits, true);
                                                    @endphp
                                                    @foreach ($limits as $key => $value)
                                                        <span class="badge bg-info bg-opacity-10 text-info me-1">
                                                            {{ $key }}:
                                                            {{ is_bool($value) ? ($value ? 'true' : 'false') : $value }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif

                                            @if ($override->notes)
                                                <small class="text-muted">
                                                    <i class="bx bx-note me-1"></i>{{ $override->notes }}
                                                </small>
                                            @endif
                                        </div>

                                        <form
                                            action="{{ route('tenants.override.delete', [$tenant->id, $override->module_name]) }}"
                                            method="POST">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Remove override?')">
                                                <i class="bx bx-x"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center text-muted py-4">
                                    <i class="bx bx-info-circle fs-2 mb-2 d-block"></i>
                                    No overrides — tenant follows plan defaults
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
