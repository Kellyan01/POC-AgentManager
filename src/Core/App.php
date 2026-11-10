<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exception\BusinessRuleException;
use App\Core\Exception\HttpException;
use Closure;
use Throwable;

/**
 * Traite une requête de bout en bout : CSRF, routage, contrôle d'accès,
 * appel du contrôleur, et transformation des exceptions en pages d'erreur.
 */
final class App
{
    private const ERROR_TITLES = [
        403 => 'Accès refusé',
        404 => 'Dossier introuvable',
        405 => 'Méthode non autorisée',
        409 => 'Opération impossible',
        413 => 'Fichier trop volumineux',
        500 => 'Incident technique',
    ];

    /**
     * @param array<class-string, Closure(): object> $controllerFactories
     *        fabriques des contrôleurs : seul celui de la route demandée est instancié
     */
    public function __construct(
        private readonly Router $router,
        private readonly array $controllerFactories,
        private readonly Session $session,
        private readonly Csrf $csrf,
        private readonly Auth $auth,
        private readonly View $view,
        private readonly bool $debug,
    ) {
    }

    public function handle(Request $request): Response
    {
        $this->session->start();
        $this->view->share('currentPath', $request->path());
        $this->view->share('currentUser', $this->auth->user());

        try {
            if ($request->isPost()) {
                $this->verifyPost($request);
            }

            [$route, $params] = $this->router->match($request->method(), $request->path());

            $denied = $this->checkAccess($route->access);
            if ($denied !== null) {
                return $denied;
            }

            $controller = $this->controllerFactories[$route->controller]();

            return $controller->{$route->action}($request, ...$params);
        } catch (HttpException $e) {
            return $this->errorPage($e->statusCode(), $e->getMessage());
        } catch (BusinessRuleException $e) {
            // Filet de sécurité : les contrôleurs interceptent normalement ces exceptions.
            return $this->errorPage(409, $e->getMessage());
        } catch (Throwable $e) {
            error_log((string) $e);

            return $this->errorPage(500, $this->debug ? (string) $e : '');
        }
    }

    /** Contrôle centralisé : aucune requête POST n'atteint un contrôleur sans jeton CSRF valide. */
    private function verifyPost(Request $request): void
    {
        if ($request->exceedsPostMaxSize()) {
            throw new HttpException(413, 'Le fichier envoyé dépasse la taille maximale autorisée.');
        }

        if (!$this->csrf->isValid($request->input(Csrf::FIELD))) {
            throw HttpException::forbidden(
                'Jeton de sécurité invalide ou session expirée. Rechargez la page puis recommencez.'
            );
        }
    }

    /** Rend une redirection si l'accès est refusé, null si la route peut être servie. */
    private function checkAccess(Access $access): ?Response
    {
        $user = $this->auth->user();

        return match ($access) {
            Access::Guest         => $user === null ? null : Response::redirect('/'),
            Access::Authenticated => $user === null ? Response::redirect('/login') : null,
            Access::Admin         => match (true) {
                $user === null      => Response::redirect('/login'),
                !$user->isAdmin()   => throw HttpException::forbidden(),
                default             => null,
            },
        };
    }

    private function errorPage(int $status, string $message): Response
    {
        $html = $this->view->render('errors/error', [
            'title'   => self::ERROR_TITLES[$status] ?? 'Erreur',
            'status'  => $status,
            'message' => $message,
        ]);

        return new Response($html, $status);
    }
}
