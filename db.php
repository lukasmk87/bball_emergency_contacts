<?php
require_once 'config.php';

class Database {
    private static $instance = null;
    private $conn;
    
    // Konstruktor - private, um Singleton-Muster zu erzwingen
    private function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die("Datenbankfehler: " . $e->getMessage());
            } else {
                die("Es ist ein Datenbankfehler aufgetreten. Bitte kontaktieren Sie den Administrator.");
            }
        }
    }
    
    // Singleton-Muster - nur eine Instanz der Datenbank erlauben
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    // Verbindung zurückgeben
    public function getConnection() {
        return $this->conn;
    }
    
    // Query ausführen mit Parametern
    public function query($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die("Query-Fehler: " . $e->getMessage() . " SQL: " . $sql);
            } else {
                die("Es ist ein Fehler bei der Datenverarbeitung aufgetreten. Bitte kontaktieren Sie den Administrator.");
            }
        }
    }
    
    // Einzelne Zeile abrufen
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    // Alle Zeilen abrufen
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    // Einfügen und letzte ID zurückgeben
    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->conn->lastInsertId();
    }
    
    // Anzahl der betroffenen Zeilen zurückgeben
    public function execute($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
}

// Hilfsfunktion für einfachen Zugriff auf die Datenbank
function db() {
    return Database::getInstance();
}