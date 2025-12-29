<?php

namespace Core;

abstract class Model
{
    /**
     * Nome da tabela no banco de dados
     */
    protected string $table = '';

    protected array $columns = [];

    private array $__data = [];

    private array $where = [];

    private int $limit_value = 0;

    private int $offset_value = 0;

    protected string $pk = 'id';

    private bool $__storage = false;

    protected bool $__protected_delete = false;

    private string $__protected_delete_column = 'exclusao_data';

    protected bool $__audit_date = false;

    private array $__audit_date_columns = ['create' => 'criacao_data', 'alter' => 'alteracao_data'];

    private static array $query_cache = [];

    public function __construct($id = null)
    {
        if($this->__audit_date){
            $this->columns = array_merge($this->columns, array_values($this->__audit_date_columns));
        }
        if(isset($id)){
            $this->load($id);
        }
    }

    /**
     * Valida se coluna existe antes de usar em queries
     */
    protected function validateColumn(string $column): void
    {
        if (!in_array($column, $this->columns) && $column !== $this->pk) {
            throw new \InvalidArgumentException("Coluna '{$column}' não existe na tabela '{$this->table}'");
        }
    }

    public function __set(string $name, $value): void
    {
        if(in_array($name, $this->columns)){
            $this->__data[$name] = $value;
        }
    }

    public function __get(string $name)
    {
        return (array_key_exists($name, $this->__data)) ? $this->__data[$name] : null;
    }

    public function query(string $sql, array $data = [])
    {
        $conn = Connection::getInstance();
        $stm = $conn->prepare($sql);
        $stm->execute($data);
        return $stm;
    }

    private function load($id): void
    {
        $this->where($this->pk, '=', $id);
        $stm = $this->select();
        $result = $stm->fetch(\PDO::FETCH_ASSOC);
        if($result){
            $this->__data = $result;
            $this->__storage = true;
        }
    }

    /**
     * Insere no banco de dados
     */
    protected function insert(array $data): self
    {
        $columns = implode(', ', array_keys($data));
        $values = implode(', :', array_keys($data));
        $sql = "INSERT INTO {$this->table} ({$columns}) values (:{$values});";
        $this->query($sql, $data);
        $id = $this->getLastInsertId();
        $pk = $this->pk;
        $this->__data = $data;
        $this->__storage = true;
        $this->$pk = $id;
        return $this;
    }

    public function getLastInsertId(): int
    {
        $conn = Connection::getInstance();
        return (int)$conn->lastInsertId($this->table);
    }

    protected function update(array $data): self
    {
        if($this->__audit_date){
            $data[$this->__audit_date_columns['alter']] = date('Y-m-d H:i:s');
        }

        // Valida colunas para evitar SQL injection
        foreach (array_keys($data) as $column) {
            $this->validateColumn($column);
        }

        $sql = "UPDATE {$this->table} SET";
        $comma = '';
        foreach($data as $key => $value){
            $sql .= "{$comma} {$key} = :{$key}";
            $comma = ',';
        }
        $sql .= " WHERE {$this->pk} = :w0";
        $this->query($sql, array_merge($data, ['w0' => $this->{$this->pk}]));
        $this->__data = $data;
        return $this;
    }

    public function save(array $data = []): self
    {
        $data = array_merge($this->__data, $data);
        if($this->__storage){
            return $this->update($data);
        }else{
            return $this->insert($data);
        }
    }

    public function delete(): bool
    {
        if($this->__storage){
            if ($this->__protected_delete) {
                $this->update([$this->__protected_delete_column => date('Y-m-d H:i:s')]);
            }else{
                $sql = "DELETE FROM {$this->table} WHERE {$this->pk} = :{$this->pk};";
                $this->query($sql, [$this->pk => $this->{$this->pk}]);
            }
            $this->__storage = false;
            return true;
        }
        return false;      
    }

    /**
     * Limita resultados (pagination)
     */
    public function limit(int $limit): self
    {
        $this->limit_value = max(0, $limit);
        return $this;
    }

    /**
     * Define offset (pagination)
     */
    public function offset(int $offset): self
    {
        $this->offset_value = max(0, $offset);
        return $this;
    }

    /**
     * Pagination helper (page e itens por página)
     */
    public function paginate(int $per_page = 15, int $page = 1): self
    {
        $page = max(1, $page);
        $per_page = max(1, $per_page);
        return $this->limit($per_page)->offset(($page - 1) * $per_page);
    }

    private function select()
    {
        $columns = implode(', ', $this->columns);
        [$where, $data] = $this->flushWhere();
        $sql = "SELECT {$columns} FROM {$this->table}{$where}";

        if ($this->limit_value > 0) {
            $sql .= " LIMIT {$this->limit_value}";
        }

        if ($this->offset_value > 0) {
            $sql .= " OFFSET {$this->offset_value}";
        }

        $sql .= ";";

        return $this->query($sql, $data);
    }

    public function all(): array
    {
        $result = $this->select()->fetchAll(\PDO::FETCH_CLASS, get_class($this));
        array_walk($result, function(&$obj){
            $obj->__storage = true;
        });
        return $result;
    }

    public function get()
    {
        $result = $this->select()->fetchObject(get_class($this));
        if($result){
            $result->__storage = true;
        }
        return $result;
    }

    public function where(string $column, string $comparison, $value): self
    {
        $this->validateColumn($column);
        $this->where[] = ['AND', $column, $comparison, $value];
        return $this;
    }

    public function orWhere(string $column, string $comparison, $value): self
    {
        $this->validateColumn($column);
        $this->where[] = ['OR', $column, $comparison, $value];
        return $this;
    }

    private function flushWhere(): array
    {
        $where = '';
        $data = [];
        if(count($this->where) > 0){
            $this->where[0][0] = '';
            foreach($this->where as $key => $w){
                $where .= " {$w[0]} {$w[1]} {$w[2]} :w{$key}";
                $data["w{$key}"] = $w[3];
            }
            $this->where = [];
        }
        if($this->__protected_delete){
            if(empty($where)){
                $where = " WHERE {$this->__protected_delete_column} IS NULL";
            }else{
                $where = " WHERE ({$this->__protected_delete_column} IS NULL) AND ({$where})";
            }
        }else if(!empty($where)){
            $where = " WHERE {$where}";
        }
        return [$where, $data];
    }

    public function getData(): array
    {
        return $this->__data;
    }

    public function isStorage(): bool
    {
        return $this->__storage;
    }
}