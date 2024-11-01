<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VIWEBPOS_Transactions_Table {
	protected static $table_name = 'viwebpos_transactions';

	/**
	 * Create table
	 */
	public static function create_table() {
		global $wpdb;
		$table = $wpdb->prefix . self::$table_name;
		$query = "CREATE TABLE IF NOT EXISTS {$table} (
                             `id` bigint(20) NOT NULL AUTO_INCREMENT,
                             `cashier_id` bigint(20) NOT NULL ,
                             `order_id` bigint(20)  ,
                             `in` float NOT NULL ,
                             `out` float NOT NULL,
                             `method` TEXT NOT NULL ,
                             `currency` TEXT,
                             `currency_symbol` TEXT,
                             `note` LONGTEXT,
                             `create_at` DATETIME,
                             PRIMARY KEY  (`id`)
                             )";
		$wpdb->query( $query );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**Insert data to table
	 * @return int|bool
	 */
	public static function insert( $params = array() ) {
		$arg = array(
			'cashier_id'      => '',
			'order_id'        => '',
			'in'              => 0,
			'out'             => 0,
			'method'          => 'viwebpos_manual',
			'currency'        => '',
			'currency_symbol' => '',
			'note'            => '',
			'create_at'       => date( 'Y-m-d H:i:s' ),// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		);
		$arg = wp_parse_args( $params, $arg );
		if ( empty( $arg['cashier_id'] ) || ( empty( $arg['in'] ) && empty( $arg['out'] ) ) ) {
			return false;
		}
		global $wpdb;
		$table = $wpdb->prefix . self::$table_name;
		$wpdb->insert( $table, $arg, array( '%d', '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%s' ) );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		VIWEBPOS_DATA::set_data_prefix( 'transactions' );

		return $wpdb->insert_id;
	}

	/**Get rows
	 *
	 * @param $args
	 *
	 * @return array|null|object
	 */
	public static function get_transactions( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . self::$table_name;
		$args  = wp_parse_args( $args, array(
			'fields'  => '*',
			'where'   => '',
			'limit'   => 30,
			'offset'  => 0,
			'orderby' => 'id',
			'order'   => 'DESC',
		) );
		if ( isset( $args['fields'] ) && is_array( $args['fields'] ) ) {
			$args['fields'] = implode( ', ', $args['fields'] );
		}
		$where = ! empty( $args['where'] ) ? "WHERE {$args['where']}" : '';
		$query = "SELECT  {$args['fields']} FROM {$table}  {$where}  ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d ";

		return $wpdb->get_results( $wpdb->prepare( $query, $args['limit'], $args['offset'] ), ARRAY_A );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function count_records( $args = array() ) {
		$args = wp_parse_args( $args, [ 'fields' => 'id', 'where' => '' ] );
		global $wpdb;
		$table = $wpdb->prefix . self::$table_name;
		$where = ! empty( $args['where'] ) ? "WHERE {$args['where']}" : '';
		$query = "SELECT COUNT({$args['fields']}) FROM {$table} {$where}";

		return $wpdb->get_var( $query );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**Delete row
	 * @return false|int
	 */
	public static function delete( $id ) {
		if ( ! $id ) {
			return false;
		}
		global $wpdb;
		$table  = $wpdb->prefix . self::$table_name;
		$delete = $wpdb->delete( $table, array( 'id' => $id ) );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		VIWEBPOS_DATA::set_data_prefix( 'transactions' );

		return $delete;
	}

	/**Delete transaction while delete order
	 * @return false|int
	 */
	public static function delete_by_order_id( $id ) {
		if ( ! $id ) {
			return false;
		}
		global $wpdb;
		$table  = $wpdb->prefix . self::$table_name;
		$delete = $wpdb->delete( $table, array( 'order_id' => $id ) );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		VIWEBPOS_DATA::set_data_prefix( 'transactions' );

		return $delete;
	}

	/**delete all row
	 */
	public static function delete_all() {
		global $wpdb;
		$table = $wpdb->prefix . self::$table_name;
		$wpdb->query( "TRUNCATE TABLE {$table}" );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		VIWEBPOS_DATA::set_data_prefix( 'transactions' );
	}
}