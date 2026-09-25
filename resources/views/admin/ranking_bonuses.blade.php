@extends('admin.layout')

@section('title', 'Bônus de ranking')

@section('content')
    <p><a href="{{ route('admin.dashboard') }}">&larr; Voltar</a></p>
    <h1>Bônus de ranking dos comandantes</h1>

    @if ($errors->any())
        <p class="error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('admin.ranking-bonuses.update') }}">
        @csrf
        @method('PUT')

        <div class="card">
            <h2>Proficiência (I–V)</h2>
            <p class="muted">Aplicada por classe de nave e por tipo de arma da qual o comandante tem proficiência. Ataque e defesa (estrutura + escudo) em %.</p>
            <table>
                <thead>
                    <tr><th>Nível</th><th>Bônus de ataque %</th><th>Bônus de defesa %</th></tr>
                </thead>
                <tbody>
                    @foreach ($proficiencyLevels as $lvl)
                        @php $b = $bonuses->get('proficiency:'.$lvl); @endphp
                        <tr>
                            <th>{{ $roman[$lvl] ?? $lvl }}</th>
                            <td><input type="number" name="attack[proficiency][{{ $lvl }}]" value="{{ old("attack.proficiency.$lvl", $b->attack_percent ?? 0) }}"></td>
                            <td><input type="number" name="defense[proficiency][{{ $lvl }}]" value="{{ old("defense.proficiency.$lvl", $b->defense_percent ?? 0) }}"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>Ranking do comandante (I–X)</h2>
            <p class="muted">Bônus geral do comandante conforme seu ranking. (Efeito a definir; comece com 0.)</p>
            <table>
                <thead>
                    <tr><th>Ranking</th><th>Bônus de ataque %</th><th>Bônus de defesa %</th></tr>
                </thead>
                <tbody>
                    @foreach ($commanderLevels as $lvl)
                        @php $b = $bonuses->get('commander:'.$lvl); @endphp
                        <tr>
                            <th>{{ $roman[$lvl] ?? $lvl }}</th>
                            <td><input type="number" name="attack[commander][{{ $lvl }}]" value="{{ old("attack.commander.$lvl", $b->attack_percent ?? 0) }}"></td>
                            <td><input type="number" name="defense[commander][{{ $lvl }}]" value="{{ old("defense.commander.$lvl", $b->defense_percent ?? 0) }}"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="margin-top:1rem;">
                <button class="btn" type="submit">Salvar bônus</button>
            </div>
        </div>
    </form>
@endsection
