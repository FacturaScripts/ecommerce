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
use Stripe\Stripe;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Event as StripeEvent;
use Stripe\PaymentIntent as StripePaymentIntent;
use Symfony\Component\HttpFoundation\Request;

/**
 * Description of PaymentGatewayStripe
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class PaymentGatewayStripe extends PaymentGatewayBase
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
                'success_url' => $domain . '/' . $url . '&platform=' . $this->name(),
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
     * @return bool
     */
    public function isEnabled(): bool
    {
        return !empty($this->toolBox()->appSettings()->get('ecommerce', 'stripesk'));
    }

    /**
     * 
     * @return string
     */
    public function name(): string
    {
        return 'stripe';
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
            $orderPayment->platform = $this->name();
            $orderPayment->status = $payment->status;
            $orderPayment->save();

            $order->codpago = $this->toolBox()->appSettings()->get('ecommerce', 'stripecodpago');
            return $orderPayment->status == 'succeeded' ? $this->approveOrder($order, $orderPayment) : false;
        }

        $this->toolBox()->log()->critical('ecommerce-stripe-payment-error : order #' . $order->primaryColumnValue());
        return false;
    }
}
