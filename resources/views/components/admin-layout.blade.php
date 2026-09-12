@extends('admin.layouts.app')

@section('title', __('Super Admin Dashboard'))

@section('content')
    {{ $slot }}
@endsection