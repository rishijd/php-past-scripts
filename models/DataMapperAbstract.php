<?php
abstract class DataMapperAbstract {

    protected $_dbTable = '';
    protected $_identityMap = array();

    // get list of domain objects by ID (implemented by concrete domain object subclasses)
    abstract public function fetchAll();  
} 