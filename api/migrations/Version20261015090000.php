<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mesure d\'audience des annonces d\'événements (personnes uniques, sans donnée identifiante)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE club_event ADD audience_key VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE club_event ADD archived_audience JSON DEFAULT NULL');
        $this->addSql('CREATE TABLE event_view (id UUID NOT NULL, visitor_key VARCHAR(64) NOT NULL, member BOOLEAN NOT NULL, first_seen_on DATE NOT NULL, event_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_event_view_visitor ON event_view (event_id, visitor_key)');
        $this->addSql('ALTER TABLE event_view ADD CONSTRAINT FK_E136E72571F7E88B FOREIGN KEY (event_id) REFERENCES club_event (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE event_view');
        $this->addSql('ALTER TABLE club_event DROP archived_audience');
        $this->addSql('ALTER TABLE club_event DROP audience_key');
    }
}
