@extends('admin.layout')

@section('title', $creating ? 'Nova pesquisa' : $def->name)

@section('content')
    <p><a href="{{ route('admin.dashboard') }}">&larr; Voltar</a></p>
    <h1>
        <span class="swatch" style="background: {{ $def->color ?? '#7ec8e3' }}"></span>
        {{ $creating ? 'Nova pesquisa' : $def->name }}
        <span class="muted" style="font-size: .9rem;">(pesquisa)</span>
    </h1>

    @if ($errors->any())
        <p class="error">{{ $errors->first() }}</p>
    @endif

    <div class="card">
        <h2>Definição</h2>
        <p class="muted">
            Tecnologias podem ter vários níveis e dependências. Plantas são sempre nível único,
            exigem uma planta (item) no inventário e podem consumir itens extras.
        </p>

        <form method="POST"
              action="{{ $creating ? route('admin.research-definitions.store') : route('admin.research-definitions.update', $def) }}"
              class="grid">
            @csrf
            @unless ($creating) @method('PUT') @endunless

            <div class="grid grid-3">
                <div>
                    <label>Identificador (key)</label>
                    <input name="key" value="{{ old('key', $def->key) }}" placeholder="ex.: gold_collection" required>
                    <small class="muted">Único; só letras minúsculas, números e _.</small>
                </div>
                <div>
                    <label>Nome</label>
                    <input name="name" value="{{ old('name', $def->name) }}" required>
                </div>
                <div>
                    <label>Cor</label>
                    <input name="color" value="{{ old('color', $def->color) }}" placeholder="#7ec8e3">
                </div>
            </div>

            <div class="grid grid-3">
                <div>
                    <label>Tipo</label>
                    <select name="type">
                        @foreach (['technology' => 'Tecnologia', 'plant' => 'Planta'] as $val => $lbl)
                            <option value="{{ $val }}" @selected(old('type', $def->type) === $val)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Área (só tecnologia)</label>
                    <select name="area">
                        <option value="" @selected(old('area', $def->area) === null)>—</option>
                        @foreach (\App\Models\ResearchDefinition::AREA_LABELS as $val => $lbl)
                            <option value="{{ $val }}" @selected(old('area', $def->area) === $val)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Nível máximo (plantas = 1)</label>
                    <input type="number" name="max_level" value="{{ old('max_level', $def->max_level) }}" min="1" max="100">
                </div>
            </div>

            <div>
                <label>Descrição</label>
                <textarea name="description" rows="2">{{ old('description', $def->description) }}</textarea>
            </div>

            <div class="grid grid-2" style="align-items:end;">
                <div>
                    <label>Imagem (URL)</label>
                    <input name="image_url" value="{{ old('image_url', $def->image_url) }}" placeholder="https://... (opcional)">
                    <small class="muted">
                        Uma imagem por pesquisa, usada em todos os níveis. Tecnologias recebem um brilho
                        dourado por cima que aumenta conforme o nível (efeito visual no jogo). Para plantas,
                        use a mesma imagem do item da nave. Vazio = placeholder 🖼️.
                    </small>
                </div>
                <div>
                    <label>Prévia</label>
                    @if ($def->image_url)
                        <img src="{{ $def->image_url }}" alt="{{ $def->name }}" style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid rgba(120,160,220,.3);">
                    @else
                        <div style="width:64px;height:64px;display:grid;place-items:center;font-size:1.8rem;border-radius:8px;border:1px dashed rgba(120,160,220,.4);background:#0e1420;">🖼️</div>
                    @endif
                </div>
            </div>

            <h3 style="margin:.5rem 0 0;">Custo e tempo</h3>
            <div class="grid grid-2">
                <div>
                    <label>Custo em ouro (nível 1)</label>
                    <input type="number" name="gold_cost" value="{{ old('gold_cost', $def->gold_cost) }}" min="0">
                </div>
                <div>
                    <label>Crescimento do custo (1 = fixo)</label>
                    <input type="number" step="0.001" name="gold_cost_growth" value="{{ old('gold_cost_growth', $def->gold_cost_growth) }}" min="1" max="10">
                </div>
                <div>
                    <label>Tempo base (s, nível 1)</label>
                    <input type="number" name="research_time" value="{{ old('research_time', $def->research_time) }}" min="1">
                </div>
                <div>
                    <label>Crescimento do tempo (1 = fixo)</label>
                    <input type="number" step="0.001" name="research_time_growth" value="{{ old('research_time_growth', $def->research_time_growth) }}" min="1" max="10">
                </div>
            </div>

            <h3 style="margin:.5rem 0 0;">Requisitos de planta (só tipo Planta)</h3>
            <div class="grid grid-2" style="align-items:end;">
                <div>
                    <label>Item requerido (key da planta)</label>
                    <input name="required_item_key" value="{{ old('required_item_key', $def->required_item_key) }}" placeholder="ex.: blueprint_ion_thruster">
                    <small class="muted">A planta precisa estar no inventário. Deixe vazio para tecnologias.</small>
                </div>
                <div>
                    <label><input type="checkbox" name="consumes_required_item" value="1" @checked(old('consumes_required_item', $def->consumes_required_item))> Consumir a planta ao pesquisar</label>
                </div>
            </div>

            <h3 style="margin:.5rem 0 0;">Efeitos (JSON)</h3>
            <div>
                <label>Lista de efeitos aplicados por nível concluído</label>
                <textarea name="effects" rows="6" placeholder='[{"type":"resource_production","resource":"gold","percent_per_level":5}]'>{{ old('effects', ! empty($def->effects) ? json_encode($def->effects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '') }}</textarea>
                <small class="muted">
                    Uma lista JSON. Tipos suportados:
                    <code>resource_production</code> (resource: gold/metal/energy/any, percent_per_level),
                    <code>warehouse_protection</code> (flat_per_level),
                    <code>weapon_damage</code> (weapon: machinegun/laser/missile/any, percent_per_level),
                    <code>aircraft_class_attack</code> (class: cruiser/battleship/frigate/fighter/any, percent_per_level).
                </small>
            </div>

            <h3 style="margin:.5rem 0 0;">Dependências (tecnologias)</h3>
            <p class="muted">Cada dependência exige outra pesquisa em um nível mínimo. Deixe vazio para nenhuma.</p>
            @php
                $currentDeps = old('dep_id')
                    ? collect(old('dep_id'))->map(fn ($id, $i) => ['id' => $id, 'level' => old('dep_level')[$i] ?? 1])
                    : $def->dependencies->map(fn ($d) => ['id' => $d->id, 'level' => $d->pivot->min_level]);
                // Always render a few blank rows so admins can add more.
                $depRows = max(4, $currentDeps->count() + 2);
            @endphp
            <table>
                <thead><tr><th>Pesquisa</th><th>Nível mínimo</th></tr></thead>
                <tbody>
                    @for ($i = 0; $i < $depRows; $i++)
                        @php $row = $currentDeps->values()->get($i); @endphp
                        <tr>
                            <td>
                                <select name="dep_id[]">
                                    <option value="">—</option>
                                    @foreach ($allDefinitions as $other)
                                        <option value="{{ $other->id }}" @selected($row && (int) $row['id'] === $other->id)>{{ $other->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="number" name="dep_level[]" value="{{ $row['level'] ?? 1 }}" min="1" style="width:90px;"></td>
                        </tr>
                    @endfor
                </tbody>
            </table>

            <h3 style="margin:.5rem 0 0;">Itens consumidos ao pesquisar</h3>
            <p class="muted">Itens do inventário consumidos (além da planta requerida, se marcada). Use a key do item.</p>
            @php
                $currentItems = old('item_key')
                    ? collect(old('item_key'))->map(fn ($k, $i) => ['key' => $k, 'qty' => old('item_qty')[$i] ?? 1])
                    : $def->requiredItems->map(fn ($r) => ['key' => $r->item_key, 'qty' => $r->quantity]);
                $itemRows = max(3, $currentItems->count() + 2);
            @endphp
            <table>
                <thead><tr><th>Item (key)</th><th>Quantidade</th></tr></thead>
                <tbody>
                    @for ($i = 0; $i < $itemRows; $i++)
                        @php $row = $currentItems->values()->get($i); @endphp
                        <tr>
                            <td><input name="item_key[]" value="{{ $row['key'] ?? '' }}" placeholder="ex.: rare_alloy"></td>
                            <td><input type="number" name="item_qty[]" value="{{ $row['qty'] ?? 1 }}" min="1" style="width:90px;"></td>
                        </tr>
                    @endfor
                </tbody>
            </table>

            <div>
                <button class="btn" type="submit">{{ $creating ? 'Criar pesquisa' : 'Salvar pesquisa' }}</button>
            </div>
        </form>
    </div>

    @unless ($creating)
        <div class="card">
            <form method="POST" action="{{ route('admin.research-definitions.destroy', $def) }}"
                  onsubmit="return confirm('Remover esta pesquisa? O progresso dos jogadores nela também será removido.');">
                @csrf
                @method('DELETE')
                <button class="btn btn-danger" type="submit">Remover pesquisa</button>
            </form>
        </div>
    @endunless
@endsection
