<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Affiche des événements (grande version, version carte, dimensions)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE club_event ADD poster_large_id VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE club_event ADD poster_card_id VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE club_event ADD poster_width INT DEFAULT NULL');
        $this->addSql('ALTER TABLE club_event ADD poster_height INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE club_event DROP poster_height');
        $this->addSql('ALTER TABLE club_event DROP poster_width');
        $this->addSql('ALTER TABLE club_event DROP poster_card_id');
        $this->addSql('ALTER TABLE club_event DROP poster_large_id');
    }
}
