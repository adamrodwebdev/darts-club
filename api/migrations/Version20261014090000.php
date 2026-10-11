<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Événements publics (ville, adresse de page), réponse « pas sûr », réponses sans compte';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE club_event ADD public BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE club_event ADD city VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE club_event ADD slug VARCHAR(140) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9BC02015989D9B62 ON club_event (slug)');
        $this->addSql('ALTER TABLE event_registration ADD answer VARCHAR(10) DEFAULT \'je-viens\' NOT NULL');
        $this->addSql('CREATE TABLE event_guest_response (id UUID NOT NULL, name VARCHAR(40) NOT NULL, email VARCHAR(180) NOT NULL, answer VARCHAR(10) DEFAULT NULL, pending_answer VARCHAR(10) DEFAULT NULL, token_hash VARCHAR(64) NOT NULL, token_issued_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, language VARCHAR(2) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, event_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9E1575F7B3BC57DA ON event_guest_response (token_hash)');
        $this->addSql('CREATE UNIQUE INDEX uniq_guest_response_event_email ON event_guest_response (event_id, email)');
        $this->addSql('ALTER TABLE event_guest_response ADD CONSTRAINT FK_9E1575F771F7E88B FOREIGN KEY (event_id) REFERENCES club_event (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE event_guest_response');
        $this->addSql('ALTER TABLE event_registration DROP answer');
        $this->addSql('DROP INDEX UNIQ_9BC02015989D9B62');
        $this->addSql('ALTER TABLE club_event DROP slug');
        $this->addSql('ALTER TABLE club_event DROP city');
        $this->addSql('ALTER TABLE club_event DROP public');
    }
}
