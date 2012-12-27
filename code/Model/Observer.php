<?php

/*
 * Copyright 2011 Daniel Sloof
 * http://www.rubick.nl/
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
*/

/**
 * Observer that generates migrations and logs backend configuration changes.
 *
 * @author Daniel Sloof <daniel@rubick.nl>
 */
class Rubick_MigrationHelper_Model_Observer
{

    /**
     * Determines how large version parts can be.
     */
    const VERSION_MAX = 9;

    /**
     * Entry point for the observer.
     *
     * @param Varien_Event_Observer $observer
     * @return \Rubick_MigrationHelper_Model_Observer
     */
    public function configSaveAfter($observer)
    {
        $data = $observer->getDataObject();
        if ($data->isValueChanged()) {
            /**
             * Generate a message from migration data.
             */
            $migration = array(
                'path'     => $data->getPath(),
                'value'    => $data->getValue(),
                'scope'    => $data->getScope(),
                'scope_id' => $data->getScopeId()
            );
            $message = Mage::getModel('migration_helper/message_migration', $migration);

            /**
             * Log the migration, just in case.
             */
            Mage::log($migration, null, 'migrations.log');

            /**
             * Generate a physical migration if module is configured that way.
             */
            if ($this->_doGenerateMigrations()) {
                $this->_generateMigration($message);
            }

            /**
             * Add the message to the admin session.
             */
            $this->_getAdminSession()->addMessage($message);
        }
        return $this;
    }

    /**
     * Returns the admin session.
     *
     * @return Mage_Adminhtml_Model_Session
     */
    protected function _getAdminSession()
    {
        return Mage::getSingleton('adminhtml/session');
    }

    /**
     * Bumps the version of the migration module.
     * Returns an array with previous and current version.
     * If previous version is false, we are dealing with an install rather than upgrade.
     *
     * @param Mage_Core_Model_Config_Base $moduleConfig
     * @return array
     */
    protected function _bumpVersion(&$moduleConfig)
    {
        /**
         * Extract previous version.
         */
        $moduleNode      = $moduleConfig->getNode('modules')->children()->{$this->_getMigrationModuleName()};
        $previousVersion = (string)$moduleNode->version;
        if (empty($previousVersion)) {
            $previousVersion = false;
        }

        /**
         * Generate current version.
         */
        $currentVersion = '0.0.1';
        if ($previousVersion) {
            $versionInfo = explode('.', $previousVersion);
            if (++$versionInfo[2] == self::VERSION_MAX + 1) {
                $versionInfo[2] = 0;
                if (++$versionInfo[1] == self::VERSION_MAX + 1) {
                    $versionInfo[1] = 0;
                    $versionInfo[0]++;
                }
            }
            $currentVersion = implode('.', $versionInfo);
        }
        $moduleNode->version = $currentVersion;

        /**
         * Return previous and current versions.
         */
        return array(
            'previous' => $previousVersion,
            'current'  => $currentVersion
        );
    }

    /**
     * Configuration value that determines if we should generate migrations.
     *
     * @return bool
     */
    protected function _doGenerateMigrations()
    {
        return (bool)Mage::getStoreConfig('migration_helper/generate_migrations');
    }

    /**
     * Configuration value that determines the name of our migration module.
     *
     * TODO:
     * We can get this configuration value through:
     * config -> global -> resources -> (resource_name) -> setup -> module
     *
     * @return string
     */
    protected function _getMigrationModuleName()
    {
        return Mage::getStoreConfig('migration_helper/migration_module');
    }

    /**
     * Configuration value that determines the resource node of our migration module.
     *
     * @return string
     */
    protected function _getMigrationModuleResource()
    {
        return Mage::getStoreConfig('migration_helper/migration_resource');
    }

    /**
     * Gets the path to bootstrap file of our migration module.
     *
     * @return string
     */
    protected function _getMigrationModuleBootstrapFile()
    {
        return Mage::getModel('core/config_options')->getEtcDir() . DS . 'modules' . DS .
            $this->_getMigrationModuleName() . '.xml';
    }

