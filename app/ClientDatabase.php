<?php

namespace App;

use App\Exceptions\InvalidQueryException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Config\Repository as ConfigRepository;

class ClientDatabase
{
    /**
     * @var DatabaseManager
     */
    public $db;
    /**
     * @var ConfigRepository
     */
    private $configRepository;

    /**
     * @var array
     */
    private $credentials;

    /**
     * @var array
     */
    private $errors = [];

    /**
     * @param DatabaseManager $db
     * @param ConfigRepository $configRepository
     */
    public function __construct(DatabaseManager $db, ConfigRepository $configRepository)
    {
        $this->db = $db;
        $this->configRepository = $configRepository;
    }

    /**
     * @return bool
     */
    public function canConnect()
    {
        $this->clearErrors();

        if (is_null($this->credentials)) {
            $this->errors[]['code'] = 500;
            $this->errors[]['message'] = ['No credentials have been provided'];
            return false;
        }

        try {

            $this->db->connection('client')->getDatabaseName();

        } catch (\Exception $e) {
            $this->errors[]['code'] = $e->getCode();
            $this->errors[]['message'] = $e->getMessage();
            return false;
        }

        return true;

    }

    private function clearErrors()
    {
        $this->errors = [];
    }

    /**
     * @param array $credentials
     */
    public function connect(array $credentials)
    {
        $this->setDatabaseConfig($credentials);

        $this->clearErrors();

        try {
            $this->db->connection('client');
        } catch (\Exception $e) {
            $this->errors[]['code'] = $e->getCode();
            $this->errors[]['message'] = $e->getMessage();
        }
    }

    /**
     * @param string $query
     * @return array
     * @throws Exceptions\InvalidQueryTypeException
     * @throws InvalidQueryException
     */
    public function execute($query)
    {
        if ($this->isSelectQuery($query)) {

            $query = $this->applyLimitToQuery($query, 5);
            $query = $this->applySemiColon($query);

            try {
                $this->setFetchMethod(\PDO::FETCH_ASSOC);
                return $this->db->connection('client')->select($query);
            } catch (\Exception $e) {
                $this->errors[]['code'] = $e->getCode();
                $this->errors[]['message'] = $e->getMessage();
                throw new \App\Exceptions\InvalidQueryException();
            }
        }
        throw new \App\Exceptions\InvalidQueryTypeException();
    }

    /**
     * @param string $query
     * @return bool
     */
    public function isSelectQuery($query)
    {
        return substr(strtolower($query), 0, 7) === 'select ';
    }

    /**
     * @param string $query
     * @param int $limit
     * @return string
     */
    public function applyLimitToQuery($query, $limit)
    {
        if ($existingLimit = $this->existingLimit($query)) {

            if ($existingLimit > $limit) {
                // If the existing limit is higher than what we're
                // allowing, replace it with a lower one.
                return str_ireplace('LIMIT ' . $existingLimit, 'LIMIT ' . $limit, $query);
            }

            // Leave the existing limit in place.
            return $query;
        }

        return $query . ' LIMIT ' . $limit;
    }

    /**
     * @param string $query
     * @return null|int
     */
    public function existingLimit($query)
    {
        preg_match('/limit ([\d]+)/', strtolower($query), $matches);

        if (sizeof($matches)>0) {
            return $matches[1];
        }
        return null;
    }

    /**
     * @param string $query
     * @return string
     */
    public function applySemiColon($query)
    {
        return rtrim($query, ';') . ';';
    }

    /**
     * @return bool
     */
    public function hasErrors()
    {
        return sizeof($this->errors) > 0;
    }

    /**
     * @return array
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * @param array $config
     * @return $this
     */
    public function setDatabaseConfig(array $config)
    {
        $this->credentials = $config;
        $this->configRepository->set('database.connections.client', $config);
        return $this;
    }

    public function setFetchMethod($method)
    {
        $this->db->connection('client')->setFetchMode($method);
        return $this;
    }
}