<?php
/**
 * Capture Request
 *
 * @author      Webjump Core Team <dev@webjump.com>
 * @copyright   2016 Webjump (http://www.webjump.com.br)
 * @license     http://www.webjump.com.br  Copyright
 *
 * @link        http://www.webjump.com.br
 */
namespace Webjump\BraspagPagador\Gateway\Transaction\CreditCard\Resource\AntiFraud;


use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Webjump\Braspag\Pagador\Transaction\Api\CreditCard\AntiFraud\RequestInterface as BraspaglibRequestInterface;
use Webjump\BraspagPagador\Gateway\Transaction\CreditCard\Config\AntiFraudConfigInterface as ConfigInterface;
use Webjump\BraspagPagador\Gateway\Transaction\Base\Resource\RequestInterface as BraspagMagentoRequestInterface;
use Magento\Payment\Model\InfoInterface;
use Webjump\BraspagPagador\Gateway\Transaction\CreditCard\Resource\AntiFraud\Items\RequestFactory;
use Webjump\BraspagPagador\Gateway\Transaction\CreditCard\Resource\AntiFraud\MDD\AdapterGeneralInterface;

class Request implements BraspaglibRequestInterface, BraspagMagentoRequestInterface
{
    protected $config;
    protected $orderAdapter;
    protected $paymentData;
    protected $requestItemFactory;
    protected $billingAddress;
    protected $shippingAddress;
    protected $quote;
    protected $fingerPrintId;
    protected $mdd;

    public function __construct(
        ConfigInterface $config,
        RequestFactory $requestItemFactory,
        AdapterGeneralInterface $adapterGeneral
    )
    {
        $this->setConfig($config);
        $this->setRequestItemFactory($requestItemFactory);
        $this->setMdd($adapterGeneral);
    }

    public function getSequence()
    {
        return $this->getConfig()->getSequence();
    }

    public function getSequenceCriteria()
    {
        return $this->getConfig()->getSequenceCriteria();
    }

    public function getFingerPrintId()
    {
        if ($this->getConfig()->userOrderIdToFingerPrint()) {
            return (string) $this->getReservedOrderId();
        }

        return $this->getConfig()->getSession()->getSessionId();
    }

    public function getCaptureOnLowRisk()
    {
        return (boolean) $this->getConfig()->getCaptureOnLowRisk();
    }

    public function getVoidOnHighRisk()
    {
        return (boolean) $this->getConfig()->getVoidOnHighRisk();
    }

    public function getBrowserCookiesAccepted()
    {
    }

    public function getBrowserEmail()
    {
    }

    public function getBrowserHostName()
    {
    }

    public function getBrowserIpAddress()
    {
        return $this->getOrderAdapter()->getRemoteIp();
    }

    public function getBrowserType()
    {
    }

    public function getCartIsGift()
    {
    }

    public function getCartReturnsAccepted()
    {
    }

    public function getCartItems()
    {
        $items = [];
        /** @var \Magento\Sales\Model\Order\Item $item */
        foreach ($this->getOrderAdapter()->getItems() as $item) {
            if ($item->getProductType() !== 'simple' && $item->getProductType() !== 'grouped') {
                continue;
            }

            if (!$item->isDeleted() && !$item->getParentItemId()) {
                $items[] = $this->getRequestItemFactory()->create($item);
            }
        }

        return $items;
    }

    public function getMerchantDefinedFields()
    {
        $mdd = $this->getMdd();
        $mdd->setPaymentData($this->getPaymentData());
        $mdd->setOrderAdapter($this->getOrderAdapter());
        return $mdd;
    }

    public function getCartShippingAddressee()
    {
        return trim(
            $this->getShippingAddress()->getFirstname() . ' ' .
            $this->getShippingAddress()->getLastname()
        );
    }

    public function getCartShippingMethod()
    {
    }

    public function getCartShippingPhone()
    {
        return ConfigInterface::COUNTRY_TELEPHONE_CODE .
               preg_replace('/[^0-9]/', '', $this->getOrderAdapter()->getShippingAddress()->getTelephone());
    }

    public function setOrderAdapter(OrderAdapterInterface $orderAdapter)
    {
        $this->orderAdapter = $orderAdapter;
        return $this;
    }

    public function setPaymentData(InfoInterface $payment)
    {
        $this->paymentData = $payment;
    }

    public function getPaymentData()
    {
        return $this->paymentData;
    }

    protected function setMdd(AdapterGeneralInterface $mdd)
    {
        $this->mdd = $mdd;
        return $this;
    }

    protected   function getMdd()
    {
        return $this->mdd;
    }

    protected function getOrderAdapter()
    {
        return $this->orderAdapter;
    }

    protected function getConfig()
    {
        return $this->config;
    }

    public function getRequestItemFactory()
    {
        return $this->requestItemFactory;
    }

    public function setRequestItemFactory(RequestFactory $requestItemFactory)
    {
        $this->requestItemFactory = $requestItemFactory;
    }

    protected function setConfig(ConfigInterface $config)
    {
        $this->config = $config;
        return $this;
    }

    protected function getShippingAddress()
    {
        if (! $this->shippingAddress) {
            $this->shippingAddress = $this->getOrderAdapter()->getShippingAddress();
        }

        return $this->shippingAddress;
    }

    protected function getBillingAddress()
    {
        if (!$this->billingAddress) {
            $this->billingAddress = $this->getOrderAdapter()->getBillingAddress();
        }

        return $this->billingAddress;
    }

    protected function getQuote()
    {
        if (! $this->quote) {
            $this->quote =  $this->getConfig()->getSession()->getQuote();
        }

        return $this->quote;
    }

    protected function getReservedOrderId()
    {
        $quote = $this->getQuote();

        if (! $quote->getReservedOrderId()) {
            $quote->reserveOrderId();
            $quote->save();
        }

        return $quote->getReservedOrderId();
    }
}
