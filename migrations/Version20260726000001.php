<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260726000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Alertify core tables: user, alert, alert_validation, user_notification_settings, notification';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE `user` (
                id INT AUTO_INCREMENT NOT NULL,
                email VARCHAR(180) NOT NULL,
                name VARCHAR(255) DEFAULT NULL,
                password VARCHAR(255) NOT NULL,
                roles JSON NOT NULL,
                trust_score INT NOT NULL DEFAULT 0,
                contribution_count INT NOT NULL DEFAULT 0,
                avatar_url VARCHAR(255) DEFAULT NULL,
                emergency_contact VARCHAR(50) DEFAULT NULL,
                password_reset_token VARCHAR(255) DEFAULT NULL,
                password_reset_token_expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX UNIQ_8D93D649E7927C74 (email),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ");

        $this->addSql("
            CREATE TABLE alert (
                id CHAR(36) NOT NULL COMMENT '(DC2Type:guid)',
                user_id INT NOT NULL,
                category VARCHAR(50) NOT NULL,
                description LONGTEXT NOT NULL,
                latitude DECIMAL(10,7) NOT NULL,
                longitude DECIMAL(10,7) NOT NULL,
                visibility VARCHAR(10) NOT NULL DEFAULT 'public',
                media JSON NOT NULL,
                validation_count INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX IDX_17FD46C1A76ED395 (user_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_alert_user FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ");

        $this->addSql("
            CREATE TABLE alert_validation (
                id INT AUTO_INCREMENT NOT NULL,
                user_id INT NOT NULL,
                alert_id CHAR(36) NOT NULL COMMENT '(DC2Type:guid)',
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX IDX_AV_USER (user_id),
                INDEX IDX_AV_ALERT (alert_id),
                UNIQUE INDEX unique_user_alert_validation (user_id, alert_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_av_user FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE,
                CONSTRAINT FK_av_alert FOREIGN KEY (alert_id) REFERENCES alert (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ");

        $this->addSql("
            CREATE TABLE user_notification_settings (
                id INT AUTO_INCREMENT NOT NULL,
                user_id INT NOT NULL,
                alert_radius_km INT NOT NULL DEFAULT 5,
                night_mode TINYINT(1) NOT NULL DEFAULT 0,
                enabled_categories JSON NOT NULL,
                UNIQUE INDEX UNIQ_UNS_USER (user_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_uns_user FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ");

        $this->addSql("
            CREATE TABLE notification (
                id INT AUTO_INCREMENT NOT NULL,
                user_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                body LONGTEXT NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX IDX_NOTIF_USER (user_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_notif_user FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS notification');
        $this->addSql('DROP TABLE IF EXISTS user_notification_settings');
        $this->addSql('DROP TABLE IF EXISTS alert_validation');
        $this->addSql('DROP TABLE IF EXISTS alert');
        $this->addSql('DROP TABLE IF EXISTS `user`');
    }
}
