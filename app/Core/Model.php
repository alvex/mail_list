<?php
namespace App\Core;

/**
 * Abstrakt basmodell för alla domänmodeller.
 *
 * Tillhandahåller en delad mysqli-anslutning via Database-klassen
 * baserat på angivet databasanamn.
 */
abstract class Model
{
    /**
     * @var \mysqli Aktiv databasanslutning för modellen.
     */
    protected $db;

    /**
     * Skapa en ny modellinstans med kopplad DB-anslutning.
     *
     * @param string $dbName Namnet på databasen som modellen ska använda.
     */
    public function __construct($dbName = DB_NAME)
    {
        $this->db = Database::getConnection($dbName);
    }
}
