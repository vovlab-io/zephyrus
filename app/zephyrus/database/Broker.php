<?php namespace Zephyrus\Database;

use Zephyrus\Exceptions\DatabaseException;
use Zephyrus\Utilities\Pager;

abstract class Broker
{
    const SQL_FORMAT_DATE = "Y-m-d";
    const SQL_FORMAT_DATE_TIME = "Y-m-d H:i:s";

    /**
     * @var Database
     */
    private $database;

    /**
     * Broker constructor called by children. Simply get the database reference
     * for further use.
     */
    public function __construct()
    {
        $this->database = Database::getInstance();
    }

    /**
     * @param int $count
     * @param int $limit
     * @param string $urlParameter
     * @return Pager
     */
    public function buildPager($count, $limit = Pager::PAGE_MAX_ENTITIES, $urlParameter = Pager::URL_PARAMETER)
    {
        return new Pager($count, $limit, $urlParameter);
    }

    /**
     * @return string
     */
    protected function getLastInsertedId()
    {
        return $this->database->getLastInsertedId();
    }

    /**
     * @param $limit
     */
    protected function setPagerLimit($limit)
    {
        $this->pagerLimit = $limit;
    }

    /**
     * @return Database
     */
    protected function getDatabase()
    {
        return $this->database;
    }

    /**
     * Sort a collection of objects naturally using a specified getter method.
     *
     * @param Object[] $objects
     * @param string $getterMethod
     * @return Object[]
     */
    public static function naturalSort($objects, $getterMethod = 'getNumber')
    {
        $orderedResults = [];
        $numbers = [];
        foreach ($objects as $object) {
            $numbers[] = $object->{$getterMethod}();
        }
        natsort($numbers);
        $orderedKeys = array_keys($numbers);
        foreach ($orderedKeys as $index) {
            $orderedResults[] = $objects[$index];
        }
        return $orderedResults;
    }

    /**
     * Execute a SELECT query which should return a single data row. Best
     * suited for queries involving primary key in where. Will return null
     * if the query did not return any results. If more than one row is
     * returned, an exception is thrown.
     *
     * @param string $query
     * @param array $parameters
     * @param string $allowedTags
     * @return array | null
     * @throws DatabaseException
     */
    protected function selectUnique($query, $parameters = [], $allowedTags = "")
    {
        $statement = $this->query($query, $parameters);
        if (!empty($this->allowedHtmlTags)) {
            $statement->setAllowedHtmlTags($this->allowedHtmlTags);
        }
        $statement->setAllowedHtmlTags($allowedTags);
        $n = $statement->count();
        if ($n == 0) {
            return null;
        } elseif ($n > 1) {
            throw new DatabaseException("Specified SELECT query « $query » should return a unique row, but $n rows found");
        }
        return $statement->next();
    }

    /**
     * Execute a SELECT query which return the entire set of rows in an array. Will
     * return an empty array if the query did not return any results.
     *
     * @param string $query
     * @param array $parameters
     * @param string $allowedTags
     * @return array
     */
    protected function selectAll($query, $parameters = [], $allowedTags = "")
    {
        $statement = $this->query($query, $parameters);
        if (!empty($this->allowedHtmlTags)) {
            $statement->setAllowedHtmlTags($this->allowedHtmlTags);
        }
        $statement->setAllowedHtmlTags($allowedTags);
        $results = [];
        while ($row = $statement->next()) {
            $results[] = $row;
        }
        return $results;
    }

    /**
     * Execute a query which should be contain inside a transaction. The specified
     * callback method will optionally receive the Database instance if one argument
     * is defined. Will work with nested transactions if using the TransactionPDO
     * handler. Best suited method for INSERT, UPDATE and DELETE queries.
     *
     * @param callable $callback
     * @return mixed
     * @throws DatabaseException
     */
    protected function transaction(Callable $callback)
    {
        try {
            $this->database->beginTransaction();
            $reflect = new \ReflectionFunction($callback);
            if ($reflect->getNumberOfParameters() == 1) {
                $result = $callback($this->database);
            } elseif ($reflect->getNumberOfParameters() == 0) {
                $result = $callback();
            } else {
                throw new \InvalidArgumentException("Specified callback must have 0 or 1 argument");
            }
            $this->database->commit();
            return $result;
        } catch (\Exception $e) {
            $this->database->rollback();
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Execute any type of query and simply return the DatabaseStatement
     * object ready to be fetched.
     *
     * @param string $query
     * @param array $parameters
     * @return DatabaseStatement
     * @throws DatabaseException
     */
    protected function query($query, $parameters = [])
    {
        return $this->database->query($query, $parameters);
    }
}