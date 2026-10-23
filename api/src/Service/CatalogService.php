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
            out($this->repository->findMenu()->fetchAll());
        }
        if ($action === 'tables' && $method === 'GET') {
            out($this->repository->findDiningTables()->fetchAll());
        }
    }
}
