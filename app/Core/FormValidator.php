<?php

namespace Core;

/**
 * Validador de formulários com regras customizáveis
 */
class FormValidator
{
    private array $data = [];
    private array $rules = [];
    private array $messages = [];
    private array $errors = [];

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Define regras de validação
     */
    public function rules(array $rules): self
    {
        $this->rules = $rules;
        return $this;
    }

    /**
     * Define mensagens de erro customizadas
     */
    public function messages(array $messages): self
    {
        $this->messages = $messages;
        return $this;
    }

    /**
     * Executa validação
     */
    public function validate(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $field => $rules_str) {
            $rules_array = explode('|', $rules_str);
            $value = $this->data[$field] ?? null;

            foreach ($rules_array as $rule) {
                $this->validateRule($field, $value, trim($rule));
            }
        }

        return empty($this->errors);
    }

    /**
     * Valida uma regra específica
     */
    private function validateRule(string $field, $value, string $rule): void
    {
        if (isset($this->errors[$field])) {
            return; // Para na primeira falha
        }

        // Parse da regra (ex: "max:10" -> ["max", "10"])
        $parts = explode(':', $rule, 2);
        $rule_name = $parts[0];
        $rule_param = $parts[1] ?? null;

        $result = match($rule_name) {
            'required' => $this->validateRequired($value),
            'email' => $this->validateEmail($value),
            'min' => $this->validateMin($value, $rule_param),
            'max' => $this->validateMax($value, $rule_param),
            'minlength' => $this->validateMinLength($value, $rule_param),
            'maxlength' => $this->validateMaxLength($value, $rule_param),
            'numeric' => $this->validateNumeric($value),
            'integer' => $this->validateInteger($value),
            'float' => $this->validateFloat($value),
            'boolean' => $this->validateBoolean($value),
            'url' => $this->validateUrl($value),
            'regex' => $this->validateRegex($value, $rule_param),
            'confirmed' => $this->validateConfirmed($field, $value),
            'unique' => $this->validateUnique($field, $value, $rule_param),
            'in' => $this->validateIn($value, $rule_param),
            'same' => $this->validateSame($field, $value, $rule_param),
            'date' => $this->validateDate($value),
            'cpf' => $this->validateCPF($value),
            'phone' => $this->validatePhone($value),
            default => true
        };

        if (!$result) {
            $this->setError($field, $rule_name, $rule_param);
        }
    }

    // Validadores
    private function validateRequired($value): bool
    {
        return !empty($value) && trim((string)$value) !== '';
    }

    private function validateEmail($value): bool
    {
        if (empty($value)) return true;
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function validateMin($value, $param): bool
    {
        if (empty($value)) return true;
        return (int)$value >= (int)$param;
    }

    private function validateMax($value, $param): bool
    {
        if (empty($value)) return true;
        return (int)$value <= (int)$param;
    }

    private function validateMinLength($value, $param): bool
    {
        if (empty($value)) return true;
        return strlen($value) >= (int)$param;
    }

    private function validateMaxLength($value, $param): bool
    {
        if (empty($value)) return true;
        return strlen($value) <= (int)$param;
    }

    private function validateNumeric($value): bool
    {
        if (empty($value)) return true;
        return is_numeric($value);
    }

    private function validateInteger($value): bool
    {
        if (empty($value)) return true;
        return is_int($value) || ctype_digit((string)$value);
    }

    private function validateFloat($value): bool
    {
        if (empty($value)) return true;
        return is_float($value) || (is_numeric($value) && strpos($value, '.') !== false);
    }

    private function validateBoolean($value): bool
    {
        if (empty($value)) return true;
        return in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false'], true);
    }

    private function validateUrl($value): bool
    {
        if (empty($value)) return true;
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private function validateRegex($value, $pattern): bool
    {
        if (empty($value)) return true;
        return preg_match($pattern, $value) === 1;
    }

    private function validateConfirmed(string $field, $value): bool
    {
        $confirmation_field = $field . '_confirmation';
        return isset($this->data[$confirmation_field]) && $this->data[$confirmation_field] === $value;
    }

    private function validateIn($value, $options): bool
    {
        if (empty($value)) return true;
        $allowed = explode(',', $options);
        return in_array($value, array_map('trim', $allowed));
    }

    private function validateSame(string $field, $value, string $other): bool
    {
        return ($this->data[$other] ?? null) === $value;
    }

    private function validateDate($value): bool
    {
        if (empty($value)) return true;
        $formats = ['Y-m-d', 'Y-m-d H:i:s', 'd/m/Y'];
        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, $value);
            if ($parsed !== false) return true;
        }
        return false;
    }

    private function validateCPF($value): bool
    {
        if (empty($value)) return true;
        
        $cpf = preg_replace('/\D/', '', $value);
        if (strlen($cpf) !== 11) return false;

        // Verifica se todos os dígitos são iguais
        if (preg_match('/^(\d)\1{10}$/', $cpf)) return false;

        // Valida dígitos verificadores
        for ($i = 0; $i < 2; $i++) {
            $sum = 0;
            $multiplier = $i === 0 ? 10 : 11;
            
            for ($j = 0; $j < $multiplier - 1; $j++) {
                $sum += (int)$cpf[$j] * ($multiplier - $j);
            }
            
            $digit = (int)((10 * $sum) % 11);
            if ($digit === 10) $digit = 0;
            
            if ((int)$cpf[9 + $i] !== $digit) return false;
        }

        return true;
    }

    private function validatePhone($value): bool
    {
        if (empty($value)) return true;
        return preg_match('/^\(?[0-9]{2}\)?9?[0-9]{4}-?[0-9]{4}$/', $value) === 1;
    }

    private function validateUnique(string $field, $value, string $config): bool
    {
        // Formato: "table:column" ou "table:column:ignore_id"
        if (empty($value)) return true;

        $parts = explode(':', $config);
        if (count($parts) < 2) return true;

        $table = $parts[0];
        $column = $parts[1];
        $ignore_id = $parts[2] ?? null;

        try {
            $query = "SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?";
            $params = [$value];

            if ($ignore_id) {
                $query .= " AND id != ?";
                $params[] = $ignore_id;
            }

            $stmt = Connection::getInstance()->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $result['count'] == 0;
        } catch (\Exception $e) {
            Logger::getInstance()->warning("Erro ao validar unique", ['error' => $e->getMessage()]);
            return true;
        }
    }

    /**
     * Define erro para um campo
     */
    private function setError(string $field, string $rule, $param = null): void
    {
        $message = $this->messages["{$field}.{$rule}"] ?? $this->getDefaultMessage($rule, $param);
        $this->errors[$field] = $message;
    }

    /**
     * Retorna mensagem de erro padrão
     */
    private function getDefaultMessage(string $rule, $param = null): string
    {
        return match($rule) {
            'required' => 'Este campo é obrigatório',
            'email' => 'E-mail inválido',
            'min' => "Mínimo de {$param}",
            'max' => "Máximo de {$param}",
            'minlength' => "Mínimo {$param} caracteres",
            'maxlength' => "Máximo {$param} caracteres",
            'numeric' => 'Deve ser um número',
            'integer' => 'Deve ser um inteiro',
            'float' => 'Deve ser um decimal',
            'url' => 'URL inválida',
            'confirmed' => 'Confirmação não corresponde',
            'unique' => 'Valor já existe',
            'in' => 'Valor inválido',
            'date' => 'Data inválida',
            'cpf' => 'CPF inválido',
            'phone' => 'Telefone inválido',
            default => 'Valor inválido'
        };
    }

    /**
     * Retorna erros
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Obtém erro específico
     */
    public function getError(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    /**
     * Verifica se tem erro
     */
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    /**
     * Retorna dados validados (apenas campos com regras)
     */
    public function validated(): array
    {
        $validated = [];
        foreach ($this->rules as $field => $rule) {
            if (isset($this->data[$field]) && !isset($this->errors[$field])) {
                $validated[$field] = $this->data[$field];
            }
        }
        return $validated;
    }
}
