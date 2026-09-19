# Across the Stars — Backend (API)

API em Laravel para o jogo (inspirado no Galaxy Online 2). Totalmente dockerizado:
nada precisa ser instalado na máquina além de Docker.

## Stack
- Laravel (PHP 8.3-FPM) + Nginx
- MySQL 8
- Autenticação por token (Laravel Sanctum)

## Como rodar

```sh
docker compose up --build
```

Na primeira subida o container instala as dependências, roda as migrations e o
seed automaticamente (via `docker/php/entrypoint.sh`).

- API: http://localhost:8201/api
- MySQL (host): porta `8206` (db `across_the_stars`, user `ats`, senha `ats_secret`)

Para parar:

```sh
docker compose down
```

Para zerar o banco (apaga o volume):

```sh
docker compose down -v
```

## Endpoints

Públicos:
- `POST /api/register` — `{ name, email, password, password_confirmation }` → `{ user, token }`
- `POST /api/login` — `{ email, password }` → `{ user, token }`

Autenticados (header `Authorization: Bearer <token>`):
- `GET /api/me` — usuário atual
- `POST /api/logout` — revoga o token atual
- `GET /api/base` — terreno, recursos, estruturas posicionadas e catálogo de estruturas
- `POST /api/structures` — `{ structure_type_id, x, y }` posiciona uma estrutura
- `POST /api/structures/collect` — coleta recursos de todas as estruturas
- `POST /api/structures/{id}/collect` — coleta recursos de uma estrutura

## Modelo do jogo

- **Base**: terreno do jogador em unidades genéricas (padrão 1000x1000, customizável)
  e saldos de `gold`, `metal`, `energy`.
- **StructureType**: catálogo. Inicialmente 3, todas com footprint 20x20:
  - Mina de Ouro → ouro
  - Mina de Metal → metal
  - Gerador de Eletricidade → eletricidade
- **Structure**: instância posicionada em `(x, y)`. Não pode sair dos limites nem
  sobrepor outra estrutura (validação AABB no backend).

### Produção por hora
A produção é calculada de forma "preguiçosa": ao coletar, o servidor conta quantas
**horas inteiras** se passaram desde a última coleta e credita
`horas * produção_por_hora`. As horas fracionárias ficam acumuladas para a próxima
coleta. Isso não depende de cron/worker sempre ativo.

## Ferramentas úteis

```sh
# Rodar migrations manualmente
docker exec ats_backend_app php artisan migrate

# Tinker
docker exec -it ats_backend_app php artisan tinker
```
