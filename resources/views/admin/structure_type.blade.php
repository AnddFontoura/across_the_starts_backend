@extends('admin.layout')

@section('title', $type->name)

@section('content')
    <p><a href="{{ route('admin.dashboard') }}">&larr; Voltar</a></p>
    <h1>
        <span class="swatch" style="background: {{ $type->color }}"></span>
        {{ $type->name }}
        <span class="muted" style="font-size: .9rem;">({{ $type->category }})</span>
    </h1>

    @if ($errors->any())
        <p class="error">{{ $errors->first() }}</p>
    @endif

    <div class="card">
        <h2>Configuração base (fórmula)</h2>
        <p class="muted">valor(nível) = base × crescimento^(nível − 1). O custo pode usar 1, 2 ou os 3 recursos.</p>

        <form method="POST" action="{{ route('admin.structure-types.update', $type) }}" class="grid">
            @csrf
            @method('PUT')

            <div class="grid grid-3">
                <div>
                    <label>Nome</label>
                    <input name="name" value="{{ old('name', $type->name) }}" required>
                </div>
                <div>
                    <label>Categoria</label>
                    <select name="category">
                        @foreach (['producer' => 'Produtor', 'storage' => 'Depósito (armazena/protege)'] as $val => $lbl)
                            <option value="{{ $val }}" @selected($type->category === $val)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Recurso produzido (produtores)</label>
                    <select name="resource">
                        @foreach (['gold' => 'Ouro', 'metal' => 'Metal', 'energy' => 'Energia'] as $val => $lbl)
                            <option value="{{ $val }}" @selected($type->resource === $val)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label>Descrição</label>
                <textarea name="description" rows="2">{{ old('description', $type->description) }}</textarea>
            </div>

            <div class="grid grid-3">
                <div>
                    <label>Cor</label>
                    <input name="color" value="{{ old('color', $type->color) }}">
                </div>
                <div>
                    <label>Largura</label>
                    <input type="number" name="width" value="{{ old('width', $type->width) }}">
                </div>
                <div>
                    <label>Altura</label>
                    <input type="number" name="height" value="{{ old('height', $type->height) }}">
                </div>
            </div>

            <div class="grid grid-3">
                <div>
                    <label>Nível máximo</label>
                    <input type="number" name="max_level" value="{{ old('max_level', $type->max_level) }}">
                </div>
            </div>

            <h3 style="margin:.5rem 0 0;">Produção (produtores)</h3>
            <div class="grid grid-2">
                <div>
                    <label>Produção base (nível 1)</label>
                    <input type="number" name="production_base" value="{{ old('production_base', $type->production_base) }}">
                </div>
                <div>
                    <label>Crescimento produção</label>
                    <input type="number" step="0.001" name="production_growth" value="{{ old('production_growth', $type->production_growth) }}">
                </div>
                <div>
                    <label>Capacidade base (nível 1)</label>
                    <input type="number" name="capacity_base" value="{{ old('capacity_base', $type->capacity_base) }}">
                </div>
                <div>
                    <label>Crescimento capacidade</label>
                    <input type="number" step="0.001" name="capacity_growth" value="{{ old('capacity_growth', $type->capacity_growth) }}">
                </div>
            </div>

            <h3 style="margin:.5rem 0 0;">Proteção (depósitos)</h3>
            <div class="grid grid-2">
                <div>
                    <label>Proteção base por recurso (nível 1)</label>
                    <input type="number" name="protection_base" value="{{ old('protection_base', $type->protection_base) }}">
                </div>
                <div>
                    <label>Crescimento proteção</label>
                    <input type="number" step="0.001" name="protection_growth" value="{{ old('protection_growth', $type->protection_growth) }}">
                </div>
            </div>

            <h3 style="margin:.5rem 0 0;">Custo de upgrade por recurso</h3>
            <div class="grid grid-2">
                <div>
                    <label>Ouro — base</label>
                    <input type="number" name="upgrade_cost_gold_base" value="{{ old('upgrade_cost_gold_base', $type->upgrade_cost_gold_base) }}">
                </div>
                <div>
                    <label>Ouro — crescimento</label>
                    <input type="number" step="0.001" name="upgrade_cost_gold_growth" value="{{ old('upgrade_cost_gold_growth', $type->upgrade_cost_gold_growth) }}">
                </div>
                <div>
                    <label>Metal — base</label>
                    <input type="number" name="upgrade_cost_metal_base" value="{{ old('upgrade_cost_metal_base', $type->upgrade_cost_metal_base) }}">
                </div>
                <div>
                    <label>Metal — crescimento</label>
                    <input type="number" step="0.001" name="upgrade_cost_metal_growth" value="{{ old('upgrade_cost_metal_growth', $type->upgrade_cost_metal_growth) }}">
                </div>
                <div>
                    <label>Energia — base</label>
                    <input type="number" name="upgrade_cost_energy_base" value="{{ old('upgrade_cost_energy_base', $type->upgrade_cost_energy_base) }}">
                </div>
                <div>
                    <label>Energia — crescimento</label>
                    <input type="number" step="0.001" name="upgrade_cost_energy_growth" value="{{ old('upgrade_cost_energy_growth', $type->upgrade_cost_energy_growth) }}">
                </div>
            </div>

            <h3 style="margin:.5rem 0 0;">Tempos (em segundos)</h3>
            <div class="grid grid-3">
                <div>
                    <label>Tempo de construção</label>
                    <input type="number" name="build_time" value="{{ old('build_time', $type->build_time) }}">
                </div>
                <div>
                    <label>Tempo de upgrade base (nível 2)</label>
                    <input type="number" name="upgrade_time_base" value="{{ old('upgrade_time_base', $type->upgrade_time_base) }}">
                </div>
                <div>
                    <label>Crescimento do tempo</label>
                    <input type="number" step="0.001" name="upgrade_time_growth" value="{{ old('upgrade_time_growth', $type->upgrade_time_growth) }}">
                </div>
            </div>

            <div>
                <button class="btn" type="submit">Salvar fórmula</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Adicionar / editar override de nível</h2>
        <p class="muted">Deixe um campo vazio para usar o valor da fórmula naquele nível.</p>
        <form method="POST" action="{{ route('admin.structure-types.overrides.save', $type) }}" class="grid grid-2">
            @csrf
            <div>
                <label>Nível (1–{{ $type->max_level }})</label>
                <input type="number" name="level" min="1" max="{{ $type->max_level }}" required>
            </div>
            <div>
                <label>Produção/hora (opcional)</label>
                <input type="number" name="production_per_hour" min="0">
            </div>
            <div>
                <label>Capacidade máx. (opcional)</label>
                <input type="number" name="max_capacity" min="0">
            </div>
            <div>
                <label>Proteção por recurso (opcional)</label>
                <input type="number" name="protection" min="0">
            </div>
            <div>
                <label>Custo ouro (opcional)</label>
                <input type="number" name="upgrade_cost_gold" min="0">
            </div>
            <div>
                <label>Custo metal (opcional)</label>
                <input type="number" name="upgrade_cost_metal" min="0">
            </div>
            <div>
                <label>Custo energia (opcional)</label>
                <input type="number" name="upgrade_cost_energy" min="0">
            </div>
            <div>
                <label>Tempo de upgrade em seg. (opcional)</label>
                <input type="number" name="upgrade_time" min="0">
            </div>
            <div style="grid-column: 1 / -1;">
                <button class="btn" type="submit">Salvar override</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Prévia por nível</h2>
        <table>
            <thead>
                <tr>
                    <th>Nível</th>
                    <th>Produção/h</th>
                    <th>Capacidade</th>
                    <th>Proteção</th>
                    <th>Custo (O / M / E)</th>
                    <th>Tempo upg (s)</th>
                    <th>Override?</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($preview as $row)
                    <tr>
                        <td>{{ $row['level'] }}</td>
                        <td>{{ $row['production'] }}</td>
                        <td>{{ $row['capacity'] }}</td>
                        <td>{{ $row['protection'] }}</td>
                        <td>
                            @if ($row['upgrade_cost'])
                                {{ $row['upgrade_cost']['gold'] }} / {{ $row['upgrade_cost']['metal'] }} / {{ $row['upgrade_cost']['energy'] }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $row['upgrade_time'] ?? '—' }}</td>
                        <td>
                            @if ($row['override'])
                                <span style="color:#f4c542;">sim</span>
                            @else
                                <span class="muted">fórmula</span>
                            @endif
                        </td>
                        <td>
                            @if ($row['override'])
                                <form method="POST" action="{{ route('admin.structure-types.overrides.delete', [$type, $row['override']]) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-danger btn-sm" type="submit">Remover</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
