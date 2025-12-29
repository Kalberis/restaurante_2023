<?php

namespace Controllers\Api;

use Core\ApiController;
use Core\Request;
use Models\Produto;
use Models\Usuario;

/**
 * API REST para Produtos
 */
class ProdutosApi extends ApiController
{
    /**
     * GET /api/produtos
     * Retorna lista paginada de produtos
     */
    public function index(Request $request)
    {
        try {
            $page = (int)$request->get('page', 1);
            $per_page = (int)$request->get('per_page', 15);
            $search = $request->get('search', '');

            $query = new Produto();

            // Filtro de busca
            if (!empty($search)) {
                $query->where('nome', 'LIKE', "%{$search}%");
            }

            // Total sem paginação
            $total_stmt = $query->query(
                "SELECT COUNT(*) as count FROM {$query->table}"
            );
            $total = (int)$total_stmt->fetch(\PDO::FETCH_ASSOC)['count'];

            // Resultado paginado
            $produtos = $query
                ->paginate($per_page, $page)
                ->all();

            $this->successList($produtos, $total, $page, $per_page);
        } catch (\Exception $e) {
            $this->error('Erro ao buscar produtos', 500);
        }
    }

    /**
     * GET /api/produtos/{id}
     */
    public function show(Request $request)
    {
        try {
            $id = $request->get('id');
            $produto = new Produto($id);

            if (!$produto->isStorage()) {
                $this->error('Produto não encontrado', 404);
                return;
            }

            $this->success($produto->getData());
        } catch (\Exception $e) {
            $this->error('Erro ao buscar produto', 500);
        }
    }

    /**
     * POST /api/produtos
     * Cria novo produto
     */
    public function store(Request $request)
    {
        try {
            // Valida entrada JSON
            if (!$this->validateJsonInput([
                'nome' => 'required|minlength:3',
                'descricao' => 'minlength:10',
                'preco' => 'required|numeric|min:0.01'
            ])) {
                return;
            }

            $input = $this->getJsonInput();
            $produto = new Produto();
            $produto->save($input);

            $this->success($produto->getData(), 201);
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/produtos/{id}
     * Atualiza produto
     */
    public function update(Request $request)
    {
        try {
            $id = $request->get('id');
            $produto = new Produto($id);

            if (!$produto->isStorage()) {
                $this->error('Produto não encontrado', 404);
                return;
            }

            $input = $this->getJsonInput();
            $produto->save($input);

            $this->success($produto->getData());
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/produtos/{id}
     */
    public function destroy(Request $request)
    {
        try {
            $id = $request->get('id');
            $produto = new Produto($id);

            if (!$produto->isStorage()) {
                $this->error('Produto não encontrado', 404);
                return;
            }

            $produto->delete();
            $this->success(['message' => 'Produto deletado com sucesso']);
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
}

/**
 * API REST para Usuários
 */
class UsuariosApi extends ApiController
{
    /**
     * GET /api/usuarios
     */
    public function index(Request $request)
    {
        try {
            $page = (int)$request->get('page', 1);
            $per_page = (int)$request->get('per_page', 15);

            $query = new Usuario();
            $total_stmt = $query->query("SELECT COUNT(*) as count FROM {$query->table}");
            $total = (int)$total_stmt->fetch(\PDO::FETCH_ASSOC)['count'];

            $usuarios = $query
                ->paginate($per_page, $page)
                ->all();

            $this->successList($usuarios, $total, $page, $per_page);
        } catch (\Exception $e) {
            $this->error('Erro ao buscar usuários', 500);
        }
    }

    /**
     * GET /api/usuarios/{id}
     */
    public function show(Request $request)
    {
        try {
            $id = $request->get('id');
            $usuario = new Usuario($id);

            if (!$usuario->isStorage()) {
                $this->error('Usuário não encontrado', 404);
                return;
            }

            $this->success($usuario->getData());
        } catch (\Exception $e) {
            $this->error('Erro ao buscar usuário', 500);
        }
    }

    /**
     * POST /api/usuarios
     */
    public function store(Request $request)
    {
        try {
            if (!$this->validateJsonInput([
                'login' => 'required|minlength:3',
                'email' => 'required|email',
                'password' => 'required|minlength:8|confirmed'
            ])) {
                return;
            }

            $input = $this->getJsonInput();
            $usuario = new Usuario();
            $usuario->save($input);

            // Remove password da resposta
            $data = $usuario->getData();
            unset($data['password']);

            $this->success($data, 201);
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
}
