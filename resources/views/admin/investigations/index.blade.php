@extends('admin.layout')

@section('title', 'Investigações')

@section('content')
    <p><a href="{{ route('admin.dashboard') }}">&larr; Voltar ao painel</a></p>
    <h1>Investigações interplanetárias</h1>

    <div class="card">
        <p class="muted">
            Batalhas pré-definidas que o jogador inicia pelo Centro de Operações. Cada uma tem frotas
            inimigas fixas, um limite de rounds e prêmios (itens) + experiência para os comandantes.
        </p>
        <p><a class="btn btn-sm" href="{{ route('admin.investigations.create') }}">+ Nova investigação</a></p>

        <table>
            <thead>
                <tr>
                    <th>Investigação</th>
                    <th>Frotas do jogador</th>
                    <th>Rounds máx.</th>
                    <th>Exp</th>
                    <th>Frotas inimigas</th>
                    <th>Prêmios</th>
                    <th>Ativa?</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($investigations as $inv)
                    <tr>
                        <td>
                            <span class="swatch" style="background: {{ $inv->color ?? '#7ec8e3' }}"></span>
                            {{ $inv->name }}
                            <span class="muted">({{ $inv->key }})</span>
                        </td>
                        <td>{{ $inv->min_player_fleets }}–{{ $inv->max_player_fleets }}</td>
                        <td>{{ $inv->max_rounds }}</td>
                        <td>{{ $inv->exp_reward }}</td>
                        <td>{{ $inv->enemy_fleets_count }}</td>
                        <td>{{ $inv->prizes_count }}</td>
                        <td>{{ $inv->is_active ? 'Sim' : 'Não' }}</td>
                        <td><a class="btn btn-ghost btn-sm" href="{{ route('admin.investigations.edit', $inv) }}">Editar</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Nenhuma investigação configurada ainda.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
