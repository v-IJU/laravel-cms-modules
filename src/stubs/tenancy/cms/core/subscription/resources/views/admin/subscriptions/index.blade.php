@extends('layouts.master')

@section('title', 'Subscriptions')
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
            Subscriptions
        @endslot
    @endcomponent

    {{-- Assign Plan Modal --}}
    <div class="modal fade" id="assignPlanModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="assignPlanForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <input type="hidden" id="assign_tenant_id" name="tenant_id">

                        <div class="mb-3">
                            <label class="form-label">Tenant</label>
                            <input type="text" id="assign_tenant_name" class="form-control" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Plan <span class="text-danger">*</span></label>
                            <select name="plan_id" class="form-select" required>
                                <option value="">Select Plan</option>
                                @foreach ($plans as $plan)
                                    <option value="{{ $plan->id }}">
                                        {{ $plan->name }} — ${{ $plan->price }}/{{ $plan->billing_cycle }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="starts_at" class="form-control" value="{{ date('Y-m-d') }}"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">End Date
                                <small class="text-muted">(leave empty = auto from billing cycle)</small>
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

    {{-- Main content --}}
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">
                        <i class="bx bx-group me-2"></i>Tenant Subscriptions
                    </h4>
                </div>
                <div class="card-body">
                    <table id="subscriptions-table" class="table table-bordered dt-responsive nowrap w-100">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Tenant</th>
                                <th>Email</th>
                                <th>Plan</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')
    <script>
        $(document).ready(function() {
            $('#subscriptions-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('subscription.index') }}',
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'tenant_name',
                        name: 'tenants.id'
                    },
                    {
                        data: 'tenant_email',
                        name: 'tenant_email',
                        orderable: false
                    },
                    {
                        data: 'plan_name',
                        name: 'plans.name'
                    },
                    {
                        data: 'price',
                        name: 'plans.price'
                    },
                    {
                        data: 'status_badge',
                        name: 'tenant_plans.status',
                        orderable: false
                    },
                    {
                        data: 'starts_at',
                        name: 'tenant_plans.starts_at'
                    },
                    {
                        data: 'ends_at_label',
                        name: 'tenant_plans.ends_at',
                        orderable: false
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false
                    },
                ]
            });

            // Cancel confirmation
            $(document).on('submit', '.cancel-form', function(e) {
                e.preventDefault();
                if (confirm('Cancel this subscription? Tenant will lose access!')) {
                    this.submit();
                }
            });
        });

        function assignPlan(tenantId) {
            document.getElementById('assign_tenant_id').value = tenantId;
            document.getElementById('assign_tenant_name').value = tenantId;

            // Update form action
            document.getElementById('assignPlanForm').action =
                '/administrator/subscription/' + tenantId + '/assign';

            new bootstrap.Modal(document.getElementById('assignPlanModal')).show();
        }
    </script>
@endsection
