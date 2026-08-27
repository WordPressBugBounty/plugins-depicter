<?php

namespace Depicter\Database\Repository;

use Depicter\Database\Entity\DocumentAnalytics;
use Depicter\Utility\Sanitize;

class DocumentAnalyticsRepository
{

	private const ALLOWED_ORDER_BY = ['created_at', 'id', 'source_id', 'event_type'];
	private const ALLOWED_ORDER    = ['ASC', 'DESC'];
	private const ALLOWED_EVENT_TYPES = ['view', 'click', 'impression', 'submission'];

	/**
	 * @var DocumentAnalytics
	 */
	private DocumentAnalytics $documentAnalytics;


	public function __construct(){
		$this->documentAnalytics = DocumentAnalytics::new();
	}

	/**
	 * Access to an instance of DocumentAnalytics entity
	 *
	 * @return DocumentAnalytics
	 */
	public function documentAnalytics(): DocumentAnalytics{
		return DocumentAnalytics::new();
	}

	/**
	 * Create a document analytics record
	 *
	 * @param int    $documentId  Document ID
	 * @param string $eventType   Event type (e.g., 'view', 'click', 'impression')
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function create( $sourceId, $eventType ) {
		if ( ! in_array( $eventType, self::ALLOWED_EVENT_TYPES ) ) {
			return false;
		}

		$ipAddress = \Depicter::geoLocate()->getIP();
		$userSession = \Depicter::authorization()->getUserSession();
		$userID = is_user_logged_in() ? get_current_user_id() : 0;

		return $this->documentAnalytics()->create([
			'source_id' => $sourceId,
			'event_type'  => $eventType,
			'session_id' => $userSession,
			'user_id' => $userID,
			'ip_address'  => $ipAddress,
			'created_at'  => $this->documentAnalytics()->currentDateTime()
        ]);
	}

	/**
	 * Get analytics by document ID
	 *
	 * @param int   $documentId
	 * @param array $args
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function getByDocumentId( $documentId, $args = [] ): array {
		$query = $this->documentAnalytics()->where('source_id', $documentId);

		if ( ! empty( $args['event_type'] ) && in_array( $args['event_type'], self::ALLOWED_EVENT_TYPES ) ) {
			$query->where('event_type', $args['event_type']);
		}

		if (!empty($args['dateStart'])) {
			$args['dateStart'] = $this->normalizeDateTime( $args['dateStart'], 'start' );
			$query->where('created_at', '>=', $args['dateStart']);
		}

		if (!empty($args['dateEnd'])) {
			$args['dateEnd'] = $this->normalizeDateTime( $args['dateEnd'], 'end' );
			$query->where('created_at', '<=', $args['dateEnd']);
		}

		$orderBy = in_array( $args['orderBy'] ?? '', self::ALLOWED_ORDER_BY, true ) ? $args['orderBy'] : 'created_at';
		$order = in_array( strtoupper( $args['order'] ?? '' ), self::ALLOWED_ORDER, true ) ? strtoupper($args['order']) : 'DESC';
		$query->orderBy($orderBy, $order);

		if (!empty($args['perPage'])) {
			$page = $args['page'] ?? 1;
			if ($pager = $query->paginate($args['perPage'], $page)) {
				$results = $pager->getResults();
			} else {
				$results = [];
			}
		} else {
			$results = $query->findAll()->get();
		}

		return $results ? $results->toArray() : [];
	}

	/**
	 * Get analytics count by document ID and event type
	 *
	 * @param int    $documentId
	 * @param string $eventType
	 * @param array  $args
	 *
	 * @return int
	 * @throws \Exception
	 */
	public function getCount( $documentId, $eventType = '', $args = [] ): int {
		$query = DocumentAnalytics::new()->where('source_id', $documentId);

		if (!empty($eventType) && in_array( $eventType, self::ALLOWED_EVENT_TYPES ) ) {
			$query->where('event_type', $eventType);
		}

		if (!empty($args['dateStart'])) {
			$args['dateStart'] = $this->normalizeDateTime( $args['dateStart'], 'start' );
			$query->where('created_at', '>=', $args['dateStart']);
		}

		if (!empty($args['dateEnd'])) {
			$args['dateEnd'] = $this->normalizeDateTime( $args['dateEnd'], 'end' );
			$query->where('created_at', '<=', $args['dateEnd']);
		}

		return $query->count();
	}

	/**
	 * Delete analytics by document ID
	 *
	 * @param int $documentId
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function deleteByDocumentId( $documentId ) {
		$analytics = $this->documentAnalytics()->where('source_id', $documentId)->findAll()->get();
		
		if ($analytics && $analytics->count()) {
			foreach ($analytics as $analytic) {
				$analytic->delete();
			}
			return true;
		}

		return false;
	}

	/**
	 * Delete analytics by ID
	 *
	 * @param mixed $id  Can be an ID or list of comma separated IDs
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function delete( $id ) {
		$succeed = false;

		if( is_array( $id ) ){
			$ids = $id;
		} elseif( false !== strpos( $id, ',' ) ){
			$ids = explode(',', $id );
		} else {
			$ids = [$id];
		}

		foreach( $ids as $id ){
			$id = Sanitize::int( $id );
			if( $analytic = $this->documentAnalytics()->findById( $id ) ){
				$analytic->delete();
				$succeed = true;
			}
		}

		return $succeed;
	}

	/**
	 * Normalize date to include time
	 *
	 * @param string $date
	 * @param string $type
	 *
	 * @return string
	 */
	public function normalizeDateTime( string $date, $type = 'start' ){
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)){
			return $date . ' ' . ($type === 'start' ? '00:00:00' : '23:59:59');
		}
		return $date;
	}

}
