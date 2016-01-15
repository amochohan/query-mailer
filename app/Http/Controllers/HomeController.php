<?php

namespace App\Http\Controllers;

use App\ClientDatabase;
use App\Http\Requests;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class HomeController extends Controller
{

    /**
     * @var ClientDatabase
     */
    private $clientDatabase;

    public function __construct(ClientDatabase $clientDatabase)
    {
        $this->clientDatabase = $clientDatabase;
    }

    public function index()
    {
        $credentials = [
            'driver'    => 'mysql',
            'host'      => 'localhost',
            'database'  => 'homestead',
            'username'  => 'homestead',
            'password'  => 'secret',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
        ];

        $this->clientDatabase->setDatabaseConfig($credentials);

        if ($this->clientDatabase->canConnect()) {

            try {
                return $this->clientDatabase->execute('SELECT name FROM exp_members ORDER BY member_id DESC');
            } catch (\Exception $e) {

            }
        }

        if ($this->clientDatabase->hasErrors()) {
            return $this->clientDatabase->errors();
        }

    }
}
