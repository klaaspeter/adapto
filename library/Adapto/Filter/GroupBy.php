<?php

/**
 * This file is part of the Adapto Toolkit.
 * Detailed copyright and licensing information can be found
 * in the doc/COPYRIGHT and doc/LICENSE files which should be 
 * included in the distribution.
 *
 * @package adapto
 * @subpackage filters
 *
 * @copyright (c)2000-2004 Ibuildings.nl BV
 * @license http://www.achievo.org/atk/licensing ATK Open Source License
 *

 */ 

/** @internal include baseclass */
usefilter("atkfilter");

/**
 * Add a group by clausule to a query.
 *
 * Use this filter, like you use an attribute, for example:
 * $this->add(new Adapto_Filter_GroupBy("street_place", "street, place"));
 * 
 * @author Kees van Dieren <kees@ibuildings.nl>
 * @author ijansch
 * @package adapto
 * @subpackage filters
 *
 */
class Adapto_Filter_GroupBy extends Adapto_Filter
{
    /**
     * the group by statement
     *
     * @access private
     * @var string groupbystmt
     */
    public $m_groupbystmt; // defaulted to public

    /**
     * constructor
     *
     * @param string $name
     * @param string $groupbystmt
     * @param int $flags
     */

    public function __construct($name, $groupbystmt, $flags = 0)
    {
        $this->m_groupbystmt = $groupbystmt;
        parent::__construct($name, $flags);
    }

    /**
     * add the group by statement to the query
     *
     * @param atkQuery $query The SQL query object
     * @return void
     */
    function addToQuery(&$query)
    {
        $query->addGroupBy($this->m_groupbystmt);
    }
}
?>