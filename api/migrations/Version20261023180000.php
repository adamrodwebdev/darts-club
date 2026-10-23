<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Réponses sans compte : le nom d\'une nouvelle demande attend sa confirmation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_guest_response ADD pending_name VARCHAR(40) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_guest_response DROP pending_name');
    }
}
