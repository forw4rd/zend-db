<?php

namespace Zend\Db\TableGateway;

interface TableGatewayInterface
{
    public function getTableName();
    public function select($where = null);
    public function insert($set);
    public function update($set, $where);
    public function delete($where);
}