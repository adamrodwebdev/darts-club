<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Double authentification : graine TOTP chiffrée, codes de secours';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD totp_secret TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD totp_enabled BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE "user" ALTER totp_enabled DROP DEFAULT');
        $this->addSql('ALTER TABLE "user" ADD backup_codes JSON DEFAULT \'[]\' NOT NULL');
        $this->addSql('ALTER TABLE "user" ALTER backup_codes DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP totp_secret');
        $this->addSql('ALTER TABLE "user" DROP totp_enabled');
        $this->addSql('ALTER TABLE "user" DROP backup_codes');
    }
}
