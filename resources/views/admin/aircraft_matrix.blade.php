@extends('admin.layout')

@section('title', 'Matriz de contra-tipos')

@section('content')
    <p><a href="{{ route('admin.dashboard') }}">&larr; Voltar</a></p>
    <h1>Matriz de contra-tipos das aeronaves</h1>

    @if ($errors->any())
        <p class="error">{{ $errors->first() }}</p>
    @endif

    <div class="card">
        <p class="muted">
            Para cada par <strong>Atacante → Defensor</strong>: <strong>Bônus</strong> é o % extra de dano que o atacante
            causa ao defensor; <strong>Redução</strong> é o % de dano que o defensor mitiga daquele atacante.
            As linhas são o atacante, as colunas o defensor.
        </p>

        <form method="POST" action="{{ route('admin.aircraft-matrix.update') }}">
            @csrf
            @method('PUT')

            <table>
                <thead>
                    <tr>
                        <th>Atacante \ Defensor</th>
                        @foreach ($classes as $def)
                            <th>{{ $labels[$def] ?? $def }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($classes as $atk)
                        <tr>
                            <th>{{ $labels[$atk] ?? $atk }}</th>
                            @foreach ($classes as $def)
                                @php $m = $matchups->get($atk.':'.$def); @endphp
                                <td>
                                    <label style="margin:0 0 .1rem;">Bônus %</label>
                                    <input type="number" name="bonus[{{ $atk }}][{{ $def }}]"
                                           value="{{ old("bonus.$atk.$def", $m->damage_bonus_percent ?? 0) }}">
                                    <label style="margin:.3rem 0 .1rem;">Redução %</label>
                                    <input type="number" name="reduction[{{ $atk }}][{{ $def }}]"
                                           value="{{ old("reduction.$atk.$def", $m->damage_reduction_percent ?? 0) }}">
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="muted" style="margin-top:.6rem;">A matriz é entre <strong>classes</strong> de nave. Todas as naves de uma classe compartilham estes valores.</p>

            <div style="margin-top:1rem;">
                <button class="btn" type="submit">Salvar matriz</button>
            </div>
        </form>
    </div>
@endsection
