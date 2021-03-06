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

use FacturaScripts\Core\Base\ControllerPermissions;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Lib\BusinessDocumentTools;
use FacturaScripts\Dinamic\Model\CodeModel;
use FacturaScripts\Dinamic\Model\PedidoCliente;
use FacturaScripts\Dinamic\Model\PresupuestoCliente;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Dinamic\Model\Variante;
use FacturaScripts\Plugins\webportal\Lib\WebPortal\PortalController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Description of ShoppingCart
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
class ShoppingCart extends PortalController
{

    /**
     *
     * @var CodeModel
     */
    public $codeModel;

    /**
     *
     * @var BusinessDocumentTools
     */
    protected $docTools;

    /**
     *
     * @var PedidoCliente
     */
    public $pedidos = [];

    /**
     *
     * @var PresupuestoCliente
     */
    public $presupuesto;

    /**
     * 
     * @param string $className
     * @param string $uri
     */
    public function __construct(string $className, string $uri = '')
    {
        parent::__construct($className, $uri);
        $this->codeModel = new CodeModel();
        $this->docTools = new BusinessDocumentTools();
    }

    /**
     * 
     * @return array
     */
    public function getPageData()
    {
        $data = parent::getPageData();
        $data['title'] = 'shopping-cart';
        return $data;
    }

    /**
     * 
     * @param Response              $response
     * @param User                  $user
     * @param ControllerPermissions $permissions
     */
    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        if (empty($this->contact)) {
            $this->toolBox()->log()->error('Contact not found');
            return;
        }

