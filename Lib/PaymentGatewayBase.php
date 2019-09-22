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

use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Plugins\ecommerce\Model\OrderPayment;
use Symfony\Component\HttpFoundation\Request;

/**
 * 
 */
abstract class PaymentGatewayBase
{

    /**
     * 
     * @param string        $url
     * @param SalesDocument $order
     * @param string        $email
     *
     * @return string
     */
    abstract public function getHtml($url, $order, $email): string;

    /**
     * 
     * @return bool
     */
    abstract public function isEnabled(): bool;

    /**
     * 
     * @return string
     */
    abstract public function name(): string;

    /**
     * 
     * @param SalesDocument $order
     * @param Request       $request
     *
     * @return bool
     */
    abstract public function payAction(&$order, $request): bool;

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
     * @return ToolBox
     */
    protected function toolBox()
    {
        return new ToolBox();
    }
}
