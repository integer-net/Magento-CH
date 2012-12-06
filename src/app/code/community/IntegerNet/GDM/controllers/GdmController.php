<?php
class IntegerNet_GDM_GdmController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Basic action: setup form
     *
     * @return void
     */
    public function indexAction()
    {
        $helper = Mage::helper('gdm');

        $this->_title($helper->__('System'))
            ->_title($helper->__('German Distribution for Magento'));

        $this->loadLayout()
            ->_setActiveMenu('system/gdm')
            ->_addBreadcrumb($helper->__('German Distribution for Magento'), $helper->__('German Distribution for Magento'));

        $this->getLayout()
            ->getBlock('content')
            ->append($this->getLayout()->createBlock('gdm/form'));

        $this->getLayout()
            ->getBlock('root')
            ->unsetChild('notifications');

        $this->renderLayout();
    }

    /**
     * Basic action: setup save action
     *
     * @return void
     */
    public function saveAction()
    {
        $this->_deactivateCache();

        $this->_updateConfigData();

        $this->_markNotificationsAsRead();

        $this->_runGermanSetup();

        $this->_reindexAll();

        Mage::getSingleton('adminhtml/session')->addSuccess($this->__('Magento was prepared successfully.'));

        // Set a config flag to indicate that the setup has been initialized.
        $this->_setConfigData('gdm/is_initialized', 1);

        $this->_redirect('');
    }

    public function _updateConfigData()
    {
        if ($this->getRequest()->isPost()) {

            $fieldData = $this->getRequest()->getParam('field');
            if (is_array($fieldData)) {
                foreach ($fieldData as $key => $value) {
                    $fieldCode = implode('/', explode('__', $key));
                    $this->_setConfigData($fieldCode, $value);
                }
                $this->_setConfigData('general/store_information/name', $fieldData['general__imprint__shop_name']);
                $this->_setConfigData('general/store_information/phone', $fieldData['general__imprint__telephone']);
                $this->_setConfigData('general/store_information/merchant_vat_number', $fieldData['general__imprint__vat_id']);
                $this->_setConfigData('general/store_information/address', $this->_getAddress($fieldData));
                $this->_setConfigData('sales/identity/address', $this->_getAddress($fieldData));
                $this->_setConfigData('design/head/default_title', $fieldData['general__imprint__shop_name']);
                $this->_setConfigData('design/head/title_suffix', ' - ' . $fieldData['general__imprint__shop_name']);
                $this->_setConfigData('design/head/default_description', $fieldData['general__imprint__shop_name']);
                $this->_setConfigData('design/head/default_keywords', $fieldData['general__imprint__shop_name']);
                $this->_setConfigData('design/header/logo_alt', $fieldData['general__imprint__shop_name']);
                $this->_setConfigData('design/footer/copyright', '&copy; ' . date('Y') . ' ' . $fieldData['general__imprint__company_first']);
                $this->_setConfigData('trans_email/ident_general/name', $fieldData['general__imprint__shop_name']);
                $this->_setConfigData('trans_email/ident_general/email', $fieldData['general__imprint__email']);
                $this->_setConfigData('trans_email/ident_sales/name', $fieldData['general__imprint__shop_name']);
                $this->_setConfigData('trans_email/ident_sales/email', $fieldData['general__imprint__email']);
                $this->_setConfigData('trans_email/ident_support/name', $fieldData['general__imprint__shop_name']);
                $this->_setConfigData('trans_email/ident_support/email', $fieldData['general__imprint__email']);
                $this->_setConfigData('trans_email/ident_custom1/name', $fieldData['general__imprint__shop_name']);
                $this->_setConfigData('trans_email/ident_custom1/email', $fieldData['general__imprint__email']);
                $this->_setConfigData('trans_email/ident_custom2/name', $fieldData['general__imprint__shop_name']);
                $this->_setConfigData('trans_email/ident_custom2/email', $fieldData['general__imprint__email']);
                $this->_setConfigData('contacts/email/recipient_email', $fieldData['general__imprint__email']);
                $this->_setConfigData('sales_email/order/copy_to', $fieldData['general__imprint__email']);
                $this->_setConfigData('sales_pdf/firegento_pdf/sender_address_bar', $this->_getAddress($fieldData, ' - '));
                $this->_setConfigData('checkout/payment_failed/copy_to', $fieldData['general__imprint__email']);
                $this->_setConfigData('shipping/origin/postcode', $fieldData['general__imprint__zip']);
                $this->_setConfigData('shipping/origin/city', $fieldData['general__imprint__city']);
                $this->_setConfigData('shipping/origin/street_line1', $fieldData['general__imprint__street']);
                $this->_setConfigData('payment/banktransfer/instructions', $this->__(
                    'After completion of this order, please transfer the order amount to: %s, Account %s, Bank number %s, %s (IBAN %s, SWIFT %s)',
                    $fieldData['general__imprint__bank_account_owner'],
                    $fieldData['general__imprint__bank_account'],
                    $fieldData['general__imprint__bank_code_number'],
                    $fieldData['general__imprint__bank_name'],
                    $fieldData['general__imprint__iban'],
                    $fieldData['general__imprint__swift']
                ));
            }

            $payPalEmailAddress = $this->getRequest()->getParam('paypal_email');
            if ($payPalEmailAddress) {
                $this->_setConfigData('paypal/general/business_account', $payPalEmailAddress);
                $this->_setConfigData('payment/paypal_standard/active', 1);
                $this->_setConfigData('payment/paypal_standard/title', 'PayPal');
                $this->_setConfigData('payment/paypal_standard/sort_order', 10);


            }
        }

        $this->_setConfigData('general/region/state_required', '');
        $this->_setConfigData('general/region/display_all', 0);
        $this->_setConfigData('admin/startup/page', 'dashboard');
    }

    protected function _getAddress($fieldData, $seperator = "\n")
    {
        $address = ($fieldData['general__imprint__company_first'] ? $fieldData['general__imprint__company_first'] . $seperator : '');
        $address .= ($fieldData['general__imprint__company_second'] ? $fieldData['general__imprint__company_second'] . $seperator : '');
        $address .= ($fieldData['general__imprint__street'] ? $fieldData['general__imprint__street'] . $seperator : '');
        $address .= ($fieldData['general__imprint__zip'] ? $fieldData['general__imprint__zip'] . ' ' : '');
        $address .= ($fieldData['general__imprint__city'] ? $fieldData['general__imprint__city'] : '');
        return $address;
    }

    protected function _setConfigData($key, $value)
    {
        Mage::getModel('eav/entity_setup', 'core_setup')->setConfigData($key, $value);
    }


    protected function _reindexAll()
    {
        $processCollection = Mage::getModel('index/process')->getCollection();

        foreach ($processCollection as $process) {
            /* @var $process Mage_Index_Model_Process */
            $process->reindexAll();
        }
    }

    protected function _markNotificationsAsRead()
    {
        $notificationCollection = Mage::getModel('adminnotification/inbox')->getCollection();
        foreach ($notificationCollection as $notification) {
            /* @var $notification Mage_AdminNotification_Model_Inbox */
            if (!$notification->getIsRead()) {
                $notification->setIsRead(1)
                    ->save();
            }
        }
    }

    protected function _runGermanSetup()
    {
        Mage::getSingleton('germansetup/setup')->setup();
    }

    protected  function _deactivateCache()
    {
        /* @var $cache Mage_Core_Model_Cache */
        $cache = Mage::getModel('core/cache');

        /* @var $options array */
        $options = $cache->canUse(null);

        $newOptions = array();
        foreach ($options as $option => $value) {
            $newOptions[$option] = 0;
        }

        $cache->saveOptions($newOptions);
    }
}
