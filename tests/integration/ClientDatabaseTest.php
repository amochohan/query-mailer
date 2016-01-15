<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ClientDatabaseTest extends TestCase
{
    use DatabaseMigrations, DatabaseTransactions;

    /**
     * @var App\ClientDatabase
     */
    private $sut;

    /**
     * @var array
     */
    private $validCredentials = [
        'driver'    => 'sqlite',
        'host'      => 'localhost',
        'database'  => ':memory:',
        'username'  => 'homestead',
        'password'  => 'secret',
        'charset'   => 'utf8',
        'collation' => 'utf8_unicode_ci',
    ];

    /**
     * @var array
     */
    private $invalidHost = [
        'driver'    => 'mysql',
        'host'      => 'localhost123',
        'database'  => 'homestead',
        'username'  => 'homestead',
        'password'  => 'secret',
        'charset'   => 'utf8',
        'collation' => 'utf8_unicode_ci',
    ];

    /**
     * @var array
     */
    private $invalidDatabase = [
        'driver'    => 'mysql',
        'host'      => 'localhost',
        'database'  => 'homestead321',
        'username'  => 'homestead',
        'password'  => 'secret',
        'charset'   => 'utf8',
        'collation' => 'utf8_unicode_ci',
    ];

    /**
     * @var array
     */
    private $invalidUsername = [
        'driver'    => 'mysql',
        'host'      => 'localhost',
        'database'  => 'homestead',
        'username'  => 'homestead123',
        'password'  => 'secret',
        'charset'   => 'utf8',
        'collation' => 'utf8_unicode_ci',
    ];

    /**
     * @var array
     */
    private $invalidPassword = [
        'driver'    => 'mysql',
        'host'      => 'localhost',
        'database'  => 'homestead',
        'username'  => 'homestead',
        'password'  => 'secret321',
        'charset'   => 'utf8',
        'collation' => 'utf8_unicode_ci',
    ];

    /**
     * @var array
     */
    private $invalidCharset = [
        'driver'    => 'mysql',
        'host'      => 'localhost',
        'database'  => 'homestead321',
        'username'  => 'homestead123',
        'password'  => 'secret321',
        'charset'   => 'utf8saafsf^$',
        'collation' => 'utf8_unicode_ci',
    ];

    public function setUp()
    {
        parent::setUp();

        $databaseManager = app()->make('Illuminate\Database\DatabaseManager');
        $configRepository = app()->make('Illuminate\Config\Repository');

        $this->sut = new App\ClientDatabase($databaseManager, $configRepository);

    }

    private function migrateClientDatabase()
    {
        $exitCode = \Artisan::call('migrate:refresh', [
            '--database' => 'client',
            '--force' => true,
        ]);
    }

    /** @test */
    public function it_can_connect_to_a_remote_database_server_using_valid_credentials()
    {
        // Given I have valid database credentials
        // When I connect to the remote
        // A connection is established

        $this->assertFalse($this->sut->canConnect());
        $this->sut->connect($this->validCredentials);
        $this->assertTrue($this->sut->canConnect());
        $this->assertFalse($this->sut->hasErrors());
    }

    /** @test */
    public function it_handles_connection_attempts_with_an_invalid_hostname_gracefully()
    {
        // Given I have invalid database credentials
        // When I connect to the remote
        // A connection can not be established

        $this->assertFalse($this->sut->canConnect());
        $this->sut->connect($this->invalidHost);
        $this->assertFalse($this->sut->canConnect());
        $this->assertTrue($this->sut->hasErrors());
    }

    /** @test */
    public function it_handles_connection_attempts_with_an_invalid_database_name_gracefully()
    {
        $this->assertFalse($this->sut->canConnect());
        $this->sut->connect($this->invalidDatabase);
        $this->assertFalse($this->sut->canConnect());
        $this->assertTrue($this->sut->hasErrors());
    }

    /** @test */
    public function it_handles_connection_attempts_with_an_invalid_username_gracefully()
    {
        $this->assertFalse($this->sut->canConnect());
        $this->sut->connect($this->invalidUsername);
        $this->assertFalse($this->sut->canConnect());
        $this->assertTrue($this->sut->hasErrors());
    }

    /** @test */
    public function it_handles_connection_attempts_with_an_invalid_password_gracefully()
    {
        $this->assertFalse($this->sut->canConnect());
        $this->sut->connect($this->invalidPassword);
        $this->assertFalse($this->sut->canConnect());
        $this->assertTrue($this->sut->hasErrors());
    }

    /** @test */
    public function it_sets_database_configuration_parameters()
    {
        // Given I have a client's database configuration parameters
        // When I set the parameters
        // Then Laravel can access those parameters under database.connections.client

        $this->sut->setDatabaseConfig($this->validCredentials);
        $this->assertEquals(config('database.connections.client'), $this->validCredentials);
    }

    /** @test */
    public function it_can_execute_select_queries()
    {
        $this->sut->connect($this->validCredentials);
        $this->assertTrue($this->sut->canConnect());

        // The database migration must be performed manually
        // as we're not using the standard Laravel database.
        $this->migrateClientDatabase();

        factory(\App\User::class, 3)->create();

        $results = $this->sut->execute('SELECT email FROM users LIMIT 3;');
        $this->assertCount(3, $results);
    }

    /** @test */
    public function it_can_only_execute_select_queries()
    {
        $this->setExpectedException('\App\Exceptions\InvalidQueryTypeException');

        $this->sut->connect($this->validCredentials);
        $results = $this->sut->execute("UPDATE users SET name='Amo' WHERE name='Fred' LIMIT 1;");
        $this->assertCount(0, $results);
    }

    /** @test */
    public function it_handles_malformed_select_queries_gracefully()
    {
        $this->setExpectedException('\App\Exceptions\InvalidQueryException');
        $this->sut->connect($this->validCredentials);
        $results = $this->sut->execute('SELECT sadsafs FROM asfaf LIMIT 3;');
    }

    /** @test */
    public function it_checks_if_a_query_is_a_select_statement()
    {
        $this->assertFalse(
            $this->sut->isSelectQuery("UPDATE users SET name='Amo' WHERE name='Fred' LIMIT 1;")
        );
    }

    /** @test */
    public function it_can_apply_a_limit_to_a_query()
    {
        $query = 'SELECT * FROM users';
        $limitedQuery = $this->sut->applyLimitToQuery($query, 5);
        $this->assertEquals('SELECT * FROM users LIMIT 5', $limitedQuery);
    }

    /** @test */
    public function it_checks_if_a_query_has_an_existing_limit()
    {
        $this->assertNull($this->sut->existingLimit('SELECT * FROM users'));
        $this->assertEquals(1, $this->sut->existingLimit('SELECT * FROM users LIMIT 1'));
        $this->assertEquals(1, $this->sut->existingLimit('SELECT * FROM users limit 1'));
        $this->assertEquals(1, $this->sut->existingLimit('SELECT * FROM users limit 1;'));
        $this->assertEquals(4, $this->sut->existingLimit('SELECT * FROM users LIMIT 4'));
        $this->assertEquals(4512351, $this->sut->existingLimit('SELECT * FROM users LIMIT 4512351'));
    }

    /** @test */
    public function it_can_change_an_existing_defined_limit_to_a_new_one()
    {
        $limitedQuery = $this->sut->applyLimitToQuery('SELECT * FROM users LIMIT 1000', 5);
        $this->assertEquals('SELECT * FROM users LIMIT 5', $limitedQuery);

        $limitedQuery = $this->sut->applyLimitToQuery('SELECT * FROM users limit 100', 15);
        $this->assertEquals('SELECT * FROM users LIMIT 15', $limitedQuery);
    }

    /** @test */
    public function it_only_applies_a_limit_if_a_lower_one_hasnt_already_been_defined()
    {
        $limitedQuery = $this->sut->applyLimitToQuery('SELECT * FROM users LIMIT 1000', 5);
        $this->assertEquals('SELECT * FROM users LIMIT 5', $limitedQuery);

        $limitedQuery = $this->sut->applyLimitToQuery('SELECT * FROM users LIMIT 5', 5);
        $this->assertEquals('SELECT * FROM users LIMIT 5', $limitedQuery);

        $limitedQuery = $this->sut->applyLimitToQuery('SELECT * FROM users LIMIT 2', 5);
        $this->assertEquals('SELECT * FROM users LIMIT 2', $limitedQuery);

        $limitedQuery = $this->sut->applyLimitToQuery('SELECT * FROM users', 5);
        $this->assertEquals('SELECT * FROM users LIMIT 5', $limitedQuery);
    }

    /** @test */
    public function it_appends_a_semi_colon_to_the_end_of_each_query()
    {
        $this->assertEquals('SELECT * FROM users LIMIT 5;',
            $this->sut->applySemiColon('SELECT * FROM users LIMIT 5;')
        );
        $this->assertEquals('SELECT * FROM users LIMIT 5;',
            $this->sut->applySemiColon('SELECT * FROM users LIMIT 5')
        );
    }

}
