@extends('layouts.master')

@section('title', 'tenants')
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
            Tenants
        @endslot
    @endcomponent

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">
                        <i class="bx bx-buildings me-2"></i>Tenants
                    </h4>
                    <a href="{{ route('tenants.create') }}" class="btn btn-primary btn-sm">
                        <i class="bx bx-plus me-1"></i> Add Tenant
                    </a>
                </div>
                <div class="card-body">
                    <table id="tenants-table" class="table table-bordered dt-responsive nowrap w-100">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Plan</th>
                                <th>Status</th>
                                <th>Trial Left</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Reject Modal --}}
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">
                        <i class="bx bx-x-circle me-2"></i>Reject Tenant
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="rejectForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Reason (optional)</label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="Reason for rejection..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bx bx-x me-1"></i> Reject
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@section('script')
    <script>
        $(document).ready(function() {
            $('#tenants-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('tenants.index') }}',
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'id',
                        name: 'tenants.id'
                    },
                    {
                        data: 'name',
                        name: 'tenants.name'
                    },
                    {
                        data: 'email',
                        name: 'tenants.email'
                    },
                    {
                        data: 'plan_name',
                        name: 'plans.name'
                    },
                    {
                        data: 'onboard_badge',
                        name: 'tenants.onboard_status',
                        orderable: false
                    },
                    {
                        data: 'trial_left',
                        name: 'tenants.trial_ends_at',
                        orderable: false
                    },
                    {
                        data: 'created_at',
                        name: 'tenants.created_at'
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false
                    },
                ]
            });

            // Reject with modal
            $(document).on('submit', '.reject-form', function(e) {
                e.preventDefault();
                var action = $(this).attr('action');
                $('#rejectForm').attr('action', action);
                new bootstrap.Modal(document.getElementById('rejectModal')).show();
            });
        });
    </script>
@endsection
