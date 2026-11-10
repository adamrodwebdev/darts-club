<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Coupures de presse : une photo de la galerie classée « Dans la presse », avec journal, date de parution, titre et crédit facultatifs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_photo ADD press_clipping BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE event_photo ALTER press_clipping DROP DEFAULT');
        $this->addSql('ALTER TABLE event_photo ADD press_outlet VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE event_photo ADD press_published_on DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE event_photo ADD press_title VARCHAR(200) DEFAULT NULL');
        $this->addSql('ALTER TABLE event_photo ADD press_credit VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_photo DROP press_clipping');
        $this->addSql('ALTER TABLE event_photo DROP press_outlet');
        $this->addSql('ALTER TABLE event_photo DROP press_published_on');
        $this->addSql('ALTER TABLE event_photo DROP press_title');
        $this->addSql('ALTER TABLE event_photo DROP press_credit');
    }
}
