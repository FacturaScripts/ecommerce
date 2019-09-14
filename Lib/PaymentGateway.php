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

use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Plugins\ecommerce\Model\OrderPayment;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Orders\OrdersGetRequest;
use Stripe\Stripe;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Event as StripeEvent;
use Stripe\PaymentIntent as StripePaymentIntent;
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
        if ($this->isEnabled('stripe')) {
            $html .= $this->getStripeHtml($url, $order, $email);
        }

        if ($this->isEnabled('paypal')) {
            $html .= $this->getPaypalHtml($url, $order);
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
                return !empty($this->toolBox()->appSettings()->get('ecommerce', 'paypalsk'));

            case 'stripe':
                return !empty($this->toolBox()->appSettings()->get('ecommerce', 'stripesk'));
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
                return $this->payActionStripe($order);
        }

        return false;
    }

    /**
     * 
     * @param SalesDocument $order
     * @param OrderPayment  $payment
     *
     * @return bool
     */
    protected function approveOrder(&$order, $payment)
    {
        /// prevent fraud
        if ($payment->amount < $order->total || strtolower($payment->currency) != strtolower($order->coddivisa)) {
            $this->toolBox()->log()->critical('ecommerce-payment-disagreements : order #' . $order->primaryColumnValue());
            return false;
        }

        /// approve order
        foreach ($order->getAvaliableStatus() as $status) {
            if (!empty($status->generadoc)) {
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
        $clientId = $this->toolBox()->appSettings()->get('ecommerce', 'paypalpk');
        $clientSecret = $this->toolBox()->appSettings()->get('ecommerce', 'paypalsk');
        if ($this->toolBox()->appSettings()->get('ecommerce', 'paypalsandbox') === true) {
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
        $paypalLink = 'https://www.paypal.com/sdk/js?client-id=' . $this->toolBox()->appSettings()->get('ecommerce', 'paypalpk', '')
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
        $domain = $this->toolBox()->appSettings()->get('webportal', 'url');

        Stripe::setApiKey($this->toolBox()->appSettings()->get('ecommerce', 'stripesk'));
        $session = StripeSession::create([
                'payment_method_types' => ['card'],
                'customer_email' => $email,
                'line_items' => [[
                    'name' => $order->primaryColumnValue(),
                    'description' => $this->toolBox()->i18n()->trans('order') . ' ' . $order->primaryDescription(),
                    'images' => [$domain . '/Dinamic/Assets/Images/apple-icon-180x180.png'],
                    'amount' => $order->total * 100,
                    'currency' => $order->coddivisa,
                    'quantity' => 1,
                    ]],
                'success_url' => $domain . '/' . $url . '&platform=stripe',
                'cancel_url' => $domain . '/' . $url,
        ]);

        return '<script src="https://js.stripe.com/v3/"></script>'
            . '<script>'
            . 'function payWithStripe() {'
            . "var stripe = Stripe('" . $this->toolBox()->appSettings()->get('ecommerce', 'stripepk') . "');"
            . "stripe.redirectToCheckout({sessionId: '" . $session->id . "'}).then(function (result) {"
            . "console.log(result);"
            . "});"
            . 'return false;'
            . '}'
            . '</script>'
            . '<a href="#" class="btn btn-primary btn-block mb-2" onclick="return payWithStripe();">'
            . $this->toolBox()->i18n()->trans('pay-with-card')
            . '</a>';
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

        $order->codpago = $this->toolBox()->appSettings()->get('ecommerce', 'paypalcodpago');
        return $response->result->status == 'COMPLETED' ? $this->approveOrder($order, $orderPayment) : false;
    }

    /**
     * 
     * @param SalesDocument $order
     *
     * @return bool
     */
    protected function payActionStripe(&$order)
    {
        Stripe::setApiKey($this->toolBox()->appSettings()->get('ecommerce', 'stripesk'));
        $events = StripeEvent::all([
                'type' => 'checkout.session.completed',
                'created' => [
                    // Check for events created in the last 24 hours.
                    'gte' => time() - 24 * 60 * 60,
                ],
        ]);

        /// read the last session events
        foreach ($events as $event) {
            $session = $event->data->object;
            if (empty($session->payment_intent) || $session->display_items[0]->custom->name != $order->primaryColumnValue()) {
                continue;
            }

            /// get PaymentIntent
            $payment = StripePaymentIntent::retrieve($session->payment_intent);

            /// save payment
            $orderPayment = new OrderPayment();
            $orderPayment->amount = (float) $payment->amount / 100;
            $orderPayment->currency = $payment->currency;
            $orderPayment->customid = $payment->id;
            $orderPayment->fee = 0;
            $orderPayment->idpedido = $order->primaryColumnValue();
            $orderPayment->platform = 'stripe';
            $orderPayment->status = $payment->status;
            $orderPayment->save();

            $order->codpago = $this->toolBox()->appSettings()->get('ecommerce', 'stripecodpago');
            return $orderPayment->status == 'succeeded' ? $this->approveOrder($order, $orderPayment) : false;
        }

        $this->toolBox()->log()->critical('ecommerce-stripe-payment-error : order #' . $order->primaryColumnValue());
        return false;
    }

    /**
     * 
     * @return ToolBox
     */
    protected function toolBox()
    {
        return new ToolBox();
    }
}
