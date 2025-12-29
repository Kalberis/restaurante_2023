<?php

namespace Core;

/**
 * JWT (JSON Web Token) Handler para autenticação stateless
 * Formato: Header.Payload.Signature
 */
class JwtHandler
{
    private string $secret;
    private string $algorithm = 'HS256';
    private int $expiration = 3600; // 1 hora
    private int $refresh_expiration = 604800; // 7 dias

    public function __construct(string $secret = null)
    {
        $this->secret = $secret ?? $_ENV['JWT_SECRET'] ?? 'your-secret-key-change-this';
        
        if (strlen($this->secret) < 32) {
            Logger::getInstance()->warning('JWT secret é muito curto. Use mínimo 32 caracteres.');
        }
    }

    /**
     * Gera um token JWT
     */
    public function encode(array $payload, int $expiration = null): string
    {
        $expiration = $expiration ?? $this->expiration;

        // Adiciona claims padrão
        $payload['iat'] = time();
        $payload['exp'] = time() + $expiration;
        $payload['alg'] = $this->algorithm;

        $header = $this->base64Encode(json_encode([
            'typ' => 'JWT',
            'alg' => $this->algorithm
        ]));

        $payload_encoded = $this->base64Encode(json_encode($payload));

        $signature = $this->createSignature("{$header}.{$payload_encoded}");

        return "{$header}.{$payload_encoded}.{$signature}";
    }

    /**
     * Valida e decodifica um token JWT
     */
    public function decode(string $token): ?array
    {
        try {
            $parts = explode('.', $token);

            if (count($parts) !== 3) {
                throw new \Exception('Token inválido: formato incorreto');
            }

            [$header, $payload, $signature] = $parts;

            // Verifica assinatura
            $expected_signature = $this->createSignature("{$header}.{$payload}");
            if (!hash_equals($signature, $expected_signature)) {
                throw new \Exception('Token inválido: assinatura não corresponde');
            }

            // Decodifica payload
            $decoded = json_decode($this->base64Decode($payload), true);

            if ($decoded === null) {
                throw new \Exception('Token inválido: payload não é JSON válido');
            }

            // Verifica expiração
            if (isset($decoded['exp']) && time() > $decoded['exp']) {
                throw new \Exception('Token expirado');
            }

            return $decoded;
        } catch (\Exception $e) {
            Logger::getInstance()->warning('Erro ao decodificar JWT', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Cria token de refresh (vida útil maior)
     */
    public function refreshToken(array $payload): string
    {
        return $this->encode($payload, $this->refresh_expiration);
    }

    /**
     * Valida um token por um tempo mínimo
     */
    public function isValid(string $token): bool
    {
        return $this->decode($token) !== null;
    }

    /**
     * Obtém tempo até expiração (em segundos)
     */
    public function getTimeToExpiration(string $token): ?int
    {
        $payload = $this->decode($token);
        
        if ($payload === null || !isset($payload['exp'])) {
            return null;
        }

        $remaining = $payload['exp'] - time();
        return max(0, $remaining);
    }

    /**
     * Cria assinatura HMAC
     */
    private function createSignature(string $data): string
    {
        $hash = hash_hmac('sha256', $data, $this->secret, true);
        return $this->base64Encode($hash);
    }

    /**
     * Encode Base64 URL-safe
     */
    private function base64Encode(string $data): string
    {
        $base64 = base64_encode($data);
        return str_replace(['+', '/', '='], ['-', '_', ''], $base64);
    }

    /**
     * Decode Base64 URL-safe
     */
    private function base64Decode(string $data): string
    {
        $padding = 4 - (strlen($data) % 4);
        if ($padding !== 4) {
            $data .= str_repeat('=', $padding);
        }
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    }

    /**
     * Extrai token do header Authorization
     */
    public static function extractFromHeader(): ?string
    {
        $headers = getallheaders();
        $auth_header = $headers['Authorization'] ?? null;

        if ($auth_header && preg_match('/Bearer\s+(\S+)/', $auth_header, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
