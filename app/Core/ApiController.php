<?php

namespace Core;

/**
 * Controller base para APIs REST
 * Fornece métodos para respostas JSON estruturadas
 */
abstract class ApiController extends Controller
{
    protected int $status_code = 200;

    /**
     * Define status code para resposta
     */
    protected function setStatusCode(int $code): self
    {
        $this->status_code = $code;
        return $this;
    }

    /**
     * Resposta bem-sucedida
     */
    protected function success($data, int $code = 200, array $meta = []): void
    {
        $response = [
            'success' => true,
            'data' => $data,
            'meta' => $meta
        ];

        $this->respondJson($response, $code);
    }

    /**
     * Resposta com listagem (com paginação)
     */
    protected function successList(array $items, int $total, int $page = 1, int $per_page = 15, int $code = 200): void
    {
        $response = [
            'success' => true,
            'data' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $per_page,
                'pages' => ceil($total / $per_page)
            ]
        ];

        $this->respondJson($response, $code);
    }

    /**
     * Resposta de erro
     */
    protected function error(string $message, int $code = 400, array $errors = []): void
    {
        $response = [
            'success' => false,
            'error' => $message,
            'errors' => $errors
        ];

        $this->respondJson($response, $code);
    }

    /**
     * Resposta genérica JSON
     */
    protected function respondJson(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Obtém entrada JSON do request
     */
    protected function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }

    /**
     * Valida entrada JSON
     */
    protected function validateJsonInput(array $rules): bool
    {
        $data = $this->getJsonInput();
        $validator = new FormValidator($data);
        $validator->rules($rules);

        if (!$validator->validate()) {
            $this->error('Validação falhou', 422, $validator->errors());
            return false;
        }

        return true;
    }
}
