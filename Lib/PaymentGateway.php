<?php
/**
 * This file is part of ecommerce plugin for FacturaScripts.
 * Copyright (C) 2018 Carlos Garcia Gomez <carlos@facturascripts.com>
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
use Stripe\Stripe;
use Stripe\Charge as StripeCharge;
use Symfony\Component\HttpFoundation\Request;

/**
 * Description of PaymentGateway
 *
 * @author carlos
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

        return $this->getStripeHtml($url, $order, $email);
    }

    /**
     * 
     * @param Request $request
     * @param SalesDocument $order
     *
     * @return bool
     */
    public function payAction($request, $order)
    {
        Stripe::setApiKey(AppSettings::get('ecommerce', 'stripesk'));

        $charge = StripeCharge::create([
                'amount' => $order->total * 100,
                'currency' => $order->coddivisa,
                'description' => $order->codigo,
                'source' => $request->request->get('stripeToken')
        ]);
        if ($charge['status'] == 'succeeded') {
            $order->pagado = true;
            return $order->save();
        }

        return false;
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
        return '<form action="' . $url . '" method="post">
  <script
    src="https://checkout.stripe.com/checkout.js" class="stripe-button"
    data-key="' . AppSettings::get('ecommerce', 'stripepk') . '"
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
</form>';
    }
}
