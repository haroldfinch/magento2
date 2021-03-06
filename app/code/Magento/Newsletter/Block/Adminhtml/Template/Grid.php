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
 * @package     Magento_Newsletter
 * @copyright   Copyright (c) 2013 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Newsletter templates grid block
 *
 * @category   Magento
 * @package    Magento_Newsletter
 * @author      Magento Core Team <core@magentocommerce.com>
 */
namespace Magento\Newsletter\Block\Adminhtml\Template;

class Grid extends \Magento\Backend\Block\Widget\Grid\Extended
{
    /**
     * @var \Magento\Newsletter\Model\Resource\Template\Collection
     */
    protected $_templateCollection;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Core\Helper\Data $coreData
     * @param \Magento\Core\Model\Url $urlModel
     * @param \Magento\Newsletter\Model\Resource\Template\Collection $templateCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Core\Helper\Data $coreData,
        \Magento\Core\Model\Url $urlModel,
        \Magento\Newsletter\Model\Resource\Template\Collection $templateCollection,
        array $data = array()
    ) {
        $this->_templateCollection = $templateCollection;
        parent::__construct($context, $coreData, $urlModel, $data);
        $this->setEmptyText(__('No Templates Found'));
    }

    protected function _prepareCollection()
    {
        $this->setCollection($this->_templateCollection->useOnlyActual());

        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $this->addColumn('template_code',
            array(
                'header'    => __('ID'),
                'index'     => 'template_id',
                'header_css_class'  => 'col-id',
                'column_css_class'  => 'col-id'
        ));
        $this->addColumn('code',
            array(
                'header'    => __('Template'),
                'index'     => 'template_code',
                'header_css_class'  => 'col-template',
                'column_css_class'  => 'col-template'
        ));

        $this->addColumn('added_at',
            array(
                'header'    => __('Added'),
                'index'     => 'added_at',
                'gmtoffset' => true,
                'type'      => 'datetime',
                'header_css_class'  => 'col-added',
                'column_css_class'  => 'col-added'
        ));

        $this->addColumn('modified_at',
            array(
                'header'    => __('Updated'),
                'index'     => 'modified_at',
                'gmtoffset' => true,
                'type'      => 'datetime',
                'header_css_class'  => 'col-updated',
                'column_css_class'  => 'col-updated'
        ));

        $this->addColumn('subject',
            array(
                'header'    => __('Subject'),
                'index'     => 'template_subject',
                'header_css_class'  => 'col-subject',
                'column_css_class'  => 'col-subject'
        ));

        $this->addColumn('sender',
            array(
                'header'    => __('Sender'),
                'index'     => 'template_sender_email',
                'renderer'  => 'Magento\Newsletter\Block\Adminhtml\Template\Grid\Renderer\Sender',
                'header_css_class'  => 'col-sender',
                'column_css_class'  => 'col-sender'
        ));

        $this->addColumn('type',
            array(
                'header'    => __('Template Type'),
                'index'     => 'template_type',
                'type'      => 'options',
                'options'   => array(
                    \Magento\Newsletter\Model\Template::TYPE_HTML   => 'html',
                    \Magento\Newsletter\Model\Template::TYPE_TEXT 	=> 'text'
                ),
                'header_css_class'  => 'col-type',
                'column_css_class'  => 'col-type'
        ));

        $this->addColumn('action',
            array(
                'header'    => __('Action'),
                'index'     => 'template_id',
                'sortable'  => false,
                'filter'    => false,
                'no_link'   => true,
                'renderer'  => 'Magento\Newsletter\Block\Adminhtml\Template\Grid\Renderer\Action',
                'header_css_class'  => 'col-actions',
                'column_css_class'  => 'col-actions'
        ));

        return $this;
    }

    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', array('id'=>$row->getId()));
    }
}

