<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clés d\'accès (passkeys) : clés publiques des adhérents, identifiant WebAuthn du compte';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD passkey_handle VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_passkey_handle ON "user" (passkey_handle)');
        $this->addSql('CREATE TABLE passkey (id UUID NOT NULL, credential_id TEXT NOT NULL, public_key TEXT NOT NULL, counter INT NOT NULL, transports JSON NOT NULL, aaguid VARCHAR(36) NOT NULL, backed_up BOOLEAN NOT NULL, label VARCHAR(60) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_passkey_credential ON passkey (credential_id)');
        $this->addSql('CREATE INDEX idx_passkey_user ON passkey (user_id)');
        $this->addSql('ALTER TABLE passkey ADD CONSTRAINT FK_passkey_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE passkey');
        $this->addSql('DROP INDEX uniq_user_passkey_handle');
        $this->addSql('ALTER TABLE "user" DROP passkey_handle');
    }
}
