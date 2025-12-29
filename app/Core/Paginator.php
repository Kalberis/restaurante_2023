<?php

namespace Core;

/**
 * Sistema de paginação com HTML
 */
class Paginator
{
    private int $currentPage;
    private int $perPage;
    private int $total;
    private int $lastPage;
    private array $items;

    public function __construct(array $items, int $total, int $perPage = 15, int $currentPage = 1)
    {
        $this->items = $items;
        $this->total = $total;
        $this->perPage = $perPage;
        $this->currentPage = max(1, $currentPage);
        $this->lastPage = (int) ceil($total / $perPage);
    }

    /**
     * Retorna os itens da página atual
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * Total de registros
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * Página atual
     */
    public function currentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * Última página
     */
    public function lastPage(): int
    {
        return $this->lastPage;
    }

    /**
     * Registros por página
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * Verifica se tem mais páginas
     */
    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    /**
     * Número do primeiro item da página
     */
    public function firstItem(): int
    {
        return ($this->currentPage - 1) * $this->perPage + 1;
    }

    /**
     * Número do último item da página
     */
    public function lastItem(): int
    {
        return min($this->currentPage * $this->perPage, $this->total);
    }

    /**
     * Gera HTML da paginação (Bootstrap)
     */
    public function links(string $url, array $queryParams = []): string
    {
        if ($this->lastPage <= 1) {
            return '';
        }

        $html = '<nav><ul class="pagination">';

        // Botão "Anterior"
        if ($this->currentPage > 1) {
            $prevUrl = $this->buildUrl($url, $this->currentPage - 1, $queryParams);
            $html .= '<li class="page-item"><a class="page-link" href="' . $prevUrl . '">Anterior</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">Anterior</span></li>';
        }

        // Páginas
        $start = max(1, $this->currentPage - 2);
        $end = min($this->lastPage, $this->currentPage + 2);

        // Primeira página
        if ($start > 1) {
            $firstUrl = $this->buildUrl($url, 1, $queryParams);
            $html .= '<li class="page-item"><a class="page-link" href="' . $firstUrl . '">1</a></li>';
            if ($start > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        // Páginas do meio
        for ($i = $start; $i <= $end; $i++) {
            $pageUrl = $this->buildUrl($url, $i, $queryParams);
            $active = $i === $this->currentPage ? ' active' : '';
            $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . $pageUrl . '">' . $i . '</a></li>';
        }

        // Última página
        if ($end < $this->lastPage) {
            if ($end < $this->lastPage - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            $lastUrl = $this->buildUrl($url, $this->lastPage, $queryParams);
            $html .= '<li class="page-item"><a class="page-link" href="' . $lastUrl . '">' . $this->lastPage . '</a></li>';
        }

        // Botão "Próximo"
        if ($this->currentPage < $this->lastPage) {
            $nextUrl = $this->buildUrl($url, $this->currentPage + 1, $queryParams);
            $html .= '<li class="page-item"><a class="page-link" href="' . $nextUrl . '">Próximo</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">Próximo</span></li>';
        }

        $html .= '</ul></nav>';

        return $html;
    }

    /**
     * Constrói URL com parâmetros
     */
    private function buildUrl(string $baseUrl, int $page, array $queryParams): string
    {
        $queryParams['page'] = $page;
        return $baseUrl . '?' . http_build_query($queryParams);
    }

    /**
     * Info de paginação
     */
    public function info(): string
    {
        if ($this->total === 0) {
            return 'Nenhum registro encontrado';
        }

        return sprintf(
            'Exibindo %d a %d de %d registros',
            $this->firstItem(),
            $this->lastItem(),
            $this->total
        );
    }

    /**
     * Converte para array (útil para APIs)
     */
    public function toArray(): array
    {
        return [
            'current_page' => $this->currentPage,
            'data' => $this->items,
            'first_page_url' => '?page=1',
            'from' => $this->firstItem(),
            'last_page' => $this->lastPage,
            'last_page_url' => '?page=' . $this->lastPage,
            'next_page_url' => $this->hasMorePages() ? '?page=' . ($this->currentPage + 1) : null,
            'path' => Request::uri(),
            'per_page' => $this->perPage,
            'prev_page_url' => $this->currentPage > 1 ? '?page=' . ($this->currentPage - 1) : null,
            'to' => $this->lastItem(),
            'total' => $this->total
        ];
    }
}
