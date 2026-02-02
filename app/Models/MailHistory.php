<?php
namespace App\Models;

use App\Core\Model;
use Exception;

/**
 * MailHistory
 *
 * Hanterar tabellen `si_mail_history` som fungerar som en cursor för
 * senast behandlade kunder (per kundtyp). Används av API:t för att
 * veta var nästa batch av kunder ska börja.
 */
class MailHistory extends Model
{
    /**
     * Säkerställ att tabellen `si_mail_history` finns.
     *
     * Skapar tabellen om den saknas. I en större applikation skulle
     * detta normalt ligga i ett migrationssystem, men här behålls
     * logiken nära användningen för enkelhetens skull.
     *
     * @return void
     *
     * @throws Exception Vid SQL-fel.
     */
    public function ensureTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS si_mail_history (" .
            "id INT AUTO_INCREMENT PRIMARY KEY," .
            "customer_type TINYINT NOT NULL," .
            "kundnr INT NOT NULL," .
            "name VARCHAR(255) NOT NULL," .
            "email VARCHAR(255) NOT NULL," .
            "created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP," .
            "INDEX idx_customer_type_created_at (customer_type, created_at)" .
            ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        if (!$this->db->query($sql)) {
            error_log('Error creating si_mail_history table: ' . $this->db->error);
            throw new Exception('Database query failed');
        }

        // I den ursprungliga koden fanns extra logik för att säkerställa
        // AUTO_INCREMENT/PRIMARY KEY; här hålls det enklare men samma idé.
    }

    /**
     * Hämta senaste rad för en given kundtyp.
     *
     * @param int $customerType Kundtyp (t.ex. 1 = aktiv, 2 = temp).
     *
     * @return array|null Assoc-array med fält (kundnr, name, email, created_at)
     *                    eller null om ingen rad finns.
     *
     * @throws Exception Vid prepare-fel.
     */
    public function getLatest($customerType)
    {
        $sql = "SELECT kundnr, name, email, created_at FROM si_mail_history WHERE customer_type = ? ORDER BY created_at DESC, id DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $this->db->error);
        }
        $stmt->bind_param('i', $customerType);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Lägg till en ny historikrad.
     *
     * @param int    $customerType Kundtyp.
     * @param int    $kundnr       Kundnummer.
     * @param string $name         Kundnamn.
     * @param string $email        Kundens e-postadress.
     *
     * @return bool True vid lyckad insert, annars false.
     *
     * @throws Exception Vid prepare-fel.
     */
    public function add($customerType, $kundnr, $name, $email)
    {
        $sql = "INSERT INTO si_mail_history (customer_type, kundnr, name, email) VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $this->db->error);
        }
        $stmt->bind_param('iiss', $customerType, $kundnr, $name, $email);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
