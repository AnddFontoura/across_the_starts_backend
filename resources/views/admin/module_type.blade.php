@extends('admin.layout')

@section('title', $creating ? 'Novo módulo' : $module->name)

@section('content')
    <p><a href="{{ route('admin.dashboard') }}">&larr; Voltar</a></p>
    <h1>
        <span class="swatch" style="background: {{ $module->color }}"></span>
        {{ $creating ? 'Novo módulo' : $module->name }}
        <span class="muted" style="font-size: .9rem;">(módulo)</span>
    </h1>

    @if ($errors->any())
        <p class="error">{{ $errors->first() }}</p>
    @endif

    <div class="card">
        <h2>Atributos do módulo</h2>
        <p class="muted">Qualquer atributo pode ser 0. O tipo de arma só vale quando o ataque &gt; 0.</p>

        <form method="POST" action="{{ $creating ? route('admin.module-types.store') : route('admin.module-types.update', $module) }}" class="grid">
            @csrf
            @unless ($creating) @method('PUT') @endunless

            <div class="grid grid-3">
                <div>
                    <label>Identificador (key)</label>
                    <input name="key" value="{{ old('key', $module->key) }}" placeholder="ex.: machinegun_3" required>
                    <small class="muted">Único; só letras minúsculas, números e _.</small>
                </div>
                <div>
                    <label>Nome</label>
                    <input name="name" value="{{ old('name', $module->name) }}" required>
                </div>
                <div>
                    <label>Cor</label>
                    <input name="color" value="{{ old('color', $module->color) }}">
                </div>
            </div>

            <div class="grid grid-2" style="align-items:end;">
                <div>
                    <label>Imagem (URL)</label>
                    <input name="image_url" value="{{ old('image_url', $module->image_url) }}" placeholder="https://... (opcional)">
                    <small class="muted">Deixe vazio para usar o placeholder 🖼️. Upload será adicionado depois.</small>
                </div>
                <div>
                    <label>Prévia</label>
                    @if ($module->image_url)
                        <img src="{{ $module->image_url }}" alt="{{ $module->name }}" style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid rgba(120,160,220,.3);">
                    @else
                        <div style="width:64px;height:64px;display:grid;place-items:center;font-size:1.8rem;border-radius:8px;border:1px dashed rgba(120,160,220,.4);background:#0e1420;">🖼️</div>
                    @endif
                </div>
            </div>

            <div>
                <label>Descrição</label>
                <textarea name="description" rows="2">{{ old('description', $module->description) }}</textarea>
            </div>

            <h3 style="margin:.5rem 0 0;">Atributos</h3>
            <div class="grid grid-3">
                <div>
                    <label>Movimento</label>
                    <input type="number" name="movement" value="{{ old('movement', $module->movement) }}">
                </div>
                <div>
                    <label>Estrutura (hull)</label>
                    <input type="number" name="hull" value="{{ old('hull', $module->hull) }}">
                </div>
                <div>
                    <label>Escudo</label>
                    <input type="number" name="shield" value="{{ old('shield', $module->shield) }}">
                </div>
                <div>
                    <label>Espaço ocupado</label>
                    <input type="number" name="space" value="{{ old('space', $module->space) }}" min="1">
                </div>
                <div>
                    <label>+Tempo de construção (s)</label>
                    <input type="number" name="build_time_add" value="{{ old('build_time_add', $module->build_time_add) }}">
                </div>
            </div>

            <h3 style="margin:.5rem 0 0;">Armamento</h3>
            <div class="grid grid-3">
                <div>
                    <label>Ataque</label>
                    <input type="number" name="attack" value="{{ old('attack', $module->attack) }}">
                </div>
                <div>
                    <label>Tipo de arma (só se ataque &gt; 0)</label>
                    <select name="attack_type">
                        <option value="" @selected(old('attack_type', $module->attack_type) === null)>Nenhum</option>
                        @foreach (['machinegun' => 'Metralhadora', 'laser' => 'Laser', 'missile' => 'Míssil'] as $val => $lbl)
                            <option value="{{ $val }}" @selected(old('attack_type', $module->attack_type) === $val)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Alcance</label>
                    <input type="number" name="range" value="{{ old('range', $module->range) }}">
                </div>
            </div>

            <h3 style="margin:.5rem 0 0;">Custo de construção (0 = não usa)</h3>
            <div class="grid grid-3">
                <div>
                    <label>Ouro</label>
                    <input type="number" name="cost_gold" value="{{ old('cost_gold', $module->cost_gold) }}">
                </div>
                <div>
                    <label>Metal</label>
                    <input type="number" name="cost_metal" value="{{ old('cost_metal', $module->cost_metal) }}">
                </div>
                <div>
                    <label>Energia</label>
                    <input type="number" name="cost_energy" value="{{ old('cost_energy', $module->cost_energy) }}">
                </div>
            </div>

            <div>
                <label>Atributos especiais (JSON)</label>
                <textarea name="special_attributes" rows="3" placeholder='{"pierces_shield": true}'>{{ old('special_attributes', $module->special_attributes !== null ? json_encode($module->special_attributes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '') }}</textarea>
                <small class="muted">Objeto JSON livre. Deixe vazio para nenhum.</small>
            </div>

            <div>
                <button class="btn" type="submit">{{ $creating ? 'Criar módulo' : 'Salvar módulo' }}</button>
            </div>
        </form>
    </div>

    @unless ($creating)
        <div class="card">
            <form method="POST" action="{{ route('admin.module-types.destroy', $module) }}"
                  onsubmit="return confirm('Remover este módulo? Modelos que o usam também serão afetados.');">
                @csrf
                @method('DELETE')
                <button class="btn btn-danger" type="submit">Remover módulo</button>
            </form>
        </div>
    @endunless
@endsection
