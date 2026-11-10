<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Exception\HttpException;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

/**
 * Outils communs aux contrôleurs.
 *
 * Un contrôleur reste « fin » : il lit la requête, vérifie les droits, valide la
 * saisie, délègue aux repositories et services, puis choisit la réponse.
 * Toute règle qui touche plusieurs tables vit dans un service.
 */
abstract class AbstractController
{
    public function __construct(
        protected readonly View $view,
        protected readonly Session $session,
        protected readonly Auth $auth,
    ) {
    }

    /** @param array<string, mixed> $data */
    protected function render(string $template, array $data = [], int $status = 200): Response
    {
        return new Response($this->view->render($template, $data), $status);
    }

    /**
     * Ré-affiche un formulaire invalide : code 422, valeurs saisies et erreurs conservées.
     *
     * @param array<string, mixed> $data
     */
    protected function renderInvalid(string $template, array $data): Response
    {
        return $this->render($template, $data, 422);
    }

    protected function redirect(string $url): Response
    {
        return Response::redirect($url);
    }

    /** Message de succès puis redirection (motif Post/Redirect/Get). */
    protected function success(string $message, string $url): Response
    {
        $this->session->flash('success', $message);

        return Response::redirect($url);
    }

    /** Message d'erreur puis redirection. */
    protected function failure(string $message, string $url): Response
    {
        $this->session->flash('error', $message);

        return Response::redirect($url);
    }

    /**
     * Renvoie l'entité, ou lève une 404 si elle n'existe pas.
     *
     * @template T of object
     * @param T|null $entity
     * @return T
     */
    protected function found(?object $entity): object
    {
        return $entity ?? throw HttpException::notFound();
    }

    /** @throws HttpException 403 si la condition de droit n'est pas remplie */
    protected function denyUnless(bool $allowed): void
    {
        if (!$allowed) {
            throw HttpException::forbidden();
        }
    }
}
