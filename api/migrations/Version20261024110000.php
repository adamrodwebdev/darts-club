<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Démarches des soirées : fiche de l\'association et documents pré-remplis';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE association_profile (id UUID NOT NULL, official_name VARCHAR(120) NOT NULL, rna_number VARCHAR(10) DEFAULT NULL, siret VARCHAR(14) DEFAULT NULL, seat_address VARCHAR(300) DEFAULT NULL, president_name VARCHAR(80) DEFAULT NULL, secretary_name VARCHAR(80) DEFAULT NULL, treasurer_name VARCHAR(80) DEFAULT NULL, insurer_name VARCHAR(80) DEFAULT NULL, insurance_policy VARCHAR(60) DEFAULT NULL, certificate_file_id VARCHAR(40) DEFAULT NULL, certificate_valid_until DATE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_4F053AA5896DBBDE ON association_profile (updated_by_id)');
        $this->addSql('CREATE TABLE event_procedure (id UUID NOT NULL, type VARCHAR(30) NOT NULL, status VARCHAR(20) NOT NULL, field_values JSON NOT NULL, rewrites JSON NOT NULL, sent_on DATE DEFAULT NULL, received_on DATE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, event_id UUID NOT NULL, updated_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_event_procedure_type ON event_procedure (event_id, type)');
        $this->addSql('CREATE INDEX IDX_1AA9822271F7E88B ON event_procedure (event_id)');
        $this->addSql('CREATE INDEX IDX_1AA98222896DBBDE ON event_procedure (updated_by_id)');
        $this->addSql('ALTER TABLE association_profile ADD CONSTRAINT FK_4F053AA5896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE event_procedure ADD CONSTRAINT FK_1AA9822271F7E88B FOREIGN KEY (event_id) REFERENCES club_event (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE event_procedure ADD CONSTRAINT FK_1AA98222896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE event_procedure');
        $this->addSql('DROP TABLE association_profile');
    }
}
