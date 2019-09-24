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

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\DownloadTools;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Plugins\ecommerce\Model\OrderPayment;
use Symfony\Component\HttpFoundation\Request;

/**
 * Description of PaymentGatewayBitcoin
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class PaymentGatewayBitcoin extends PaymentGatewayBase
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
        $payment = $this->getOrderPayment($order);
        if ($payment->exists()) {
            return '<a href="#" class="btn btn-block mb-2" onclick="return showModal(\'modalBitcoinPayment\')">'
                . $this->toolBox()->i18n()->trans('pay-with-btc')
                . '</a>'
                . $this->getModalHtml($url, $payment);
        }

        return '<a href="' . $url . '&platform=' . $this->name() . '" class="btn btn-block mb-2">'
            . $this->toolBox()->i18n()->trans('pay-with-btc')
            . '</a>';
    }

    /**
     * 
     * @return bool
     */
    public function isEnabled(): bool
    {
        return !empty($this->toolBox()->appSettings()->get('ecommerce', 'btcaddr1'));
    }

    /**
     * 
     * @return string
     */
    public function name(): string
    {
        return 'bitcoin';
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
        $orderPayment = $this->getOrderPayment($order);
        if ($orderPayment->exists()) {
            $balance = $this->getBitcoinAddressBalance($orderPayment->customid);
            if ($balance >= $orderPayment->amount) {
                $orderPayment->status = 'success';
                return $orderPayment->save();
            }

            return false;
        }

        $orderPayment->amount = $this->getBitcoinAmoun($order->total, $order->coddivisa);
        $orderPayment->currency = 'BTC';
        $orderPayment->customid = $this->getBitcoinAddress();
        $orderPayment->idpedido = $order->primaryColumnValue();
        $orderPayment->platform = $this->name();
        $orderPayment->status = 'pending';
        return $orderPayment->save();
    }

    /**
     * 
     * @return string
     */
    protected function getBitcoinAddress()
    {
        $addresses = [];
        for ($num = 1; $num <= 4; $num++) {
            $addr = $this->toolBox()->appSettings()->get('ecommerce', 'btcaddr' . $num);
            if (!empty($addr)) {
                $addresses[] = $addr;
            }
        }

        shuffle($addresses);
        return $addresses[0];
    }

    /**
     * 
     * @param string $address
     *
     * @return float
     */
    protected function getBitcoinAddressBalance(string $address): float
    {
        $downloader = new DownloadTools();
        $url = 'https://blockchain.info/rawaddr/' . $address;
        $data = $downloader->getContents($url, 3);
        if ($data) {
            $json = json_decode($data, true);
            return (float) $json['final_balance'];
        }

        return 0.0;
    }

    /**
     * 
     * @param float  $amoun
     * @param string $currency
     *
     * @return float
     */
    protected function getBitcoinAmoun(float $amoun, string $currency): float
    {
        $downloader = new DownloadTools();
        $url = 'https://blockchain.info/tobtc?currency=' . $currency . '&value=' . $amoun;
        return (float) $downloader->getContents($url, 3);
    }

    /**
     * 
     * @param string       $url
     * @param OrderPayment $payment
     *
     * @return string
     */
    protected function getModalHtml($url, $payment): string
    {
        return '<div class="modal modal-sm active text-left" id="modalBitcoinPayment">
  <a href="#close" class="modal-overlay" aria-label="Close" onclick="return hideModal(\'modalBitcoinPayment\');"></a>
  <div class="modal-container">
    <div class="modal-header">
      <a href="#close" class="btn btn-clear float-right" aria-label="Close" onclick="return hideModal(\'modalBitcoinPayment\');"></a>
      <div class="modal-title h5">' . $this->toolBox()->i18n()->trans('pay-with-btc') . '</div>
    </div>
    <div class="modal-body">
      <div class="content">
        <div class="form-group">
            ' . $this->toolBox()->i18n()->trans('total') . '
            <input class="form-input" type="text" value="' . $payment->amount . '" readonly="" />
        </div>
        <div class="form-group">
            ' . $this->toolBox()->i18n()->trans('address') . '
            <input class="form-input" type="text" value="' . $payment->customid . '" readonly="" />
        </div>
      </div>
    </div>
    <div class="modal-footer">
        <a href="#" class="btn" onclick="return hideModal(\'modalBitcoinPayment\');">
            ' . $this->toolBox()->i18n()->trans('cancel') . '
        </a>
        <a href="' . $url . '&platform=' . $this->name() . '" class="btn btn-success">
            ' . $this->toolBox()->i18n()->trans('paid') . '
        </a>
    </div>
  </div>
</div>';
    }

    /**
     * 
     * @param SalesDocument $order
     *
     * @return OrderPayment
     */
    protected function getOrderPayment($order)
    {
        $where = [new DataBaseWhere('idpedido', $order->primaryColumnValue())];

        $orderPayment = new OrderPayment();
        $orderPayment->loadFromCode('', $where);
        return $orderPayment;
    }
}
