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
 * @package     Magento_Tax
 * @copyright   Copyright (c) 2013 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


/**
 * Tax Calculation Resource Model
 */
namespace Magento\Tax\Model\Resource;

class Calculation extends \Magento\Core\Model\Resource\Db\AbstractDb
{
    /**
     * Rates cache
     *
     * @var array
     */
    protected $_ratesCache              = array();

    /**
     * Primary key auto increment flag
     *
     * @var bool
     */
    protected $_isPkAutoIncrement    = false;

    /**
     * Tax data
     *
     * @var \Magento\Tax\Helper\Data
     */
    protected $_taxData;

    /**
     * @var \Magento\Core\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * Class constructor
     *
     * @param \Magento\Tax\Helper\Data $taxData
     * @param \Magento\Core\Model\StoreManagerInterface $storeManager
     * @param \Magento\App\Resource $resource
     */
    public function __construct(
        \Magento\Tax\Helper\Data $taxData,
        \Magento\Core\Model\StoreManagerInterface $storeManager,
        \Magento\App\Resource $resource
    ) {
        $this->_taxData = $taxData;
        $this->_storeManager = $storeManager;
        parent::__construct($resource);
    }

    /**
     * Resource initialization
     *
     */
    protected function _construct()
    {
        $this->_setMainTable('tax_calculation');
    }

    /**
     * Delete calculation settings by rule id
     *
     * @param int $ruleId
     * @return \Magento\Tax\Model\Resource\Calculation
     */
    public function deleteByRuleId($ruleId)
    {
        $conn = $this->_getWriteAdapter();
        $where = $conn->quoteInto('tax_calculation_rule_id = ?', (int)$ruleId);
        $conn->delete($this->getMainTable(), $where);

        return $this;
    }

    /**
     * Retrieve distinct calculation
     *
     * @param  string $field
     * @param  int $ruleId
     * @return array
     */
    public function getDistinct($field, $ruleId)
    {
        $select = $this->_getReadAdapter()->select();
        $select->from($this->getMainTable(), $field)
            ->where('tax_calculation_rule_id = ?', (int)$ruleId);

        return $this->_getReadAdapter()->fetchCol($select);
    }

    /**
     * Get tax rate information: calculation process data and tax rate
     *
     * @param \Magento\Object $request
     * @return array
     */
    public function getRateInfo($request)
    {
        $rates = $this->_getRates($request);
        return array(
            'process'   => $this->getCalculationProcess($request, $rates),
            'value'     => $this->_calculateRate($rates)
        );
    }

    /**
     * Get tax rate for specific tax rate request
     *
     * @param \Magento\Object $request
     * @return int
     */
    public function getRate($request)
    {
        return $this->_calculateRate($this->_getRates($request));
    }

    /**
     * Retrieve Calculation Process
     *
     * @param \Magento\Object $request
     * @param array $rates
     * @return array
     */
    public function getCalculationProcess($request, $rates = null)
    {
        if (is_null($rates)) {
            $rates = $this->_getRates($request);
        }

        $result = array();
        $row = array();
        $ids = array();
        $currentRate = 0;
        $totalPercent = 0;
        $countedRates = count($rates);
        for ($i = 0; $i < $countedRates; $i++) {
            $rate = $rates[$i];
            $value = (isset($rate['value']) ? $rate['value'] : $rate['percent'])*1;

            $oneRate = array(
                'code'=>$rate['code'],
                'title'=>$rate['title'],
                'percent'=>$value,
                'position'=>$rate['position'],
                'priority'=>$rate['priority'],
            );
            if (isset($rate['tax_calculation_rule_id'])) {
                $oneRate['rule_id'] = $rate['tax_calculation_rule_id'];
            }

            if (isset($rate['hidden'])) {
                $row['hidden'] = $rate['hidden'];
            }

            if (isset($rate['amount'])) {
                $row['amount'] = $rate['amount'];
            }

            if (isset($rate['base_amount'])) {
                $row['base_amount'] = $rate['base_amount'];
            }
            if (isset($rate['base_real_amount'])) {
                $row['base_real_amount'] = $rate['base_real_amount'];
            }
            $row['rates'][] = $oneRate;

            if (isset($rates[$i+1]['tax_calculation_rule_id'])) {
                $rule = $rate['tax_calculation_rule_id'];
            }
            $priority = $rate['priority'];
            $ids[] = $rate['code'];

            if (isset($rates[$i+1]['tax_calculation_rule_id'])) {
                while(isset($rates[$i+1]) && $rates[$i+1]['tax_calculation_rule_id'] == $rule) {
                    $i++;
                }
            }

            $currentRate += $value;

            if (!isset($rates[$i+1]) || $rates[$i+1]['priority'] != $priority
                || (isset($rates[$i+1]['process']) && $rates[$i+1]['process'] != $rate['process'])
            ) {
                $row['percent'] = (100+$totalPercent)*($currentRate/100);
                $row['id'] = implode($ids);
                $result[] = $row;
                $row = array();
                $ids = array();

                $totalPercent += (100+$totalPercent)*($currentRate/100);
                $currentRate = 0;
            }
        }

        return $result;
    }

