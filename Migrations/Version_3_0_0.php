<?php

declare(strict_types=1);

namespace MauticPlugin\HostnetAuthBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Migrations\Exception\SkipMigration;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

final class Version_3_0_0 extends AbstractMigration
{
    public function isApplicable(): bool {
        return true;
    }

    public function getDescription(): string
    {
        return 'Create plugin_auth_browsers table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE IF NOT EXISTS ' . MAUTIC_TABLE_PREFIX . 'plugin_auth_browsers (
                id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT(11) NOT NULL,
                hash VARCHAR(255) NOT NULL,
                date_added DATETIME NOT NULL,
                PRIMARY KEY (id)
            )
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('
            DROP TABLE IF EXISTS ' . MAUTIC_TABLE_PREFIX . 'plugin_auth_browsers
        ');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
