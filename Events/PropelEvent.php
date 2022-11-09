<?php
/*************************************************************************************/
/*      This file is part of the module CreditNote                                   */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace GoogleShoppingXml\Events;

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Thelia\Core\Event\ActionEvent;

/**
 * @author Gilles Bourgeat <gilles@thelia.fr>
 */
class PropelEvent extends ActionEvent
{
    /** @var ActiveRecordInterface */
    protected $instance;

    /** @var mixed */
    protected $return;

    /**
     * PropelEvent constructor.
     * @param ActiveRecordInterface $instance
     */
    public function __construct(ActiveRecordInterface $instance)
    {
        $this->instance = $instance;
    }

    /**
     * @return ActiveRecordInterface
     */
    public function getInstance()
    {
        return $this->instance;
    }

    /**
     * @param ActiveRecordInterface $instance
     * @return PropelEvent
     */
    public function setInstance($instance)
    {
        $this->instance = $instance;
        return $this;
    }
}