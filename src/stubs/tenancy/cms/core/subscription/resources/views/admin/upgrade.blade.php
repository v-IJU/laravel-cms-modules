@extends('layouts.master')

@section('title', 'Upgrade Plan')
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
            Upgrade Plan
        @endslot
    @endcomponent

    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="alert alert-warning d-flex align-items-center mb-4">
                <i class="bx bx-lock fs-2 me-3"></i>
                <div>
                    <h5 class="mb-1">Module Not Available</h5>
                    <p class="mb-0">{{ session('error') ?? 'This module is not included in your current plan.' }}</p>
                </div>
            </div>

            <h4 class="mb-4 text-center">Upgrade Your Plan</h4>

            <div class="row">
                @foreach ($plans as $planOption)
                    <div class="col-md-4 mb-4">
                        <div class="card border h-100 {{ $plan && $plan->id == $planOption->id ? 'border-success' : '' }}">
                            <div class="card-body text-center p-4">
                                @if ($plan && $plan->id == $planOption->id)
                                    <span class="badge bg-success mb-2">Current Plan</span>
                                @endif
                                <h5 class="mb-1">{{ $planOption->name }}</h5>
                                <h3 class="text-primary mb-1">
                                    ${{ $planOption->price }}
                                    <small class="fs-14 text-muted">/{{ $planOption->billing_cycle }}</small>
                                </h3>
                                <p class="text-muted small mb-3">{{ $planOption->description }}</p>
                                <ul class="list-unstyled text-start small mb-4">
                                    <li class="mb-1">
                                        <i class="bx bx-check text-success me-1"></i>
                                        {{ $planOption->max_users == -1 ? 'Unlimited' : $planOption->max_users }} Users
                                    </li>
                                    <li class="mb-1">
                                        <i class="bx bx-check text-success me-1"></i>
                                        {{ $planOption->max_modules == -1 ? 'All' : $planOption->max_modules }} Modules
                                    </li>
                                    @if ($planOption->trial_days > 0)
                                        <li class="mb-1">
                                            <i class="bx bx-check text-success me-1"></i>
                                            {{ $planOption->trial_days }} days trial
                                        </li>
                                    @endif
                                </ul>
                                @if (!$plan || $plan->id != $planOption->id)
                                    <a href="mailto:support@yoursaas.com?subject=Upgrade to {{ $planOption->name }}"
                                        class="btn btn-primary btn-sm w-100">
                                        Upgrade to {{ $planOption->name }}
                                    </a>
                                @else
                                    <span class="btn btn-outline-success btn-sm w-100 disabled">
                                        Current Plan
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="text-center mt-3">
                <a href="{{ route('backenddashboard') }}" class="btn btn-secondary">
                    <i class="bx bx-arrow-back me-1"></i> Back to Dashboard
                </a>
            </div>

        </div>
    </div>
@endsection
