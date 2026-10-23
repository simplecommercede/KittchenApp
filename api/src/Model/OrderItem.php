<?php

declare(strict_types=1);

namespace Kitchen\Model;

final readonly class OrderItem
{
    public function __construct(public int $id, public int $menuId, public string $name, public int $quantity, public int $price, public string $note)
    {
    }
    public static function fromArray(array $row): self
    {
        return new self((int)$row['id'], (int)$row['menu_id'], $row['name'], (int)$row['quantity'], (int)$row['price'], $row['note']);
    }
    public function toArray(): array
    {
        return ['id' => $this->id,'menu_id' => $this->menuId,'name' => $this->name,'quantity' => $this->quantity,'price' => $this->price,'note' => $this->note];
    }
}
