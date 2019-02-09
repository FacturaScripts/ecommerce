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
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Base\DivisaTools;
use FacturaScripts\Dinamic\Lib\ExportManager;
use FacturaScripts\Dinamic\Model\PedidoCliente;
use FacturaScripts\Plugins\ecommerce\Lib\PaymentGateway;
use FacturaScripts\Plugins\webportal\Lib\WebPortal\EditSectionController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Description of ViewOrder
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
class ViewOrder extends EditSectionController
{

    /**
     *
     * @var DivisaTools
     */
    public $divisaTools;

    /**
     *
     * @var PedidoCliente
     */
    protected $mainModel;

    /**
     *
     * @var PaymentGateway
     */
    protected $paymentGateway;

    public function __construct(&$cache, &$i18n, &$miniLog, $className, $uri = '')
    {
        parent::__construct($cache, $i18n, $miniLog, $className, $uri);
        $this->divisaTools = new DivisaTools();
        $this->paymentGateway = new PaymentGateway();
    }

    /**
     * 
     * @return bool
     */
    public function contactCanEdit()
    {
        if (empty($this->contact)) {
            return false;
        }

        $order = $this->getMainModel();
        return $order->codcliente == $order->codcliente;
    }

    /**
     * 
     * @return bool
     */
    public function contactCanSee()
    {
        return $this->contactCanEdit();
    }

    /**
     * 
     * @param bool $reload
     *
     * @return PedidoCliente
     */
    public function getMainModel($reload = false)
    {
        if (isset($this->mainModel) && !$reload) {
            return $this->mainModel;
        }

        $this->mainModel = new PedidoCliente();
        $code = $this->request->request->get('code', $this->request->query->get('code', ''));
        $this->mainModel->loadFromCode($code);
        return $this->mainModel;
    }

    /**
     * 
     * @return string
     */
    public function getPaymentGatewayHtml()
    {
        $order = $this->getMainModel();
        $url = $order->url('public') . '&action=pay';
        return $this->paymentGateway->getHtml($url, $order, $this->contact->email);
    }

    protected function createSections()
    {
        $this->fixedSection();
        $this->addHtmlSection('order', 'order', 'Section/Order', 'PedidoCliente', 'fas fa-shopping-cart');
    }

    /**
     * 
     * @param string $action
     *
     * @return bool
     */
    protected function execPreviousAction(string $action)
    {
        switch ($action) {
            case 'pay':
                $this->payAction();
                return true;

            case 'print':
                $this->printAction();
                return true;
        }

        return parent::execPreviousAction($action);
    }

    protected function loadData(string $sectionName)
    {
        switch ($sectionName) {
            case 'order':
                return $this->loadOrder();
        }
    }

    protected function loadOrder()
    {
        if (!$this->getMainModel(true)->exists()) {
            $this->miniLog->alert($this->i18n->trans('no-data'));
            $this->response->setStatusCode(Response::HTTP_NOT_FOUND);
            $this->webPage->noindex = true;
            $this->setTemplate('Master/Portal404');
            return;
        }

        if (!$this->contactCanSee()) {
            $this->miniLog->alert($this->i18n->trans('access-denied'));
            $this->response->setStatusCode(Response::HTTP_FORBIDDEN);
            $this->webPage->noindex = true;
            $this->setTemplate('Master/AccessDenied');
            return;
        }

        $this->title = $this->i18n->trans('order') . ' ' . $this->getMainModel()->codigo;
    }

    protected function payAction()
    {
        $order = $this->getMainModel();
        if ($order->pagado) {
            return;
        }

        if ($this->paymentGateway->payAction($this->request, $order)) {
            $this->miniLog->notice($this->i18n->trans('record-updated-correctly'));
            $this->getMainModel(true);
            return;
        }

        $this->miniLog->error($this->i18n->trans('record-save-error'));
    }

    protected function printAction()
    {
        $this->setTemplate(false);
        $exportManager = new ExportManager();
        $exportManager->newDoc($exportManager->defaultOption());
        $exportManager->generateBusinessDocPage($this->getMainModel());
        $exportManager->show($this->response);
    }
}
