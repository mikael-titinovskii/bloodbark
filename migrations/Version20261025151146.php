<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261025151146 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the address-confirmation stamp and the hashed link token to app_user.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD confirmed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE app_user ADD confirmation_token_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_app_user_confirmation_token_hash ON app_user (confirmation_token_hash)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_app_user_confirmation_token_hash');
        $this->addSql('ALTER TABLE app_user DROP confirmed_at');
        $this->addSql('ALTER TABLE app_user DROP confirmation_token_hash');
    }
}
