<?php
/**
 * @var App\Core\View         $this
 * @var array{email: string}  $values
 * @var array<string, string> $errors
 * @var string|null           $alert
 */
?>
<section class="login panel hud" aria-labelledby="login-title">
    <p class="kicker">Terminal sécurisé // Identification requise</p>
    <h1 id="login-title" class="page-title">
        <span class="brand__name">Agent<span class="brand__sep" aria-hidden="true">//</span>Manager</span>
        <span class="login__subtitle">Connexion</span>
    </h1>

    <?php if ($alert !== null): ?>
        <div class="alert alert--error" role="alert">
            <span class="alert__label">Accès refusé</span>
            <span class="alert__message"><?= e($alert) ?></span>
        </div>
    <?php endif; ?>

    <?= $this->partial('form-errors', ['errors' => $errors, 'labels' => ['email' => 'Adresse e-mail', 'password' => 'Mot de passe']]) ?>

    <form method="post" action="/login">
        <?= $this->csrfField() ?>
        <?= $this->partial('field', [
            'name' => 'email', 'label' => 'Adresse e-mail', 'type' => 'email', 'required' => true,
            'value' => $values['email'], 'errors' => $errors,
            'attrs' => ['autocomplete' => 'username', 'maxlength' => 100],
        ]) ?>
        <?= $this->partial('field', [
            'name' => 'password', 'label' => 'Mot de passe', 'type' => 'password', 'required' => true,
            'errors' => $errors, 'attrs' => ['autocomplete' => 'current-password'],
        ]) ?>
        <button type="submit" class="btn btn--primary btn--block">S'identifier</button>
    </form>
</section>
