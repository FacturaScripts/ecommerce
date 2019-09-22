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
     * @var PedidoCliente
     */
    protected $mainModel;

    /**
     *
     * @var PaymentGateway
     */
    protected $paymentGateway;

    /**
     * 
     * @param string $className
     * @param string $uri
     */
    public function __construct(string $className, string $uri = '')
    {
        parent::__construct($className, $uri);
        $this->paymentGateway = new PaymentGateway();
    }

    /**
     * 
     * @return bool
     */
    public function contactCanEdit()
    {
        if ($this->user) {
            return true;
        }

        if (empty($this->contact)) {
            return false;
        }

        return $this->getMainModel()->codcliente == $this->contact->codcliente;
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

    /**
     * 
     * @param string $sectionName
     */
    protected function loadData(string $sectionName)
    {
        switch ($sectionName) {
            case 'order':
                $this->loadOrder();
                break;
        }
    }

    protected function loadOrder()
    {
        if (!$this->getMainModel(true)->exists()) {
            $this->response->setStatusCode(Response::HTTP_NOT_FOUND);
            $this->webPage->noindex = true;
            $this->setTemplate('Master/Portal404');
            return;
        }

        if (!$this->contactCanSee()) {
            $this->response->setStatusCode(Response::HTTP_FORBIDDEN);
            $this->webPage->noindex = true;
            $this->setTemplate('Master/AccessDenied');
            return;
        }

        $this->title = $this->toolBox()->i18n()->trans('order') . ' ' . $this->getMainModel()->codigo;
    }

    protected function payAction()
    {
        $order = $this->getMainModel();
        if ($order->editable == false) {
            return;
        }

        if ($this->paymentGateway->payAction($order, $this->request)) {
            $this->toolBox()->i18nLog()->notice('record-updated-correctly');
            $this->getMainModel(true);
            return;
        }

        $this->toolBox()->i18nLog()->error('record-save-error');
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
