<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\PlayerItem;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(protected InventoryService $inventory)
    {
    }

    /**
     * Full inventory snapshot: the Forte Protetor's slot capacity/usage and the
     * player's owned item stacks (with catalog data for display and filtering).
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->snapshot($request));
    }

    /**
     * Add N of a catalog item to the player's inventory. Stacks onto an
     * existing stack (bounded by max_stack) or opens a new slot when free.
     */
    public function addItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);

        $item = Item::findOrFail($data['item_id']);
        $this->inventory->addItem($request->user(), $item, (int) ($data['quantity'] ?? 1));

        return response()->json([
            'message' => "{$item->name} adicionado ao inventário.",
            ...$this->snapshot($request),
        ], 201);
    }

    /**
     * Remove N of an item the player owns. Frees the slot when the stack hits 0.
     */
    public function removeItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);

        $item = Item::findOrFail($data['item_id']);
        $this->inventory->removeItem($request->user(), $item, (int) ($data['quantity'] ?? 1));

        return response()->json([
            'message' => "{$item->name} removido do inventário.",
            ...$this->snapshot($request),
        ]);
    }

    /**
     * Build the JSON snapshot used by the inventory UI.
     */
    protected function snapshot(Request $request): array
    {
        $user = $request->user();

        $items = $this->inventory->items($user)->map(fn (PlayerItem $pi) => [
            'id' => $pi->id,
            'item_id' => $pi->item_id,
            'quantity' => $pi->quantity,
            'key' => $pi->item->key,
            'name' => $pi->item->name,
            'description' => $pi->item->description,
            'type' => $pi->item->type,
            'effects' => $pi->item->effects ?? [],
            'max_stack' => $pi->item->max_stack,
            'icon' => $pi->item->icon,
            'color' => $pi->item->color,
        ])->values();

        return [
            'inventory' => [
                'has_fort' => $this->inventory->fort($user) !== null,
                'capacity' => $this->inventory->capacity($user),
                'used' => $this->inventory->usedSlots($user),
                'remaining' => $this->inventory->remainingSlots($user),
            ],
            'items' => $items,
        ];
    }
}
