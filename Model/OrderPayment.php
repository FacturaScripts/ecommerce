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
namespace FacturaScripts\Plugins\ecommerce\Model;

use FacturaScripts\Core\Model\Base;

/**
 * Description of OrderPayment
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
class OrderPayment extends Base\ModelClass
{

    use Base\ModelTrait;

    /**
     *
     * @var float
     */
    public $amount;

    /**
     *
     * @var string
     */
    public $creationtime;

    /**
     *
     * @var string
     */
    public $customid;

    /**
     *
     * @var string
     */
    public $currency;

    /**
     *
     * @var float
     */
    public $fee;

    /**
     *
     * @var int
     */
    public $id;

    /**
     *
     * @var int
     */
    public $idpedido;

    /**
     *
     * @var string
     */
    public $platform;

    /**
     *
     * @var string
     */
    public $status;

    public function clear()
    {
        parent::clear();
        $this->creationtime = date('d-m-Y H:i:s');
        $this->status = 'unknown';
    }

    /**
     * 
     * @return string
     */
    public static function primaryColumn()
    {
        return 'id';
    }

    /**
     * 
     * @return string
     */
    public static function tableName()
    {
        return 'orderpayments';
    }
}
