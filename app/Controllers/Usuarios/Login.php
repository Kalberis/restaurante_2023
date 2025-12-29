<?php

namespace Controllers\Usuarios;

use Components\ToastsAlert;
use Core\Controller;
use Core\RateLimiter;
use Core\Request;
use Core\Session;
use Core\View;
use Models\Usuario;

class Login extends Controller{
    public function index ()
    {
        $view = new View('usuarios.login','blank');
        $view->setTitle('Login')->show();
    }

    public function logar(Request $request)
    {
        // Validar CSRF
        try {
            $request->validateCsrf();
        } catch (\Exception $e) {
            ToastsAlert::addAlertDanger('Erro de segurança. Tente novamente.', 'Erro CSRF');
            $this->redirect();
            return;
        }

        $cpf = $request->post('cpf');
        $password = $request->post('password');

        if (empty($cpf) || empty($password)) {
            ToastsAlert::addAlertWarning('CPF e senha são obrigatórios', 'Campos vazios');
            $this->redirect();
            return;
        }

        // Rate limiting: 5 tentativas a cada 15 minutos
        $limiter = new RateLimiter('login_' . $cpf, 5, 15);
        
        if ($limiter->isLimited()) {
            $minutes = $limiter->getMinutesRemaining();
            ToastsAlert::addAlertDanger(
                "Muitas tentativas de login. Tente novamente em {$minutes} minuto(s).",
                'Bloqueado Temporariamente'
            );
            $this->redirect();
            return;
        }

        if (Usuario::login($cpf, $password)) {
            $limiter->reset(); // Limpa tentativas após sucesso
            ToastsAlert::addAlertSuccess('Bem vindo!');
            action(\Controllers\Home::class)->redirect();
        } else {
            $limiter->recordAttempt(); // Registra tentativa falha
            ToastsAlert::addAlertWarning('Usuário e/ou Senha Inválida(s)','Falha de Login');
            $this->redirect();
        }
    }

    public function logout(): void
    {
        Session::getInstance()->clearUser();
        $this->redirect();
    }
}