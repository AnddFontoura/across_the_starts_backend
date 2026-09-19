@extends('admin.layout')

@section('title', 'Entrar')

@section('content')
    <div class="card" style="max-width: 380px; margin: 3rem auto;">
        <h1>Entrar no painel</h1>

        @if ($errors->any())
            <p class="error">{{ $errors->first() }}</p>
        @endif

        <form method="POST" action="{{ route('admin.login.submit') }}" class="grid">
            @csrf
            <div>
                <label>E-mail</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>
            <div>
                <label>Senha</label>
                <input type="password" name="password" required>
            </div>
            <button class="btn" type="submit">Entrar</button>
        </form>
    </div>
@endsection
