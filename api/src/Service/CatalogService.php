<?php

declare(strict_types=1);

namespace Kitchen\Service;

use Kitchen\Repository\CatalogRepository;

use function Kitchen\Support\{out};

final class CatalogService
{
    public function __construct(private CatalogRepository $repository)
    {
    }

    public function handle(string $action, string $method, array $body, array $u): void
    {
        if ($action === 'menu' && $method === 'GET') {
            // Each product lists the products that can optionally be added to it (sides, dips, extras).
            $addons = [];
            foreach ($this->repository->findAddons()->fetchAll() as $link) {
                $addons[(int)$link['menu_id']][] = (int)$link['addon_id'];
            }
            $menu = $this->repository->findMenu()->fetchAll();
            foreach ($menu as &$product) {
                $product['addons'] = $addons[(int)$product['id']] ?? [];
            }
            unset($product);
            out($menu);
        }
        if ($action === 'tables' && $method === 'GET') {
            out($this->repository->findDiningTables()->fetchAll());
        }
    }
}
