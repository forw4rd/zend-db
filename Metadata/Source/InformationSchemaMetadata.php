<?php

namespace Zend\Db\Metadata\Source;

use Zend\Db\Metadata\MetadataInterface,
    Zend\Db\Adapter\Adapter,
    Zend\Db\Metadata\Object;

class InformationSchemaMetadata implements MetadataInterface
{
    protected $adapter = null;
    protected $defaultSchema = null;
    protected $data = array();

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->defaultSchema = ($adapter->getDefaultSchema()) ?: '__DEFAULT_SCHEMA__';
    }


    public function getSchemas()
    {
        // TODO: Implement getSchemas() method.
    }

    public function getTableNames($schema = null, $database = null)
    {
        // set values for database & schema
        $database = ($database) ?: '__DEFAULT_DB__';
        if ($schema == null && $this->defaultSchema != null) {
            $schema = $this->defaultSchema;
        }

        if (isset($this->data[$database][$schema]['tables'])) {
            return $this->data[$database][$schema]['tables'];
        }

        $platform = $this->adapter->getPlatform();

        $sql = 'SELECT ' . $platform->quoteIdentifier('TABLE_NAME')
            . 'FROM ' . $platform->quoteIdentifier('INFORMATION_SCHEMA')
            . $platform->getIdentifierSeparator() . $platform->quoteIdentifier('TABLES')
            . ' WHERE ' . $platform->quoteIdentifier('TABLE_SCHEMA')
            . ' != ' . $platform->quoteValue('INFORMATION_SCHEMA');

        if ($schema != '__DEFAULT_SCEMA__') {
            $sql .= ' AND ' . $platform->quoteIdentifier('TABLE_SCHEMA')
                . ' = ' . $platform->quoteValue($schema);
        }

        $results = $this->adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
        $tables = array();
        foreach ($results->toArray() as $row) {
            $tables[] = $row['TABLE_NAME'];
        }
        $this->prepareDataHeirarchy($database, $schema, array('tables'));
        $this->data[$database][$schema]['tables'] = $tables;
        return $tables;
    }

    public function getTables($schema = null, $database = null)
    {
        // set values for database & schema
        $database = ($database) ?: '__DEFAULT_DB__';
        if ($schema == null && $this->defaultSchema != null) {
            $schema = $this->defaultSchema;
        }

        $tables = array();
        foreach ($this->getTableNames($schema, $database) as $tableName) {
            $tables[] = $this->getTable($tableName, $schema, $database);
        }
        return $tables;
    }

    public function getTable($tableName, $schema = null, $database = null)
    {
        // set values for database & schema
        $database = ($database) ?: '__DEFAULT_DB__';
        if ($schema == null && $this->defaultSchema != null) {
            $schema = $this->defaultSchema;
        }

        $table = new Object\TableObject($tableName);
        $table->setColumns($this->getColumns($tableName, $schema, $database));
        return $table;
    }

    public function getViewNames($schema = null, $database = null)
    {
        // TODO: Implement getViewNames() method.
    }

    public function getViews($schema = null, $database = null)
    {
        // TODO: Implement getViews() method.
    }

    public function getView($viewName, $schema = null, $database = null)
    {
        // TODO: Implement getView() method.
    }

    public function getColumnNames($table, $schema = null, $database = null)
    {
        // set values for database & schema
        $database = ($database) ?: '__DEFAULT_DB__';
        if ($schema == null && $this->defaultSchema != null) {
            $schema = $this->defaultSchema;
        }

        if (!isset($this->data[$database][$schema]['columns'][$table])) {
            $this->loadColumnData($schema, $database);
        }

        $columns = array();
        foreach ($this->data[$database][$schema]['columns'][$table] as $columnName => $columnData) {
            $columns[] = $columnName;
        }
        return $columns;
    }

    public function getColumns($table, $schema = null, $database = null)
    {
        // set values for database & schema
        $database = ($database) ?: '__DEFAULT_DB__';
        if ($schema == null && $this->defaultSchema != null) {
            $schema = $this->defaultSchema;
        }

        if (!isset($this->data[$database][$schema]['columns'][$table])) {
            $this->loadColumnData($schema, $database);
        }

        $columns = array();
        foreach ($this->data[$database][$schema]['columns'][$table] as $columnName => $columnData) {
            $columns[] = $column = $this->getColumn($columnName, $table, $schema, $database);
        }
        return $columns;
    }

    public function getColumn($columnName, $table, $schema = null, $database = null)
    {
        // set values for database & schema
        $database = ($database) ?: '__DEFAULT_DB__';
        if ($schema == null && $this->defaultSchema != null) {
            $schema = $this->defaultSchema;
        }

        if (!isset($this->data[$database][$schema]['columns'][$table][$columnName])) {
            $this->loadColumnData($table, $schema, $database);
        }

        if (!isset($this->data[$database][$schema]['columns'][$table][$columnName])) {
            throw new \Exception('A column by that name was not found.');
        }

        $columnInfo = &$this->data[$database][$schema]['columns'][$table][$columnName];

        $column = new Object\ColumnObject($columnName, $table, $schema);
        $column->setOrdinalPosition($columnInfo['ORDINAL_POSITION']);
        $column->setColumnDefault($columnInfo['COLUMN_DEFAULT']);
        $column->setIsNullable($columnInfo['IS_NULLABLE']);
        $column->setDataType($columnInfo['DATA_TYPE']);
        $column->setCharacterMaximumLength($columnInfo['CHARACTER_MAXIMUM_LENGTH']);
        $column->setCharacterOctetLength($columnInfo['CHARACTER_OCTET_LENGTH']);
        $column->setNumericPrecision($columnInfo['NUMERIC_PRECISION']);
        $column->setNumericScale($columnInfo['NUMERIC_SCALE']);
        return $column;
    }


    public function getConstraints($table, $schema = null, $database = null)
    {
        // set values for database & schema
        $database = ($database) ?: '__DEFAULT_DB__';
        if ($schema == null && $this->defaultSchema != null) {
            $schema = $this->defaultSchema;
        }

        if (!isset($this->data[$database][$schema]['constraints'])) {
            $this->loadConstraintData($schema, $database);
        }

        $constraints = array();
        foreach ($this->data[$database][$schema]['constraints']['names'] as $constraintInfo) {
            if ($constraintInfo['table_name'] == $table) {
                $constraints[] = $this->getConstraint($constraintInfo['constraint_name'], $table, $schema, $database);
            }
        }

        return $constraints;
    }

    public function getConstraint($constraintName, $table, $schema = null, $database = null)
    {
        // set values for database & schema
        $database = ($database) ?: '__DEFAULT_DB__';
        if ($schema == null && $this->defaultSchema != null) {
            $schema = $this->defaultSchema;
        }

        if (!isset($this->data[$database][$schema]['constraints'])) {
            $this->loadConstraintData($schema, $database);
        }

        $found = false;
        foreach ($this->data[$database][$schema]['constraints']['names'] as $constraintInfo) {
            if ($constraintInfo['constraint_name'] == $constraintName && $constraintInfo['table_name'] == $table) {
                $found = $constraintInfo;
                break;
            }
        }

        if (!$found) {
            throw new \Exception('Cannot find a constraint by that name in this table');
        }

        $constraint = new Object\ConstraintObject($constraintName, $table, $schema);
        $constraint->setType($found['constraint_type']);
        $constraint->setKeys($this->getConstraintKeys($constraintName, $table, $schema, $database));
        return $constraint;
    }

    public function getConstraintKeys($constraint, $table, $schema = null, $database = null)
    {
        // set values for database & schema
        $database = ($database) ?: '__DEFAULT_DB__';
        if ($schema == null && $this->defaultSchema != null) {
            $schema = $this->defaultSchema;
        }

        if (!isset($this->data[$database][$schema]['constraints'])) {
            $this->loadConstraintData($schema, $database);
        }

        // organize references first
        $references = array();
        foreach ($this->data[$database][$schema]['constraints']['references'] as $refKeyInfo) {
            if ($refKeyInfo['constraint_name'] == $constraint) {
                $references[$refKeyInfo['constraint_name']] = $refKeyInfo;
            }
        }

        $keys = array();
        foreach ($this->data[$database][$schema]['constraints']['keys'] as $constraintKeyInfo) {
            if ($constraintKeyInfo['table_name'] == $table && $constraintKeyInfo['constraint_name'] === $constraint) {
                $keys[] = $key = new Object\ConstraintKeyObject($constraintKeyInfo['column_name']);
                $key->setOrdinalPosition($constraintKeyInfo['ordinal_position']);
                if (isset($references[$constraint])) {
                    //$key->setReferencedTableSchema($constraintKeyInfo['referenced_table_schema']);
                    $key->setForeignKeyUpdateRule($references[$constraint]['update_rule']);
                    $key->setForeignKeyDeleteRule($references[$constraint]['delete_rule']);
                    $key->setReferencedTableName($references[$constraint]['referenced_table_name']);
                    $key->setReferencedColumnName($references[$constraint]['referenced_column_name']);
                }
            }
        }

        return $keys;
    }



    public function getTriggerNames($schema = null, $database = null)
    {
        // TODO: Implement getTriggerNames() method.
    }

    public function getTriggers($schema = null, $database = null)
    {
        // TODO: Implement getTriggers() method.
    }

    public function getTrigger($triggerName, $schema = null, $database = null)
    {
        // TODO: Implement getTrigger() method.
    }

    protected function loadColumnData($schema, $database)
    {
        $platform = $this->adapter->getPlatform();

        $isColumns = array(
            'TABLE_NAME',
            'COLUMN_NAME',
            'ORDINAL_POSITION',
            'COLUMN_DEFAULT',
            'IS_NULLABLE',
            'DATA_TYPE',
            'CHARACTER_MAXIMUM_LENGTH',
            'CHARACTER_OCTET_LENGTH',
            'NUMERIC_PRECISION',
            'NUMERIC_SCALE',
        );

        array_walk($isColumns, function (&$c) use ($platform) { $c = $platform->quoteIdentifier($c); });

        $sql = 'SELECT ' . implode(', ', $isColumns)
            . ' FROM ' . $platform->quoteIdentifier('INFORMATION_SCHEMA')
            . $platform->getIdentifierSeparator() . $platform->quoteIdentifier('COLUMNS')
            . ' WHERE ' . $platform->quoteIdentifier('TABLE_SCHEMA')
            . ' != ' . $platform->quoteValue('INFORMATION_SCHEMA');

        if ($schema != '__DEFAULT_SCHEMA__') {
            $sql .= ' AND ' . $platform->quoteIdentifier('TABLE_SCHEMA')
                . ' = ' . $platform->quoteValue($schema);
        }

        $results = $this->adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
        $columnByTableInfos = array();
        foreach ($results->toArray() as $row) {
            if (!isset($columnByTableInfos[$row['TABLE_NAME']])) {
                $columnByTableInfos[$row['TABLE_NAME']] = array();
            }
            if (!isset($columnByTableInfos[$row['TABLE_NAME']][$row['COLUMN_NAME']])) {
                $columnByTableInfos[$row['TABLE_NAME']][$row['COLUMN_NAME']] = array();
            }
            array_change_key_case($row, CASE_LOWER);
            $columnByTableInfos[$row['TABLE_NAME']][$row['COLUMN_NAME']] = $row;
        }

        $this->prepareDataHeirarchy($database, $schema, array('columns'));
        $this->data[$database][$schema]['columns'] = $columnByTableInfos;
    }

    protected function loadConstraintData($schema, $database)
    {
        $this->loadConstraintDataNames($schema, $database);
        $this->loadConstraintDataKeys($schema, $database);
        $this->loadConstraintReferences($schema, $database);


    }

    protected function loadConstraintDataNames($schema, $database)
    {
        $platform = $this->adapter->getPlatform();

        $isColumns = array(
            'CONSTRAINT_NAME',
            'TABLE_SCHEMA',
            'TABLE_NAME',
            'CONSTRAINT_TYPE'
        );

        array_walk($isColumns, function (&$c) use ($platform) { $c = $platform->quoteIdentifier($c); });

        $sql = 'SELECT ' . implode(', ', $isColumns)
            . ' FROM ' . $platform->quoteIdentifier('INFORMATION_SCHEMA')
            . $platform->getIdentifierSeparator() . $platform->quoteIdentifier('TABLE_CONSTRAINTS')
            . ' WHERE ' . $platform->quoteIdentifier('TABLE_SCHEMA')
            . ' != ' . $platform->quoteValue('INFORMATION_SCHEMA');

        if ($schema !== '__DEFAULT_SCHEMA__') {
            $sql .= ' AND ' . $platform->quoteIdentifier('TABLE_SCHEMA')
                . ' = ' . $platform->quoteValue($schema);
        }

        $results = $this->adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);

        $constraintData = array();
        foreach ($results->toArray() as $row) {
            $constraintData[] = array_change_key_case($row, CASE_LOWER);
        }

        $this->prepareDataHeirarchy($database, $schema, array('constraints', 'names'));
        $this->data[$database][$schema]['constraints']['names'] = $constraintData;
    }

    protected function loadConstraintDataKeys($schema, $database)
    {
        $platform = $this->adapter->getPlatform();

        $isColumns = array(
            'CONSTRAINT_NAME',
            'TABLE_SCHEMA',
            'TABLE_NAME',
            'COLUMN_NAME',
            'ORDINAL_POSITION'
        );

        array_walk($isColumns, function (&$c) use ($platform) { $c = $platform->quoteIdentifier($c); });

        $sql = 'SELECT ' . implode(', ', $isColumns)
            . ' FROM ' . $platform->quoteIdentifier('INFORMATION_SCHEMA')
            . $platform->getIdentifierSeparator() . $platform->quoteIdentifier('KEY_COLUMN_USAGE')
            . ' WHERE ' . $platform->quoteIdentifier('TABLE_SCHEMA')
            . ' != ' . $platform->quoteValue('INFORMATION_SCHEMA');;

        if ($schema != null || $this->defaultSchema != null) {
            if ($schema == null) {
                $schema = $this->defaultSchema;
            }
            $sql .= ' AND ' . $platform->quoteIdentifier('TABLE_SCHEMA')
                . ' = ' . $platform->quoteValue($schema);
        }

        $results = $this->adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);

        $constraintKeyData = array();
        foreach ($results->toArray() as $row) {
            $constraintKeyData[] = array_change_key_case($row, CASE_LOWER);
        }

        $this->prepareDataHeirarchy($database, $schema, array('constraints', 'keys'));
        $this->data[$database][$schema]['constraints']['keys'] = $constraintKeyData;
    }

    protected function loadConstraintReferences($schema, $database)
    {
        /** @var $platform \Zend\Db\Adapter\PlatformInterface */
        $platform = $this->adapter->getPlatform();

        $quoteIdentifierForWalk = function (&$c) use ($platform) { $c = $platform->quoteIdentifierWithSeparator($c); };
        $quoteSelectList = function (array $identifierList) use ($platform, $quoteIdentifierForWalk) {
            array_walk($identifierList, $quoteIdentifierForWalk);
            return implode(', ', $identifierList);
        };

        // target: CONSTRAINT_SCHEMA, CONSTRAINT_NAME, UPDATE_RULE, DELETE_RULE, REFERENCE_CONSTRAINT_NAME

        if ($platform->getName() == 'MySQL') {
            $sql = 'SELECT ' . $quoteSelectList(array(
                    'RC.CONSTRAINT_NAME', 'RC.UPDATE_RULE', 'RC.DELETE_RULE',
                    'RC.TABLE_NAME', 'CK.REFERENCED_TABLE_NAME', 'CK.REFERENCED_COLUMN_NAME'
                    ))
                . ' FROM ' . $platform->quoteIdentifierWithSeparator('INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS RC')
                . ' INNER JOIN ' . $platform->quoteIdentifierWithSeparator('INFORMATION_SCHEMA.KEY_COLUMN_USAGE CK')
                . ' ON ' . $platform->quoteIdentifierWithSeparator('RC.CONSTRAINT_NAME')
                . ' = ' . $platform->quoteIdentifierWithSeparator('CK.CONSTRAINT_NAME');
        } else {
            $sql = 'SELECT ' . $quoteSelectList(array(
                    'RC.CONSTRAINT_NAME', 'RC.UPDATE_RULE', 'RC.DELETE_RULE',
                    'TC1.TABLE_NAME', 'CK.TABLE_NAME AS REFERENCED_TABLE_NAME', 'CK.COLUMN_NAME AS REFERENCED_COLUMN_NAME'
                    ))
                . ' FROM ' . $platform->quoteIdentifierWithSeparator('INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS RC')
                . ' INNER JOIN ' . $platform->quoteIdentifierWithSeparator('INFORMATION_SCHEMA.TABLE_CONSTRAINTS TC1')
                . ' ON ' . $platform->quoteIdentifierWithSeparator('RC.CONSTRAINT_NAME')
                . ' = ' . $platform->quoteIdentifierWithSeparator('TC1.CONSTRAINT_NAME')
                . ' INNER JOIN ' . $platform->quoteIdentifierWithSeparator('INFORMATION_SCHEMA.TABLE_CONSTRAINTS TC2')
                . ' ON ' . $platform->quoteIdentifierWithSeparator('RC.UNIQUE_CONSTRAINT_NAME')
                . ' = ' . $platform->quoteIdentifierWithSeparator('TC2.CONSTRAINT_NAME')
                . ' INNER JOIN ' . $platform->quoteIdentifierWithSeparator('INFORMATION_SCHEMA.KEY_COLUMN_USAGE CK')
                . ' ON ' . $platform->quoteIdentifierWithSeparator('TC2.CONSTRAINT_NAME')
                . ' = ' . $platform->quoteIdentifierWithSeparator('CK.CONSTRAINT_NAME');
        }

        if ($schema != '__DEFAULT_SCHEMA__') {
            $sql .= ' AND ' . $platform->quoteIdentifierWithSeparator('RC.CONSTRAINT_SCHEMA')
                . ' = ' . $platform->quoteValue($schema);
        }

        $results = $this->adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);

        $constraintRefData = array();
        foreach ($results->toArray() as $row) {
            $constraintRefData[] = array_change_key_case($row, CASE_LOWER);
        }

        $this->prepareDataHeirarchy($database, $schema, array('constraints', 'keys'));
        $this->data[$database][$schema]['constraints']['references'] = $constraintRefData;
    }

    protected function prepareDataHeirarchy($database, $schema, array $rest)
    {
        $data = &$this->data;
        if (!isset($data[$database])) {
            $data[$database] = array();
        }
        if (!isset($this->data[$database][$schema])) {
            $data[$database][$schema] = array();
        }
        $data = &$data[$database][$schema];
        foreach ($rest as $i) {
            if (!isset($data[$i])) {
                $data[$i] = array();
            }
            $data =& $data[$i];
        }
        unset($t);
    }

}