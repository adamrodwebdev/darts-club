<?php

declare(strict_types=1);

namespace App\Controller;

use App\ClubEvent\PressClippings;
use App\Dto\PressClippingInput;
use App\Entity\EventPhoto;
use App\Entity\User;
use App\Security\Voter\ClubEventVoter;
use App\Security\Voter\EventGalleryVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * « Dans la presse » : qui organise l'événement (ClubEventVoter::MANAGE, comme pour le modifier)
 * classe une photo de sa galerie parmi les coupures de presse, en corrige les détails ou la rend à
 * la galerie. Il faut aussi voir la photo : une photo masquée reste à la présidence et à son auteur.
 */
#[IsGranted('ROLE_MEMBER')]
final class PressClippingController extends AbstractController
{
    private const UUID = '[0-9a-f-]{36}';

    public function __construct(
        private readonly PressClippings $clippings,
    ) {
    }

    /** Corps : {outlet?, publishedOn? (AAAA-MM-JJ), title?, credit?} ; renvoie la photo et ses détails */
    #[Route('/api/event-photos/{id}/press', name: 'events_photos_press', requirements: ['id' => self::UUID], methods: ['PUT'], format: 'json')]
    #[IsGranted(EventGalleryVoter::VIEW, subject: 'photo')]
    #[IsGranted(ClubEventVoter::MANAGE, subject: new Expression('args["photo"].getEvent()'))]
    public function mark(EventPhoto $photo, #[MapRequestPayload(acceptFormat: 'json')] PressClippingInput $input, #[CurrentUser] User $actor): JsonResponse
    {
        $this->clippings->mark($photo, DomainCall::run($input->toDetails(...)), $actor);

        return $this->json($photo->toArray() + ['mine' => $photo->isUploadedBy($actor)]);
    }

    /** La photo revient dans la galerie ; sans effet sur une photo qui n'est pas une coupure */
    #[Route('/api/event-photos/{id}/press', name: 'events_photos_unpress', requirements: ['id' => self::UUID], methods: ['DELETE'], format: 'json')]
    #[IsGranted(EventGalleryVoter::VIEW, subject: 'photo')]
    #[IsGranted(ClubEventVoter::MANAGE, subject: new Expression('args["photo"].getEvent()'))]
    public function unmark(EventPhoto $photo, #[CurrentUser] User $actor): Response
    {
        $this->clippings->unmark($photo, $actor);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
