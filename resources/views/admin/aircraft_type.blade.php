@extends('admin.layout')

@section('title', $creating ? 'Nova aeronave' : $type->name)

@section('content')
    <p><a href="{{ route('admin.dashboard') }}">&larr; Voltar</a></p>
    <h1>
        <span class="swatch" style="background: {{ $type->color }}"></span>
        {{ $creating ? 'Nova aeronave' : $type->name }}
        <span class="muted" style="font-size: .9rem;">(aeronave)</span>
    </h1>

    @if ($errors->any())
        <p class="error">{{ $errors->first() }}</p>
    @endif

    <div class="card">
        <h2>Atributos</h2>
        <p class="muted">Aeronaves não têm nível. O dano vem de módulos (futuro); bônus de módulo ficam no JSON.</p>

        <form method="POST" action="{{ $creating ? route('admin.aircraft-types.store') : route('admin.aircraft-types.update', $type) }}" class="grid">
            @csrf
            @unless ($creating) @method('PUT') @endunless

            <div class="grid grid-3">
                <div>
                    <label>Identificador (key)</label>
                    <input name="key" value="{{ old('key', $type->key) }}" placeholder="ex.: cruiser_mk1" required>
                    <small class="muted">Único; só letras minúsculas, números e _.</small>
                </div>
                <div>
                    <label>Classe</label>
                    <select name="class">
                        @foreach (['cruiser' => 'Cruzador', 'battleship' => 'Encouraçado', 'frigate' => 'Fragata', 'fighter' => 'Caça'] as $val => $lbl)
                            <option value="{{ $val }}" @selected(old('class', $type->class) === $val)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Nome</label>
                    <input name="name" value="{{ old('name', $type->name) }}" required>
                </div>
            </div>

            <div class="grid grid-2">
                <div>
                    <label>Cor</label>
                    <input name="color" value="{{ old('color', $type->color) }}">
                </div>
            </div>

            <div class="grid grid-2" style="align-items:end;">
                <div>
                    <label>Imagem (URL)</label>
                    <input name="image_url" value="{{ old('image_url', $type->image_url) }}" placeholder="https://... (opcional)">
                    <small class="muted">Deixe vazio para usar o placeholder 🖼️. Upload será adicionado depois.</small>
                </div>
                <div>
                    <label>Prévia</label>
                    @if ($type->image_url)
                        <img src="{{ $type->image_url }}" alt="{{ $type->name }}" style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid rgba(120,160,220,.3);">
                    @else
                        <div style="width:64px;height:64px;display:grid;place-items:center;font-size:1.8rem;border-radius:8px;border:1px dashed rgba(120,160,220,.4);background:#0e1420;">🖼️</div>
                    @endif
                </div>
            </div>

            <div>
                <label>Descrição</label>
                <textarea name="description" rows="2">{{ old('description', $type->description) }}</textarea>
            </div>

            <h3 style="margin:.5rem 0 0;">Custo de construção (0 = não usa o recurso)</h3>
            <div class="grid grid-3">
                <div>
                    <label>Ouro</label>
                    <input type="number" name="cost_gold" value="{{ old('cost_gold', $type->cost_gold) }}">
                </div>
                <div>
                    <label>Metal</label>
                    <input type="number" name="cost_metal" value="{{ old('cost_metal', $type->cost_metal) }}">
                </div>
                <div>
                    <label>Energia</label>
                    <input type="number" name="cost_energy" value="{{ old('cost_energy', $type->cost_energy) }}">
                </div>
            </div>

            <h3 style="margin:.5rem 0 0;">Atributos</h3>
            <div class="grid grid-3">
                <div>
                    <label>Tempo de construção (s)</label>
                    <input type="number" name="build_time" value="{{ old('build_time', $type->build_time) }}">
                </div>
                <div>
                    <label>Escudo (0 ou N)</label>
                    <input type="number" name="shield" value="{{ old('shield', $type->shield) }}">
                </div>
                <div>
                    <label>Estrutura (0 ou N)</label>
                    <input type="number" name="hull" value="{{ old('hull', $type->hull) }}">
                </div>
                <div>
                    <label>Movimento (0 ou N)</label>
                    <input type="number" name="movement" value="{{ old('movement', $type->movement) }}">
                </div>
                <div>
                    <label>Armazenamento (módulos)</label>
                    <input type="number" name="storage" value="{{ old('storage', $type->storage) }}">
                </div>
            </div>

            <div>
                <label>Atributos especiais (JSON)</label>
                <textarea name="special_attributes" rows="4" placeholder='{"module_bonus": {"laser": 10}}'>{{ old('special_attributes', $type->special_attributes !== null ? json_encode($type->special_attributes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '') }}</textarea>
                <small class="muted">Objeto JSON livre para bônus de módulos e efeitos especiais. Deixe vazio para nenhum.</small>
            </div>

            <div>
                <button class="btn" type="submit">{{ $creating ? 'Criar aeronave' : 'Salvar aeronave' }}</button>
            </div>
        </form>
    </div>

    @unless ($creating)
        <div class="card">
            <form method="POST" action="{{ route('admin.aircraft-types.destroy', $type) }}"
                  onsubmit="return confirm('Remover esta aeronave? Modelos que a usam também serão afetados.');">
                @csrf
                @method('DELETE')
                <button class="btn btn-danger" type="submit">Remover aeronave</button>
            </form>
        </div>
    @endunless
@endsection
