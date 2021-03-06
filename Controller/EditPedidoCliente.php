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
use FacturaScripts\Core\Controller\EditPedidoCliente as ParentController;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;

/**
 * Description of EditPedidoCliente
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
class EditPedidoCliente extends ParentController
{

    /**
     * 
     * @param string $name
     */
    protected function createViewOrderPayments(string $name = 'ListOrderPayment')
    {
        $this->addListView($name, 'OrderPayment', 'payments', 'fas fa-credit-card');
        $this->setSettings($name, 'btnNew', false);
    }

    protected function createViews()
    {
        parent::createViews();
        $this->createViewOrderPayments();
    }

    /**
     * 
     * @param string   $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'ListOrderPayment':
                $idpedido = $this->getViewModelValue('EditPedidoCliente', 'idpedido');
                $where = [new DataBaseWhere('idpedido', $idpedido)];
                $view->loadData('', $where);
                break;

            default:
                parent::loadData($viewName, $view);
        }
    }
}
