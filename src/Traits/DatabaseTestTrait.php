<?php

namespace Haiz\TestTrait\Traits;

use DomainException;
use PDO;
use PDOStatement;
use UnexpectedValueException;

/**
 * Database test.
 */
trait DatabaseTestTrait
{
    /**
     * @var string Path to schema.sql
     */
    protected $schemaFile = '';

    /**
     * Create tables and insert fixtures.
     *
     * TestCases must call this method inside setUp().
     *
     * @param string|null $schemaFile The sql schema file
     *
     * @return void
     */
    protected function setUpDatabase($schemaFile = null)
    {
        if (isset($schemaFile)) {
            $this->schemaFile = $schemaFile;
        }

        $this->getConnection();

        $this->unsetStatsExpiry();
        $this->createTables();
        $this->truncateTables();

        if (!empty($this->fixtures)) {
            $this->insertFixtures($this->fixtures);
        }
    }

    /**
     * Get database connection.
     *
     * @return PDO The PDO instance
     */
    protected function getConnection()
    {
        return $this->container->get(PDO::class);
    }

    /**
     * Workaround for MySQL 8: update_time not working.
     *
     * https://bugs.mysql.com/bug.php?id=95407
     *
     * @return void
     */
    private function unsetStatsExpiry()
    {
        if (version_compare($this->getMySqlVersion(), '8.0.0') >= 0) {
            $this->getConnection()->exec('SET information_schema_stats_expiry=0;');
        }
    }

    /**
     * Get MySql version.
     *
     * @throws UnexpectedValueException
     *
     * @return string The version
     */
    private function getMySqlVersion()
    {
        $statement = $this->getConnection()->query("SHOW VARIABLES LIKE 'version';");

        if ($statement === false) {
            throw new UnexpectedValueException('Invalid sql statement');
        }

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new UnexpectedValueException('Version not found');
        }

