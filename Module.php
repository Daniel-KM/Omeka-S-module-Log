<?php

/*
 * Copyright Daniel Berthereau, 2017-2018
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace Log;

use Omeka\Module\AbstractModule;
use Zend\ServiceManager\ServiceLocatorInterface;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $connection = $serviceLocator->get('Omeka\Connection');
        $sql = <<<'SQL'
CREATE TABLE log (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT DEFAULT NULL,
    job_id INT DEFAULT NULL,
    reference VARCHAR(190) DEFAULT '' NOT NULL,
    severity INT DEFAULT 0 NOT NULL,
    message LONGTEXT NOT NULL,
    context LONGTEXT NOT NULL COMMENT '(DC2Type:json_array)',
    created DATETIME NOT NULL,
    INDEX user_idx (user_id),
    INDEX job_idx (job_id),
    INDEX reference_idx (reference),
    INDEX severity_idx (severity),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
ALTER TABLE log ADD CONSTRAINT FK_8F3F68C5A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL;
ALTER TABLE log ADD CONSTRAINT FK_8F3F68C5BE04EA9 FOREIGN KEY (job_id) REFERENCES job (id) ON DELETE CASCADE;
SQL;
        $sqls = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($sqls as $sql) {
            $connection->exec($sql);
        }
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        // TODO Move all job logs into standard log.
        $connection = $serviceLocator->get('Omeka\Connection');
        $sql = <<<'SQL'
ALTER TABLE log DROP FOREIGN KEY FK_8F3F68C5A76ED395;
ALTER TABLE log DROP FOREIGN KEY FK_8F3F68C5BE04EA9;
DROP TABLE IF EXISTS log;
SQL;
        $sqls = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($sqls as $sql) {
            $connection->exec($sql);
        }
    }
}
