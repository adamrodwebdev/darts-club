<?php

namespace App\Controller;

use App\Entity\ClubEvent;
use App\Media\QrCodeSvg;
use App\Security\Voter\ClubEventVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** QR codes des affiches à poser pendant les soirées (organisateurs) : le site, ou la page publique d'une soirée. */
final class QrCodeController extends AbstractController
{
    private const UUID = '[0-9a-f-]{36}';

    public function __construct(
        private readonly QrCodeSvg $qrCodes,
        #[Autowire('%env(FRONTEND_URL)%')]
        private readonly string $siteUrl,
    ) {
    }

    #[Route('/api/qr/site.svg', name: 'qr_site', methods: ['GET'])]
    #[IsGranted(ClubEventVoter::MANAGE)]
    public function site(): Response
    {
        return $this->svg($this->qrCodes->of(rtrim($this->siteUrl, '/').'/'), 'qr-skull-darts-71.svg');
    }

    /** Seulement pour une soirée ouverte au public : sa page publique est l'adresse du QR code */
    #[Route('/api/events/{id}/qr.svg', name: 'events_qr', requirements: ['id' => self::UUID], methods: ['GET'])]
    #[IsGranted(ClubEventVoter::MANAGE, subject: 'event')]
    public function event(ClubEvent $event): Response
    {
        $slug = $event->getSlug();
        if (!$event->isPublic() || null === $slug) {
            throw new NotFoundHttpException();
        }

        return $this->svg($this->qrCodes->of(rtrim($this->siteUrl, '/').'/e/'.$slug), 'qr-'.$slug.'.svg');
    }

    private function svg(string $content, string $fileName): Response
    {
        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => \sprintf('inline; filename="%s"', $fileName),
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
