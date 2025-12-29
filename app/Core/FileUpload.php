<?php

namespace Core;

/**
 * Sistema de upload com validação e segurança
 */
class FileUpload
{
    private array $allowedTypes = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx'],
        'all' => []
    ];

    private int $maxSize = 5242880; // 5MB
    private string $uploadPath = 'storage/uploads/';

    public function __construct(string $uploadPath = null)
    {
        if ($uploadPath) {
            $this->uploadPath = rtrim($uploadPath, '/') . '/';
        }
    }

    /**
     * Define tipos permitidos
     */
    public function setAllowedTypes(string $type): self
    {
        if (!isset($this->allowedTypes[$type])) {
            throw new \InvalidArgumentException("Tipo inválido: {$type}");
        }
        
        $this->allowedTypes['current'] = $this->allowedTypes[$type];
        return $this;
    }

    /**
     * Define tamanho máximo em bytes
     */
    public function setMaxSize(int $bytes): self
    {
        $this->maxSize = $bytes;
        return $this;
    }

    /**
     * Faz upload de arquivo único
     */
    public function upload(array $file): array
    {
        // Validações
        $this->validateFile($file);

        // Gera nome único
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = uniqid() . '_' . time() . '.' . $extension;
        
        // Cria diretório se não existir
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }

        $destination = $this->uploadPath . $filename;

        // Move arquivo
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new \RuntimeException('Falha ao mover arquivo');
        }

        Logger::getInstance()->info('Arquivo enviado', [
            'original' => $file['name'],
            'saved' => $filename,
            'size' => $file['size']
        ]);

        return [
            'original_name' => $file['name'],
            'filename' => $filename,
            'path' => $destination,
            'size' => $file['size'],
            'extension' => $extension,
            'mime_type' => mime_content_type($destination)
        ];
    }

    /**
     * Upload múltiplo
     */
    public function uploadMultiple(array $files): array
    {
        $uploaded = [];

        foreach ($files['name'] as $key => $name) {
            $file = [
                'name' => $files['name'][$key],
                'type' => $files['type'][$key],
                'tmp_name' => $files['tmp_name'][$key],
                'error' => $files['error'][$key],
                'size' => $files['size'][$key]
            ];

            try {
                $uploaded[] = $this->upload($file);
            } catch (\Exception $e) {
                Logger::getInstance()->warning("Falha no upload", [
                    'file' => $name,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $uploaded;
    }

    /**
     * Valida arquivo
     */
    private function validateFile(array $file): void
    {
        // Verifica erro
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException($this->getUploadError($file['error']));
        }

        // Verifica tamanho
        if ($file['size'] > $this->maxSize) {
            throw new \RuntimeException("Arquivo muito grande. Máximo: " . 
                $this->formatBytes($this->maxSize));
        }

        // Verifica extensão
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (isset($this->allowedTypes['current']) && 
            !empty($this->allowedTypes['current']) && 
            !in_array($extension, $this->allowedTypes['current'])) {
            throw new \RuntimeException("Tipo de arquivo não permitido: {$extension}");
        }

        // Validação extra de segurança - verifica mime type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (strpos($mimeType, 'php') !== false) {
            throw new \RuntimeException('Tipo de arquivo perigoso detectado');
        }
    }

    /**
     * Retorna mensagem de erro de upload
     */
    private function getUploadError(int $code): string
    {
        return match($code) {
            UPLOAD_ERR_INI_SIZE => 'Arquivo excede upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'Arquivo excede MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'Upload parcial',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo enviado',
            UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário ausente',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever no disco',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão PHP',
            default => 'Erro desconhecido no upload'
        };
    }

    /**
     * Formata bytes em formato legível
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * Deleta arquivo
     */
    public function delete(string $filename): bool
    {
        $path = $this->uploadPath . $filename;
        
        if (file_exists($path)) {
            return unlink($path);
        }

        return false;
    }
}
