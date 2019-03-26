<?php
namespace dokuwiki\plugin\structjoin\types;

use dokuwiki\plugin\struct\meta\QueryBuilder;
use dokuwiki\plugin\struct\meta\QueryBuilderWhere;
use dokuwiki\plugin\struct\meta\StructException;
use dokuwiki\plugin\struct\types\AbstractBaseType;
use dokuwiki\plugin\struct\types\Lookup;
use ReflectionClass;

class Join extends Lookup {

    public function valueEditor($name, $rawvalue, $htmlID)
    {
        return "<strong>{$this->config['schema']}.{$this->config['field']}</strong>";
    }

    /**
     * Render using linked field
     *
     * @param int|string $value
     * @param \Doku_Renderer $R
     * @param string $mode
     * @return bool
     */
    public function renderValue($value, \Doku_Renderer $R, $mode) {
        $column = $this->getLookupColumn();
        if(!$column) return false;
        return $column->getType()->renderValue($value, $R, $mode);
    }

    /**
     * Render using linked field
     *
     * @param \int[]|\string[] $values
     * @param \Doku_Renderer $R
     * @param string $mode
     * @return bool
     */
    public function renderMultiValue($values, \Doku_Renderer $R, $mode) {
        $column = $this->getLookupColumn();
        if(!$column) return false;
        return $column->getType()->renderMultiValue($values, $R, $mode);
    }

    /**
     * @param string $value
     * @return string
     */
    public function rawValue($value) {
        return $value;
    }

    /**
     * @param string $value
     * @return string
     */
    public function displayValue($value) {
        $column = $this->getLookupColumn();
        if($column) {
            return $column->getType()->displayValue($value);
        } else {
            return '';
        }
    }

    /**
     * This is the value to be used as argument to a filter for another column.
     *
     * In a sense this is the counterpart to the @see filter() function
     *
     * @param string $value
     *
     * @return string
     */
    public function compareValue($value) {
        $column = $this->getLookupColumn();
        if($column) {
            return $column->getType()->rawValue($value);
        } else {
            return '';
        }
    }

    /**
     * Merge with lookup table
     *
     * @param QueryBuilder $QB
     * @param string $tablealias
     * @param string $colname
     * @param string $alias
     * @throws \ReflectionException
     */
    public function select(QueryBuilder $QB, $tablealias, $colname, $alias) {
        $column = $this->getLookupColumn();
        if(!$column) {
            AbstractBaseType::select($QB, $tablealias, $colname, $alias);
            return;
        }

        $rightalias = $this->findRightAlias($QB);
        if (!$rightalias) {
            throw new StructException("Select Lookup column to use Join.");
        }

        $field = $column->getColName();
        $column->getType()->select($QB, $rightalias, $field, $alias);
    }

    /**
     * @param QueryBuilder $QB
     * @return false|int|string
     * @throws \ReflectionException
     */
    protected function findRightAlias(QueryBuilder $QB) {
        $from = $this->getProtectedPropertyFromQB($QB, 'from');
        $leftJoinAliases = array_filter(array_map(function($from) {
            if (preg_match('/^LEFT OUTER JOIN data_([^\s]*)/', $from, $matches)) {
                return $matches[1];
            }
            return false;
        }, $from));

        return array_search($this->config['schema'], $leftJoinAliases);
    }

    /**
     * @param QueryBuilder $QB
     * @param $property
     * @return mixed
     * @throws \ReflectionException
     */
    protected function getProtectedPropertyFromQB(QueryBuilder $QB, $property) {
        $reflectionClass = new ReflectionClass(QueryBuilder::class);
        $reflectionProperty = $reflectionClass->getProperty($property);
        $reflectionProperty->setAccessible(true);

        return $reflectionProperty->getValue($QB);
    }

    /**
     * Compare against lookup table
     *
     * @param QueryBuilderWhere $add
     * @param string $tablealias
     * @param string $colname
     * @param string $comp
     * @param string|\string[] $value
     * @param string $op
     */
    public function filter(QueryBuilderWhere $add, $tablealias, $colname, $comp, $value, $op) {
        $schema = 'data_' . $this->config['schema'];
        $column = $this->getLookupColumn();
        if(!$column) {
            AbstractBaseType::filter($add, $tablealias, $colname, $comp, $value, $op);
            return;
        }
        $field = $column->getColName();

        // compare against lookup field
        $QB = $add->getQB();
        $rightalias = $this->findRightAlias($QB);
        if (!$rightalias) {
            throw new StructException("Select Lookup column to use Join.");
        }
        $column->getType()->filter($add, $rightalias, $field, $comp, $value, $op);
    }

    /**
     * Sort by lookup table
     *
     * @param QueryBuilder $QB
     * @param string $tablealias
     * @param string $colname
     * @param string $order
     * @throws \ReflectionException
     */
    public function sort(QueryBuilder $QB, $tablealias, $colname, $order) {
        $schema = 'data_' . $this->config['schema'];
        $column = $this->getLookupColumn();
        if(!$column) {
            AbstractBaseType::sort($QB, $tablealias, $colname, $order);
            return;
        }
        $field = $column->getColName();

        $rightalias = $this->findRightAlias($QB);
        if (!$rightalias) {
            throw new StructException("Select Lookup column to use Join.");
        }
        $column->getType()->sort($QB, $rightalias, $field, $order);
    }
}
