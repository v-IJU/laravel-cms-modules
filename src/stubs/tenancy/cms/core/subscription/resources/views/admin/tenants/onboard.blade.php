@extends('layouts.master')

@section('title', 'Onboard: ' . $tenant->name)
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
            {{ 'Onboard: ' . $tenant->name }}
        @endslot
    @endcomponent

    <div class="row justify-content-center">
        <div class="col-lg-12">

            {{-- Tenant summary --}}
            <div class="card border-info mb-4">
                <div class="card-header bg-info bg-opacity-10">
                    <h5 class="mb-0 text-info">
                        <i class="bx bx-info-circle me-2"></i>Tenant Summary
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm mb-0">
                                <tr>
                                    <td class="text-muted">ID</td>
                                    <td><code>{{ $tenant->id }}</code></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Name</td>
                                    <td><strong>{{ $tenant->name }}</strong></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Email</td>
                                    <td>{{ $tenant->email }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm mb-0">
                                <tr>
                                    <td class="text-muted">Status</td>
                                    <td>
                                        <span class="badge bg-info">
                                            {{ ucfirst($tenant->onboard_status) }}
                                        </span>
                                    </td>
                                </tr>
                                @if ($tenant->trial_ends_at)
                                    <tr>
                                        <td class="text-muted">Trial ends</td>
                                        <td>{{ \Carbon\Carbon::parse($tenant->trial_ends_at)->format('Y-m-d') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Days left</td>
                                        <td>
                                            <span
                                                class="{{ $tenant->trialDaysLeft() <= 3 ? 'text-danger' : 'text-success' }}">
                                                {{ $tenant->trialDaysLeft() }} days
                                            </span>
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <td class="text-muted">Registered</td>
                                    <td>{{ \Carbon\Carbon::parse($tenant->created_at)->format('Y-m-d') }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Approve form --}}
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bx bx-check-circle me-2 text-success"></i>Approve & Onboard
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('tenants.approve', $tenant->id) }}" method="POST">
                        @csrf

                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                Select Plan <span class="text-danger">*</span>
                            </label>

                            <div class="row">
                                @foreach ($plans as $plan)
                                    <div class="col-md-4 mb-3">
                                        <div class="card border plan-option" style="cursor:pointer"
                                            onclick="selectPlan({{ $plan->id }}, this)">
                                            <div class="card-body p-3 text-center">
                                                <div class="form-check d-none">
                                                    <input class="form-check-input plan-radio" type="radio" name="plan_id"
                                                        value="{{ $plan->id }}" id="plan_{{ $plan->id }}"
                                                        {{ $loop->first ? 'checked' : '' }}>
                                                </div>
                                                <h6 class="mb-1">{{ $plan->name }}</h6>
                                                <h4 class="text-primary mb-1">
                                                    ${{ $plan->price }}
                                                    <small class="fs-12 text-muted">/{{ $plan->billing_cycle }}</small>
                                                </h4>
                                                <div class="text-muted small">
                                                    <i class="bx bx-user me-1"></i>
                                                    {{ $plan->max_users == -1 ? '∞' : $plan->max_users }} users
                                                </div>
                                                <div class="text-muted small">
                                                    <i class="bx bx-puzzle me-1"></i>
                                                    {{ $plan->max_modules == -1 ? 'All' : $plan->max_modules }} modules
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Hidden real radio --}}
                            <select name="plan_id" id="plan_id_select" class="form-select d-none">
                                @foreach ($plans as $plan)
                                    <option value="{{ $plan->id }}" {{ $loop->first ? 'selected' : '' }}>
                                        {{ $plan->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes (optional)</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Any notes about this approval..."></textarea>
                        </div>

                        <div class="alert alert-warning py-2">
                            <i class="bx bx-info-circle me-1"></i>
                            This will activate the tenant and start their subscription immediately.
                            Trial plan will be cancelled.
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="bx bx-check me-1"></i> Approve & Activate
                            </button>
                            <a href="{{ route('tenants.show', $tenant->id) }}" class="btn btn-secondary">Cancel</a>

                            {{-- Reject option --}}
                            <form action="{{ route('tenants.reject', $tenant->id) }}" method="POST" class="ms-auto">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger"
                                    onclick="return confirm('Reject this tenant?')">
                                    <i class="bx bx-x me-1"></i> Reject
                                </button>
                            </form>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>

@endsection

@section('script')
    <script>
        // Select first plan by default
        document.addEventListener('DOMContentLoaded', function() {
            const firstCard = document.querySelector('.plan-option');
            if (firstCard) firstCard.classList.add('border-success');
        });

        function selectPlan(planId, card) {
            // Update hidden select
            document.getElementById('plan_id_select').value = planId;

            // Update radio
            document.querySelectorAll('.plan-radio').forEach(r => r.checked = false);
            document.getElementById('plan_' + planId).checked = true;

            // Update card styling
            document.querySelectorAll('.plan-option').forEach(c => {
                c.classList.remove('border-success', 'bg-soft-success');
            });
            card.classList.add('border-success', 'bg-soft-success');
        }
    </script>
@endsection
