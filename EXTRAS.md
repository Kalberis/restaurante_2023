# 🚀 Funcionalidades Extras Implementadas

## Data de implementação: <?= date('d/m/Y H:i') ?>

---

## 📦 1. Sistema de Eventos (EventDispatcher)

### Descrição
Permite desacoplar código através de eventos e listeners.

### Localização
- `app/Core/EventDispatcher.php`

### Funcionalidades
- ✅ Registro de listeners com prioridade
- ✅ Disparo de eventos com dados
- ✅ Múltiplos listeners por evento
- ✅ Remoção de listeners
- ✅ Logging automático de eventos

### Exemplo de Uso

```php
// Registrar listener
EventDispatcher::listen('usuario.criado', function($data) {
    // Enviar email de boas-vindas
    Mailer::send($data['email'], 'Bem-vindo!', 'emails/welcome', $data);
}, priority: 10);

EventDispatcher::listen('usuario.criado', function($data) {
    // Criar log
    Logger::getInstance()->info('Novo usuário', $data);
}, priority: 5);

// Disparar evento
EventDispatcher::dispatch('usuario.criado', [
    'id' => $usuario->id,
    'nome' => $usuario->nome,
    'email' => $usuario->email
]);
```

### Eventos Sugeridos
- `usuario.criado` - Quando usuário é cadastrado
- `usuario.login` - Quando usuário faz login
- `produto.criado` - Quando produto é cadastrado
- `pedido.criado` - Quando pedido é realizado
- `pedido.pago` - Quando pagamento é confirmado

---

## 📤 2. Sistema de Upload (FileUpload)

### Descrição
Upload seguro de arquivos com validação completa.

### Localização
- `app/Core/FileUpload.php`

### Funcionalidades
- ✅ Validação de tipo de arquivo
- ✅ Validação de tamanho
- ✅ Nomes únicos para arquivos
- ✅ Upload múltiplo
- ✅ Detecção de mime-type
- ✅ Proteção contra arquivos maliciosos
- ✅ Logging de uploads
- ✅ Deleção de arquivos

### Exemplo de Uso

```php
// Upload único
$uploader = new FileUpload('storage/uploads/produtos/');
$uploader->setAllowedTypes('image')
         ->setMaxSize(2097152); // 2MB

try {
    $result = $uploader->upload($_FILES['foto']);
    
    echo "Arquivo: " . $result['filename'];
    echo "Tamanho: " . $result['size'];
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}

// Upload múltiplo
$results = $uploader->uploadMultiple($_FILES['fotos']);

foreach ($results as $file) {
    echo $file['filename'] . "\n";
}

// Deletar arquivo
$uploader->delete('arquivo.jpg');
```

### Tipos Permitidos
- `image` - jpg, jpeg, png, gif, webp
- `document` - pdf, doc, docx, xls, xlsx
- `all` - Qualquer tipo

---

## 📄 3. Sistema de Paginação (Paginator)

### Descrição
Paginação completa com HTML Bootstrap.

### Localização
- `app/Core/Paginator.php`

### Funcionalidades
- ✅ Navegação de páginas
- ✅ HTML Bootstrap responsivo
- ✅ Informações de paginação
- ✅ URLs com query params
- ✅ Conversão para array (APIs)
- ✅ Cálculos automáticos

### Exemplo de Uso

```php
// No Controller
$page = Request::get('page', 1);
$perPage = 20;

// Total de registros
$total = Produto::count();

// Buscar registros da página
$produtos = Produto::limit($perPage)
                   ->offset(($page - 1) * $perPage)
                   ->orderBy('created_at', 'DESC')
                   ->get();

// Criar paginador
$paginator = new Paginator($produtos, $total, $perPage, $page);

// Na View
echo $paginator->info(); // "Exibindo 1 a 20 de 100 registros"
echo $paginator->links('/produtos'); // HTML da paginação

// Para APIs
return json_encode($paginator->toArray());
```

### HTML Gerado

```html
<nav>
    <ul class="pagination">
        <li class="page-item"><a class="page-link" href="?page=1">Anterior</a></li>
        <li class="page-item"><a class="page-link" href="?page=1">1</a></li>
        <li class="page-item active"><a class="page-link" href="?page=2">2</a></li>
        <li class="page-item"><a class="page-link" href="?page=3">3</a></li>
        <li class="page-item"><a class="page-link" href="?page=3">Próximo</a></li>
    </ul>
</nav>
```

---

## 💾 4. Sistema de Backup (DatabaseBackup)

### Descrição
Backup e restauração automática do banco de dados.

### Localização
- `app/Core/DatabaseBackup.php`

### Funcionalidades
- ✅ Backup completo do banco
- ✅ Backup de tabelas específicas
- ✅ Compressão GZIP automática
- ✅ Restauração de backups
- ✅ Listagem de backups
- ✅ Limpeza de backups antigos
- ✅ Logging de operações

### Exemplo de Uso

```php
$backup = new DatabaseBackup();

// Criar backup completo
$filename = $backup->backup();
echo "Backup criado: {$filename}";

// Backup de tabelas específicas
$filename = $backup->backup(['usuarios', 'produtos']);

// Listar backups
$backups = $backup->list();
foreach ($backups as $bkp) {
    echo "{$bkp['filename']} - {$bkp['formatted_size']} - {$bkp['date']}\n";
}

// Restaurar backup
$backup->restore('backup_pweb_restaurante_2024-01-15_143022.sql.gz');

// Deletar backup antigo
$backup->delete('backup_old.sql.gz');

// Manter apenas últimos 5 backups
$deleted = $backup->cleanup(5);
echo "Deletados {$deleted} backups antigos";
```

