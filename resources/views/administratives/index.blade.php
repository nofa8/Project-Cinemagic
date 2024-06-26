@extends('layouts.main')

@section('header-title', 'List of Administratives & Employees')

@section('main')
@php
$trash = $tr ?? '' ==="trash" ? true :false
@endphp
    <div class="flex justify-center">
        <div class="my-4 p-6 bg-white dark:bg-gray-900 overflow-hidden
                    shadow-sm sm:rounded-lg text-gray-900 dark:text-gray-50">
            <x-administratives.filter-card
                :filterAction="route('administratives.index')"
                :resetUrl="route('administratives.index')"
                :name="old('name', $filterByName)"
                class="mb-6"
                />
            @if(!$trash)
                <div class="flex items-center gap-4 mb-4">
                    <x-button
                        href="{{ route('administratives.create') }}"
                        text="Create a new administrative or employee"
                        type="success"/>
                </div>
            @endif
            <div class="font-base text-sm text-gray-700 dark:text-gray-300">
                <x-administratives.table :administratives="$administratives"
                    :showView="true"
                    :showEdit="true"
                    :showDelete="true"
                    :trash="$trash"
                    />
            </div>
            <div class="mt-4">
                {{ $administratives->links() }}
            </div>
        </div>
    </div>
@endsection
