<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251220003856 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE tontine (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT DEFAULT NULL, tontine_code VARCHAR(255) DEFAULT NULL, amount_per_point VARCHAR(255) DEFAULT NULL, total_points VARCHAR(255) DEFAULT NULL, frequency VARCHAR(255) DEFAULT NULL, start_date DATE DEFAULT NULL, next_due_date DATE DEFAULT NULL, reminder_enabled TINYINT(1) DEFAULT NULL, statut VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_3F164B7FFB88E14F (utilisateur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tontine_point (id INT AUTO_INCREMENT NOT NULL, tontine_id INT DEFAULT NULL, transaction_id INT DEFAULT NULL, point_number VARCHAR(255) DEFAULT NULL, amount VARCHAR(255) DEFAULT NULL, method VARCHAR(255) DEFAULT NULL, pointed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_B28F631ADEB5C9FD (tontine_id), INDEX IDX_B28F631A2FC0CB0F (transaction_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tontine_reminder (id INT AUTO_INCREMENT NOT NULL, tontine_id INT DEFAULT NULL, reminder_type VARCHAR(255) DEFAULT NULL, reminder_channel VARCHAR(255) DEFAULT NULL, scheduled_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', sent VARCHAR(255) DEFAULT NULL, sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_C438C413DEB5C9FD (tontine_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE transaction (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT DEFAULT NULL, tontine_id INT DEFAULT NULL, amount VARCHAR(255) DEFAULT NULL, payment_method VARCHAR(255) DEFAULT NULL, provider VARCHAR(255) DEFAULT NULL, external_reference VARCHAR(255) DEFAULT NULL, statut VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_723705D1FB88E14F (utilisateur_id), INDEX IDX_723705D1DEB5C9FD (tontine_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_UUID (uuid), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE tontine ADD CONSTRAINT FK_3F164B7FFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE tontine_point ADD CONSTRAINT FK_B28F631ADEB5C9FD FOREIGN KEY (tontine_id) REFERENCES tontine (id)');
        $this->addSql('ALTER TABLE tontine_point ADD CONSTRAINT FK_B28F631A2FC0CB0F FOREIGN KEY (transaction_id) REFERENCES transaction (id)');
        $this->addSql('ALTER TABLE tontine_reminder ADD CONSTRAINT FK_C438C413DEB5C9FD FOREIGN KEY (tontine_id) REFERENCES tontine (id)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1DEB5C9FD FOREIGN KEY (tontine_id) REFERENCES tontine (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tontine DROP FOREIGN KEY FK_3F164B7FFB88E14F');
        $this->addSql('ALTER TABLE tontine_point DROP FOREIGN KEY FK_B28F631ADEB5C9FD');
        $this->addSql('ALTER TABLE tontine_point DROP FOREIGN KEY FK_B28F631A2FC0CB0F');
        $this->addSql('ALTER TABLE tontine_reminder DROP FOREIGN KEY FK_C438C413DEB5C9FD');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1FB88E14F');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1DEB5C9FD');
        $this->addSql('DROP TABLE tontine');
        $this->addSql('DROP TABLE tontine_point');
        $this->addSql('DROP TABLE tontine_reminder');
        $this->addSql('DROP TABLE transaction');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
