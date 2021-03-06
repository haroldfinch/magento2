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
 * @copyright   Copyright (c) 2013 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * "Checkout" link
 */
namespace Magento\Checkout\Block;

class Link extends \Magento\Page\Block\Link
{
    /**
     * @var \Magento\Module\Manager
     */
    protected $_moduleManager;

    /**
     * @param \Magento\View\Block\Template\Context $context
     * @param \Magento\Core\Helper\Data $coreData
     * @param \Magento\Module\Manager $moduleManager
     * @param array $data
     */
    public function __construct(
        \Magento\View\Block\Template\Context $context,
        \Magento\Core\Helper\Data $coreData,
        \Magento\Module\Manager $moduleManager,
        array $data = array()
    ) {
        parent::__construct($context, $coreData, $data);
        $this->_moduleManager = $moduleManager;
    }

    /**
     * @return string
     */
    public function getHref()
    {
        return $this->getUrl('checkout', array('_secure' => true));
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    protected function _toHtml()
    {
        if (!$this->helper('Magento\Checkout\Helper\Data')->canOnepageCheckout()
            || !$this->_moduleManager->isOutputEnabled('Magento_Checkout')
        ) {
            return '';
        }
        return parent::_toHtml();
    }
}
