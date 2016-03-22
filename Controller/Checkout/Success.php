<?php
/**
 * Attribution Notice: Based on the Paypal payment module included with Magento 2
 *
 * @copyright  Copyright (c) 2015 Magento
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */
namespace Coinbase\Magento2PaymentGateway\Controller\Checkout;

class Success extends \Magento\Framework\App\Action\Action
{
  /**
   * @var \Magento\Checkout\Model\Session
   */
  protected $_checkoutSession;

  /**
   * @var \Magento\Framework\UrlInterface
   */
  protected $_urlBuilder;

  /**
   * @var \Magento\Store\Model\StoreManagerInterface
   */
  protected $_storeManager;

  /**
   * @param \Magento\Framework\App\Action\Context $context
   * @param \Magento\Checkout\Model\Session $checkoutSession
   * @param \Magento\Framework\UrlInterface $urlBuilder
   * @param \Magento\Store\Model\StoreManagerInterface $storeManager
   */
  public function __construct(
    \Magento\Framework\App\Action\Context $context,
    \Magento\Checkout\Model\Session $checkoutSession,
    \Magento\Framework\UrlInterface $urlBuilder,
    \Magento\Store\Model\StoreManagerInterface $storeManager
  ) {
    $this->_checkoutSession = $checkoutSession;
    $this->_urlBuilder = $urlBuilder;
    $this->_storeManager = $storeManager;
    parent::__construct($context);
  }

  /**
   * Unset the quote and redirect to checkout success
   *
   * @return void
   */
  public function execute()
  {
    $this->getResponse()->setRedirect(
      $this->_getUrl('checkout/onepage/success')
    );
  }

  /**
   * Build URL for store
   *
   * @param string $path
   * @param int $storeId
   * @param bool|null $secure
   * @return string
   */
  protected function _getUrl($path, $secure = null)
  {
    $store = $this->_storeManager->getStore(null);
    return $this->_urlBuilder->getUrl(
        $path,
        ["_store" => $store, "_secure" => $secure === null ? $store->isCurrentlySecure() : $secure]
    );
  }
}
