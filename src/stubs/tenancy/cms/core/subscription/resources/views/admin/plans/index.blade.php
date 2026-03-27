@extends('layouts.master')
@section('title', 'users')
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
            Plans
        @endslot
    @endcomponent

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">
                        <i class="bx bx-credit-card me-2"></i>Plans
                    </h4>
                    <a href="{{ route('subscription.plans.create') }}" class="btn btn-primary btn-sm">
                        <i class="bx bx-plus me-1"></i> Add Plan
                    </a>
                </div>
                <div class="card-body">
                    <table id="plans-table" class="table table-bordered dt-responsive nowrap w-100">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Price</th>
                                <th>Max Users</th>
                                <th>Max Modules</th>
                                <th>Trial Days</th>
                                <th>Tenants</th>
                                <th>Status</th>
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
            var table = $('#plans-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('subscription.plans.index') }}',
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'name',
                        name: 'name'
                    },
                    {
                        data: 'price_label',
                        name: 'price',
                        orderable: false
                    },
                    {
                        data: 'max_users_label',
                        name: 'max_users',
                        orderable: false
                    },
                    {
                        data: 'max_modules_label',
                        name: 'max_modules',
                        orderable: false
                    },
                    {
                        data: 'trial_days',
                        name: 'trial_days'
                    },
                    {
                        data: 'tenant_plans_count',
                        name: 'tenant_plans_count'
                    },
                    {
                        data: 'status_badge',
                        name: 'is_active',
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

            // Delete confirmation
            $(document).on('submit', '.delete-form', function(e) {
                e.preventDefault();
                if (confirm('Delete this plan? This cannot be undone!')) {
                    this.submit();
                }
            });
        });
    </script>
@endsection
