@extends('layouts.app')
@section('title', $user->exists ? 'Edit user' : 'New panel user')

@section('content')
    <div style="max-width:700px">
        @include('superadmin-users._form')
    </div>
@endsection
