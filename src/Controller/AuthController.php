<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

/**
 * Connexion et déconnexion.
 */
final class AuthController extends AbstractController
{
    public function showLogin(Request $request): Response
    {
        return $this->renderLogin(['email' => ''], [], null);
    }

    public function login(Request $request): Response
    {
        $data = [
            'email'    => (string) $request->input('email'),
            'password' => (string) $request->input('password'),
        ];

        $validator = (new Validator($data))
            ->required('email', 'Saisissez votre adresse e-mail.')
            ->email('email')
            ->required('password', 'Saisissez votre mot de passe.');

        if ($validator->fails()) {
            return $this->renderLogin($data, $validator->errors(), null, 422);
        }

        $user = $this->auth->attempt($data['email'], $data['password']);
        if ($user === null) {
            // Message volontairement vague : ne pas indiquer lequel des deux champs est faux.
            return $this->renderLogin($data, [], 'Identifiants incorrects ou compte désactivé.', 422);
        }

        $this->auth->login($user);

        return $this->success('Accréditation validée. Bienvenue, ' . $user->firstname . '.', '/');
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout();

        // La session vient d'être détruite : on en démarre une neuve pour le message flash.
        $this->session->start();

        return $this->success('Vous êtes déconnecté.', '/login');
    }

    /**
     * @param array{email: string} $values
     * @param array<string, string> $errors
     */
    private function renderLogin(array $values, array $errors, ?string $alert, int $status = 200): Response
    {
        $html = $this->view->render('auth/login', [
            'title'  => 'Connexion',
            'values' => ['email' => $values['email']], // le mot de passe n'est jamais renvoyé
            'errors' => $errors,
            'alert'  => $alert,
        ], 'layouts/auth');

        return new Response($html, $status);
    }
}
