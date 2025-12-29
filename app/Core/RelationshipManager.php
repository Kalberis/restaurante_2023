<?php

namespace Core;

/**
 * Gerenciador de relacionamentos e eager loading
 * Evita problema N+1 carregando relacionamentos antecipadamente
 */
class RelationshipManager
{
    private array $loaded_relations = [];
    private array $relations_to_load = [];

    /**
     * Define relacionamento para carregar antecipadamente
     */
    public function with(string $relation, callable $query = null): self
    {
        $this->relations_to_load[$relation] = $query;
        return $this;
    }

    /**
     * Carrega múltiplos relacionamentos
     */
    public function withMany(array $relations): self
    {
        foreach ($relations as $relation => $query) {
            if (is_int($relation)) {
                // Se for índice numérico, $query é o nome da relação
                $this->relations_to_load[$query] = null;
            } else {
                $this->relations_to_load[$relation] = $query;
            }
        }
        return $this;
    }

    /**
     * Carrega relacionamento para modelo
     */
    public function loadRelation(Model $model, string $relation): Model
    {
        if (isset($this->loaded_relations[$relation])) {
            return $model;
        }

        // Obtém método do relacionamento do modelo
        if (!method_exists($model, $relation)) {
            throw new \BadMethodCallException(
                "Método de relacionamento '{$relation}' não existe em " . get_class($model)
            );
        }

        // Executa o método de relacionamento
        $related = $model->$relation();
        
        $this->loaded_relations[$relation] = true;
        
        return $model;
    }

    /**
     * Carrega todos os relacionamentos para múltiplos modelos
     */
    public function loadForCollection(array $models): array
    {
        foreach ($models as $model) {
            $this->loadForModel($model);
        }
        return $models;
    }

    /**
     * Carrega todos os relacionamentos para um modelo
     */
    public function loadForModel(Model $model): Model
    {
        foreach ($this->relations_to_load as $relation => $query) {
            $this->loadRelation($model, $relation);
        }
        return $model;
    }

    /**
     * Retorna relacionamentos para carregar
     */
    public function getRelationsToLoad(): array
    {
        return $this->relations_to_load;
    }

    /**
     * Limpa relacionamentos carregados
     */
    public function clear(): self
    {
        $this->loaded_relations = [];
        $this->relations_to_load = [];
        return $this;
    }
}
