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

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Plugins\webportal\Controller\EditProfile as ParentController;

/**
 * Description of EditProfile
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class EditProfile extends ParentController
{

    protected function createSections()
    {
        parent::createSections();
        $this->createSectionOrders();
    }

    /**
     * 
     * @param string $sectionName
     */
    protected function createSectionOrders($sectionName = 'ListPedidoClienteWeb')
    {
        $this->addListSection($sectionName, 'PedidoCliente', 'orders', 'fas fa-shopping-cart');
        $this->addOrderOption($sectionName, ['fecha', 'hora'], 'date', 2);
    }

    /**
     * 
     * @param string $sectionName
     */
    protected function loadData(string $sectionName)
    {
        switch ($sectionName) {
            case 'ListPedidoClienteWeb':
                if (isset($this->contact->idcontacto)) {
                    $where = [new DataBaseWhere('idcontactofact', $this->contact->idcontacto)];
                    $this->sections[$sectionName]->loadData('', $where);
                }
                break;

            default:
                parent::loadData($sectionName);
        }
    }
}