<?php

namespace App\Services;

use App\Models\Base;
use App\Models\Item;
use App\Models\PlayerItem;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Owns the account-level inventory rules. Items belong to the user (not a
 * base): the "Forte Protetor" (a unique inventory structure on the terrestrial
 * base) is the item box. Its level defines how many slots the player has, where
 * one slot holds a single stack of one item. Stack size is bounded per item by
 * Item::max_stack (e.g. 9999 for common consumables, 1 for unique items).
 */
class InventoryService
{
    /**
     * The player's built Forte Protetor, if any. It is a unique inventory
     * structure on the terrestrial base.
     */
    public function fort(User $user): ?Structure
    {
        $base = Base::where('user_id', $user->id)->where('kind', 'terrestrial')->first();
        if (! $base) {
            return null;
        }

        return $base->structures()
            ->with('type')
            ->get()
            ->first(fn (Structure $s) => $s->type->isInventory() && $s->is_constructed);
    }

    /** Total inventory slots granted by the Forte Protetor (0 if none built). */
    public function capacity(User $user): int
    {
        return (int) ($this->fort($user)?->inventorySlots() ?? 0);
    }

    /** How many slots are used: one per distinct owned item stack. */
    public function usedSlots(User $user): int
    {
        return (int) PlayerItem::where('user_id', $user->id)->count();
    }

    /** Remaining free slots (never negative). */
    public function remainingSlots(User $user): int
    {
        return max(0, $this->capacity($user) - $this->usedSlots($user));
    }

    /**
     * The player's owned items (one row per stack), eager-loaded with the item
     * catalog data, ordered by item type then name.
     *
     * @return Collection<int, PlayerItem>
     */
    public function items(User $user): Collection
    {
        return PlayerItem::where('user_id', $user->id)
            ->with('item')
            ->get()
            ->sortBy(fn (PlayerItem $pi) => [$pi->item->type, $pi->item->name])
            ->values();
    }

    /**
     * Add `quantity` of an item to the player's inventory. Stacks onto an
     * existing row when the player already holds the item; otherwise opens a
     * new slot (if one is free). Respects the item's max_stack.
     *
     * Throws ValidationException on: no built fort, stack overflow, or no free
     * slot for a brand-new stack.
     */
    public function addItem(User $user, Item $item, int $quantity = 1): PlayerItem
    {
        $quantity = max(1, $quantity);

        if ($this->fort($user) === null) {
            throw ValidationException::withMessages([
                'inventory' => ['Construa um Forte Protetor na base terrestre para guardar itens.'],
            ]);
        }

        $existing = PlayerItem::where('user_id', $user->id)
            ->where('item_id', $item->id)
            ->first();

        if ($existing) {
            $newQty = $existing->quantity + $quantity;
            if ($newQty > $item->max_stack) {
                throw ValidationException::withMessages([
                    'stack' => ["Limite de pilha atingido para {$item->name} (máx. {$item->max_stack})."],
                ]);
            }
            $existing->quantity = $newQty;
            $existing->save();

            return $existing;
        }

        // New stack: needs a free slot.
        if ($this->remainingSlots($user) < 1) {
            throw ValidationException::withMessages([
                'slots' => ['Inventário cheio. Evolua o Forte Protetor para liberar mais espaços.'],
            ]);
        }

        if ($quantity > $item->max_stack) {
            throw ValidationException::withMessages([
                'stack' => ["Limite de pilha atingido para {$item->name} (máx. {$item->max_stack})."],
            ]);
        }

        return PlayerItem::create([
            'user_id' => $user->id,
            'item_id' => $item->id,
            'quantity' => $quantity,
        ]);
    }

    /**
     * Remove `quantity` of an item from the player's inventory. Deletes the
     * stack (frees the slot) when it reaches zero.
     *
     * Throws ValidationException when the player doesn't own the item or lacks
     * the requested quantity.
     */
    public function removeItem(User $user, Item $item, int $quantity = 1): void
    {
        $quantity = max(1, $quantity);

        $existing = PlayerItem::where('user_id', $user->id)
            ->where('item_id', $item->id)
            ->first();

        if (! $existing || $existing->quantity < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => ['Você não possui essa quantidade do item.'],
            ]);
        }

        $existing->quantity -= $quantity;

        if ($existing->quantity <= 0) {
            $existing->delete();

            return;
        }

        $existing->save();
    }
}
