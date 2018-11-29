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
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\DivisaTools;
use FacturaScripts\Dinamic\Lib\BusinessDocumentTools;
use FacturaScripts\Dinamic\Model\Pais;
use FacturaScripts\Dinamic\Model\PedidoCliente;
use FacturaScripts\Dinamic\Model\PresupuestoCliente;
use FacturaScripts\Dinamic\Model\Variante;
use FacturaScripts\Plugins\webportal\Lib\WebPortal\PortalController;

/**
 * Description of ShoppingCart
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
class ShoppingCart extends PortalController
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
    public $pedidos = [];

    /**
     *
     * @var PresupuestoCliente
     */
    public $presupuesto;

    public function __construct(&$cache, &$i18n, &$miniLog, $className, $uri = '')
    {
        parent::__construct($cache, $i18n, $miniLog, $className, $uri);
        $this->divisaTools = new DivisaTools();
    }

    /**
     * 
     * @return Pais[]
     */
    public function getCountries()
    {
        $pais = new Pais();
        return $pais->all([], ['nombre' => 'ASC'], 0, 0);
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        if (empty($this->contact)) {
            $this->miniLog->alert('Contact not found');
            return;
        }

        $this->commonCore();
    }

    public function publicCore(&$response)
    {
        parent::publicCore($response);
        if (empty($this->contact)) {
            $this->setTemplate('Master/LoginToContinue');
            return;
        }

        $this->commonCore();
    }

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

            $product = $variant->getProducto();
            $newLine = $this->presupuesto->getNewLine();
            $newLine->referencia = $variant->referencia;
            $newLine->descripcion = $product->descripcion;
            $newLine->cantidad = 1;
            $newLine->pvpunitario = $variant->precio;
            $newLine->codimpuesto = $product->codimpuesto;
            $newLine->iva = $product->getImpuesto()->iva;
            if (!$newLine->save()) {
                $this->miniLog->error($this->i18n->trans('record-save-error'));
                return false;
            }

            $docTools = new BusinessDocumentTools();
            $docTools->recalculate($this->presupuesto);
            $this->presupuesto->save();
            $this->miniLog->notice($this->i18n->trans('record-updated-correctly'));
            return true;
        }

        $this->miniLog->error($this->i18n->trans('record-save-error'));
        return false;
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
                return $this->finalizeAction();

            case 'order':
                if ($this->editAction() && count($this->presupuesto->getLines()) > 0) {
                    $this->setTemplate('ShoppingCartOrder');
                }
                break;
        }
    }

    protected function deleteLine()
    {
        $idlinea = $this->request->get('idline', '');
        foreach ($this->presupuesto->getLines() as $line) {
            if ($line->idlinea != $idlinea) {
                continue;
            }

            if ($line->delete()) {
                $docTools = new BusinessDocumentTools();
                $docTools->recalculate($this->presupuesto);
                $this->presupuesto->save();

                $this->miniLog->notice($this->i18n->trans('record-deleted-correctly'));
                return true;
            }
        }

        $this->miniLog->warning($this->i18n->trans('record-deleted-error'));
        return false;
    }

    protected function editAction()
    {
        foreach ($this->presupuesto->getLines() as $line) {
            $cantidad = (int) $this->request->request->get('quantity_' . $line->idlinea, '0');
            if ($cantidad <= 0) {
                $line->delete();
                continue;
            }

            $line->cantidad = $cantidad;
            $line->save();
        }

        $docTools = new BusinessDocumentTools();
        $docTools->recalculate($this->presupuesto);
        if ($this->presupuesto->save()) {
            $this->miniLog->notice($this->i18n->trans('record-updated-correctly'));
            return true;
        }

        $this->miniLog->error($this->i18n->trans('record-save-error'));
        return false;
    }

    protected function finalizeAction()
    {
        $this->contact->nombre = $this->request->request->get('nombre', '');
        $this->contact->apellidos = $this->request->request->get('apellidos', '');
        $this->contact->empresa = $this->request->request->get('empresa', '');

        $fields = ['cifnif', 'direccion', 'codpostal', 'apartado', 'direccion', 'ciudad', 'provincia', 'codpais'];
        foreach ($fields as $field) {
            $this->contact->{$field} = $this->request->request->get($field, '');
            $this->presupuesto->{$field} = $this->request->request->get($field, '');
        }

        if ($this->contact->save()) {
            /// sets customer
            $cliente = $this->contact->getCustomer();
            $this->presupuesto->codcliente = $cliente->codcliente;

            /// change status
            foreach ($this->presupuesto->getAvaliableStatus() as $status) {
                if ($status->generadoc == 'PedidoCliente') {
                    $this->presupuesto->idestado = $status->idestado;
                    break;
                }
            }

            if ($this->presupuesto->save()) {
                $this->miniLog->notice($this->i18n->trans('record-updated-correctly'));

                /// redir to new order
                foreach ($this->presupuesto->childrenDocuments() as $order) {
                    $this->response->headers->set('Refresh', '0; ' . $order->url('public'));
                    return true;
                }
            }
        }

        $this->miniLog->error($this->i18n->trans('record-save-error'));
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
