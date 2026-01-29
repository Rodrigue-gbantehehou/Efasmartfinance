<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260129165159 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE two_factor_auth (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, totp_secret VARCHAR(255) DEFAULT NULL, backup_codes VARCHAR(255) DEFAULT NULL, is_enabled TINYINT(1) DEFAULT NULL, method VARCHAR(20) DEFAULT NULL, created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_used_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_2040D91CA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE two_factor_auth ADD CONSTRAINT FK_2040D91CA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE security_settings ADD totp_secret VARCHAR(255) DEFAULT NULL, ADD backup_codes VARCHAR(255) DEFAULT NULL, ADD last_two_factor_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE two_factor_auth DROP FOREIGN KEY FK_2040D91CA76ED395');
        $this->addSql('DROP TABLE two_factor_auth');
        $this->addSql('ALTER TABLE security_settings DROP totp_secret, DROP backup_codes, DROP last_two_factor_at');
    }
}
