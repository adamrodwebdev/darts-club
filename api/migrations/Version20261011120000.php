<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Administration : suspension, journal d\'audit, diffusions, annonces';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD suspended_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD suspension_reason VARCHAR(200) DEFAULT NULL');

        $this->addSql('CREATE TABLE audit_log (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, actor_name VARCHAR(80) NOT NULL, actor_id UUID DEFAULT NULL, action VARCHAR(40) NOT NULL, target VARCHAR(120) DEFAULT NULL, details JSON NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_audit_created ON audit_log (created_at)');

        $this->addSql('CREATE TABLE broadcast (id UUID NOT NULL, sent_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, label VARCHAR(20) NOT NULL, title VARCHAR(120) NOT NULL, body TEXT NOT NULL, author_name VARCHAR(80) NOT NULL, to_all_members BOOLEAN NOT NULL, recipient_count INT NOT NULL, PRIMARY KEY (id))');

        $this->addSql('CREATE TABLE announcement (id UUID NOT NULL, title VARCHAR(120) NOT NULL, body TEXT NOT NULL, pinned BOOLEAN NOT NULL, author_name VARCHAR(80) NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, edited_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_announcement_feed ON announcement (pinned, published_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE announcement');
        $this->addSql('DROP TABLE broadcast');
        $this->addSql('DROP TABLE audit_log');
        $this->addSql('ALTER TABLE "user" DROP suspended_at');
        $this->addSql('ALTER TABLE "user" DROP suspension_reason');
    }
}
