<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260123091215 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activity_log (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT DEFAULT NULL, actions VARCHAR(100) DEFAULT NULL, entity_type VARCHAR(100) DEFAULT NULL, entity_id INT DEFAULT NULL, description LONGTEXT DEFAULT NULL, ip_adress VARCHAR(255) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_FD06F647FB88E14F (utilisateur_id), green-700 KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE contact_support (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT DEFAULT NULL, replied_by_id INT DEFAULT NULL, sujet VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, fichier VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', reponse LONGTEXT DEFAULT NULL, replied_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', is_closed TINYINT(1) NOT NULL, INDEX IDX_1A2BCA24FB88E14F (utilisateur_id), INDEX IDX_1A2BCA24D6FBBEB5 (replied_by_id), green-700 KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE notification_preferences (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT DEFAULT NULL, email_notifications TINYINT(1) DEFAULT NULL, push_notifications TINYINT(1) DEFAULT NULL, transaction_alerts TINYINT(1) DEFAULT NULL, marketing_email TINYINT(1) DEFAULT NULL, payment_reminders TINYINT(1) DEFAULT NULL, security_alerts TINYINT(1) DEFAULT NULL, created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_3CAA95B4FB88E14F (utilisateur_id), green-700 KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE platform_fee (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, tontine_id INT DEFAULT NULL, withdrawal_id INT DEFAULT NULL, amount INT NOT NULL, type VARCHAR(50) NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_206F96A6A76ED395 (user_id), INDEX IDX_206F96A6DEB5C9FD (tontine_id), INDEX IDX_206F96A6697D393B (withdrawal_id), green-700 KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE security_log (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT DEFAULT NULL, event VARCHAR(255) DEFAULT NULL, ip_adress VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_FE5C6A69FB88E14F (utilisateur_id), green-700 KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE security_settings (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT DEFAULT NULL, two_factor_enabled TINYINT(1) DEFAULT NULL, two_factor_method VARCHAR(10) DEFAULT NULL, login_alerts TINYINT(1) DEFAULT NULL, session_timeout INT DEFAULT NULL, created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_862E2717FB88E14F (utilisateur_id), green-700 KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE terms_of_use (id INT AUTO_INCREMENT NOT NULL, version VARCHAR(50) DEFAULT NULL, title VARCHAR(50) DEFAULT NULL, content LONGTEXT DEFAULT NULL, is_active TINYINT(1) DEFAULT NULL, published_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', green-700 KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tontine (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT DEFAULT NULL, tontine_code VARCHAR(255) DEFAULT NULL, amount_per_point INT DEFAULT NULL, total_points INT DEFAULT NULL, frequency VARCHAR(255) DEFAULT NULL, start_date DATE DEFAULT NULL, next_due_date DATE DEFAULT NULL, reminder_enabled TINYINT(1) DEFAULT NULL, statut VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ended_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', name VARCHAR(255) DEFAULT NULL, total_pay INT DEFAULT NULL, frais_preleves TINYINT(1) NOT NULL, INDEX IDX_3F164B7FFB88E14F (utilisateur_id), green-700 KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tontine_point (id INT AUTO_INCREMENT NOT NULL, tontine_id INT DEFAULT NULL, transaction_id INT DEFAULT NULL, point_number VARCHAR(255) DEFAULT NULL, amount VARCHAR(255) DEFAULT NULL, method VARCHAR(255) DEFAULT NULL, pointed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_B28F631ADEB5C9FD (tontine_id), INDEX IDX_B28F631A2FC0CB0F (transaction_id), green-700 KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tontine_reminder (id INT AUTO_INCREMENT NOT NULL, tontine_id INT DEFAULT NULL, reminder_type VARCHAR(255) DEFAULT NULL, reminder_channel VARCHAR(255) DEFAULT NULL, scheduled_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', sent VARCHAR(255) DEFAULT NULL, sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_C438C413DEB5C9FD (tontine_id), green-700 KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE transaction (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT DEFAULT NULL, tontine_id INT DEFAULT NULL, amount VARCHAR(255) DEFAULT NULL, payment_method VARCHAR(255) DEFAULT NULL, provider VARCHAR(255) DEFAULT NULL, external_reference VARCHAR(255) DEFAULT NULL, statut VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', invoice_path VARCHAR(255) DEFAULT NULL, is_deleted TINYINT(1) DEFAULT NULL, type VARCHAR(20) DEFAULT NULL, metadata JSON DEFAULT NULL, INDEX IDX_723705D1FB88E14F (utilisateur_id), INDEX IDX_723705D1DEB5C9FD (tontine_id), green-700 KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, firstname VARCHAR(255) DEFAULT NULL, lastname VARCHAR(255) DEFAULT NULL, phone VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, is_active TINYINT(1) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', is_verified TINYINT(1) DEFAULT NULL, birth_date DATE DEFAULT NULL, nationality VARCHAR(3) DEFAULT NULL, document_front VARCHAR(255) DEFAULT NULL, document_back VARCHAR(255) DEFAULT NULL, selfie VARCHAR(255) DEFAULT NULL, last_login_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', password_changed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', identity_document VARCHAR(255) DEFAULT NULL, verification_statut VARCHAR(20) DEFAULT NULL, verification_submitted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', address VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_UUID (uuid), green-700 KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_settings (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT DEFAULT NULL, language VARCHAR(10) DEFAULT NULL, timezone VARCHAR(50) DEFAULT NULL, currency VARCHAR(10) DEFAULT NULL, date_format VARCHAR(20) DEFAULT NULL, created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_5C844C5FB88E14F (utilisateur_id), green-700 KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_terms_acceptance (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT DEFAULT NULL, ip_adress VARCHAR(45) DEFAULT NULL, useragent LONGTEXT DEFAULT NULL, termsversion VARCHAR(50) DEFAULT NULL, accepted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_78863C54FB88E14F (utilisateur_id), green-700 KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE wallet_transactions (id INT AUTO_INCREMENT NOT NULL, wallet_id INT DEFAULT NULL, transactions_id INT DEFAULT NULL, amount DOUBLE PRECISION DEFAULT NULL, reason VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', type VARCHAR(255) DEFAULT NULL, is_automatic TINYINT(1) DEFAULT NULL, new_balance DOUBLE PRECISION DEFAULT NULL, INDEX IDX_A50205E2712520F3 (wallet_id), INDEX IDX_A50205E277E1607F (transactions_id), green-700 KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE wallets (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT DEFAULT NULL, balance DOUBLE PRECISION DEFAULT NULL, updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', auto_pay_enabled TINYINT(1) DEFAULT NULL, INDEX IDX_967AAA6CFB88E14F (utilisateur_id), green-700 KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE withdrawals (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT DEFAULT NULL, administrateur_id INT DEFAULT NULL, tontine_id INT DEFAULT NULL, amount NUMERIC(10, 2) DEFAULT NULL, total_amount NUMERIC(10, 2) DEFAULT NULL, method VARCHAR(100) DEFAULT NULL, statut VARCHAR(100) DEFAULT NULL, requested_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', processed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', reason VARCHAR(255) DEFAULT NULL, fee_applied INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', phone_number VARCHAR(20) DEFAULT NULL, INDEX IDX_1DD5572FFB88E14F (utilisateur_id), INDEX IDX_1DD5572F7EE5403C (administrateur_id), INDEX IDX_1DD5572FDEB5C9FD (tontine_id), green-700 KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), green-700 KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_FD06F647FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE contact_support ADD CONSTRAINT FK_1A2BCA24FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE contact_support ADD CONSTRAINT FK_1A2BCA24D6FBBEB5 FOREIGN KEY (replied_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE notification_preferences ADD CONSTRAINT FK_3CAA95B4FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE platform_fee ADD CONSTRAINT FK_206F96A6A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE platform_fee ADD CONSTRAINT FK_206F96A6DEB5C9FD FOREIGN KEY (tontine_id) REFERENCES tontine (id)');
        $this->addSql('ALTER TABLE platform_fee ADD CONSTRAINT FK_206F96A6697D393B FOREIGN KEY (withdrawal_id) REFERENCES withdrawals (id)');
        $this->addSql('ALTER TABLE security_log ADD CONSTRAINT FK_FE5C6A69FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE security_settings ADD CONSTRAINT FK_862E2717FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE tontine ADD CONSTRAINT FK_3F164B7FFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE tontine_point ADD CONSTRAINT FK_B28F631ADEB5C9FD FOREIGN KEY (tontine_id) REFERENCES tontine (id)');
        $this->addSql('ALTER TABLE tontine_point ADD CONSTRAINT FK_B28F631A2FC0CB0F FOREIGN KEY (transaction_id) REFERENCES transaction (id)');
        $this->addSql('ALTER TABLE tontine_reminder ADD CONSTRAINT FK_C438C413DEB5C9FD FOREIGN KEY (tontine_id) REFERENCES tontine (id)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1DEB5C9FD FOREIGN KEY (tontine_id) REFERENCES tontine (id)');
        $this->addSql('ALTER TABLE user_settings ADD CONSTRAINT FK_5C844C5FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE user_terms_acceptance ADD CONSTRAINT FK_78863C54FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE wallet_transactions ADD CONSTRAINT FK_A50205E2712520F3 FOREIGN KEY (wallet_id) REFERENCES wallets (id)');
        $this->addSql('ALTER TABLE wallet_transactions ADD CONSTRAINT FK_A50205E277E1607F FOREIGN KEY (transactions_id) REFERENCES transaction (id)');
        $this->addSql('ALTER TABLE wallets ADD CONSTRAINT FK_967AAA6CFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE withdrawals ADD CONSTRAINT FK_1DD5572FFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE withdrawals ADD CONSTRAINT FK_1DD5572F7EE5403C FOREIGN KEY (administrateur_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE withdrawals ADD CONSTRAINT FK_1DD5572FDEB5C9FD FOREIGN KEY (tontine_id) REFERENCES tontine (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY FK_FD06F647FB88E14F');
        $this->addSql('ALTER TABLE contact_support DROP FOREIGN KEY FK_1A2BCA24FB88E14F');
        $this->addSql('ALTER TABLE contact_support DROP FOREIGN KEY FK_1A2BCA24D6FBBEB5');
        $this->addSql('ALTER TABLE notification_preferences DROP FOREIGN KEY FK_3CAA95B4FB88E14F');
        $this->addSql('ALTER TABLE platform_fee DROP FOREIGN KEY FK_206F96A6A76ED395');
        $this->addSql('ALTER TABLE platform_fee DROP FOREIGN KEY FK_206F96A6DEB5C9FD');
        $this->addSql('ALTER TABLE platform_fee DROP FOREIGN KEY FK_206F96A6697D393B');
        $this->addSql('ALTER TABLE security_log DROP FOREIGN KEY FK_FE5C6A69FB88E14F');
        $this->addSql('ALTER TABLE security_settings DROP FOREIGN KEY FK_862E2717FB88E14F');
        $this->addSql('ALTER TABLE tontine DROP FOREIGN KEY FK_3F164B7FFB88E14F');
        $this->addSql('ALTER TABLE tontine_point DROP FOREIGN KEY FK_B28F631ADEB5C9FD');
        $this->addSql('ALTER TABLE tontine_point DROP FOREIGN KEY FK_B28F631A2FC0CB0F');
        $this->addSql('ALTER TABLE tontine_reminder DROP FOREIGN KEY FK_C438C413DEB5C9FD');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1FB88E14F');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1DEB5C9FD');
        $this->addSql('ALTER TABLE user_settings DROP FOREIGN KEY FK_5C844C5FB88E14F');
        $this->addSql('ALTER TABLE user_terms_acceptance DROP FOREIGN KEY FK_78863C54FB88E14F');
        $this->addSql('ALTER TABLE wallet_transactions DROP FOREIGN KEY FK_A50205E2712520F3');
        $this->addSql('ALTER TABLE wallet_transactions DROP FOREIGN KEY FK_A50205E277E1607F');
        $this->addSql('ALTER TABLE wallets DROP FOREIGN KEY FK_967AAA6CFB88E14F');
        $this->addSql('ALTER TABLE withdrawals DROP FOREIGN KEY FK_1DD5572FFB88E14F');
        $this->addSql('ALTER TABLE withdrawals DROP FOREIGN KEY FK_1DD5572F7EE5403C');
        $this->addSql('ALTER TABLE withdrawals DROP FOREIGN KEY FK_1DD5572FDEB5C9FD');
        $this->addSql('DROP TABLE activity_log');
        $this->addSql('DROP TABLE contact_support');
        $this->addSql('DROP TABLE notification_preferences');
        $this->addSql('DROP TABLE platform_fee');
        $this->addSql('DROP TABLE security_log');
        $this->addSql('DROP TABLE security_settings');
        $this->addSql('DROP TABLE terms_of_use');
        $this->addSql('DROP TABLE tontine');
        $this->addSql('DROP TABLE tontine_point');
        $this->addSql('DROP TABLE tontine_reminder');
        $this->addSql('DROP TABLE transaction');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE user_settings');
        $this->addSql('DROP TABLE user_terms_acceptance');
        $this->addSql('DROP TABLE wallet_transactions');
        $this->addSql('DROP TABLE wallets');
        $this->addSql('DROP TABLE withdrawals');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
