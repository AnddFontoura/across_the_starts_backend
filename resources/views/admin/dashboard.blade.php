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
                    <th>Escopo</th>
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
                        <td>{{ ['producer' => 'Produtor', 'storage' => 'Depósito', 'command' => 'Comando', 'defense' => 'Defesa', 'support' => 'Apoio'][$type->category] ?? $type->category }}</td>
                        <td>{{ ['terrestrial' => 'Terrestre', 'planetary' => 'Planetário'][$type->scope] ?? $type->scope }}</td>
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
        <h2>Aeronaves</h2>
        <p class="muted">
            Naves da conta (sem nível), agrupadas por classe. O dano vem de módulos (futuro). O tempo de construção é reduzido pelo Hangar.
            <a href="{{ route('admin.aircraft-matrix.edit') }}">Editar matriz de contra-tipos →</a>
        </p>
        <p><a class="btn btn-sm" href="{{ route('admin.aircraft-types.create') }}">+ Nova aeronave</a></p>
        <table>
            <thead>
                <tr>
                    <th>Aeronave</th>
                    <th>Classe</th>
                    <th>Custo (O / M / E)</th>
                    <th>Constr. (s)</th>
                    <th>Escudo</th>
                    <th>Estrutura</th>
                    <th>Movimento</th>
                    <th>Armazenamento</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($aircraftTypes as $ac)
                    <tr>
                        <td>
                            <span class="swatch" style="background: {{ $ac->color }}"></span>
                            {{ $ac->name }}
                        </td>
                        <td>{{ ['cruiser'=>'Cruzador','battleship'=>'Encouraçado','frigate'=>'Fragata','fighter'=>'Caça'][$ac->class] ?? $ac->class }}</td>
                        <td>{{ $ac->cost_gold }} / {{ $ac->cost_metal }} / {{ $ac->cost_energy }}</td>
                        <td>{{ $ac->build_time }}</td>
                        <td>{{ $ac->shield }}</td>
                        <td>{{ $ac->hull }}</td>
                        <td>{{ $ac->movement }}</td>
                        <td>{{ $ac->storage }}</td>
                        <td>
                            <a class="btn btn-ghost btn-sm" href="{{ route('admin.aircraft-types.edit', $ac) }}">Editar</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Módulos de nave</h2>
        <p class="muted">Módulos instaláveis nas naves. Qualquer atributo pode ser 0. Armas têm tipo (metralhadora/laser/míssil) e alcance.</p>
        <p><a class="btn btn-sm" href="{{ route('admin.module-types.create') }}">+ Novo módulo</a></p>
        <table>
            <thead>
                <tr>
                    <th>Módulo</th>
                    <th>Espaço</th>
                    <th>Ataque (tipo)</th>
                    <th>Alcance</th>
                    <th>Estrutura</th>
                    <th>Escudo</th>
                    <th>Movimento</th>
                    <th>+Tempo (s)</th>
                    <th>Custo (O / M / E)</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($moduleTypes as $mod)
                    <tr>
                        <td>
                            <span class="swatch" style="background: {{ $mod->color }}"></span>
                            {{ $mod->name }}
                        </td>
                        <td>{{ $mod->space }}</td>
                        <td>{{ $mod->attack }}<span class="muted">{{ $mod->attack_type ? ' ('.$mod->attack_type.')' : '' }}</span></td>
                        <td>{{ $mod->range }}</td>
                        <td>{{ $mod->hull }}</td>
                        <td>{{ $mod->shield }}</td>
                        <td>{{ $mod->movement }}</td>
                        <td>{{ $mod->build_time_add }}</td>
                        <td>{{ $mod->cost_gold }} / {{ $mod->cost_metal }} / {{ $mod->cost_energy }}</td>
                        <td>
                            <a class="btn btn-ghost btn-sm" href="{{ route('admin.module-types.edit', $mod) }}">Editar</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Comandantes</h2>
        <p class="muted">
            Definições de comandante e bônus por ranking.
            <a href="{{ route('admin.ranking-bonuses.edit') }}">Editar bônus de ranking →</a>
        </p>
        <table>
            <thead>
                <tr>
                    <th>Definição</th>
                    <th>Recrutável?</th>
                    <th>Ranking máx.</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($commanderDefinitions as $def)
                    <tr>
                        <td>
                            <span class="swatch" style="background: {{ $def->color }}"></span>
                            {{ $def->name }}
                        </td>
                        <td>{{ $def->is_recruitable ? 'Sim' : 'Não' }}</td>
                        <td>{{ $def->max_rank }}</td>
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
