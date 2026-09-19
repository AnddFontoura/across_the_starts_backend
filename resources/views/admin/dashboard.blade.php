@extends('admin.layout')

@section('title', 'Painel')

@section('content')
    <h1>Configuração do jogo</h1>

    <div class="card">
        <h2>Estruturas</h2>
        <table>
            <thead>
                <tr>
                    <th>Estrutura</th>
                    <th>Categoria</th>
                    <th>Nível máx.</th>
                    <th>Produção base</th>
                    <th>Proteção base</th>
                    <th>Custo base (O / M / E)</th>
                    <th>Constr. (s)</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($structureTypes as $type)
                    <tr>
                        <td>
                            <span class="swatch" style="background: {{ $type->color }}"></span>
                            {{ $type->name }}
                        </td>
                        <td>{{ $type->category === 'storage' ? 'Depósito' : 'Produtor' }}</td>
                        <td>{{ $type->max_level }}</td>
                        <td>{{ $type->production_base }} (x{{ $type->production_growth }})</td>
                        <td>{{ $type->protection_base }} (x{{ $type->protection_growth }})</td>
                        <td>{{ $type->upgrade_cost_gold_base }} / {{ $type->upgrade_cost_metal_base }} / {{ $type->upgrade_cost_energy_base }}</td>
                        <td>{{ $type->build_time }}</td>
                        <td>
                            <a class="btn btn-ghost btn-sm" href="{{ route('admin.structure-types.edit', $type) }}">Editar</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Configurações globais</h2>
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            @method('PUT')
            <div class="grid">
                @foreach ($settings as $setting)
                    <div>
                        <label>{{ $setting->label ?? $setting->key }} <span class="muted">({{ $setting->key }})</span></label>
                        <input name="settings[{{ $setting->id }}]" value="{{ $setting->value }}">
                        @if ($setting->description)
                            <small class="muted">{{ $setting->description }}</small>
                        @endif
                    </div>
                @endforeach
            </div>
            <div style="margin-top: 1rem;">
                <button class="btn" type="submit">Salvar configurações</button>
            </div>
        </form>
    </div>
@endsection
