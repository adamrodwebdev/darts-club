<?php

namespace App\Security;

use App\Entity\User;
use App\Translation\Unprocessable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

/**
 * Confirmation de l'adresse : le lien reçu par email ET le mot de passe choisi à l'inscription. Si
 * quelqu'un a inscrit l'adresse d'un autre avec son propre mot de passe, le titulaire qui clique ne
 * peut pas activer ce compte (il passe par « mot de passe oublié », qui le lui rend) ; l'intrus,
 * sans l'email, non plus.
 */
final class EmailConfirmation
{
    public function __construct(
        private readonly VerifyEmailHelperInterface $links,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EntityManagerInterface $em,
        /** Adresse de base des liens signés (celle de l'envoi, quel que soit l'hôte qui relaie la page) */
        #[Autowire('%env(DEFAULT_URI)%')]
        private readonly string $signedBase,
    ) {
    }

    /**
     * @param Request $request requête portant, dans son adresse, les paramètres signés du lien
     *
     * @throws Unprocessable lien invalide ou expiré, mot de passe incorrect
     */
    public function confirm(User $user, Request $request, string $password): void
    {
        $signedLink = Request::create(rtrim($this->signedBase, '/').$request->getPathInfo().'?'.$request->server->getString('QUERY_STRING'));
        try {
            $this->links->validateEmailConfirmationFromRequest($signedLink, $user->getId()->toRfc4122(), $user->getEmail());
        } catch (VerifyEmailExceptionInterface) {
            throw new Unprocessable('Lien invalide ou expiré : demande un nouveau lien.');
        }
        if (!$user->hasPassword() || !$this->hasher->isPasswordValid($user, $password)) {
            throw new Unprocessable('Mot de passe incorrect.');
        }

        $user->markVerified();
        $this->em->flush();
    }
}
