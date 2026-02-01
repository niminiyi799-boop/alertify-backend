<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251228XXXXXX extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_profile table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE user_profile (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                user_email VARCHAR(191) NOT NULL,
                latitude DECIMAL(10,8) NOT NULL,
                longitude DECIMAL(11,8) NOT NULL,
                address TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE INDEX UNIQ_USER_PROFILE_EMAIL (user_email),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_profile');
    }
}
