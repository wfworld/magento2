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
 * @package     Magento_Core
 * @subpackage  unit_tests
 * @copyright   Copyright (c) 2013 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Magento\View\Design\FileResolution;

use Magento\App\State;
use Magento\App\Dir;

/**
 * StrategyPool Test
 *
 * @package Magento\View
 */
class StrategyPoolTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Magento\ObjectManager|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $objectManager;

    /**
     * @var State|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $appState;

    /**
     * @var Dir|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $dirs;

    /**
     * @var \Magento\Filesystem|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $filesystem;

    /**
     * @var StrategyPool|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $model;

    protected function setUp()
    {
        $this->objectManager = $this->getMock('Magento\ObjectManager', array(), array(), '', false);
        $this->appState = $this->getMock('Magento\App\State', array(), array(), '', false);
        $this->dirs = new Dir('base_dir');
        $this->filesystem = $this->getMock('Magento\Filesystem', array(), array(), '', false);

        $this->model = new StrategyPool(
            $this->objectManager,
            $this->appState,
            $this->dirs,
            $this->filesystem
        );
    }

    /**
     * Test, that strategy creation works and a strategy is returned.
     *
     * Do not test exact strategy returned, as it depends on configuration, which can be changed any time.
     *
     * @param string $mode
     * @dataProvider getStrategyDataProvider
     */
    public function testGetStrategy($mode)
    {
        $this->appState->expects($this->exactly(3)) // 3 similar methods tested at once
            ->method('getMode')
            ->will($this->returnValue($mode));

        $strategy = new \StdClass;
        $mapDir = 'base_dir/var/' . StrategyPool::FALLBACK_MAP_DIR;
        $mapDir = str_replace('/', DIRECTORY_SEPARATOR, $mapDir);
        $map = array(
            array(
                'Magento\View\Design\FileResolution\Strategy\Fallback\CachingProxy',
                array(
                    'mapDir' => $mapDir,
                    'baseDir' => 'base_dir'
                ),
                $strategy
            ),
            array('Magento\View\Design\FileResolution\Strategy\Fallback', array(), $strategy),
        );
        $this->objectManager->expects($this->atLeastOnce())
            ->method('create')
            ->will($this->returnValueMap($map));

        // Test
        $this->assertSame($strategy, $this->model->getFileStrategy());
        $this->assertSame($strategy, $this->model->getLocaleStrategy());
        $this->assertSame($strategy, $this->model->getViewStrategy());
    }

    /**
     * @return array
     */
    public static function getStrategyDataProvider()
    {
        return array(
            'default mode'    => array(State::MODE_DEFAULT),
            'production mode' => array(State::MODE_PRODUCTION),
            'developer mode'  => array(State::MODE_DEVELOPER),
        );
    }
}
