<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Boîtes du bureau : l\'adhérent qui a ouvert le fil (accès réservé à lui et aux titulaires actuels du rôle)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE conversation ADD requester_id UUID DEFAULT NULL');
        // Fils existants : l'auteur du premier message (null si son compte a été supprimé)
        $this->addSql('UPDATE conversation c SET requester_id = (SELECT m.author_id FROM message m WHERE m.conversation_id = c.id ORDER BY m.created_at ASC, m.id ASC LIMIT 1) WHERE c.mailbox IS NOT NULL');
        $this->addSql('CREATE INDEX idx_conversation_requester ON conversation (requester_id)');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_conversation_requester FOREIGN KEY (requester_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE conversation DROP CONSTRAINT FK_conversation_requester');
        $this->addSql('DROP INDEX idx_conversation_requester');
        $this->addSql('ALTER TABLE conversation DROP requester_id');
    }
}
