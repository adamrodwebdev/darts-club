<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Galerie : vidéos courtes (envoi par morceaux, conversion H.264 et AV1) et demandes de retrait';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE event_video (id UUID NOT NULL, uploader_name VARCHAR(80) NOT NULL, file_name VARCHAR(120) NOT NULL, declared_size INT NOT NULL, received_bytes INT NOT NULL, status VARCHAR(16) NOT NULL, uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, duration_ms INT NOT NULL, width INT DEFAULT NULL, height INT DEFAULT NULL, file_id VARCHAR(40) DEFAULT NULL, file_size INT DEFAULT NULL, av1_file_id VARCHAR(40) DEFAULT NULL, poster_id VARCHAR(40) DEFAULT NULL, poster_avif_id VARCHAR(40) DEFAULT NULL, failure VARCHAR(16) DEFAULT NULL, hidden_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, hidden_by VARCHAR(80) DEFAULT NULL, event_id UUID NOT NULL, uploader_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_3DDC6B0071F7E88B ON event_video (event_id)');
        $this->addSql('CREATE INDEX IDX_3DDC6B0016678C77 ON event_video (uploader_id)');
        $this->addSql('ALTER TABLE event_video ADD CONSTRAINT FK_3DDC6B0071F7E88B FOREIGN KEY (event_id) REFERENCES club_event (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE event_video ADD CONSTRAINT FK_3DDC6B0016678C77 FOREIGN KEY (uploader_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE event_photo ADD hidden_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE event_photo ADD hidden_by VARCHAR(80) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_photo DROP hidden_at');
        $this->addSql('ALTER TABLE event_photo DROP hidden_by');
        $this->addSql('DROP TABLE event_video');
    }
}
