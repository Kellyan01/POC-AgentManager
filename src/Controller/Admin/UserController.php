<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Model\ReferenceItem;
use App\Model\User;
use App\Repository\UserRepository;

/**
 * Gestion des comptes utilisateurs (administrateurs uniquement).
 */
final class UserController extends AbstractController
{
    private const MIN_PASSWORD_LENGTH = 10;

    public function __construct(
        View $view,
        Session $session,
        Auth $auth,
        private readonly UserRepository $users,
    ) {
        parent::__construct($view, $session, $auth);
    }

    public function index(Request $request): Response
    {
        return $this->render('admin/users/index', [
            'title' => 'Utilisateurs',
            'users' => $this->users->findAll(),
        ]);
    }

    public function new(Request $request): Response
    {
        return $this->renderForm(null, ['firstname' => '', 'lastname' => '', 'email' => '', 'role' => ''], []);
    }

    public function create(Request $request): Response
    {
        $values = $this->values($request);
        $validator = $this->validate($values, null, $request);
        if ($validator->fails()) {
            return $this->renderForm(null, $values, $validator->errors(), 422);
        }

        $this->users->insert(new User(
            null,
            $values['firstname'],
            $values['lastname'],
            $values['email'],
            password_hash((string) $request->input('password'), PASSWORD_DEFAULT),
            (int) $values['role'],
        ));

        return $this->success(sprintf('Compte de %s %s créé.', $values['firstname'], $values['lastname']), '/admin/users');
    }

    public function edit(Request $request, int $id): Response
    {
        $user = $this->editableUser($id);
        if ($user instanceof Response) {
            return $user;
        }

        return $this->renderForm($user, [
            'firstname' => $user->firstname,
            'lastname'  => $user->lastname,
            'email'     => $user->email,
            'role'      => (string) $user->roleId,
        ], []);
    }

    public function update(Request $request, int $id): Response
    {
        $user = $this->editableUser($id);
        if ($user instanceof Response) {
            return $user;
        }

        $values = $this->values($request);
        $validator = $this->validate($values, $user, $request);
        if ($validator->fails()) {
            return $this->renderForm($user, $values, $validator->errors(), 422);
        }

        $this->users->update(new User(
            $id,
            $values['firstname'],
            $values['lastname'],
            $values['email'],
            $user->passwordHash,
            (int) $values['role'],
        ));

        $password = (string) $request->input('password');
        if ($password !== '') {
            $this->users->updatePassword($id, password_hash($password, PASSWORD_DEFAULT));
        }

        return $this->success(sprintf('Compte de %s %s mis à jour.', $values['firstname'], $values['lastname']), '/admin/users');
    }

    public function archive(Request $request, int $id): Response
    {
        $user = $this->found($this->users->findById($id));

        if ($user->id === $this->auth->requireUser()->id) {
            return $this->failure('Vous ne pouvez pas archiver votre propre compte.', '/admin/users');
        }
        if ($user->isArchived()) {
            return $this->failure('Ce compte est déjà archivé.', '/admin/users');
        }
        if ($this->users->managesActiveTeams($id)) {
            return $this->failure(
                sprintf('%s gère encore des équipes actives : confiez-les à un autre manager avant d\'archiver ce compte.', $user->fullName()),
                '/admin/users',
            );
        }

        $this->users->archive($id);

        return $this->success(sprintf('Compte de %s archivé : il ne peut plus se connecter.', $user->fullName()), '/admin/users');
    }

    /** Un compte archivé n'est plus modifiable. */
    private function editableUser(int $id): User|Response
    {
        $user = $this->found($this->users->findById($id));

        return $user->isArchived()
            ? $this->failure('Ce compte est archivé : il n\'est plus modifiable.', '/admin/users')
            : $user;
    }

    /** @return array{firstname: string, lastname: string, email: string, role: string} */
    private function values(Request $request): array
    {
        return [
            'firstname' => (string) $request->input('firstname'),
            'lastname'  => (string) $request->input('lastname'),
            'email'     => mb_strtolower((string) $request->input('email')),
            'role'      => (string) $request->input('role'),
        ];
    }

    /** @param array{firstname: string, lastname: string, email: string, role: string} $values */
    private function validate(array $values, ?User $user, Request $request): Validator
    {
        $password = (string) $request->input('password');
        $data = $values + ['password' => $password];
        $roleIds = array_map(static fn (ReferenceItem $role): int => (int) $role->id, $this->users->findRoles());

        $validator = (new Validator($data))
            ->required('firstname')->maxLength('firstname', 50)
            ->required('lastname')->maxLength('lastname', 50)
            ->required('email')->email('email')->maxLength('email', 100)
            ->required('role', 'Choisissez un rôle.')->in('role', $roleIds)
            ->minLength('password', self::MIN_PASSWORD_LENGTH);

        // Le mot de passe est obligatoire à la création, facultatif en modification.
        if ($user === null) {
            $validator->required('password');
        }
        if ($password !== '' && $password !== (string) $request->input('password_confirm')) {
            $validator->addError('password_confirm', 'Les deux mots de passe ne correspondent pas.');
        }
        if ($values['email'] !== '' && $this->users->emailExists($values['email'], $user?->id)) {
            $validator->addError('email', 'Cette adresse est déjà utilisée par un autre compte.');
        }
        // Un administrateur ne peut pas se retirer ses propres droits (il perdrait l'accès à cet écran).
        if ($user !== null && $user->id === $this->auth->requireUser()->id && (int) $values['role'] !== $user->roleId) {
            $validator->addError('role', 'Vous ne pouvez pas modifier votre propre rôle.');
        }

        return $validator;
    }

    /**
     * @param array{firstname: string, lastname: string, email: string, role: string} $values
     * @param array<string, string> $errors
     */
    private function renderForm(?User $user, array $values, array $errors, int $status = 200): Response
    {
        return $this->render('admin/users/form', [
            'title'     => $user === null ? 'Nouvel utilisateur' : 'Modifier ' . $user->fullName(),
            'user'      => $user,
            'roles'     => $this->users->findRoles(),
            'values'    => $values,
            'errors'    => $errors,
            'minLength' => self::MIN_PASSWORD_LENGTH,
        ], $status);
    }
}
