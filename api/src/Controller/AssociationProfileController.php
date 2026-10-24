<?php

namespace App\Controller;

use App\Documents\AssociationProfiles;
use App\Documents\AssociationProfileView;
use App\Documents\CertificateArchive;
use App\Documents\DocumentException;
use App\Documents\InsuranceDesk;
use App\Dto\AssociationProfileInput;
use App\Entity\User;
use App\Security\Voter\EventDocumentsVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Fiche de l'association (bureau) : reprise dans les documents des soirées, jamais publiée. */
final class AssociationProfileController extends AbstractController
{
    public function __construct(
        private readonly AssociationProfiles $profiles,
        private readonly AssociationProfileView $view,
    ) {
    }

    #[Route('/api/association-profile', name: 'association_profile_show', methods: ['GET'], format: 'json')]
    #[IsGranted(EventDocumentsVoter::VIEW)]
    public function show(): JsonResponse
    {
        return $this->profile();
    }

    #[Route('/api/association-profile', name: 'association_profile_update', methods: ['PUT'], format: 'json')]
    #[IsGranted(EventDocumentsVoter::EDIT)]
    public function update(#[MapRequestPayload(acceptFormat: 'json')] AssociationProfileInput $input, #[CurrentUser] User $actor): JsonResponse
    {
        $this->profiles->update($actor, DomainCall::run($input->toDetails(...)));

        return $this->profile();
    }

    /** Formulaire : file (PDF de l'assureur), validUntil (AAAA-MM-JJ) */
    #[Route('/api/association-profile/insurance', name: 'association_profile_insurance', methods: ['POST'], format: 'json')]
    #[IsGranted(EventDocumentsVoter::EDIT)]
    public function replaceInsurance(Request $request, #[CurrentUser] User $actor, InsuranceDesk $desk): JsonResponse
    {
        $file = $request->files->get('file');
        $day = $request->request->getString('validUntil');
        $validUntil = \DateTimeImmutable::createFromFormat('!Y-m-d', $day);
        if (!$file instanceof UploadedFile) {
            throw new DocumentException('L’attestation doit être un PDF.');
        }
        if (false === $validUntil || $validUntil->format('Y-m-d') !== $day) {
            throw new DocumentException('Date de fin de validité invalide.');
        }
        $desk->replace($actor, $file, $validUntil);

        return $this->profile();
    }

    /** Attestation téléchargée (jamais ouverte dans le site) */
    #[Route('/api/association-profile/insurance', name: 'association_profile_insurance_file', methods: ['GET'])]
    #[IsGranted(EventDocumentsVoter::VIEW)]
    public function insuranceFile(CertificateArchive $archive): BinaryFileResponse
    {
        $certificate = $this->profiles->current()->certificate();
        $path = null === $certificate ? null : $archive->path($certificate->fileId);
        if (null === $path) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($path, headers: ['Content-Type' => 'application/pdf']);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'attestation-assurance.pdf');

        return $response;
    }

    private function profile(): JsonResponse
    {
        return $this->json($this->view->of($this->profiles->current(), $this->isGranted(EventDocumentsVoter::EDIT)));
    }
}
