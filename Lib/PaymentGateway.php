<?php
/**
 * This file is part of ecommerce plugin for FacturaScripts.
 * Copyright (C) 2018-2019 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace FacturaScripts\Plugins\ecommerce\Lib;

use FacturaScripts\Core\App\AppSettings;
use FacturaScripts\Core\Base\Translator;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Plugins\ecommerce\Model\OrderPayment;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Orders\OrdersGetRequest;
use Stripe\Stripe;
use Stripe\Charge as StripeCharge;
use Symfony\Component\HttpFoundation\Request;

/**
 * Description of PaymentGateway
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
class PaymentGateway
{

    /**
     * 
     * @param string        $url
     * @param SalesDocument $order
     * @param string        $email
     *
     * @return string
     */
    public function getHtml($url, $order, $email)
    {
        if ($order->total <= 0) {
            return '';
        }

        $html = '';
        if ($this->isEnabled('paypal')) {
            $html .= $this->getPaypalHtml($url, $order);
        }

        if ($this->isEnabled('stripe')) {
            $html .= $this->getStripeHtml($url, $order, $email);
        }

        return $html;
    }

    /**
     * 
     * @param string $platform
     *
     * @return bool
     */
    public function isEnabled($platform)
    {
        switch ($platform) {
            case 'paypal':
                return !empty(AppSettings::get('ecommerce', 'paypalsk'));

            case 'stripe':
                return !empty(AppSettings::get('ecommerce', 'stripesk'));
        }

        return false;
    }

    /**
     * 
     * @param Request       $request
     * @param SalesDocument $order
     *
     * @return bool
     */
    public function payAction($request, &$order)
    {
        switch ($request->get('platform')) {
            case 'paypal':
                return $this->payActionPaypal($request, $order);

            case 'stripe':
                return $this->payActionStripe($request, $order);
        }

        return false;
    }

    /**
     * 
     * @param SalesDocument $order
     *
     * @return bool
     */
    protected function approveOrder(&$order)
    {
        /// approve order
        $order->pagado = true;
        foreach ($order->getAvaliableStatus() as $status) {
            if ($status->generadoc == 'AlbaranCliente') {
                $order->idestado = $status->idestado;
                break;
            }
        }

        return $order->save();
    }

    /**
     * 
     * @return SandboxEnvironment|ProductionEnvironment
     */
    protected function getPaypalEnvironment()
    {
        $clientId = AppSettings::get('ecommerce', 'paypalpk');
        $clientSecret = AppSettings::get('ecommerce', 'paypalsk');
        if (AppSettings::get('ecommerce', 'paypalsandbox') === true) {
            return new SandboxEnvironment($clientId, $clientSecret);
        }

        return new ProductionEnvironment($clientId, $clientSecret);
    }

    /**
     * 
     * @param string        $url
     * @param SalesDocument $order
     *
     * @return string
     */
    protected function getPaypalHtml($url, $order)
    {
        $paypalLink = 'https://www.paypal.com/sdk/js?client-id=' . AppSettings::get('ecommerce', 'paypalpk', '')
            . '&currency=' . $order->coddivisa;

        return '<script src="' . $paypalLink . '"></script>
<div id="paypal-button-container"></div>
<script>paypal.Buttons({
    createOrder: function(data, actions) {
      return actions.order.create({
        purchase_units: [{
          amount: {
            value: \'' . $order->total . '\'
          }
        }]
      });
    },
    onApprove: function(data, actions) {
      return actions.order.capture().then(function(details) {
        window.location.replace(\'' . $url . '&platform=paypal&orderID=\' + data.orderID);
      });
    }
  }).render(\'#paypal-button-container\');</script><br/>';
    }

    /**
     * 
     * @param string        $url
     * @param SalesDocument $order
     * @param string        $email
     *
     * @return string
     */
    protected function getStripeHtml($url, $order, $email)
    {
        $i18n = new Translator();
        $publicKey = AppSettings::get('ecommerce', 'stripepk');

        return '<form action="' . $url . '&platform=stripe" method="post">
  <script
    src="https://checkout.stripe.com/checkout.js" class="stripe-button"
    data-key="' . $publicKey . '"
    data-amount="' . $order->total * 100 . '"
    data-email="' . $email . '"
    data-name="' . $order->getCompany()->nombrecorto . '"
    data-description="' . $i18n->trans('order') . ' ' . $order->codigo . '"
    data-image="' . 'Dinamic/Assets/Images/apple-icon-180x180.png' . '"
    data-label="' . $i18n->trans('pay-with-card') . '"
    data-locale="auto"
    data-zip-code="true"
    data-currency="' . $order->coddivisa . '">
  </script>
</form><br/>';
    }

    /**
     * 
     * @param Request       $request
     * @param SalesDocument $order
     *
     * @return bool
     */
    protected function payActionPaypal($request, &$order)
    {
        $environment = $this->getPaypalEnvironment();
        $client = new PayPalHttpClient($environment);

        $orderID = $request->get('orderID', '');
        $response = $client->execute(new OrdersGetRequest($orderID));

        /// save payment
        $orderPayment = new OrderPayment();
        $orderPayment->amount = (float) $response->result->purchase_units[0]->amount->value;
        $orderPayment->currency = $response->result->purchase_units[0]->amount->currency_code;
        $orderPayment->customid = $orderID;
        $orderPayment->fee = (float) $response->result->purchase_units[0]->payments->captures[0]->seller_receivable_breakdown->paypal_fee->value;
        $orderPayment->idpedido = $order->primaryColumnValue();
        $orderPayment->platform = 'paypal';
        $orderPayment->status = $response->result->status;
        $orderPayment->save();

        if ($response->result->status != 'COMPLETED') {
            return false;
        }

        /// we must prevent from advanced users that changes data in javascript calls
        if ($orderPayment->amount >= $order->total && strtolower($orderPayment->currency) == strtolower($order->coddivisa)) {
            $order->codpago = AppSettings::get('ecommerce', 'paypalcodpago');
            return $this->approveOrder($order);
        }

        return false;
    }

    /**
     * 
     * @param Request       $request
     * @param SalesDocument $order
     *
     * @return bool
     */
    protected function payActionStripe($request, &$order)
    {
        Stripe::setApiKey(AppSettings::get('ecommerce', 'stripesk'));
        $charge = StripeCharge::create([
                'amount' => $order->total * 100,
                'currency' => $order->coddivisa,
                'description' => $order->codigo,
                'source' => $request->request->get('stripeToken'),
                'expand' => ['balance_transaction']
        ]);

        /// save payment
        $orderPayment = new OrderPayment();
        $orderPayment->amount = (float) $charge['amount'] / 100;
        $orderPayment->currency = $charge['currency'];
        $orderPayment->customid = $charge['id'];
        $orderPayment->fee = (float) $charge['balance_transaction']->fee / 100;
        $orderPayment->idpedido = $order->primaryColumnValue();
        $orderPayment->platform = 'stripe';
        $orderPayment->status = $charge['status'];
        $orderPayment->save();

        $order->codpago = AppSettings::get('ecommerce', 'stripecodpago');
        return ($charge['status'] == 'succeeded') ? $this->approveOrder($order) : false;
    }
}