        $this->commonCore();
    }

    /**
     * 
     * @param Response $response
     */
    public function publicCore(&$response)
    {
        parent::publicCore($response);
        if (empty($this->contact)) {
            $this->setTemplate('Master/LoginToContinue');
            return;
        }

        $this->commonCore();
    }

    /**
     * 
     * @return bool
     */
    protected function addProduct()
    {
        if (!$this->presupuesto->exists()) {
            $this->presupuesto->save();
        }

        $variant = new Variante();
        $ref = $this->request->get('ref');
        $where = [new DataBaseWhere('referencia', $ref)];
        if ($variant->loadFromCode('', $where)) {
            if ($this->isProductInPresupuesto($ref)) {
                return true;
            }

            $newLine = $this->presupuesto->getNewProductLine($ref);
            $newLine->cantidad = 1;
            if (!$newLine->save()) {
                $this->toolBox()->i18nLog()->error('record-save-error');
                return false;
            }

            $this->docTools->recalculate($this->presupuesto);
            $this->presupuesto->save();
            return true;
        }

        $this->toolBox()->i18nLog()->error('record-save-error');
        return false;
    }

    /**
     * Check if the client has accepted the terms and conditions and the privacy policy.
     * 
     * @return bool
     */
    protected function checkTerms()
    {
        if (!$this->contact->aceptaprivacidad && 'true' !== $this->request->request->get('privacy')) {
            $this->toolBox()->i18nLog()->warning('you-must-accept-privacy-policy');
            return false;
        }

        if ('true' !== $this->request->request->get('terms')) {
            $this->toolBox()->i18nLog()->warning('you-must-accept-terms');
            return false;
        }

        $this->contact->aceptaprivacidad = true;
        return true;
    }

    protected function commonCore()
    {
        $this->setTemplate('ShoppingCart');
        $this->loadPresupuesto();
        $this->loadPedidos();

        $action = $this->request->request->get('action', $this->request->query->get('action', ''));
        switch ($action) {
            case 'add':
                return $this->addProduct();

            case 'delete':
                return $this->deleteLine();

            case 'edit':
                return $this->editAction();

            case 'finalize':
                $this->setTemplate('ShoppingCartOrder');
                return $this->checkTerms() && $this->finalizeAction();

            case 'order':
                if ($this->editAction() && count($this->presupuesto->getLines()) > 0) {
                    $this->setTemplate('ShoppingCartOrder');
                }
                break;
        }
    }

    /**
     * 
     * @return bool
     */
    protected function deleteLine()
    {
        $idlinea = $this->request->get('idline', '');
        foreach ($this->presupuesto->getLines() as $line) {
            if ($line->idlinea != $idlinea) {
                continue;
            }

            if ($line->delete()) {
                $this->docTools->recalculate($this->presupuesto);
                $this->presupuesto->save();
                return true;
            }
        }

        $this->toolBox()->i18nLog()->error('record-deleted-error');
        return false;
    }

    /**
     * 
     * @return bool
     */
    protected function editAction()
    {
        $changes = false;
        foreach ($this->presupuesto->getLines() as $line) {
            $cantidad = (int) $this->request->request->get('quantity_' . $line->idlinea, '0');

            if ($cantidad != $line->cantidad) {
                $changes = true;
            }

            if ($cantidad <= 0) {
                $line->delete();
                continue;
            }

            $line->cantidad = $cantidad;
            $line->save();
        }

        $this->docTools->recalculate($this->presupuesto);
        if ($this->presupuesto->save()) {
            if ($changes) {
                $this->toolBox()->i18nLog()->notice('record-updated-correctly');
            }
            return true;
        }

        $this->toolBox()->i18nLog()->error('record-save-error');
        return false;
    }

    /**
     * 
     * @return bool
     */
    protected function finalizeAction()
    {
        $this->contact->nombre = $this->request->request->get('nombre', '');
        $this->contact->apellidos = $this->request->request->get('apellidos', '');
        $this->contact->empresa = $this->request->request->get('empresa', '');
        $this->contact->tipoidfiscal = $this->request->request->get('tipoidfiscal', '');

        $fields = ['cifnif', 'direccion', 'codpostal', 'apartado', 'direccion', 'ciudad', 'provincia', 'codpais'];
        foreach ($fields as $field) {
            $this->contact->{$field} = $this->request->request->get($field, '');
            $this->presupuesto->{$field} = $this->request->request->get($field, '');
        }

        if ($this->contact->save()) {
            /// sets customer
            $cliente = $this->contact->getCustomer();
            $cliente->cifnif = $this->contact->cifnif;
            $cliente->razonsocial = empty($this->contact->empresa) ? $this->contact->fullName() : $this->contact->empresa;
            $cliente->save();
            $this->presupuesto->setSubject($cliente);

            /// update totals
            $this->docTools->recalculate($this->presupuesto);

            /// change status
            foreach ($this->presupuesto->getAvaliableStatus() as $status) {
                if ($status->generadoc == 'PedidoCliente') {
                    $this->presupuesto->idestado = $status->idestado;
                    break;
                }
            }

            if ($this->presupuesto->save()) {
                $this->toolBox()->i18nLog()->notice('record-updated-correctly');

                /// redir to new order
                foreach ($this->presupuesto->childrenDocuments() as $order) {
                    $this->redirect($order->url('public'));
                    return true;
                }
            }
        }

        $this->toolBox()->i18nLog()->error('record-save-error');
        return false;
    }

    /**
     * 
     * @param string $ref
     *
     * @return bool
     */
    protected function isProductInPresupuesto($ref)
    {
        foreach ($this->presupuesto->getLines() as $line) {
            if ($line->referencia == $ref) {
                return true;
            }
        }

        return false;
    }

    protected function loadPedidos()
    {
        if (empty($this->contact->codcliente)) {
            return;
        }

        $pedido = new PedidoCliente();
        $where = [new DataBaseWhere('codcliente', $this->contact->codcliente)];
        $order = ['fecha' => 'DESC', 'hora' => 'DESC'];
        foreach ($pedido->all($where, $order) as $ped) {
            $this->pedidos[] = $ped;
        }
    }

    protected function loadPresupuesto()
    {
        $this->presupuesto = new PresupuestoCliente();
        $where = [new DataBaseWhere('idcontactofact', $this->contact->idcontacto)];
        $order = ['fecha' => 'DESC', 'hora' => 'DESC'];
        if ($this->presupuesto->loadFromCode('', $where, $order) && $this->presupuesto->editable) {
            return;
        }

        $this->presupuesto->clear();
        $this->presupuesto->setSubject($this->contact);
        $this->presupuesto->setDate($this->presupuesto->fecha, $this->presupuesto->hora);
    }
}
