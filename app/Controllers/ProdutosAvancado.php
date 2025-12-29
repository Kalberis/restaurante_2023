<?php

namespace Controllers;

use Core\Controller;
use Core\Request;
use Core\View;
use Core\FormValidator;
use Core\FileUpload;
use Core\EventDispatcher;
use Core\FlashMessage;
use Core\Paginator;
use Core\Logger;
use Models\Produto;

/**
 * Exemplo completo usando todas as funcionalidades extras
 */
class ProdutosAvancado extends Controller
{
    /**
     * Lista produtos com paginação
     */
    public function index(): void
    {
        $page = Request::get('page', 1);
        $perPage = 15;
        $search = Request::get('search', '');

        // Buscar produtos
        $query = Produto::query();
        
        if ($search) {
            $query->where('nome', 'LIKE', "%{$search}%");
        }

        // Total para paginação
        $total = $query->count();

        // Produtos da página atual
        $produtos = $query->limit($perPage)
                          ->offset(($page - 1) * $perPage)
                          ->orderBy('created_at', 'DESC')
                          ->get();

        // Criar paginador
        $paginator = new Paginator($produtos, $total, $perPage, $page);

        View::render('produtos/lista_avancada', [
            'produtos' => $paginator->items(),
            'pagination' => $paginator->links('/produtos', ['search' => $search]),
            'info' => $paginator->info(),
            'search' => $search
        ]);
    }

    /**
     * Formulário de cadastro
     */
    public function novo(): void
    {
        View::render('produtos/form');
    }

    /**
     * Processa cadastro com upload e eventos
     */
    public function salvar(): void
    {
        if (!Request::isPost()) {
            redirect('/produtos/novo');
            return;
        }

        // Validar dados
        $validator = new FormValidator(Request::all(), [
            'nome' => 'required|min:3|max:100',
            'descricao' => 'required|min:10',
            'preco' => 'required|numeric|min:0',
            'categoria' => 'required',
            'estoque' => 'required|integer|min:0'
        ], [
            'nome.required' => 'O nome do produto é obrigatório',
            'nome.min' => 'O nome deve ter no mínimo 3 caracteres',
            'preco.numeric' => 'O preço deve ser um valor numérico',
            'estoque.integer' => 'O estoque deve ser um número inteiro'
        ]);

        if (!$validator->validate()) {
            FlashMessage::error('Erro na validação', $validator->getErrors());
            redirect('/produtos/novo');
            return;
        }

        // Upload da foto (se enviada)
        $fotoFilename = null;
        
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $uploader = new FileUpload('storage/uploads/produtos/');
            $uploader->setAllowedTypes('image')
                     ->setMaxSize(5242880); // 5MB

            try {
                $foto = $uploader->upload($_FILES['foto']);
                $fotoFilename = $foto['filename'];

                Logger::getInstance()->info('Foto de produto enviada', [
                    'original' => $foto['original_name'],
                    'saved' => $foto['filename'],
                    'size' => $foto['size']
                ]);

            } catch (\Exception $e) {
                FlashMessage::error('Erro ao processar foto: ' . $e->getMessage());
                redirect('/produtos/novo');
                return;
            }
        }

