<?php

namespace Core;

/**
 * Sistema de envio de emails com suporte a templates
 */
class Mailer
{
    private string $from;
    private string $from_name = 'Restaurante';
    private array $to = [];
    private array $cc = [];
    private array $bcc = [];
    private string $subject = '';
    private string $body = '';
    private array $attachments = [];
    private bool $is_html = true;

    private ?self $instance = null;

    public function __construct()
    {
        $this->from = $_ENV['MAIL_FROM'] ?? 'noreply@restaurante.local';
        $this->from_name = $_ENV['APP_NAME'] ?? 'Restaurante';
    }

    /**
     * Define destinatário
     */
    public function to(string $email, string $name = ''): self
    {
        $this->to[] = ['email' => $email, 'name' => $name];
        return $this;
    }

    /**
     * Adiciona CC
     */
    public function cc(string $email, string $name = ''): self
    {
        $this->cc[] = ['email' => $email, 'name' => $name];
        return $this;
    }

    /**
     * Adiciona BCC
     */
    public function bcc(string $email, string $name = ''): self
    {
        $this->bcc[] = ['email' => $email, 'name' => $name];
        return $this;
    }

    /**
     * Define assunto
     */
    public function subject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Define body em HTML
     */
    public function html(string $html): self
    {
        $this->body = $html;
        $this->is_html = true;
        return $this;
    }

    /**
     * Define body em texto plano
     */
    public function text(string $text): self
    {
        $this->body = $text;
        $this->is_html = false;
        return $this;
    }

    /**
     * Carrega template e renderiza com dados
     */
    public function template(string $template, array $data = []): self
    {
        $template_path = BASE_PATH . '/app/Templates/emails/' . $template . '.html';

        if (!file_exists($template_path)) {
            throw new \RuntimeException("Template de email não encontrado: {$template}");
        }

        // Renderiza template com variáveis
        ob_start();
        extract($data);
        include $template_path;
        $html = ob_get_clean();

        return $this->html($html);
    }

    /**
     * Adiciona anexo
     */
    public function attach(string $filepath, string $filename = null): self
    {
        if (!file_exists($filepath)) {
            throw new \RuntimeException("Arquivo não encontrado: {$filepath}");
        }

        $this->attachments[] = [
            'path' => $filepath,
            'name' => $filename ?? basename($filepath)
        ];

        return $this;
    }

    /**
     * Envia email via SMTP nativo do PHP
     */
    public function send(): bool
    {
        try {
            if (empty($this->to)) {
                throw new \RuntimeException('Nenhum destinatário especificado');
            }

            if (empty($this->subject)) {
                throw new \RuntimeException('Assunto não especificado');
            }

            if (empty($this->body)) {
                throw new \RuntimeException('Body não especificado');
            }

            // Constrói headers
            $headers = $this->buildHeaders();

            // Separa múltiplos destinatários
            $to_string = implode(', ', array_map(function($recipient) {
                return $this->formatEmail($recipient);
            }, $this->to));

            // Envia email
            $result = mail(
                $to_string,
                $this->subject,
                $this->body,
                $headers
            );

            if ($result) {
                Logger::getInstance()->info('Email enviado com sucesso', [
                    'to' => $to_string,
                    'subject' => $this->subject
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::getInstance()->error('Erro ao enviar email', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Constrói headers do email
     */
    private function buildHeaders(): string
    {
        $headers = [];

        // From
        $headers[] = 'From: ' . $this->formatEmail(['email' => $this->from, 'name' => $this->from_name]);

        // Reply-To
        $headers[] = 'Reply-To: ' . $this->from;

        // CC
        if (!empty($this->cc)) {
            $cc_string = implode(', ', array_map(fn($r) => $this->formatEmail($r), $this->cc));
            $headers[] = 'Cc: ' . $cc_string;
        }

        // BCC
        if (!empty($this->bcc)) {
            $bcc_string = implode(', ', array_map(fn($r) => $this->formatEmail($r), $this->bcc));
            $headers[] = 'Bcc: ' . $bcc_string;
        }

        // Content type
        if ($this->is_html) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        }

        // Encoding
        $headers[] = 'Content-Transfer-Encoding: 8bit';

        // Adicional
        $headers[] = 'X-Mailer: Restaurante-2023';

        return implode("\r\n", $headers);
    }

    /**
     * Formata email com nome
     */
    private function formatEmail(array $recipient): string
    {
        if (!empty($recipient['name'])) {
            return "\"{$recipient['name']}\" <{$recipient['email']}>";
        }
        return $recipient['email'];
    }

    /**
     * Retorna instância singleton
     */
    public static function getInstance(): self
    {
        static $instance = null;
        
        if ($instance === null) {
            $instance = new self();
        }

        return $instance;
    }

    /**
     * Helper estático para envio rápido
     */
    public static function sendTo(string $email, string $subject, string $body, bool $is_html = true): bool
    {
        $mailer = new self();
        $mailer->to($email);
        $mailer->subject($subject);
        
        if ($is_html) {
            $mailer->html($body);
        } else {
            $mailer->text($body);
        }

        return $mailer->send();
    }
}