### Comando para Backup Automático (Cron)

```bash
# Adicionar ao crontab (Linux)
# Backup diário às 3h da manhã
0 3 * * * cd /var/www/html && php -r "require 'vendor/autoload.php'; (new Core\DatabaseBackup())->backup(); (new Core\DatabaseBackup())->cleanup(7);"
```

### Arquivos Gerados
- `storage/backups/backup_[banco]_[data]_[hora].sql.gz`
- Comprimidos com GZIP (economia ~90% de espaço)

---

## 🎯 Como Usar Tudo Junto

### Exemplo: Cadastro de Produto com Upload e Eventos

```php
class Produtos extends Controller
{
    public function cadastrar(): void
    {
        if (Request::isPost()) {
            // Validar dados
            $validator = new FormValidator(Request::all(), [
                'nome' => 'required|min:3|max:100',
                'preco' => 'required|numeric|min:0',
                'foto' => 'required'
            ]);

            if (!$validator->validate()) {
                FlashMessage::error('Erro na validação', $validator->getErrors());
                redirect('/produtos/novo');
                return;
            }

            // Upload da foto
            $uploader = new FileUpload('storage/uploads/produtos/');
            $uploader->setAllowedTypes('image')->setMaxSize(5242880); // 5MB

            try {
                $foto = $uploader->upload($_FILES['foto']);

                // Criar produto
                $produto = new Produto();
                $produto->nome = Request::post('nome');
                $produto->preco = Request::post('preco');
                $produto->foto = $foto['filename'];
                $produto->save();

                // Disparar evento
                EventDispatcher::dispatch('produto.criado', [
                    'id' => $produto->id,
                    'nome' => $produto->nome,
                    'preco' => $produto->preco
                ]);

                FlashMessage::success('Produto cadastrado!');
                redirect('/produtos');

            } catch (Exception $e) {
                Logger::getInstance()->error('Falha ao cadastrar produto', [
                    'error' => $e->getMessage()
                ]);
                FlashMessage::error('Erro ao processar foto');
                redirect('/produtos/novo');
            }
        }

        View::render('produtos/cadastro');
    }

    public function listar(): void
    {
        $page = Request::get('page', 1);
        $perPage = 20;

        // Total e produtos
        $total = Produto::count();
        $produtos = Produto::limit($perPage)
                           ->offset(($page - 1) * $perPage)
                           ->orderBy('created_at', 'DESC')
                           ->get();

        // Paginação
        $paginator = new Paginator($produtos, $total, $perPage, $page);

        View::render('produtos/lista', [
            'produtos' => $paginator->items(),
            'pagination' => $paginator->links('/produtos'),
            'info' => $paginator->info()
        ]);
    }
}
```

### Registrar Listeners no Bootstrap (app/application.php)

```php
// Eventos de usuário
EventDispatcher::listen('usuario.criado', function($data) {
    // Email de boas-vindas
    Mailer::send($data['email'], 'Bem-vindo ao Restaurante!', 'emails/welcome', $data);
});

EventDispatcher::listen('usuario.criado', function($data) {
    // Backup após novo usuário importante
    if ($data['tipo'] === 'admin') {
        (new DatabaseBackup())->backup(['usuarios']);
    }
}, priority: 5);

// Eventos de produto
EventDispatcher::listen('produto.criado', function($data) {
    // Notificar administradores
    Logger::getInstance()->info('Novo produto cadastrado', $data);
});

// Backup automático diário
if (date('H:i') === '03:00') { // 3h da manhã
    $backup = new DatabaseBackup();
    $backup->backup();
    $backup->cleanup(7); // Mantém últimos 7 dias
}
```

---

## 📊 Resumo das Funcionalidades Extras

| Sistema | Arquivo | Linhas | Complexidade |
|---------|---------|--------|--------------|
| EventDispatcher | EventDispatcher.php | 60 | Média |
| FileUpload | FileUpload.php | 220 | Alta |
| Paginator | Paginator.php | 180 | Média |
| DatabaseBackup | DatabaseBackup.php | 240 | Alta |

**Total: 4 novos sistemas | 700+ linhas de código**

---

## ✅ Checklist de Implementação

- [x] EventDispatcher criado
- [x] FileUpload com validações de segurança
- [x] Paginator com HTML Bootstrap
- [x] DatabaseBackup com compressão
- [x] Integração com Logger
- [x] Documentação completa
- [x] Exemplos práticos

---

## 🎓 Conceitos Aplicados

1. **Event-Driven Architecture** - Desacoplamento através de eventos
2. **File Security** - Validação rigorosa de uploads
3. **Pagination Pattern** - UX melhorada para listagens grandes
4. **Backup Strategy** - Proteção de dados críticos
5. **SOLID Principles** - Single Responsibility em cada classe
6. **DRY** - Reutilização de código através de componentes
7. **Defensive Programming** - Validações em todas as entradas

---

## 🚀 Próximas Evoluções Possíveis

1. **Image Manipulation** - Redimensionar/otimizar imagens
2. **Queue System** - Processamento assíncrono de tarefas
3. **Notification System** - Push notifications e websockets
4. **Search Engine** - Busca avançada com Elasticsearch
5. **Multi-tenant** - Suporte a múltiplos restaurantes
6. **GraphQL API** - Alternativa ao REST
7. **Real-time Dashboard** - Dashboard com dados ao vivo

---

**Documentação criada em:** <?= date('d/m/Y H:i:s') ?>
