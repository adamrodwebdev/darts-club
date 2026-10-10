<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Calendrier : événements, inscriptions, photos, posts, abonnement iCal';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD calendar_token_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64925F693DF ON "user" (calendar_token_hash)');

        $this->addSql('CREATE TABLE club_event (id UUID NOT NULL, title VARCHAR(120) NOT NULL, description TEXT NOT NULL, location VARCHAR(160) NOT NULL, starts_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, ends_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, capacity INT DEFAULT NULL, status VARCHAR(10) NOT NULL, cancel_reason VARCHAR(200) DEFAULT NULL, summary TEXT DEFAULT NULL, organizer_name VARCHAR(80) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, week_reminder_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, day_reminder_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_club_event_starts ON club_event (starts_at)');

        $this->addSql('CREATE TABLE event_registration (id UUID NOT NULL, registered_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, event_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_8FBBAD54A76ED395 ON event_registration (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_registration_event_user ON event_registration (event_id, user_id)');
        $this->addSql('ALTER TABLE event_registration ADD CONSTRAINT FK_8FBBAD5471F7E88B FOREIGN KEY (event_id) REFERENCES club_event (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE event_registration ADD CONSTRAINT FK_8FBBAD54A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql('CREATE TABLE event_photo (id UUID NOT NULL, uploader_name VARCHAR(80) NOT NULL, uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, large_id VARCHAR(32) NOT NULL, thumb_id VARCHAR(32) NOT NULL, width INT NOT NULL, height INT NOT NULL, caption VARCHAR(120) DEFAULT NULL, event_id UUID NOT NULL, uploader_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_55AC353471F7E88B ON event_photo (event_id)');
        $this->addSql('CREATE INDEX IDX_55AC353416678C77 ON event_photo (uploader_id)');
        $this->addSql('ALTER TABLE event_photo ADD CONSTRAINT FK_55AC353471F7E88B FOREIGN KEY (event_id) REFERENCES club_event (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE event_photo ADD CONSTRAINT FK_55AC353416678C77 FOREIGN KEY (uploader_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');

        $this->addSql('CREATE TABLE event_post (id UUID NOT NULL, body TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, event_id UUID NOT NULL, author_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_4541202671F7E88B ON event_post (event_id)');
        $this->addSql('CREATE INDEX IDX_45412026F675F31B ON event_post (author_id)');
        $this->addSql('ALTER TABLE event_post ADD CONSTRAINT FK_4541202671F7E88B FOREIGN KEY (event_id) REFERENCES club_event (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE event_post ADD CONSTRAINT FK_45412026F675F31B FOREIGN KEY (author_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE event_post');
        $this->addSql('DROP TABLE event_photo');
        $this->addSql('DROP TABLE event_registration');
        $this->addSql('DROP TABLE club_event');
        $this->addSql('DROP INDEX UNIQ_8D93D64925F693DF');
        $this->addSql('ALTER TABLE "user" DROP calendar_token_hash');
    }
}
