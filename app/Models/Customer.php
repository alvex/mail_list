<?php
namespace App\Models;

use App\Core\Model;

class Customer extends Model
{
    /**
     * Hämta en batch med kunder efter en given kundnummer-cursor.
     *
     * Affärsregler:
     *  - Endast kunder som har minst en faktura (JOIN si_invoices).
     *  - Exkludera kunder vars namn matchar '%Uppköpet%'.
     *  - Resultatet sorteras i stigande kundnummer och begränsas av $limit.
     *
     * @param int $customerType Kundtyp (1 = aktiv, 2 = temp).
     * @param int $lastKundNr   Sista redan behandlade kundnummer (cursor).
     * @param int $limit        Max antal kunder att hämta.
     *
     * @return array Lista av assoc-arrays med nycklarna 'Name', 'Email', 'KundNr'.
     */
    public function getBatch($customerType, $lastKundNr, $limit = 50)
    {
        $sql = "SELECT DISTINCT
            CONVERT(si_customers.name USING utf8) AS 'Name',
            si_customers.email AS 'Email',
            si_customers.id AS 'KundNr'
        FROM si_customers
        JOIN si_invoices ON si_customers.id = si_invoices.customer_id
        WHERE si_customers.enabled = ?
            AND si_customers.id > ?
            AND si_customers.name NOT LIKE '%Uppköpet%'
        ORDER BY si_customers.id ASC
        LIMIT ?";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            throw new \Exception('Prepare failed: ' . $this->db->error);
        }

        $stmt->bind_param("iii", $customerType, $lastKundNr, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $users = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
        }
        $stmt->close();
        return $users;
    }
}
