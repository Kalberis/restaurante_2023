<?php

namespace Core;

/**
 * Sistema de backup de banco de dados
 */
class DatabaseBackup
{
    private Connection $connection;
    private string $backupPath = 'storage/backups/';

    public function __construct()
    {
        $this->connection = Connection::getInstance();
        
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
    }

    /**
     * Cria backup completo do banco
     */
    public function backup(array $tables = []): string
    {
        $pdo = $this->connection->getConnection();
        $dbName = DotEnv::get('DB_NAME');
        
        // Se não especificou tabelas, pega todas
        if (empty($tables)) {
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        }

        $output = "-- Backup do banco {$dbName}\n";
        $output .= "-- Data: " . date('Y-m-d H:i:s') . "\n\n";
        $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $output .= $this->backupTable($table);
        }

        $output .= "SET FOREIGN_KEY_CHECKS=1;\n";

        // Salva arquivo
        $filename = 'backup_' . $dbName . '_' . date('Y-m-d_His') . '.sql';
        $filepath = $this->backupPath . $filename;
        
        file_put_contents($filepath, $output);

        // Comprime
        if (extension_loaded('zlib')) {
            $gzFilepath = $filepath . '.gz';
            $gz = gzopen($gzFilepath, 'w9');
            gzwrite($gz, $output);
            gzclose($gz);
            unlink($filepath); // Remove arquivo não comprimido
            $filepath = $gzFilepath;
            $filename .= '.gz';
        }

        Logger::getInstance()->info('Backup criado', [
            'file' => $filename,
            'size' => filesize($filepath),
            'tables' => count($tables)
        ]);

        return $filename;
    }

    /**
     * Faz backup de uma tabela específica
     */
    private function backupTable(string $table): string
    {
        $pdo = $this->connection->getConnection();
        $output = "-- Tabela: {$table}\n";

        // Estrutura da tabela
        $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
        $row = $stmt->fetch(\PDO::FETCH_NUM);
        
        $output .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $output .= $row[1] . ";\n\n";

        // Dados da tabela
        $stmt = $pdo->query("SELECT * FROM `{$table}`");
        $count = 0;

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if ($count % 100 === 0) {
                if ($count > 0) {
                    $output .= ";\n";
                }
                $output .= "INSERT INTO `{$table}` VALUES\n";
            } else {
                $output .= ",\n";
            }

            $values = array_map(function($value) use ($pdo) {
                return $value === null ? 'NULL' : $pdo->quote($value);
            }, array_values($row));

            $output .= '(' . implode(', ', $values) . ')';
            $count++;
        }

        if ($count > 0) {
            $output .= ";\n";
        }

        $output .= "\n";
        return $output;
    }

    /**
     * Restaura backup
     */
    public function restore(string $filename): bool
    {
        $filepath = $this->backupPath . $filename;

        if (!file_exists($filepath)) {
            throw new \RuntimeException("Arquivo de backup não encontrado: {$filename}");
        }

        // Descomprime se necessário
        if (pathinfo($filename, PATHINFO_EXTENSION) === 'gz') {
            $sql = gzfile($filepath);
            $sql = implode('', $sql);
        } else {
            $sql = file_get_contents($filepath);
        }

        $pdo = $this->connection->getConnection();

        try {
            $pdo->beginTransaction();
            
            // Executa comandos SQL
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $pdo->exec($statement);
                }
            }

            $pdo->commit();

            Logger::getInstance()->info('Backup restaurado', ['file' => $filename]);

            return true;

        } catch (\PDOException $e) {
            $pdo->rollBack();
            Logger::getInstance()->error('Falha ao restaurar backup', [
                'file' => $filename,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Lista backups disponíveis
     */
    public function list(): array
    {
        $files = glob($this->backupPath . 'backup_*.sql*');
        $backups = [];

        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'size' => filesize($file),
                'date' => date('Y-m-d H:i:s', filemtime($file)),
                'formatted_size' => $this->formatBytes(filesize($file))
            ];
        }

        // Ordena por data (mais recente primeiro)
        usort($backups, fn($a, $b) => strtotime($b['date']) <=> strtotime($a['date']));

        return $backups;
    }

    /**
     * Deleta backup antigo
     */
    public function delete(string $filename): bool
    {
        $filepath = $this->backupPath . $filename;
        
        if (file_exists($filepath)) {
            return unlink($filepath);
        }

        return false;
    }

    /**
     * Limpa backups antigos (mantém últimos N)
     */
    public function cleanup(int $keep = 5): int
    {
        $backups = $this->list();
        $deleted = 0;

        if (count($backups) > $keep) {
            $toDelete = array_slice($backups, $keep);
            
            foreach ($toDelete as $backup) {
                if ($this->delete($backup['filename'])) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Formata bytes
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }
}
