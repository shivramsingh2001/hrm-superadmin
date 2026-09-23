@extends('layouts.app')
@section('title', 'New feature template')

@section('content')
    <div style="max-width:700px">
        @include('feature-templates._form')
    </div>
@endsection