        // Criar produto
        try {
            $produto = new Produto();
            $produto->nome = Request::post('nome');
            $produto->descricao = Request::post('descricao');
            $produto->preco = Request::post('preco');
            $produto->categoria = Request::post('categoria');
            $produto->estoque = Request::post('estoque');
            $produto->foto = $fotoFilename;
            $produto->ativo = 1;
            $produto->save();

            // Disparar evento - outros sistemas podem reagir
            EventDispatcher::dispatch('produto.criado', [
                'id' => $produto->id,
                'nome' => $produto->nome,
                'preco' => $produto->preco,
                'categoria' => $produto->categoria,
                'usuario_id' => $_SESSION['usuario_id'] ?? null
            ]);

            FlashMessage::success('Produto cadastrado com sucesso!');
            redirect('/produtos');

        } catch (\PDOException $e) {
            Logger::getInstance()->error('Erro ao salvar produto', [
                'error' => $e->getMessage(),
                'data' => Request::all()
            ]);

            FlashMessage::error('Erro ao salvar produto. Tente novamente.');
            redirect('/produtos/novo');
        }
    }

    /**
     * Editar produto
     */
    public function editar(int $id): void
    {
        $produto = Produto::find($id);

        if (!$produto) {
            FlashMessage::error('Produto não encontrado');
            redirect('/produtos');
            return;
        }

        View::render('produtos/form', ['produto' => $produto]);
    }

    /**
     * Atualizar produto
     */
    public function atualizar(int $id): void
    {
        if (!Request::isPost()) {
            redirect('/produtos');
            return;
        }

        $produto = Produto::find($id);

        if (!$produto) {
            FlashMessage::error('Produto não encontrado');
            redirect('/produtos');
            return;
        }

        // Validar
        $validator = new FormValidator(Request::all(), [
            'nome' => 'required|min:3|max:100',
            'preco' => 'required|numeric|min:0',
            'estoque' => 'required|integer|min:0'
        ]);

        if (!$validator->validate()) {
            FlashMessage::error('Erro na validação', $validator->getErrors());
            redirect("/produtos/{$id}/editar");
            return;
        }

        // Nova foto?
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $uploader = new FileUpload('storage/uploads/produtos/');
            $uploader->setAllowedTypes('image')->setMaxSize(5242880);

            try {
                // Deletar foto antiga
                if ($produto->foto) {
                    $uploader->delete($produto->foto);
                }

                $foto = $uploader->upload($_FILES['foto']);
                $produto->foto = $foto['filename'];

            } catch (\Exception $e) {
                FlashMessage::warning('Erro ao atualizar foto: ' . $e->getMessage());
            }
        }

        // Atualizar dados
        $produto->nome = Request::post('nome');
        $produto->descricao = Request::post('descricao');
        $produto->preco = Request::post('preco');
        $produto->categoria = Request::post('categoria');
        $produto->estoque = Request::post('estoque');
        $produto->save();

        // Evento de atualização
        EventDispatcher::dispatch('produto.atualizado', [
            'id' => $produto->id,
            'nome' => $produto->nome,
            'alteracoes' => ['preco', 'estoque'] // poderia detectar campos alterados
        ]);

        FlashMessage::success('Produto atualizado!');
        redirect('/produtos');
    }

    /**
     * Deletar produto
     */
    public function deletar(int $id): void
    {
        $produto = Produto::find($id);

        if (!$produto) {
            FlashMessage::error('Produto não encontrado');
            redirect('/produtos');
            return;
        }

        // Deletar foto se existir
        if ($produto->foto) {
            $uploader = new FileUpload('storage/uploads/produtos/');
            $uploader->delete($produto->foto);
        }

        // Guardar dados antes de deletar
        $produtoData = [
            'id' => $produto->id,
            'nome' => $produto->nome
        ];

        // Deletar
        $produto->delete();

        // Evento
        EventDispatcher::dispatch('produto.deletado', $produtoData);

        FlashMessage::success('Produto deletado!');
        redirect('/produtos');
    }

    /**
     * Exportar produtos para backup
     */
    public function backup(): void
    {
        try {
            $backup = new \Core\DatabaseBackup();
            
            // Backup apenas da tabela produtos
            $filename = $backup->backup(['produtos']);

            FlashMessage::success("Backup criado: {$filename}");
            
        } catch (\Exception $e) {
            FlashMessage::error('Erro ao criar backup: ' . $e->getMessage());
        }

        redirect('/produtos');
    }

    /**
     * Listar backups disponíveis
     */
    public function backups(): void
    {
        $backup = new \Core\DatabaseBackup();
        $backups = $backup->list();

        View::render('produtos/backups', ['backups' => $backups]);
    }
}
