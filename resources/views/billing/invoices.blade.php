@extends('layouts.billing')

@section('title', 'تاریخچه‌ی پرداخت‌ها')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-lg font-bold text-gray-800">تاریخچه‌ی پرداخت‌ها</h1>
        <a href="{{ route('billing.index') }}" class="text-sm text-blue-600 hover:underline">بازگشت</a>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl p-6">
        @include('billing._payments_table', ['payments' => $payments])
        <div class="mt-4">{{ $payments->links() }}</div>
    </div>
@endsection
