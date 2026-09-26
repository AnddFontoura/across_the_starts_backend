@extends('admin.layout')

@section('title', $creating ? 'Nova investigação' : $investigation->name)

@section('content')
    <p><a href="{{ route('admin.investigations.index') }}">&larr; Voltar às investigações</a></p>
    <h1>
        <span class="swatch" style="background: {{ $investigation->color ?? '#7ec8e3' }}"></span>
        {{ $creating ? 'Nova investigação' : $investigation->name }}
    </h1>

    @if ($errors->any())
        <p class="error">{{ $errors->first() }}</p>
    @endif

    {{-- Definition --}}
    <div class="card">
        <h2>Configuração geral</h2>
        <form method="POST"
              action="{{ $creating ? route('admin.investigations.store') : route('admin.investigations.update', $investigation) }}"
              class="grid">
            @csrf
            @unless ($creating) @method('PUT') @endunless

            <div class="grid grid-3">
                <div>
                    <label>Identificador (key)</label>
                    <input name="key" value="{{ old('key', $investigation->key) }}" placeholder="ex.: ruinas_orbitais" required>
                    <small class="muted">Único; minúsculas, números e _.</small>
                </div>
                <div>
                    <label>Nome</label>
                    <input name="name" value="{{ old('name', $investigation->name) }}" required>
                </div>
                <div>
                    <label>Cor</label>
                    <input name="color" value="{{ old('color', $investigation->color) }}">
                </div>
            </div>

            <div>
                <label>Descrição</label>
                <textarea name="description" rows="2">{{ old('description', $investigation->description) }}</textarea>
            </div>

            <div class="grid grid-3">
                <div>
                    <label>Frotas mín. do jogador</label>
                    <input type="number" name="min_player_fleets" value="{{ old('min_player_fleets', $investigation->min_player_fleets) }}" min="1" required>
                </div>
                <div>
                    <label>Frotas máx. do jogador</label>
                    <input type="number" name="max_player_fleets" value="{{ old('max_player_fleets', $investigation->max_player_fleets) }}" min="1" required>
                </div>
                <div>
                    <label>Rounds máximos</label>
                    <input type="number" name="max_rounds" value="{{ old('max_rounds', $investigation->max_rounds) }}" min="1" required>
                </div>
                <div>
                    <label>Largura do mapa</label>
                    <input type="number" name="map_width" value="{{ old('map_width', $investigation->map_width) }}" min="20" required>
                </div>
                <div>
                    <label>Altura do mapa</label>
                    <input type="number" name="map_height" value="{{ old('map_height', $investigation->map_height) }}" min="20" required>
                </div>
                <div>
                    <label>Experiência (por comandante) na vitória</label>
                    <input type="number" name="exp_reward" value="{{ old('exp_reward', $investigation->exp_reward) }}" min="0" required>
                </div>
            </div>

            <div>
                <label><input type="checkbox" name="is_active" value="1" style="width:auto;" @checked(old('is_active', $investigation->is_active))> Ativa (visível para jogadores)</label>
            </div>

            <div>
                <label>Imagem (URL)</label>
                <input name="image_url" value="{{ old('image_url', $investigation->image_url) }}" placeholder="https://... (opcional)">
            </div>

            <div>
                <button class="btn" type="submit">{{ $creating ? 'Criar investigação' : 'Salvar configuração' }}</button>
            </div>
        </form>
    </div>

    @unless ($creating)
        {{-- Reference tables for building the JSON ship loadout --}}
        <div class="card">
            <h2>Referência de IDs</h2>
            <div class="grid grid-2">
                <div>
                    <h3>Aeronaves (aircraft_type_id)</h3>
                    <table>
                        <thead><tr><th>ID</th><th>Nome</th><th>Classe</th></tr></thead>
                        <tbody>
                            @foreach ($aircraftTypes as $ac)
                                <tr><td>{{ $ac->id }}</td><td>{{ $ac->name }}</td><td>{{ $ac->class }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div>
                    <h3>Módulos (module_type_id)</h3>
                    <table>
                        <thead><tr><th>ID</th><th>Nome</th><th>Arma</th></tr></thead>
                        <tbody>
                            @foreach ($moduleTypes as $mod)
                                <tr><td>{{ $mod->id }}</td><td>{{ $mod->name }}</td><td>{{ $mod->attack_type ?? '—' }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Enemy fleets --}}
        <div class="card">
            <h2>Frotas inimigas</h2>
            @if ($investigation->enemyFleets->isEmpty())
                <p class="muted">Nenhuma frota inimiga ainda. Adicione pelo menos uma para a investigação ser jogável.</p>
            @else
                <table>
                    <thead>
                        <tr><th>Nome</th><th>Posição</th><th>Velocidade</th><th>Atq%/Def%</th><th>Naves</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($investigation->enemyFleets as $ef)
                            <tr>
                                <td>{{ $ef->name }}</td>
                                <td>({{ $ef->start_x }}, {{ $ef->start_y }})</td>
                                <td>{{ $ef->commander_velocidade }}</td>
                                <td>{{ $ef->attack_percent }}% / {{ $ef->defense_percent }}%</td>
                                <td>{{ $ef->slots->sum('quantity') }} em {{ $ef->slots->count() }} pilha(s)</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.investigations.enemy-fleets.delete', [$investigation, $ef]) }}" class="inline" onsubmit="return confirm('Remover esta frota inimiga?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-danger btn-sm" type="submit">Remover</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <h3 style="margin-top:1rem;">Adicionar frota inimiga</h3>
            <form method="POST" action="{{ route('admin.investigations.enemy-fleets.save', $investigation) }}" class="grid">
                @csrf
                <div class="grid grid-3">
                    <div>
                        <label>Nome</label>
                        <input name="name" value="{{ old('name') }}" required>
                    </div>
                    <div>
                        <label>Início X</label>
                        <input type="number" name="start_x" value="{{ old('start_x', 100) }}" min="0" required>
                    </div>
                    <div>
                        <label>Início Y</label>
                        <input type="number" name="start_y" value="{{ old('start_y', 0) }}" min="0" required>
                    </div>
                    <div>
                        <label>Velocidade (iniciativa)</label>
                        <input type="number" name="commander_velocidade" value="{{ old('commander_velocidade', 10) }}" min="0" required>
                    </div>
                    <div>
                        <label>Bônus de ataque (%)</label>
                        <input type="number" name="attack_percent" value="{{ old('attack_percent', 0) }}" required>
                    </div>
                    <div>
                        <label>Bônus de defesa (%)</label>
                        <input type="number" name="defense_percent" value="{{ old('defense_percent', 0) }}" required>
                    </div>
                </div>
                <div>
                    <label>Composição (JSON)</label>
                    <textarea name="ships" rows="6" required>{{ old('ships', '[
  {"aircraft_type_id": 1, "name": "Sentinela", "quantity": 10, "modules": [{"module_type_id": 1, "quantity": 2}]}
]') }}</textarea>
                    <small class="muted">Array de naves: aircraft_type_id, quantity, name (opcional), modules [{module_type_id, quantity}].</small>
                </div>
                <div><button class="btn" type="submit">Salvar frota inimiga</button></div>
            </form>
        </div>

        {{-- Prizes --}}
        <div class="card">
            <h2>Prêmios (itens)</h2>
            @if ($investigation->prizes->isEmpty())
                <p class="muted">Nenhum prêmio configurado. Os prêmios são itens; um deles pode ser a caixa de recursos aleatória.</p>
            @else
                <table>
                    <thead><tr><th>Item</th><th>Quantidade</th><th>Chance</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($investigation->prizes as $prize)
                            <tr>
                                <td>{{ $prize->item?->name ?? '—' }}</td>
                                <td>{{ $prize->quantity }}</td>
                                <td>{{ $prize->chance }}%</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.investigations.prizes.delete', [$investigation, $prize]) }}" class="inline" onsubmit="return confirm('Remover este prêmio?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-danger btn-sm" type="submit">Remover</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <h3 style="margin-top:1rem;">Adicionar prêmio</h3>
            <form method="POST" action="{{ route('admin.investigations.prizes.save', $investigation) }}" class="grid grid-3">
                @csrf
                <div>
                    <label>Item</label>
                    <select name="item_id" required>
                        @foreach ($items as $item)
                            <option value="{{ $item->id }}">{{ $item->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Quantidade</label>
                    <input type="number" name="quantity" value="1" min="1" required>
                </div>
                <div>
                    <label>Chance (%)</label>
                    <input type="number" name="chance" value="100" min="1" max="100" required>
                </div>
                <div><button class="btn" type="submit">Adicionar prêmio</button></div>
            </form>
        </div>

        <div class="card">
            <form method="POST" action="{{ route('admin.investigations.destroy', $investigation) }}"
                  onsubmit="return confirm('Remover esta investigação e tudo dela?');">
                @csrf @method('DELETE')
                <button class="btn btn-danger" type="submit">Remover investigação</button>
            </form>
        </div>
    @endunless
@endsection
