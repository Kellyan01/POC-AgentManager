<?php
/**
 * Création et modification d'un utilisateur.
 *
 * @var App\Core\View                 $this
 * @var App\Model\User|null           $user  null en création
 * @var list<App\Model\ReferenceItem> $roles
 * @var array{firstname: string, lastname: string, email: string, role: string} $values
 * @var array<string, string>         $errors
 * @var int                           $minLength
 * @var string                        $title
 */
$action = $user === null ? '/admin/users' : '/admin/users/' . $user->id;
$labels = [
    'firstname' => 'Prénom', 'lastname' => 'Nom', 'email' => 'Adresse e-mail', 'role' => 'Rôle',
    'password' => 'Mot de passe', 'password_confirm' => 'Confirmation du mot de passe',
];
?>
<header class="page-header">
    <div>
        <p class="kicker"><a href="/admin">Administration</a> // <a href="/admin/users">Utilisateurs</a></p>
        <h1 class="page-title"><?= e($title) ?></h1>
    </div>
</header>

<section class="panel hud form" aria-label="Formulaire utilisateur">
    <p class="required-note">Les champs marqués d'un astérisque (*) sont obligatoires.</p>
    <?= $this->partial('form-errors', ['errors' => $errors, 'labels' => $labels]) ?>
    <form method="post" action="<?= e($action) ?>">
        <?= $this->csrfField() ?>
        <div class="form-grid">
            <?= $this->partial('field', ['name' => 'firstname', 'label' => $labels['firstname'], 'required' => true, 'value' => $values['firstname'], 'errors' => $errors, 'attrs' => ['maxlength' => 50, 'autocomplete' => 'off']]) ?>
            <?= $this->partial('field', ['name' => 'lastname', 'label' => $labels['lastname'], 'required' => true, 'value' => $values['lastname'], 'errors' => $errors, 'attrs' => ['maxlength' => 50, 'autocomplete' => 'off']]) ?>
        </div>
        <?= $this->partial('field', ['name' => 'email', 'label' => $labels['email'], 'type' => 'email', 'required' => true, 'value' => $values['email'], 'errors' => $errors, 'attrs' => ['maxlength' => 100, 'autocomplete' => 'off']]) ?>
        <?= $this->partial('field', [
            'name' => 'role', 'label' => $labels['role'], 'type' => 'select', 'required' => true, 'value' => $values['role'], 'errors' => $errors,
            'placeholder' => '— Choisir un rôle —', 'options' => array_map(static fn ($r) => [$r->id, $r->label], $roles),
            'hint' => 'Administrateur : accès total. Manager : gestion de ses équipes et des opérations qu\'il déclare.',
        ]) ?>
        <div class="form-grid">
            <?= $this->partial('field', [
                'name' => 'password', 'label' => $labels['password'], 'type' => 'password', 'required' => $user === null, 'errors' => $errors,
                'hint' => ($user === null ? '' : 'Laisser vide pour conserver le mot de passe actuel. ') . $minLength . ' caractères minimum.',
                'attrs' => ['autocomplete' => 'new-password', 'minlength' => $minLength],
            ]) ?>
            <?= $this->partial('field', [
                'name' => 'password_confirm', 'label' => $labels['password_confirm'], 'type' => 'password', 'required' => $user === null,
                'errors' => $errors, 'attrs' => ['autocomplete' => 'new-password'],
            ]) ?>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn--primary"><?= $user === null ? 'Créer le compte' : 'Enregistrer' ?></button>
            <a class="btn btn--ghost" href="/admin/users">Annuler</a>
        </div>
    </form>
</section>
