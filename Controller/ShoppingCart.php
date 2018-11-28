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
     * @var PresupuestoCliente
     */
    public $presupuesto;

    public function __construct(&$cache, &$i18n, &$miniLog, $className, $uri = '')
    {
        parent::__construct($cache, $i18n, $miniLog, $className, $uri);
        $this->divisaTools = new DivisaTools();
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

        $action = $this->request->request->get('action', $this->request->query->get('action', ''));
        switch ($action) {
            case 'add':
                return $this->addProduct();
        }
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
