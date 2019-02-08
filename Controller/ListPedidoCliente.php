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
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Controller\ListPedidoCliente as ParentController;

/**
 * Description of ListPedidoCliente
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class ListPedidoCliente extends ParentController
{

    /**
     * 
     * @param string $name
     */
    protected function createViewOrderPayments($name = 'ListOrderPayment')
    {
        $this->addView($name, 'OrderPayment', 'payments', 'fas fa-credit-card');
        $this->addOrderBy($name, ['creationtime'], 'date', 2);
        $this->addOrderBy($name, ['amount'], 'amount');
        $this->addOrderBy($name, ['fee'], 'fee');
        $this->addSearchFields($name, ['customid']);

        /// filters
        $platforms = $this->codeModel->all('orderpayments', 'platform', 'platform');
        $this->addFilterSelect($name, 'platform', 'platform', 'platform', $platforms);

        $status = $this->codeModel->all('orderpayments', 'status', 'status');
        $this->addFilterSelect($name, 'status', 'status', 'status', $status);
    }

    protected function createViews()
    {
        parent::createViews();
        $this->createViewOrderPayments();
    }
}
