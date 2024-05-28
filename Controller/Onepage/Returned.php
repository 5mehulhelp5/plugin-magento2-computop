<?php

namespace Fatchip\Computop\Controller\Onepage;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

class Returned extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    /**
     * Checkout session
     *
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Fatchip\Computop\Model\ResourceModel\ApiLog
     */
    protected $apiLog;

    /**
     * @var \Fatchip\Computop\Model\Api\Encryption\Blowfish
     */
    protected $blowfish;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Action\Context           $context
     * @param \Magento\Checkout\Model\Session                 $checkoutSession
     * @param \Fatchip\Computop\Model\ResourceModel\ApiLog    $apiLog
     * @param \Fatchip\Computop\Model\Api\Encryption\Blowfish $blowfish
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Fatchip\Computop\Model\ResourceModel\ApiLog $apiLog,
        \Fatchip\Computop\Model\Api\Encryption\Blowfish $blowfish
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->apiLog = $apiLog;
        $this->blowfish = $blowfish;
    }

    /**
     * @inheritdoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * @param string $errorMessage
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    protected function redirectToCart($errorMessage = null)
    {
        $this->messageManager->addErrorMessage('An error occured during the Checkout'.(empty($errorMessage) ? '.' : ': '.$errorMessage));
        return $this->_redirect($this->_url->getUrl('checkout/cart'));
    }

    /**
     * Handles return to shop
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $this->checkoutSession->unsComputopCustomerIsRedirected();

        $response = $this->blowfish->ctDecrypt($this->getRequest()->getParam('Data'), $this->getRequest()->getParam('Len'));
        $this->apiLog->addApiLogResponse($response);

        $order = $this->checkoutSession->getLastRealOrder();
        if ($order->getId()) {
            $payment = $order->getPayment();
        } else {
            $quote = $this->checkoutSession->getQuote();
            $payment = $quote->getPayment();
        }

        if (!$payment->getMethod()) { // order process probably was cancelled because of fraud prevention in \Fatchip\Computop\Observer\CancelOrderProcess
            return $this->redirectToCart();
        }
        $methodInstance = $payment->getMethodInstance();

        try {
            $methodInstance->handleResponse($payment, $response);
        } catch(\Exception $e) {
            return $this->redirectToCart();
        }

        return $this->_redirect($this->_url->getUrl('checkout/onepage/success'));
    }
}
