<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Magento
 * @package     Magento_Install
 * @subpackage  integration_tests
 * @copyright   Copyright (c) 2013 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Magento\Install\Model;

class InstallerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    protected static $_tmpDir = '';

    /**
     * @var string
     */
    protected static $_tmpConfigFile = '';

    public static function setUpBeforeClass()
    {
        self::$_tmpDir = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->get('Magento\App\Dir')
            ->getDir(\Magento\App\Dir::VAR_DIR) . DIRECTORY_SEPARATOR . 'InstallerTest';
        self::$_tmpConfigFile = self::$_tmpDir . DIRECTORY_SEPARATOR . 'local.xml';
        mkdir(self::$_tmpDir);
    }

    public static function tearDownAfterClass()
    {
        \Magento\Io\File::rmdirRecursive(self::$_tmpDir);
    }

    /**
     * Emulate configuration directory for the installer config model.
     * Method usage should be accompanied with '@magentoAppIsolation enabled' because of the object manager pollution.
     *
     * @param bool $emulateConfig
     * @return \Magento\Install\Model\Installer
     */
    protected function _getModel($emulateConfig = false)
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        if ($emulateConfig) {
            $installerConfig = new \Magento\Install\Model\Installer\Config(
                $objectManager->get('Magento\Install\Model\Installer'),
                $objectManager->get('Magento\App\RequestInterface'),
                new \Magento\App\Dir(__DIR__, array(), array(\Magento\App\Dir::CONFIG => self::$_tmpDir)),
                new \Magento\Filesystem(new \Magento\Filesystem\Adapter\Local()),
                $objectManager->get('Magento\Core\Model\StoreManager')
            );
            $objectManager->addSharedInstance($installerConfig, 'Magento\Install\Model\Installer\Config');
        }
        return $objectManager->create('Magento\Install\Model\Installer');
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoAppArea install
     */
    public function testCreateAdministrator()
    {
        $userName = 'installer_test';
        $userPassword = '123123q';
        $userData = array(
            'username'  => $userName,
            'firstname' => 'First Name',
            'lastname'  => 'Last Name',
            'email'     => 'installer_test@example.com',
        );

        /** @var $user \Magento\User\Model\User */
        $user = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
            ->create('Magento\User\Model\User');
        $user->loadByUsername($userName);
        $this->assertEmpty($user->getId());

        $this->_getModel()->createAdministrator($userData + array('password' => $userPassword));

        $user->loadByUsername($userName);
        $this->assertNotEmpty($user->getId());
        $this->assertEquals($userData, array_intersect_assoc($user->getData(), $userData));
        $this->assertNotEmpty($user->getPassword(), 'Password hash is expected to be loaded.');
        $this->assertNotEquals(
            $userPassword, $user->getPassword(),
            'Original password should not be stored/loaded as is for security reasons.'
        );
        $this->assertInstanceOf('Magento\User\Model\Role', $user->getRole());
        $this->assertEquals(1, $user->getRole()->getId(), 'User has to have admin privileges.');
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testInstallEncryptionKey()
    {
        $keyPlaceholder = \Magento\Install\Model\Installer\Config::TMP_ENCRYPT_KEY_VALUE;
        $fixtureConfigData = "<key>$keyPlaceholder</key>";
        $expectedConfigData = '<key>d41d8cd98f00b204e9800998ecf8427e</key>';

        file_put_contents(self::$_tmpConfigFile, $fixtureConfigData);
        $this->assertEquals($fixtureConfigData, file_get_contents(self::$_tmpConfigFile));
        $this->_getModel(true)->installEncryptionKey('d41d8cd98f00b204e9800998ecf8427e');
        $this->assertEquals($expectedConfigData, file_get_contents(self::$_tmpConfigFile));
    }

    /**
     * @magentoAppIsolation enabled
     * @expectedException \Magento\Exception
     * @expectedExceptionMessage Key must not exceed
     */
    public function testInstallEncryptionKeySizeViolation()
    {
        $this->_getModel(true)->installEncryptionKey(str_repeat('a', 57));
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetValidEncryptionKey()
    {
        $validKey = 'abcdef1234567890';
        $this->assertEquals($validKey, $this->_getModel()->getValidEncryptionKey($validKey));
    }

    /**
     * @magentoAppIsolation enabled
     * @expectedException \Magento\Exception
     * @expectedExceptionMessage Key must not exceed
     */
    public function testGetValidEncryptionKeySizeViolation()
    {
        $this->_getModel()->getValidEncryptionKey(str_repeat('1', 57));
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetValidEncryptionKeyRandom()
    {
        $model = $this->_getModel();
        $actualKey = $model->getValidEncryptionKey();
        $this->assertRegExp('/^[a-f0-9]{32}$/', $actualKey);
        $this->assertNotEquals($actualKey, $model->getValidEncryptionKey());
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDataFixture Magento/Backend/controllers/_files/cache/all_types_disabled.php
     */
    public function testFinish()
    {
        $configFile = \Magento\TestFramework\Helper\Bootstrap::getInstance()->getAppInstallDir() . '/etc/local.xml';
        copy($configFile, self::$_tmpConfigFile);

        $this->_getModel(true)->finish();

        /** @var $cacheState \Magento\App\Cache\StateInterface */
        $cacheState = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
            ->create('Magento\App\Cache\StateInterface');

        /** @var \Magento\App\Cache\TypeListInterface $cacheTypeList */
        $cacheTypeList = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
            ->create('Magento\App\Cache\TypeListInterface');
        $types = array_keys($cacheTypeList->getTypes());
        foreach ($types as $type) {
            $this->assertTrue(
                $cacheState->isEnabled($type),
                "'$type' cache type has not been enabled after installation"
            );
        }
    }
}
