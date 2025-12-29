<?php

/**
 * Configuração de Event Listeners
 * Registre aqui todos os eventos do sistema
 */

use Core\EventDispatcher;
use Core\Logger;
use Core\Mailer;
use Core\DatabaseBackup;

// ========================================
// EVENTOS DE USUÁRIO
// ========================================

// Quando usuário é criado
EventDispatcher::listen('usuario.criado', function($data) {
    // Enviar email de boas-vindas
    Mailer::send(
        $data['email'],
        'Bem-vindo ao ' . getenv('APP_NAME'),
        'emails/welcome',
        [
            'nome' => $data['nome'],
            'email' => $data['email']
        ]
    );
}, priority: 10);

EventDispatcher::listen('usuario.criado', function($data) {
    // Log de auditoria
    Logger::getInstance()->info('Novo usuário cadastrado', [
        'id' => $data['id'],
        'nome' => $data['nome'],
        'email' => $data['email'],
        'tipo' => $data['tipo'] ?? 'cliente'
    ]);
}, priority: 5);

// Quando usuário faz login
EventDispatcher::listen('usuario.login', function($data) {
    Logger::getInstance()->info('Login realizado', [
        'usuario_id' => $data['id'],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
});

// Quando usuário falha no login
EventDispatcher::listen('usuario.login.falhou', function($data) {
    Logger::getInstance()->warning('Tentativa de login falhou', [
        'email' => $data['email'],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'tentativas' => $data['tentativas'] ?? 1
    ]);
    
    // Se muitas tentativas, enviar alerta
    if (($data['tentativas'] ?? 0) >= 5) {
        Logger::getInstance()->critical('Possível ataque de força bruta', [
            'email' => $data['email'],
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);
    }
});

// Quando usuário atualiza perfil
EventDispatcher::listen('usuario.atualizado', function($data) {
    Logger::getInstance()->info('Perfil atualizado', [
        'usuario_id' => $data['id'],
        'campos' => $data['alteracoes'] ?? []
    ]);
});

// ========================================
// EVENTOS DE PRODUTO
// ========================================

// Quando produto é criado
EventDispatcher::listen('produto.criado', function($data) {
    Logger::getInstance()->info('Produto cadastrado', [
        'id' => $data['id'],
        'nome' => $data['nome'],
        'preco' => $data['preco'],
        'usuario_id' => $data['usuario_id'] ?? null
    ]);
});

// Quando produto é atualizado
EventDispatcher::listen('produto.atualizado', function($data) {
    Logger::getInstance()->info('Produto atualizado', [
        'id' => $data['id'],
        'nome' => $data['nome'],
        'alteracoes' => $data['alteracoes'] ?? []
    ]);
});

// Quando produto é deletado
EventDispatcher::listen('produto.deletado', function($data) {
    Logger::getInstance()->warning('Produto deletado', [
        'id' => $data['id'],
        'nome' => $data['nome']
    ]);
});

// Quando estoque fica baixo
EventDispatcher::listen('produto.estoque.baixo', function($data) {
    Logger::getInstance()->warning('Estoque baixo', [
        'produto_id' => $data['id'],
        'nome' => $data['nome'],
        'estoque_atual' => $data['estoque']
    ]);
    
    // Enviar email para administradores
    $admins = \Models\Usuario::where('tipo', '=', 'admin')->get();
    
    foreach ($admins as $admin) {
        Mailer::send(
            $admin->email,
            'Alerta: Estoque Baixo',
            'emails/estoque_baixo',
            [
                'produto' => $data['nome'],
                'estoque' => $data['estoque']
            ]
        );
    }
});

// ========================================
// EVENTOS DE PEDIDO
// ========================================

// Quando pedido é criado
EventDispatcher::listen('pedido.criado', function($data) {
    Logger::getInstance()->info('Pedido criado', [
        'id' => $data['id'],
        'cliente_id' => $data['cliente_id'],
        'total' => $data['total']
    ]);
    
    // Enviar email de confirmação
    Mailer::send(
        $data['cliente_email'],
        'Pedido Confirmado',
        'emails/pedido_confirmado',
        [
            'pedido_id' => $data['id'],
            'total' => $data['total']
        ]
    );
});

// Quando pedido é pago
EventDispatcher::listen('pedido.pago', function($data) {
    Logger::getInstance()->info('Pagamento confirmado', [
        'pedido_id' => $data['id'],
        'valor' => $data['valor'],
        'metodo' => $data['metodo_pagamento']
    ]);
    
    // Atualizar estoque dos produtos
    foreach ($data['itens'] as $item) {
        $produto = \Models\Produto::find($item['produto_id']);
        if ($produto) {
            $produto->estoque -= $item['quantidade'];
            $produto->save();
            
            // Verificar se estoque ficou baixo
            if ($produto->estoque < 10) {
                EventDispatcher::dispatch('produto.estoque.baixo', [
                    'id' => $produto->id,
                    'nome' => $produto->nome,
                    'estoque' => $produto->estoque
                ]);
            }
        }
    }
});

// Quando pedido é cancelado
EventDispatcher::listen('pedido.cancelado', function($data) {
    Logger::getInstance()->warning('Pedido cancelado', [
        'pedido_id' => $data['id'],
        'motivo' => $data['motivo'] ?? 'não informado'
    ]);
    
    // Enviar email
    Mailer::send(
        $data['cliente_email'],
        'Pedido Cancelado',
        'emails/pedido_cancelado',
        [
            'pedido_id' => $data['id'],
            'motivo' => $data['motivo'] ?? 'não informado'
        ]
    );
});

// ========================================
// EVENTOS DE BACKUP
// ========================================

// Backup automático após ações críticas
EventDispatcher::listen('usuario.criado', function($data) {
    // Só faz backup se for admin
    if (isset($data['tipo']) && $data['tipo'] === 'admin') {
        try {
            $backup = new DatabaseBackup();
            $backup->backup(['usuarios']);
            
            Logger::getInstance()->info('Backup automático após criação de admin', [
                'usuario_id' => $data['id']
            ]);
        } catch (Exception $e) {
            Logger::getInstance()->error('Falha no backup automático', [
                'error' => $e->getMessage()
            ]);
        }
    }
}, priority: 1); // Baixa prioridade - executa por último

// ========================================
// EVENTOS DE SISTEMA
// ========================================

// Quando ocorre erro crítico
EventDispatcher::listen('sistema.erro', function($data) {
    Logger::getInstance()->critical('Erro crítico no sistema', [
        'mensagem' => $data['mensagem'],
        'arquivo' => $data['arquivo'] ?? null,
        'linha' => $data['linha'] ?? null,
        'trace' => $data['trace'] ?? null
    ]);
    
    // Enviar email para desenvolvedores
    if (getenv('APP_ENV') === 'production') {
        Mailer::send(
            getenv('DEV_EMAIL'),
            'ERRO CRÍTICO: ' . getenv('APP_NAME'),
            'emails/erro_critico',
            [
                'mensagem' => $data['mensagem'],
                'detalhes' => $data
            ]
        );
    }
});

// ========================================
// LIMPEZA E MANUTENÇÃO
// ========================================

// Executar limpeza de backups antigos (manter últimos 7)
EventDispatcher::listen('sistema.manutencao', function($data) {
    try {
        $backup = new DatabaseBackup();
        $deleted = $backup->cleanup(7);
        
        Logger::getInstance()->info('Limpeza de backups executada', [
            'deletados' => $deleted
        ]);
    } catch (Exception $e) {
        Logger::getInstance()->error('Falha na limpeza de backups', [
            'error' => $e->getMessage()
        ]);
    }
});

// Rotação de logs
EventDispatcher::listen('sistema.manutencao', function($data) {
    $logger = Logger::getInstance();
    $logger->rotateLogs();
    
    Logger::getInstance()->info('Rotação de logs executada');
});
