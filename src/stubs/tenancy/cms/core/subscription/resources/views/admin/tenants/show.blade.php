@extends('layouts.master')

@section('title', 'Tenant: ' . $tenant->name)
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
            {{ 'Tenant: ' . $tenant->name }}
        @endslot
    @endcomponent

    <div class="row">

        {{-- Tenant Info Card --}}
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body text-center p-4">
                    <div class="avatar-lg mx-auto mb-3">
                        <span class="avatar-title rounded-circle bg-primary fs-2">
                            {{ strtoupper(substr($tenant->name, 0, 1)) }}
                        </span>
                    </div>
                    <h5 class="mb-1">{{ $tenant->name }}</h5>
                    <p class="text-muted mb-3">{{ $tenant->email }}</p>

                    {{-- Status badge --}}
                    @php
                        $badgeClass = match ($tenant->onboard_status) {
                            'active' => 'bg-success',
                            'trial' => 'bg-info',
                            'pending' => 'bg-warning',
                            'suspended' => 'bg-secondary',
                            'rejected' => 'bg-danger',
                            default => 'bg-light text-dark',
                        };
                    @endphp
                    <span class="badge {{ $badgeClass }} fs-12 mb-3">
                        {{ ucfirst($tenant->onboard_status ?? 'unknown') }}
                    </span>

                    {{-- Trial info --}}
                    @if ($tenant->onboard_status === 'trial' && $tenant->trial_ends_at)
                        <div class="alert alert-info py-2 mb-3">
                            <i class="bx bx-time me-1"></i>
                            Trial ends:
                            <strong>{{ \Carbon\Carbon::parse($tenant->trial_ends_at)->format('Y-m-d') }}</strong>
                            <br>
                            <small>{{ $tenant->trialDaysLeft() }} days remaining</small>
                        </div>
                    @endif

                    {{-- Actions --}}
                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                        @if (in_array($tenant->onboard_status, ['trial', 'pending']))
                            <a href="{{ route('tenants.onboard', $tenant->id) }}" class="btn btn-success btn-sm">
                                <i class="bx bx-check me-1"></i> Approve & Onboard
                            </a>
                        @endif

                        @if ($tenant->onboard_status === 'active')
                            <form action="{{ route('tenants.suspend', $tenant->id) }}" method="POST">
                                @csrf
                                <button class="btn btn-warning btn-sm">
                                    <i class="bx bx-pause me-1"></i> Suspend
                                </button>
                            </form>
                        @endif

                        @if ($tenant->onboard_status === 'suspended')
                            <form action="{{ route('tenants.reactivate', $tenant->id) }}" method="POST">
                                @csrf
                                <button class="btn btn-success btn-sm">
                                    <i class="bx bx-play me-1"></i> Reactivate
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- Details --}}
                <div class="card-footer">
                    <table class="table table-sm mb-0">
                        <tr>
                            <td class="text-muted">Tenant ID</td>
                            <td><code>{{ $tenant->id }}</code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Database</td>
                            <td><code>tenant_{{ $tenant->id }}</code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Created</td>
                            <td>{{ \Carbon\Carbon::parse($tenant->created_at)->format('Y-m-d') }}</td>
                        </tr>
                        @if ($tenant->approved_at)
                            <tr>
                                <td class="text-muted">Approved</td>
                                <td>{{ \Carbon\Carbon::parse($tenant->approved_at)->format('Y-m-d') }}</td>
                            </tr>
                        @endif
                    </table>
                </div>
            </div>

            {{-- Domains --}}
            <div class="card">
                <div class="card-header py-2">
                    <h6 class="mb-0"><i class="bx bx-globe me-2"></i>Domains</h6>
                </div>
                <div class="card-body p-2">
                    @forelse($domains as $domain)
                        <div class="d-flex align-items-center justify-content-between p-2 border-bottom">
                            <span>
                                <i class="bx bx-link me-1 text-muted"></i>
                                {{ $domain->domain }}
                            </span>
                            <a href="http://{{ $domain->domain }}:{{ request()->getPort() }}/administrator"
                                target="_blank" class="btn btn-xs btn-outline-primary">
                                <i class="bx bx-link-external"></i>
                            </a>
                        </div>
                    @empty
                        <p class="text-muted text-center p-3 mb-0">No domains</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Subscription History --}}
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="bx bx-history me-2"></i>Subscription History
                    </h6>
                    <button class="btn btn-primary btn-sm"
                        onclick="document.getElementById('assignModal').style.display='block'" data-bs-toggle="modal"
                        data-bs-target="#assignPlanModal">
                        <i class="bx bx-transfer me-1"></i> Change Plan
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Plan</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Start</th>
                                    <th>End</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($subscriptions as $sub)
                                    <tr>
                                        <td><strong>{{ $sub->plan_name }}</strong></td>
                                        <td>${{ $sub->price }}</td>
                                        <td>
                                            @php
                                                $c = match ($sub->status) {
                                                    'active' => 'success',
                                                    'trial' => 'info',
                                                    'suspended' => 'warning',
                                                    'cancelled' => 'danger',
                                                    default => 'secondary',
                                                };
                                            @endphp
                                            <span class="badge bg-{{ $c }}">{{ ucfirst($sub->status) }}</span>
                                        </td>
                                        <td>{{ $sub->starts_at ? \Carbon\Carbon::parse($sub->starts_at)->format('Y-m-d') : '—' }}
                                        </td>
                                        <td>{{ $sub->ends_at ? \Carbon\Carbon::parse($sub->ends_at)->format('Y-m-d') : 'Never' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            No subscription history
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Assign Plan Modal --}}
    <div class="modal fade" id="assignPlanModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('subscription.assign', $tenant->id) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Plan</label>
                            <select name="plan_id" class="form-select" required>
                                @foreach ($plans as $plan)
                                    <option value="{{ $plan->id }}">
                                        {{ $plan->name }} — ${{ $plan->price }}/{{ $plan->billing_cycle }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="starts_at" class="form-control" value="{{ date('Y-m-d') }}"
                                required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">End Date
                                <small class="text-muted">(optional)</small>
                            </label>
                            <input type="date" name="ends_at" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bx bx-check me-1"></i> Assign Plan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection
