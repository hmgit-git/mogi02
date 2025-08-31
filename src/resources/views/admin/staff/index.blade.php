@extends('layouts.admin')
@section('title','スタッフ一覧')
@section('content')
<table>
    <thead>
        <tr>
            <th>名前</th>
            <th>メール</th>
            <th>操作</th>
        </tr>
    </thead>
    <tbody>
        @foreach($users as $u)
        <tr>
            <td>{{ $u->name }}</td>
            <td>{{ $u->email }}</td>
            <td><a href="{{ route('admin.staff.show', $u) }}">詳細</a></td>
        </tr>
        @endforeach
    </tbody>
</table>
@endsection