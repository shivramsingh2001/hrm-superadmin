@extends('layouts.app')
@section('title', $plan->exists ? 'Edit plan' : 'New plan')

@section('content')
    <div style="max-width:1050px">
        @include('plans._form')
    </div>
@endsection
