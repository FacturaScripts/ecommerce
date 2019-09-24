<?php
/**
 * This file is part of ecommerce plugin for FacturaScripts.
 * Copyright (C) 2019 Carlos Garcia Gomez <carlos@facturascripts.com>
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

use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Plugins\ecommerce\Model\OrderPayment;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Orders\OrdersGetRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * Description of PaymentGatewayPaypal
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class PaymentGatewayPaypal extends PaymentGatewayBase
{

    /**
     * 
     * @param string        $url
     * @param SalesDocument $order
     * @param string        $email
     *
     * @return string
     */
    public function getHtml($url, $order, $email): string
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
        window.location.replace(\'' . $url . '&platform=' . $this->name() . '&orderID=\' + data.orderID);
      });
    }
  }).render(\'#paypal-button-container\');</script><br/>';
    }

    /**
     * 
     * @return bool
     */
    public function isEnabled(): bool
    {
        return !empty($this->toolBox()->appSettings()->get('ecommerce', 'paypalsk'));
    }

    /**
     * 
     * @return string
     */
    public function name(): string
    {
        return 'paypal';
    }

    /**
     * 
     * @param SalesDocument $order
     * @param Request       $request
     *
     * @return bool
     */
    public function payAction(&$order, $request): bool
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
        $orderPayment->platform = $this->name();
        $orderPayment->status = $response->result->status;
        $orderPayment->save();

        $order->codpago = $this->toolBox()->appSettings()->get('ecommerce', 'paypalcodpago');
        return $response->result->status == 'COMPLETED' ? $this->approveOrder($order, $orderPayment) : false;
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
}
