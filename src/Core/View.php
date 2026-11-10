<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Moteur de vues minimal : des fichiers PHP dans views/, rendus dans un tampon.
 *
 * Dans un gabarit, $this désigne cette classe : on peut appeler
 * $this->partial(), $this->csrfField(), $this->isCurrent()...
 * Les vues ne contiennent ni requête SQL ni logique métier : uniquement de l'affichage.
 */
final class View
{
    /** @var array<string, mixed> données disponibles dans toutes les vues */
    private array $shared = [];

    public function __construct(
        private readonly string $basePath,
        private readonly Session $session,
        private readonly Csrf $csrf,
    ) {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * Rend un gabarit, puis l'insère dans un layout (variable $content).
     *
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = [], ?string $layout = 'layouts/main'): string
    {
        $content = $this->renderFile($template, $data);

        if ($layout === null) {
            return $content;
        }

        return $this->renderFile($layout, ['content' => $content] + $data);
    }

    /**
     * Rend un fragment réutilisable (views/partials/...).
     *
     * @param array<string, mixed> $data
     */
    public function partial(string $template, array $data = []): string
    {
        return $this->renderFile('partials/' . $template, $data);
    }

    /** Champ caché contenant le jeton CSRF, à placer dans chaque formulaire POST. */
    public function csrfField(): string
    {
        return '<input type="hidden" name="' . Csrf::FIELD . '" value="' . e($this->csrf->token()) . '">';
    }

    /** @return list<array{type: string, message: string}> */
    public function flashes(): array
    {
        return $this->session->takeFlashes();
    }

    /** Vrai si la page courante appartient à la rubrique (pour aria-current dans le menu). */
    public function isCurrent(string $prefix): bool
    {
        $path = (string) ($this->shared['currentPath'] ?? '/');

        return $prefix === '/' ? $path === '/' : str_starts_with($path . '/', $prefix . '/');
    }

    /** @param array<string, mixed> $data */
    private function renderFile(string $template, array $data): string
    {
        $file = $this->basePath . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('Vue introuvable : ' . $template);
        }

        // Chaque clé du tableau devient une variable dans le gabarit ($agent, $teams...).
        // La closure isole ces variables : elles ne peuvent pas écraser celles de la méthode.
        $include = function (string $__file, array $__vars): void {
            extract($__vars);
            require $__file;
        };

        ob_start();
        try {
            $include($file, $data + $this->shared);
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
