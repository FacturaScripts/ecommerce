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

use FacturaScripts\Core\Model\Base\SalesDocument;
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
     * @var PaymentGatewayBase[]
     */
    protected static $gateways = [];

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
        foreach (static::$gateways as $gateway) {
            if ($gateway->isEnabled()) {
                $html .= $gateway->getHtml($url, $order, $email);
            }
        }

        return $html;
    }

    /**
     * 
     * @param SalesDocument $order
     * @param Request       $request
     *
     * @return bool
     */
    public function payAction(&$order, $request)
    {
        $platform = $request->get('platform', '');
        foreach (static::$gateways as $gateway) {
            if ($gateway->name() === $platform) {
                return $gateway->payAction($order, $request);
            }
        }

        return false;
    }

    /**
     * 
     * @param PaymentGatewayBase $gateway
     */
    public static function register($gateway)
    {
        static::$gateways[] = $gateway;
    }
}
