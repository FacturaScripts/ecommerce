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
namespace FacturaScripts\Plugins\ecommerce;

require_once __DIR__ . '/vendor/autoload.php';

use FacturaScripts\Core\Base\InitClass;
use FacturaScripts\Plugins\ecommerce\Lib\PaymentGateway;
use FacturaScripts\Plugins\ecommerce\Lib\PaymentGatewayBitcoin;
use FacturaScripts\Plugins\ecommerce\Lib\PaymentGatewayPaypal;
use FacturaScripts\Plugins\ecommerce\Lib\PaymentGatewayStripe;

/**
 * Description of Init
 *
 * @author Carlos García Gómez
 */
class Init extends InitClass
{

    public function init()
    {
        PaymentGateway::register(new PaymentGatewayStripe());
        PaymentGateway::register(new PaymentGatewayBitcoin());
        PaymentGateway::register(new PaymentGatewayPaypal());
    }

    public function update()
    {
        /// do not remove this file, autoloader is necessary
    }
}
