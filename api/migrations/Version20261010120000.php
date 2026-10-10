<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table oauth_identity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE oauth_identity (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, provider VARCHAR(20) NOT NULL, subject VARCHAR(191) NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_4AE96AD8A76ED395 ON oauth_identity (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_oauth_provider_subject ON oauth_identity (provider, subject)');
        $this->addSql('ALTER TABLE oauth_identity ADD CONSTRAINT FK_4AE96AD8A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE oauth_identity');
    }
}
