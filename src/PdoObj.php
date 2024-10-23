<?php

namespace ByJG\AnyDataset\Db;

use ByJG\AnyDataset\Core\Exception\NotAvailableException;
use ByJG\Util\Uri;
use PDO;

class PdoObj
{
    private Uri $uri;

    /**
     * @var true
     */
    private bool $useStmtCache = false;

    private string $connectionString;

    /**
     * @throws NotAvailableException
     */
    public function __construct(Uri $uri)
    {
        $this->uri = $uri;
        $this->validateConnUri();
    }

    public function getUri(): Uri
    {
        return $this->uri;
    }

    public function getConnStr(): string
    {
        if (empty($this->connectionString)) {
            if ($this->uri->getScheme() == "pdo") {
                $this->connectionString = $this->preparePdoConnectionStr($this->uri->getHost(), ".", null, null, $this->uri->getQuery());
            } else if ($this->uri->getScheme() == "literal") {
                $this->connectionString = $this->uri->getHost() . ":" . $this->uri->getQueryPart("connection");
            } else {
                $this->connectionString = $this->preparePdoConnectionStr($this->uri->getScheme(), $this->uri->getHost(), $this->uri->getPath(), $this->uri->getPort(), $this->uri->getQuery());
            }
        }

        return $this->connectionString;
    }

    public function expectToCacheResults(): bool
    {
        return $this->useStmtCache;
    }

    public function createInstance(?array $preOptions = [], ?array $postOptions = [], array $executeAfterConnect = []): PDO
    {
        $pdoConnectionString = $this->getConnStr();

        // Create Connection
        $instance = new PDO(
            $pdoConnectionString,
            $this->uri->getUsername(),
            $this->uri->getPassword(),
            (array)$preOptions
        );

        $this->uri = $this->uri->withScheme($instance->getAttribute(PDO::ATTR_DRIVER_NAME));

        $this->setPdoDefaultParams($instance, $postOptions);

        foreach ($executeAfterConnect as $sql) {
            $instance->exec($sql);
        }

        return $instance;
    }

    protected function setPdoDefaultParams(PDO $instance, ?array $postOptions = []): void
    {
        // Set Specific Attributes
        $defaultPostOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_CASE => PDO::CASE_LOWER,
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];
        $defaultPostOptions = $defaultPostOptions + (array)$postOptions;

        foreach ($defaultPostOptions as $key => $value) {
            $instance->setAttribute($key, $value);
        }
    }

    /**
     * @throws NotAvailableException
     */
    protected function validateConnUri(): void
    {
        if (!defined('PDO::ATTR_DRIVER_NAME')) {
            throw new NotAvailableException("Extension 'PDO' is not loaded");
        }

        $scheme = strtolower($this->uri->getScheme());
        $extension = "pdo_" . ($scheme == "literal" ? $this->uri->getHost() : $scheme);

        if ($scheme != "pdo" && !extension_loaded($extension)) {
            throw new NotAvailableException("Extension '$extension' is not loaded");
        }

        if ($this->uri->getQueryPart(DbPdoDriver::STATEMENT_CACHE) == "true") {
            $this->useStmtCache = true;
        }
    }

    protected function preparePdoConnectionStr(string $scheme, string $host, ?string $database, ?int $port, ?string $query): string
    {
        if (empty($host) && !str_contains($query ?? "", DbPdoDriver::UNIX_SOCKET)) {
            return $scheme . ":" . $database;
        }

        $database = ltrim(empty($database) ? "" : $database, '/');

        $pdoAr = [];
        if (!empty($host) && $host != ".") {
            $pdoAr[] = "host=" . $host;
        }

        if (!empty($database)) {
            $pdoAr[] = "dbname=$database";
        }

        if (!empty($port)) {
            $pdoAr[] = "port=" . $port;
        }

        parse_str($query, $queryArr);
        unset($queryArr[DbPdoDriver::DONT_PARSE_PARAM]);
        unset($queryArr[DbPdoDriver::STATEMENT_CACHE]);

        $pdoAr = array_merge($pdoAr, array_map(function ($k, $v) {
            return "$k=" . urldecode($v);
        }, array_keys($queryArr), $queryArr));

        return $scheme . ":" . implode(";", $pdoAr);
    }

    public static function getUriFromPdoConnStr(string $connStr, string $username = "", string $password = ""): Uri
    {
        if (preg_match("~^([^:]+):(/.*)~", $connStr, $matches) !== 0) {
            return Uri::getInstanceFromString("{$matches[1]}://{$matches[2]}");
        }

        $parts = explode(":", $connStr, 2);
        $scheme = $parts[0];

        $host = "";
        $port = "";
        $database = "";
        $query = [];
        $params = explode(";", $parts[1]);
        foreach ($params as $param) {
            $paramParts = explode("=", $param, 2);
            $key = $paramParts[0];
            if (empty($key)) {
                continue;
            }
            $value = !empty($paramParts[1]) ? $paramParts[1] : "";
            if ($key == "host") {
                $host = $value;
            } else if ($key == "port") {
                $port = ":$value";
            } else if ($key == "dbname") {
                $database = "/$value";
            } else {
                $query[$key] = $value;
            }
        }

        $credentials = "";
        if (!empty($username)) {
            $credentials = "$username:$password@";
        }

        if (!empty($query)) {
            $query = "?" . http_build_query($query);
        } else {
            $query = "";
        }

        $str = "{$scheme}://{$credentials}{$host}{$port}{$database}{$query}";
        return Uri::getInstanceFromString($str);
    }

}