        return (string)$row['Value'];
    }

    /**
     * Create tables.
     *
     * @return void
     */
    protected function createTables()
    {
        if (defined('DB_TEST_TRAIT_INIT')) {
            return;
        }

        $this->dropTables();
        $this->importSchema();

        define('DB_TEST_TRAIT_INIT', 1);
    }

    /**
     * Clean up database. Truncate tables.
     *
     * @throws UnexpectedValueException
     *
     * @return void
     */
    protected function dropTables()
    {
        $pdo = $this->getConnection();

        $pdo->exec('SET unique_checks=0; SET foreign_key_checks=0;');

        $statement = $this->createQueryStatement(
            'SELECT TABLE_NAME
                FROM information_schema.tables
                WHERE table_schema = database()'
        );

        $sql = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $sql[] = sprintf('DROP TABLE `%s`;', $row['TABLE_NAME']);
        }

        if ($sql) {
            $pdo->exec(implode("\n", $sql));
        }

        $pdo->exec('SET unique_checks=1; SET foreign_key_checks=1;');
    }

    /**
     * Create PDO statement.
     *
     * @param string $sql The sql
     *
     * @throws UnexpectedValueException
     *
     * @return PDOStatement The statement
     */
    private function createQueryStatement(string $sql)
    {
        $statement = $this->getConnection()->query($sql, PDO::FETCH_ASSOC);

        if (!$statement instanceof PDOStatement) {
            throw new UnexpectedValueException('Invalid SQL statement');
        }

        return $statement;
    }

    /**
     * Import table schema.
     *
     * @throws UnexpectedValueException
     *
     * @return void
     */
    protected function importSchema()
    {
        if (!$this->schemaFile) {
            throw new UnexpectedValueException('The path for schema.sql is not defined');
        }

        if (!file_exists($this->schemaFile)) {
            throw new UnexpectedValueException(sprintf('File not found: %s', $this->schemaFile));
        }

        $pdo = $this->getConnection();
        $pdo->exec('SET unique_checks=0; SET foreign_key_checks=0;');
        $pdo->exec((string)file_get_contents($this->schemaFile));
        $pdo->exec('SET unique_checks=1; SET foreign_key_checks=1;');
    }

    /**
     * Clean up database.
     *
     * @throws UnexpectedValueException
     *
     * @return void
     */
    protected function truncateTables()
    {
        $pdo = $this->getConnection();

        $pdo->exec('SET unique_checks=0; SET foreign_key_checks=0;');

        // Truncate only changed tables
        $statement = $this->createQueryStatement(
            'SELECT TABLE_NAME
                FROM information_schema.tables
                WHERE table_schema = database()
                AND update_time IS NOT NULL'
        );

        $sql = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $sql[] = sprintf('TRUNCATE TABLE `%s`;', $row['TABLE_NAME']);
        }

        if ($sql) {
            $pdo->exec(implode("\n", $sql));
        }

        $pdo->exec('SET unique_checks=1; SET foreign_key_checks=1;');
    }

    /**
     * Iterate over all fixtures and insert them into their tables.
     *
     * @param array<mixed> $fixtures The fixtures
     *
     * @return void
     */
    protected function insertFixtures($fixtures)
    {
        foreach ($fixtures as $fixture) {
            $object = new $fixture();

            foreach ($object->records as $row) {
                $this->insertFixture($object->table, $row);
            }
        }
    }

    /**
     * Insert row into table.
     *
     * @param string $table The table name
     * @param array<mixed> $row The row data
     *
     * @return void
     */
    protected function insertFixture($table, array $row)
    {
        $fields = array_keys($row);

        array_walk(
            $fields,
            function (&$value) {
                $value = sprintf('`%s`=:%s', $value, $value);
            }
        );

        $statement = $this->createPreparedStatement(sprintf('INSERT INTO `%s` SET %s', $table, implode(',', $fields)));
        $statement->execute($row);
    }

    /**
     * Create PDO statement.
     *
     * @param string $sql The sql
     *
     * @throws UnexpectedValueException
     *
     * @return PDOStatement The statement
     */
    private function createPreparedStatement($sql)
    {
        $statement = $this->getConnection()->prepare($sql);

        if (!$statement instanceof PDOStatement) {
            throw new UnexpectedValueException('Invalid SQL statement');
        }

        return $statement;
    }

    /**
     * Asserts that a given table is the same as the given row.
     *
     * @param array<mixed> $expectedRow Row expected to find
     * @param string $table Table to look into
     * @param int $id The primary key
     * @param array<mixed>|null $fields The columns
     * @param string $message Optional message
     *
     * @return void
     */
    protected function assertTableRow($expectedRow, $table, $id, $fields = null, $message = '')
    {
        $this->assertSame(
            $expectedRow,
            $this->getTableRowById($table, $id, $fields ?: array_keys($expectedRow)),
            $message
        );
    }

    /**
     * Fetch row by ID.
     *
     * @param string $table Table name
     * @param int $id The primary key value
     * @param array<mixed>|null $fields The array of fields
     *
     * @throws DomainException
     *
     * @return array<mixed> Row
     */
    protected function getTableRowById($table, $id, $fields = null)
    {
        $sql = sprintf('SELECT * FROM `%s` WHERE `id` = :id', $table);
        $statement = $this->createPreparedStatement($sql);
        $statement->execute(['id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (empty($row)) {
            throw new DomainException(sprintf('Row not found: %s', $id));
        }

        if ($fields) {
            $row = array_intersect_key($row, array_flip($fields));
        }

        return $row;
    }

    /**
     * Asserts that a given table equals the given row.
     *
     * @param array<mixed> $expectedRow Row expected to find
     * @param string $table Table to look into
     * @param int $id The primary key
     * @param array<mixed>|null $fields The columns
     * @param string $message Optional message
     *
     * @return void
     */
    protected function assertTableRowEquals($expectedRow, $table, $id, $fields = null, $message = '')
    {
        $this->assertEquals(
            $expectedRow,
            $this->getTableRowById($table, $id, $fields ?: array_keys($expectedRow)),
            $message
        );
    }

    /**
     * Asserts that a given table contains a given row value.
     *
     * @param mixed $expected The expected value
     * @param string $table Table to look into
     * @param int $id The primary key
     * @param string $field The column name
     * @param string $message Optional message
     *
     * @return void
     */
    protected function assertTableRowValue($expected, $table, $id, $field, $message = '')
    {
        $actual = $this->getTableRowById($table, $id, [$field])[$field];
        $this->assertSame($expected, $actual, $message);
    }

    /**
     * Asserts that a given table contains a given number of rows.
     *
     * @param int $expected The number of expected rows
     * @param string $table Table to look into
     * @param string $message Optional message
     *
     * @return void
     */
    protected function assertTableRowCount($expected, $table, $message = '')
    {
        $this->assertSame($expected, $this->getTableRowCount($table), $message);
    }

    /**
     * Get table row count.
     *
     * @param string $table The table name
     *
     * @return int The number of rows
     */
    protected function getTableRowCount($table)
    {
        $sql = sprintf('SELECT COUNT(*) AS counter FROM `%s`;', $table);
        $statement = $this->createQueryStatement($sql);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

        return (int)(isset($row['counter']) ? $row['counter'] : 0);
    }

    /**
     * Asserts that a given table contains a given number of rows.
     *
     * @param string $table Table to look into
     * @param int $id The id
     * @param string $message Optional message
     *
     * @return void
     */
    protected function assertTableRowExists($table, $id, $message = '')
    {
        $this->assertTrue((bool)$this->findTableRowById($table, $id), $message);
    }

    /**
     * Fetch row by ID.
     *
     * @param string $table Table name
     * @param int $id The primary key value
     *
     * @throws DomainException
     *
     * @return array<mixed> Row
     */
    protected function findTableRowById($table, $id)
    {
        $sql = sprintf('SELECT * FROM `%s` WHERE `id` = :id', $table);
        $statement = $this->createPreparedStatement($sql);
        $statement->execute(['id' => $id]);

        return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Asserts that a given table contains a given number of rows.
     *
     * @param string $table Table to look into
     * @param int $id The id
     * @param string $message Optional message
     *
     * @return void
     */
    protected function assertTableRowNotExists($table, $id, $message = '')
    {
        $this->assertFalse((bool)$this->findTableRowById($table, $id), $message);
    }
}