    /**
     * Create search templates for postcode
     *
     * @param string $postcode
     * @return array  $strArr
     */
    protected function _createSearchPostCodeTemplates($postcode)
    {
        $len = $this->_taxData->getPostCodeSubStringLength();
        $strlen = strlen($postcode);
        if ($strlen > $len) {
            $postcode = substr($postcode, 0, $len);
            $strlen = $len;
        }

        $strArr = array($postcode, $postcode . '*');
        if ($strlen > 1) {
            for ($i = 1; $i < $strlen; $i++) {
                $strArr[] = sprintf('%s*', substr($postcode, 0, - $i));
            }
        }

        return $strArr;
    }

    /**
     * Returns tax rates for request - either pereforms SELECT from DB, or returns already cached result
     * Notice that productClassId due to optimization can be array of ids
     *
     * @param \Magento\Object $request
     * @return array
     */
    protected function _getRates($request)
    {
        // Extract params that influence our SELECT statement and use them to create cache key
        $storeId = $this->_storeManager->getStore($request->getStore())->getId();
        $customerClassId = $request->getCustomerClassId();
        $countryId = $request->getCountryId();
        $regionId = $request->getRegionId();
        $postcode = $request->getPostcode();

        // Process productClassId as it can be array or usual value. Form best key for cache.
        $productClassId = $request->getProductClassId();
        $ids = is_array($productClassId) ? $productClassId : array($productClassId);
        foreach ($ids as $key => $val) {
            $ids[$key] = (int) $val; // Make it integer for equal cache keys even in case of null/false/0 values
        }
        $ids = array_unique($ids);
        sort($ids);
        $productClassKey = implode(',', $ids);

        // Form cache key and either get data from cache or from DB
        $cacheKey = implode('|', array($storeId, $customerClassId, $productClassKey, $countryId, $regionId, $postcode));

        if (!isset($this->_ratesCache[$cacheKey])) {
            // Make SELECT and get data
            $select = $this->_getReadAdapter()->select();
            $select
                ->from(array('main_table' => $this->getMainTable()),
                array(  'tax_calculation_rate_id',
                        'tax_calculation_rule_id',
                        'customer_tax_class_id',
                        'product_tax_class_id'
                    )
                )
                ->where('customer_tax_class_id = ?', (int)$customerClassId);
            if ($productClassId) {
                $select->where('product_tax_class_id IN (?)', $productClassId);
            }
            $ifnullTitleValue = $this->_getReadAdapter()->getCheckSql(
                'title_table.value IS NULL',
                'rate.code',
                'title_table.value'
            );
            $ruleTableAliasName = $this->_getReadAdapter()->quoteIdentifier('rule.tax_calculation_rule_id');
            $select
                ->join(
                    array('rule' => $this->getTable('tax_calculation_rule')),
                    $ruleTableAliasName . ' = main_table.tax_calculation_rule_id',
                    array('rule.priority', 'rule.position'))
                ->join(
                    array('rate'=>$this->getTable('tax_calculation_rate')),
                    'rate.tax_calculation_rate_id = main_table.tax_calculation_rate_id',
                    array(
                        'value' => 'rate.rate',
                        'rate.tax_country_id',
                        'rate.tax_region_id',
                        'rate.tax_postcode',
                        'rate.tax_calculation_rate_id',
                        'rate.code'
                ))
                ->joinLeft(
                    array('title_table' => $this->getTable('tax_calculation_rate_title')),
                   "rate.tax_calculation_rate_id = title_table.tax_calculation_rate_id "
                   . "AND title_table.store_id = '{$storeId}'",
                    array('title' => $ifnullTitleValue))
                ->where('rate.tax_country_id = ?', $countryId)
                ->where("rate.tax_region_id IN(?)", array(0, (int)$regionId));
            $postcodeIsNumeric = is_numeric($postcode);
            $postcodeIsRange = is_string($postcode) && preg_match('/^(.+)-(.+)$/', $postcode, $matches);
            if ($postcodeIsRange) {
                $zipFrom = $matches[1];
                $zipTo = $matches[2];
            }

            if ($postcodeIsNumeric || $postcodeIsRange) {
                $selectClone = clone $select;
                $selectClone->where('rate.zip_is_range IS NOT NULL');
            }
            $select->where('rate.zip_is_range IS NULL');

            if ($postcode != '*' || $postcodeIsRange) {
                $select
                    ->where("rate.tax_postcode IS NULL OR rate.tax_postcode IN('*', '', ?)",
                        $postcodeIsRange ? $postcode : $this->_createSearchPostCodeTemplates($postcode));
                if ($postcodeIsNumeric) {
                    $selectClone
                        ->where('? BETWEEN rate.zip_from AND rate.zip_to', $postcode);
                } else if ($postcodeIsRange) {
                    $selectClone->where('rate.zip_from >= ?', $zipFrom)
                        ->where('rate.zip_to <= ?', $zipTo);
                }
            }

            /**
             * @see ZF-7592 issue http://framework.zend.com/issues/browse/ZF-7592
             */
            if ($postcodeIsNumeric || $postcodeIsRange) {
                $select = $this->_getReadAdapter()->select()->union(
                    array(
                        '(' . $select . ')',
                        '(' . $selectClone . ')'
                    )
                );
            }

            $select->order('priority ' . \Magento\DB\Select::SQL_ASC)
                   ->order('tax_calculation_rule_id ' . \Magento\DB\Select::SQL_ASC)
                   ->order('tax_country_id ' . \Magento\DB\Select::SQL_DESC)
                   ->order('tax_region_id ' . \Magento\DB\Select::SQL_DESC)
                   ->order('tax_postcode ' . \Magento\DB\Select::SQL_DESC)
                   ->order('value ' . \Magento\DB\Select::SQL_DESC);

            $fetchResult = $this->_getReadAdapter()->fetchAll($select);
            $filteredRates = array();
            if ($fetchResult) {
                foreach ($fetchResult as $rate) {
                    if (!isset($filteredRates[$rate['tax_calculation_rate_id']])) {
                        $filteredRates[$rate['tax_calculation_rate_id']] = $rate;
                    }
                }
            }
            $this->_ratesCache[$cacheKey] = array_values($filteredRates);
        }

        return $this->_ratesCache[$cacheKey];
    }

