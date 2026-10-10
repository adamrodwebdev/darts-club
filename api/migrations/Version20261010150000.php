<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Notifications : centre de notifications, abonnements Web Push, préférences';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD notification_preferences JSON DEFAULT \'{}\' NOT NULL');
        $this->addSql('ALTER TABLE "user" ALTER notification_preferences DROP DEFAULT');

        $this->addSql('CREATE TABLE notification (id UUID NOT NULL, category VARCHAR(20) NOT NULL, title VARCHAR(120) NOT NULL, body TEXT NOT NULL, link VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, recipient_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_notification_recipient_created ON notification (recipient_id, created_at)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAE92F8F78 FOREIGN KEY (recipient_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql('CREATE TABLE push_subscription (id UUID NOT NULL, endpoint TEXT NOT NULL, public_key VARCHAR(255) NOT NULL, auth_token VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_562830F3C4420F7B ON push_subscription (endpoint)');
        $this->addSql('CREATE INDEX IDX_562830F3A76ED395 ON push_subscription (user_id)');
        $this->addSql('ALTER TABLE push_subscription ADD CONSTRAINT FK_562830F3A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE push_subscription');
        $this->addSql('DROP TABLE notification');
        $this->addSql('ALTER TABLE "user" DROP notification_preferences');
    }
}
