<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Double authentification : codes TOTP à usage unique, fermeture des autres sessions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD totp_last_timestep INT DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD session_generation INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP session_generation');
        $this->addSql('ALTER TABLE "user" DROP totp_last_timestep');
    }
}
