<?php
declare(strict_types=1);
namespace App\Core;

final class Paginator
{
    public readonly int $total;
    public readonly int $perPage;
    public readonly int $currentPage;
    public readonly int $lastPage;
    public readonly int $offset;
    public readonly bool $hasPrev;
    public readonly bool $hasNext;

    public function __construct(int $total, int $perPage, int $currentPage)
    {
        $this->total       = max(0, $total);
        $this->perPage     = max(1, $perPage);
        $this->lastPage    = max(1, (int)ceil($this->total / $this->perPage));
        $this->currentPage = max(1, min($currentPage, $this->lastPage));
        $this->offset      = ($this->currentPage - 1) * $this->perPage;
        $this->hasPrev     = $this->currentPage > 1;
        $this->hasNext     = $this->currentPage < $this->lastPage;
    }

    public function prevPage(): int { return $this->hasPrev ? $this->currentPage - 1 : 1; }
    public function nextPage(): int { return $this->hasNext ? $this->currentPage + 1 : $this->lastPage; }
}