    /**
     * Gets the path to the migration script directory.
     * If the directory does not exist, it will be created.
     *
     * @param Mage_Core_Model_Config_Base $moduleConfig
     * @return string
     * @exception Mage_Exception
     */
    protected function _getMigrationModuleDirectory($moduleConfig = null)
    {
        /**
         * We need this configuration to determine codepool.
         */
        if ($moduleConfig === null) {
            $moduleConfig = $this->_getMigrationModuleConfig();
        }

        /**
         * Extract codepool from module configuration.
         */
        $moduleName = $this->_getMigrationModuleName();
        $codePool = (string)$moduleConfig->getNode('modules')->children()->{$moduleName}->codePool;

        /**
         * Get the path to the migration script directory based on codepool and resource.
         */
        $configOptions = Mage::getModel('core/config_options');
        $directory = $configOptions->getAppDir() . DS . 'code' . DS . $codePool .
            DS . str_replace('_', DS, $moduleName) . DS . 'data' . DS . $this->_getMigrationModuleResource();

        /**
         * Creates the directory if it does not exist and returns the path.
         */
        if (!$configOptions->createDirIfNotExists($directory)) {
            Mage::throwException(sprintf('Could not find nor create: %s', $directory));
        }
        return $directory;
    }

    /**
     * Determines if the migration module is actually present and loaded.
     * Returns the whole config if the module was found and loaded.
     *
     * @return Mage_Core_Model_Config_Base
     */
    protected function _getMigrationModuleConfig()
    {
        $bootstrapPath = $this->_getMigrationModuleBootstrapFile();

        /**
         * Bootstrap file doesn't even exist.
         */
        if(!file_exists($bootstrapPath)) {
            return false;
        }

        /**
         * Loads config tree based on bootstrap file.
         */
        $moduleConfig = Mage::getModel('core/config_base');
        $fileConfig   = Mage::getModel('core/config_base');
        $moduleConfig->loadString('<config/>');
        $fileConfig->loadFile($bootstrapPath);
        $moduleConfig->extend($fileConfig);

        /**
         * Check if the node for the migration module exists.
         */
        $moduleNode = $moduleConfig->getNode('modules')->children()->{$this->_getMigrationModuleName()};
        if (!$moduleNode) {
            return false;
        }

        /**
         * Check if the module is active.
         */
        return 'true' === (string)$moduleNode->active ? $moduleConfig : false;
    }

    /**
     * Overwrite bootstrap file with new XML data.
     *
     * @param Mage_Core_Model_Config_Base $moduleConfig
     * @return bool
     */
    protected function _saveMigrationModuleConfig($moduleConfig)
    {
        /**
         * Overwrite the bootstrap file.
         */
        return (bool)file_put_contents(
            $this->_getMigrationModuleBootstrapFile(),
            $moduleConfig->getNode()->asNiceXml('', 0)
        );
    }

    /**
     * Generates a migration for a specific migration message.
     *
     * @param Rubick_MigrationHelper_Model_Message_Migration $message
     * @return \Rubick_MigrationHelper_Model_Observer
     * @exception Mage_Exception
     */
    protected function _generateMigration($message)
    {
        $moduleConfig = $this->_getMigrationModuleConfig();
        if (!$moduleConfig) {
            Mage::throwException('You specified to generate migrations, but the migrations module was not found or loaded.');
        }

        /**
         * Get current and previous versions.
         */
        $versionInfo = $this->_bumpVersion($moduleConfig);

        /**
         * Save current version to bootstrap.
         */
        if (!$this->_saveMigrationModuleConfig($moduleConfig)) {
            Mage::throwException('Could not save new version to migration bootstrap. Is it writable?');
        }

        /**
         * Create the actual migration file.
         */
        if (!$this->_createMigrationFile($message, $versionInfo)) {
            Mage::throwException('Could not create migration.');
        }
        return $this;
    }

    /**
     * Creates a filename for the migration file depending on version.
     *
     * @param array $versionInfo
     * @return string
     */
    protected function _getMigrationFileName($versionInfo)
    {
        return ($versionInfo['previous'] ? ('data-upgrade-' . $versionInfo['previous'] . '-') : 'data-install-') .
            $versionInfo['current'] . '.php';
    }

    /**
     * Creates a migration file for specific message and version.
     *
     * @param Rubick_MigrationHelper_Model_Message_Migration $message
     * @param array $versionInfo
     * @return bool
     */
    protected function _createMigrationFile($message, $versionInfo)
    {
        /**
         * Get the path to our migration file.
         */
        $migrationFilePath = $this->_getMigrationModuleDirectory() . DS . $this->_getMigrationFileName($versionInfo);

        /**
         * Get the migration setup code.
         */
        $setupCode = '<?php' . str_repeat(PHP_EOL, 2) .
            '$installer = $this;' . PHP_EOL .
            '$installer->startSetup();' . PHP_EOL .
            Mage::helper('migration_helper')->getMigrationFromMessage($message) . PHP_EOL .
            '$installer->endSetup();' . PHP_EOL;

        /**
         * Save the migration file.
         */
        return (bool)file_put_contents($migrationFilePath, $setupCode);
    }

}
