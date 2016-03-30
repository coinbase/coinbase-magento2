<?php
/**
 * Attribution Notice: Based on the Paypal payment module included with Magento 2.
 *
 * @copyright  Copyright (c) 2015 Magento
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Coinbase\Magento2PaymentGateway\Controller\Ipn;

use Magento\Framework\App\Action\Action as AppAction;
use Coinbase\Wallet\Enum\NotificationType;

class Callback extends AppAction
{
    /**
    * @var \Coinbase\Magento2PaymentGateway\Model\PaymentMethod
    */
    protected $_paymentMethod;

    /**
    * @var \Coinbase\Wallet\Resource\Notification
    */
    protected $_notification;

    /**
    * @var \Coinbase\Wallet\Resource\Order
    */
    protected $_coinbase_order;

    /**
    * @var \Magento\Sales\Model\Order
    */
    protected $_order;

    /**
    * @var \Magento\Sales\Model\OrderFactory
    */
    protected $_orderFactory;

    /**
    * @var Magento\Sales\Model\Order\Email\Sender\OrderSender
    */
    protected $_orderSender;

    /**
    * @var \Psr\Log\LoggerInterface
    */
    protected $_logger;

    /**
    * @param \Magento\Framework\App\Action\Context $context
    * @param \Magento\Sales\Model\OrderFactory $orderFactory
    * @param \Coinbase\Magento2PaymentGateway\Model\PaymentMethod $paymentMethod
    * @param Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
    * @param  \Psr\Log\LoggerInterface $logger
    */
    public function __construct(
    \Magento\Framework\App\Action\Context $context,
    \Magento\Sales\Model\OrderFactory $orderFactory,
    \Coinbase\Magento2PaymentGateway\Model\PaymentMethod $paymentMethod,
    \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
    \Psr\Log\LoggerInterface $logger
    ) {
        $this->_paymentMethod = $paymentMethod;
        $this->_orderFactory = $orderFactory;
        $this->_client = $this->_paymentMethod->getClient();
        $this->_orderSender = $orderSender;
        $this->_logger = $logger;
        parent::__construct($context);
    }

    /**
    * Handle POST request to Coinbase callback endpoint.
    */
    public function execute()
    {
        try {
            // Cryptographically verify authenticity of callback
            $this->_verifyCallbackAuthenticity();

            switch ($this->_notification->getType()) {
                case NotificationType::PING:
                    $this->_handlePingCallback();
                    break;
                case NotificationType::ORDER_PAID:
                case NotificationType::ORDER_MISPAID:
                    $this->_coinbase_order = $this->_notification->getData();
                    $this->_loadOrder();
                    break;
                default:
                    $this->_handleUnknownCallback();
                    break;
            }

            switch ($this->_notification->getType()) {
                case NotificationType::ORDER_PAID:
                    $this->_registerPaymentCapture();
                    break;
                case NotificationType::ORDER_MISPAID:
                    $this->_registerMispayment();
                    break;
            }

            $this->_success();
        } catch (\Exception $e) {
            $this->_logger->addError("Coinbase: error processing callback");
            $this->_logger->addError($e->getMessage());
            return $this->_failure();
        }
    }

    protected function _verifyCallbackAuthenticity()
    {
        $raw_post_body = $this->getRequest()->getContent();
        $signature = $this->getRequest()->getHeader('CB-SIGNATURE');
        $authentic = $this->_client->verifyCallback($raw_post_body, $signature);

        if (!$authentic) {
            throw new Exception('Callback authenticity could not be verified.');
        }

        $this->_notification = $this->_client->parseNotification($raw_post_body);
    }

    protected function _handleUnknownCallback()
    {
        $this->_logger->addNotice("Coinbase: Received callback of unknown type $this->_notification->getType()");

        return;
    }

    protected function _handlePingCallback()
    {
        $this->_logger->addInfo('Coinbase: Handled ping callback');

        return;
    }

    protected function _registerMispayment()
    {
        $coinbase_order_code = $this->_coinbase_order->getCode();
        $this->_order->hold()->save();
        $this->_createIpnComment("Coinbase Order $coinbase_order_code mispaid; manual intervention required", true);
        $this->_order->save();
    }

    protected function _registerPaymentCapture()
    {
        $coinbase_order_code = $this->_coinbase_order->getCode();

        $payment = $this->_order->getPayment();

        $payment->setTransactionId($coinbase_order_code)
        ->setCurrencyCode($this->_coinbase_order->getAmount()->getCurrency())
        ->setPreparedMessage($this->_createIpnComment(''))
        ->setShouldCloseParentTransaction(true)
        ->setIsTransactionClosed(0)
        ->registerCaptureNotification(
            $this->_coinbase_order->getAmount()->getAmount(),
            true // No fraud detection required with bitcoin :)
        );

        $this->_order->save();

        $invoice = $payment->getCreatedInvoice();
        if ($invoice && !$this->_order->getEmailSent()) {
            $this->_orderSender->send($this->_order);
            $this->_order->addStatusHistoryComment(
                __('You notified customer about invoice #%1.', $invoice->getIncrementId())
            )->setIsCustomerNotified(
                true
            )->save();
        }
    }

    protected function _loadOrder()
    {
        $order_id = $this->_coinbase_order->getMetadata()['order_id'];
        $this->_order = $this->_orderFactory->create()->loadByIncrementId($order_id);

        if (!$this->_order && $this->_order->getId()) {
            throw new Exception('Could not find Magento order with id $order_id');
        }

        $callback_replay_token = $this->_coinbase_order->getMetadata()['replay_token'];
        $replay_token = $this->_order->getPayment()->getAdditionalInformation('replay_token');

        if ($replay_token !== $callback_replay_token) {
            throw new Exception('Replay tokens did not match');
        }
    }

    protected function _success()
    {
        $this->getResponse()
             ->setStatusHeader(200);
    }

    protected function _failure()
    {
        $this->getResponse()
             ->setStatusHeader(400);
    }

    /**
    * Generate an "IPN" comment with additional explanation.
    * Returns the generated comment or order status history object.
    *
    * @param string $comment
    * @param bool $addToHistory
    *
    * @return string|\Magento\Sales\Model\Order\Status\History
    */
    protected function _createIpnComment($comment = '', $addToHistory = false)
    {
        $message = __('IPN "%1"', $this->_notification->getType());
        if ($comment) {
            $message .= ' '.$comment;
        }
        if ($addToHistory) {
            $message = $this->_order->addStatusHistoryComment($message);
            $message->setIsCustomerNotified(null);
        }

        return $message;
    }
}
