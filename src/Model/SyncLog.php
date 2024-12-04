<?php

namespace Saksono\Woojurnal\Model;

defined( 'ABSPATH' ) || exit;

class SyncLog {

    /**
	 * Main table used for storing sync data
	 */
    private const SYNC_TABLE  = 'wji_order_sync_log';

    /**
	 * Holds the sync record data
	 */
    private $sync_data = [];

    /**
	 * Holds the primary key for the current record
	 */
    private $id = null;

    /**
     * Constructor
     * Initialize the object with a single record from the database if an ID or Order ID is provided.
     *
     * @param int|null $sync_id   The primary key ID of the sync record.
     * @param int|null $order_id  The WooCommerce order ID of the sync record.
     */
    public function __construct(?int $sync_id = null, ?int $order_id = null)
    {
        if ($sync_id) {
            $this->sync_data = $this->find($sync_id);
            $this->id = $sync_id;
        } elseif ($order_id) {
            $this->sync_data = $this->findByOrderId($order_id);
            $this->id = $this->sync_data->id ?? null;
        }
	}

    /**
     * Get the sync table name.
     *
     * @return string
     */
    public function getTableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::SYNC_TABLE;
    }

    /**
     * Retrieve a single record by ID.
     *
     * @param int $id
     * @return object|null
     */
    public function find(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->getTableName()} WHERE id = %d", $id)
        );
    }

    /**
     * Find a single record based on WooCommerce Order ID.
     *
     * @param int $order_id t The WooCommerce Order ID.
     * @return object|null The matching record or null if not found.
     */
    public function findByOrderId(int $order_id): ?object
    {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->getTableName()} WHERE wc_order_id = %d LIMIT 1", $order_id)
        );
    }

    /**
     * Retrieve all records with optional filtering, sorting, and pagination.
     *
     * @param string|null $where
     * @param int|null $limit
     * @param int|null $offset
     * @return array
     */
    public function all(?string $where = null, ?int $limit = null, ?int $offset = null, ?string $order_by = null, ?string $order_direction = 'ASC'): array
    {
        global $wpdb;

        $query = "SELECT * FROM {$this->getTableName()}";
    
        $query_args = [];
        if ($where) {
            $query .= " WHERE $where"; // Ensure $where is safely constructed before calling this method.
        }
        if ($order_by) {
            $allowed_directions = ['ASC', 'DESC'];
            $order_direction = in_array(strtoupper($order_direction), $allowed_directions) ? strtoupper($order_direction) : 'ASC';
            $query .= " ORDER BY $order_by $order_direction";
        }
        if ($limit !== null) {
            $query .= " LIMIT %d";
            $query_args[] = $limit;
        }
        if ($offset !== null) {
            $query .= " OFFSET %d";
            $query_args[] = $offset;
        }

        $prepared_query = $wpdb->prepare($query, $limit, $offset);
        return $wpdb->get_results($prepared_query);
    }

    /**
     * Count records with optional filtering.
     *
     * @param string|null $where
     * @return int
     */
    public function count(?string $where = null): int
    {
        global $wpdb;

        $query = "SELECT COUNT(*) FROM {$this->getTableName()}";
        if ($where) {
            $query .= " WHERE $where";
        }
        return $wpdb->get_var($query);
    }

    /**
     * Create a new sync log record.
     *
     * @param array $data Data to be inserted into the sync log.
     * @return self|null New instance of the class or null on failure.
     */
    public function create(array $data): ?self
    {
        global $wpdb;
        $inserted = $wpdb->insert($this->getTableName(), $data);

        if ($inserted) {
            $new_id = $wpdb->insert_id;
            return new self($new_id);
        }

        return null;
    }

    /**
     * Update the current record or a specific record by ID.
     *
     * @param array $data
     * @param int|null $id
     * @return bool|int Number of rows updated or false on failure
     */
    public function update(array $data, ?int $id = null)
    {
        global $wpdb;
        $id = $id ?? $this->id;
        if (!$id) {
            return false;
        }
        return $wpdb->update($this->getTableName(), $data, ['id' => $id]);
    }

    /**
     * Delete a record by ID or the current record.
     *
     * @param int|null $id
     * @return bool|int Number of rows deleted or false on failure
     */
    public function delete(?int $id = null)
    {
        global $wpdb;
        $id = $id ?? $this->id;
        if (!$id) {
            return false;
        }
        return $wpdb->delete($this->getTableName(), ['id' => $id]);
    }

    /**
	 * Check if the sync status is 'SYNCED'.
	 *
	 * @return bool
	 */
    public function isSynced(): bool
    {
		return isset($this->sync_data->sync_status) && $this->sync_data->sync_status === 'SYNCED';
	}

    /**
	 * Get a specific field from the sync data.
	 *
	 * @param string $field
	 * @return mixed|null
	 */
	public function getField($field)
    {
		return $this->sync_data->$field ?? null;
	}

    /**
     * Get the entire sync data for the current record.
     *
     * @return object|null
     */
    public function getData(): ?object
    {
        return $this->sync_data;
    }

    /**
     * Check if Sync Record Exists
     *
     * @param int $order_id WooCommerce Order ID.
     * @param string $sync_action Sync Action.
     * @return bool
     */
    public function check_sync_exists($order_id, $sync_action)
    {
        global $wpdb;

        $query = $wpdb->prepare("SELECT id FROM {$this->getTableName()} WHERE wc_order_id = %d AND sync_action = %s AND sync_status = 'SYNCED'", $order_id, $sync_action);
        return (bool) $wpdb->get_var($query);
    }
}