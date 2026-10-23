<?php

namespace App\Controller;

use App\Admin\AdministrationException;
use App\Admin\AuditEntry;
use App\Admin\AuditTrail;
use App\Admin\BroadcastAudience;
use App\Admin\BroadcastDraft;
use App\Admin\BroadcastLabel;
use App\Admin\MemberAdministration;
use App\Admin\PresidentialBroadcast;
use App\Announcement\AnnouncementText;
use App\Entity\Announcement;
use App\Entity\AuditLog;
use App\Entity\Broadcast;
use App\Entity\User;
use App\Enum\Role;
use App\Membership\MemberBadges;
use App\Membership\Season;
use App\Profile\ProfileView;
use App\Repository\AuditLogRepository;
use App\Repository\BroadcastRepository;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Interface du président (l'admin technique y accède par la hiérarchie des rôles). */
#[Route('/api/admin', name: 'admin_', format: 'json')]
#[IsGranted('ROLE_PRESIDENT')]
final class AdminController extends AbstractController
{
    private const UUID = '[0-9a-f-]{36}';
    private const ROLE_IDS = ['secretaire' => Role::Secretary, 'tresorier' => Role::Treasurer, 'president' => Role::President];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditTrail $audit,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/members', name: 'members', methods: ['GET'])]
    public function members(UserRepository $users, MembershipRepository $memberships, MemberBadges $badges, ClockInterface $clock, #[MapQueryParameter] string $q = ''): JsonResponse
    {
        $needle = mb_strtolower(trim($q));
        $season = Season::containing($clock->now());
        $statusByUser = [];
        foreach ($memberships->forSeason($season) as $membership) {
            if (null !== $u = $membership->getUser()) {
                $statusByUser[$u->getId()->toRfc4122()] = $membership->status()->value;
            }
        }

        $list = array_filter(
            $users->findBy([], ['email' => 'ASC']),
            static fn (User $u): bool => '' === $needle
                || str_contains(mb_strtolower($u->displayName()->value), $needle)
                || str_contains($u->getEmail(), $needle),
        );

        return $this->json([
            'season' => $season->label(),
            'items' => array_values(array_map(static fn (User $u): array => [
                'id' => $u->getId()->toRfc4122(),
                'displayName' => $u->displayName()->value,
                'email' => $u->getEmail(),
                'phone' => $u->getPhone(),
                'avatarUrl' => ProfileView::avatarUrl($u),
                'roles' => array_keys(array_filter(self::ROLE_IDS, static fn (Role $r): bool => \in_array($r, $u->assignedRoles(), true))),
                'admin' => \in_array(Role::Admin, $u->assignedRoles(), true),
                'badges' => $badges->present($u),
                'verified' => $u->isVerified(),
                'twoFactorEnabled' => $u->hasTwoFactor(),
                'suspended' => $u->isSuspended(),
                'suspensionReason' => $u->suspensionReason(),
                'membership' => $statusByUser[$u->getId()->toRfc4122()] ?? null,
                'createdAt' => $u->getCreatedAt()->format(\DATE_ATOM),
            ], $list)),
        ]);
    }

    #[Route('/members/{id}/roles', name: 'member_roles', requirements: ['id' => self::UUID], methods: ['PUT'])]
    public function changeRoles(User $member, Request $request, #[CurrentUser] User $actor, MemberAdministration $administration): Response
    {
        $ids = $request->toArray()['roles'] ?? null;
        if (!\is_array($ids)) {
            throw new AdministrationException('Champ « roles » attendu.');
        }

        $roles = [];
        foreach ($ids as $id) {
            $roles[] = self::ROLE_IDS[\is_string($id) ? $id : ''] ?? throw new AdministrationException('Rôle inconnu : « %role% ».', null, ['%role%' => \is_scalar($id) ? (string) $id : '?']);
        }

        $administration->changeBoardRoles($actor, $member, $roles);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/members/{id}/suspend', name: 'member_suspend', requirements: ['id' => self::UUID], methods: ['POST'])]
    public function suspend(User $member, Request $request, #[CurrentUser] User $actor, MemberAdministration $administration): Response
    {
        $reason = $request->toArray()['reason'] ?? '';
        $administration->suspend($actor, $member, \is_string($reason) ? $reason : '');

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/members/{id}/reactivate', name: 'member_reactivate', requirements: ['id' => self::UUID], methods: ['POST'])]
    public function reactivate(User $member, #[CurrentUser] User $actor, MemberAdministration $administration): Response
    {
        $administration->reactivate($actor, $member);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/broadcasts', name: 'broadcasts', methods: ['GET'])]
    public function broadcasts(BroadcastRepository $broadcasts): JsonResponse
    {
        return $this->json([
            'items' => array_map(static fn (Broadcast $b): array => $b->toArray(), $broadcasts->latest()),
            'labels' => array_map(static fn (BroadcastLabel $l): array => ['id' => $l->value, 'label' => $l->category()->label()], BroadcastLabel::cases()),
        ]);
    }

    /** Corps : {label, title, body, userIds?: string[]} — sans userIds : tous les adhérents. */
    #[Route('/broadcasts', name: 'broadcast_send', methods: ['POST'])]
    public function sendBroadcast(Request $request, #[CurrentUser] User $president, PresidentialBroadcast $broadcast): JsonResponse
    {
        $data = $request->toArray();
        $label = BroadcastLabel::tryFrom(\is_string($data['label'] ?? null) ? $data['label'] : '')
            ?? throw new AdministrationException('Label inconnu (cotisation, evenement, reunion, annulation).');

        $audience = BroadcastAudience::allMembers();
        if (\array_key_exists('userIds', $data) && null !== $data['userIds']) {
            if (!\is_array($data['userIds'])) {
                throw new AdministrationException('« userIds » doit être une liste.');
            }
            $audience = BroadcastAudience::members(array_values(array_filter($data['userIds'], 'is_string')));
        }

        $sent = $broadcast->send($president, new BroadcastDraft(
            label: $label,
            title: \is_string($data['title'] ?? null) ? $data['title'] : '',
            body: \is_string($data['body'] ?? null) ? $data['body'] : '',
        ), $audience);

        return $this->json($sent->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/announcements', name: 'announcement_publish', methods: ['POST'])]
    public function publishAnnouncement(Request $request, #[CurrentUser] User $president): JsonResponse
    {
        $data = $request->toArray();

        try {
            $announcement = Announcement::publish($president, AnnouncementText::of(self::text($data, 'title'), self::text($data, 'body')), $this->clock->now());
            if (true === ($data['pinned'] ?? false)) {
                $announcement->pin();
            }
        } catch (\InvalidArgumentException $e) {
            throw new AdministrationException($e->getMessage(), $e);
        }

        $this->em->persist($announcement);
        $this->audit->record($president, new AuditEntry('announcement.publish', $announcement->getTitle()));
        $this->em->flush();

        return $this->json($announcement->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/announcements/{id}', name: 'announcement_edit', requirements: ['id' => self::UUID], methods: ['PATCH'])]
    public function editAnnouncement(Announcement $announcement, Request $request, #[CurrentUser] User $president): JsonResponse
    {
        $data = $request->toArray();

        try {
            if (\array_key_exists('title', $data) || \array_key_exists('body', $data)) {
                $announcement->edit(
                    AnnouncementText::of(
                        \array_key_exists('title', $data) ? self::text($data, 'title') : $announcement->getTitle(),
                        \array_key_exists('body', $data) ? self::text($data, 'body') : $announcement->getBody(),
                    ),
                    $this->clock->now(),
                );
            }
        } catch (\InvalidArgumentException $e) {
            throw new AdministrationException($e->getMessage(), $e);
        }
        if (\array_key_exists('pinned', $data)) {
            true === $data['pinned'] ? $announcement->pin() : $announcement->unpin();
        }

        $this->audit->record($president, new AuditEntry('announcement.edit', $announcement->getTitle()));
        $this->em->flush();

        return $this->json($announcement->toArray());
    }

    #[Route('/announcements/{id}', name: 'announcement_delete', requirements: ['id' => self::UUID], methods: ['DELETE'])]
    public function deleteAnnouncement(Announcement $announcement, #[CurrentUser] User $president): Response
    {
        $this->audit->record($president, new AuditEntry('announcement.delete', $announcement->getTitle()));
        $this->em->remove($announcement);
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/audit', name: 'audit', methods: ['GET'])]
    public function audit(AuditLogRepository $logs): JsonResponse
    {
        return $this->json(['items' => array_map(static fn (AuditLog $l): array => $l->toArray(), $logs->latest())]);
    }

    /** @param array<mixed> $data */
    private static function text(array $data, string $key): string
    {
        return \is_string($data[$key] ?? null) ? $data[$key] : '';
    }
}
