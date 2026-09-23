@extends('layouts.app')
@section('title', 'Create tenant')

@section('content')
    <div style="max-width:900px">
        @include('tenants._form')
    </div>
@endsection