    /**
     * Calculate rate
     *
     * @param array $rates
     * @return int
     */
    protected function _calculateRate($rates)
    {
        $result      = 0;
        $currentRate = 0;
        $countedRates = count($rates);
        for ($i = 0; $i < $countedRates; $i++) {
            $rate       = $rates[$i];
            $rule       = $rate['tax_calculation_rule_id'];
            $value      = $rate['value'];
            $priority   = $rate['priority'];

            while(isset($rates[$i+1]) && $rates[$i+1]['tax_calculation_rule_id'] == $rule) {
                $i++;
            }

            $currentRate += $value;

            if (!isset($rates[$i+1]) || $rates[$i+1]['priority'] != $priority) {
                $result += (100+$result)*($currentRate/100);
                $currentRate = 0;
            }
        }

        return $result;
    }

    /**
     * Retrieve rate ids
     *
     * @param \Magento\Object $request
     * @return array
     */
    public function getRateIds($request)
    {
        $result = array();
        $rates  = $this->_getRates($request);
        $countedRates = count($rates);
        for ($i = 0; $i < $countedRates; $i++) {
            $rate = $rates[$i];
            $rule = $rate['tax_calculation_rule_id'];
            $result[] = $rate['tax_calculation_rate_id'];
            while(isset($rates[$i+1]) && $rates[$i+1]['tax_calculation_rule_id'] == $rule) {
                $i++;
            }
        }

        return $result;
    }

    /**
     * Retrieve rates by customer tax class
     *
     * @param int $customerTaxClassId
     * @param int $productTaxClassId
     * @return array
     */
    public function getRatesByCustomerTaxClass($customerTaxClass, $productTaxClass = null)
    {
        $adapter = $this->_getReadAdapter();
        $customerTaxClassId = (int)$customerTaxClass;
        $calcJoinConditions = array(
            'calc_table.tax_calculation_rate_id = main_table.tax_calculation_rate_id',
            $adapter->quoteInto('calc_table.customer_tax_class_id = ?', $customerTaxClassId),

        );
        if ($productTaxClass !== null) {
            $productTaxClassId = (int)$productTaxClass;
            $calcJoinConditions[] = $adapter->quoteInto('calc_table.product_tax_class_id = ?', $productTaxClassId);
        }

        $selectCSP = $adapter->select();
        $selectCSP
            ->from(
                array('main_table' => $this->getTable('tax_calculation_rate')),
                array('country' => 'tax_country_id', 'region_id' => 'tax_region_id', 'postcode' => 'tax_postcode'))
            ->joinInner(
                    array('calc_table' => $this->getTable('tax_calculation')),
                    implode(' AND ', $calcJoinConditions),
                    array('product_class' => 'calc_table.product_tax_class_id'))
            ->joinLeft(
                    array('state_table' => $this->getTable('directory_country_region')),
                    'state_table.region_id = main_table.tax_region_id',
                    array('region_code' => 'state_table.code'))
            ->distinct(true);

        $CSP = $adapter->fetchAll($selectCSP);

        $result = array();
        foreach ($CSP as $one) {
            $request = new \Magento\Object();
            $request->setCountryId($one['country'])
                ->setRegionId($one['region_id'])
                ->setPostcode($one['postcode'])
                ->setCustomerClassId($customerTaxClassId)
                ->setProductClassId($one['product_class']);

            $rate = $this->getRate($request);
            if ($rate) {
                $row = array(
                    'value'         => $rate/100,
                    'country'       => $one['country'],
                    'state'         => $one['region_code'],
                    'postcode'      => $one['postcode'],
                    'product_class' => $one['product_class'],
                );

                $result[] = $row;
            }
        }

        return $result;
    }
}
