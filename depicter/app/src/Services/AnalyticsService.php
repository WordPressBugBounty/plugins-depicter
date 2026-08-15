<?php

namespace Depicter\Services;

use Averta\Core\Utility\Arr;
use WPEmerge\Requests\RequestInterface;

class AnalyticsService
{
	/**
	 * Track an analytics event
	 *
	 * @param int              $sourceId  Document ID
	 * @param string           $eventType   Event type (e.g., 'view', 'click', 'impression')
	 * @param string           $sessionID   Session ID
	 * @param array            $metaData    Optional meta data (e.g., ['browser_width' => '1920', 'referrer' => 'https://...'])
	 *
	 * @return array
	 */
	public function track($sourceId, $eventType, $metaData = [])
	{
		try {
			
			$analyticsId = \Depicter::documentAnalyticsRepository()->create( $sourceId, $eventType );
			
			if (!$analyticsId) {
				return [
					'success' => false,
					'errors'  => [__('Error while tracking analytics', 'depicter')]
				];
			}

			// Store meta data if provided
			if (!empty($metaData) && is_array($metaData)) {
				foreach ($metaData as $key => $value) {
					\Depicter::metaRepository()->update($analyticsId, $key, $value, 'document_analytics');
				}
			}

			// Fire action hook for extensibility
			do_action('depicter/analytics/tracked', $analyticsId, $sourceId, $eventType, $metaData);

			return ['success' => true, 'id' => $analyticsId];

		} catch (\Exception $e) {
			return [
				'success' => false,
				'errors'  => [$e->getMessage()]
			];
		}
	}

	/**
	 * Get analytics for a document
	 *
	 * @param int   $sourceId
	 * @param array $args
	 *
	 * @return array
	 */
	public function get($sourceId, $args = [])
	{
		try {
			$analytics = \Depicter::documentAnalyticsRepository()->getByDocumentId($sourceId, $args);

			return [
				'success'   => true,
				'analytics' => $analytics,
				'total'     => count($analytics)
			];

		} catch (\Exception $e) {
			return [
				'success' => false,
				'errors'  => [$e->getMessage()]
			];
		}
	}

	/**
	 * Get analytics count
	 *
	 * @param int   $sourceId
	 * @param array $args
	 *
	 * @return int
	 */
	public function getCount($sourceId, $args = [])
	{
		try {
			$eventType = $args['event_type'] ?? '';
			return \Depicter::documentAnalyticsRepository()->getCount($sourceId, $eventType, $args);

		} catch (\Exception $e) {
			return 0;
		}
	}

	/**
	 * Get analytics statistics for a document
	 *
	 * @param int   $sourceId
	 * @param array $args
	 *
	 * @return array
	 */
	public function getStats($sourceId, $args = [])
	{
		try {
			$analytics = \Depicter::documentAnalyticsRepository()->getByDocumentId($sourceId, $args);

			// Group by event type
			$stats = [];
			foreach ($analytics as $analytic) {
				$eventType = $analytic['event_type'];
				if (!isset($stats[$eventType])) {
					$stats[$eventType] = 0;
				}
				$stats[$eventType]++;
			}

			return [
				'success' => true,
				'stats'   => $stats,
				'total'   => count($analytics)
			];

		} catch (\Exception $e) {
			return [
				'success' => false,
				'errors'  => [$e->getMessage()]
			];
		}
	}

	/**
	 * Calculate conversion rate between two event types
	 *
	 * @param int    $sourceId         Document ID
	 * @param string $fromEventType    Starting event type (e.g., 'view', 'impression')
	 * @param string $toEventType      Conversion event type (e.g., 'submission', 'click')
	 * @param array  $args             Optional arguments (dateStart, dateEnd, uniqueSessions)
	 *
	 * @return array
	 */
	public function getConversionRate($sourceId, $fromEventType, $toEventType, $args = [])
	{
		try {
			$uniqueSessions = $args['uniqueSessions'] ?? true;
			$dateArgs = [];
			
			if (!empty($args['dateStart'])) {
				$dateArgs['dateStart'] = $args['dateStart'];
			}
			if (!empty($args['dateEnd'])) {
				$dateArgs['dateEnd'] = $args['dateEnd'];
			}

			// Get count of starting events
			$fromCount = \Depicter::documentAnalyticsRepository()->getCount(
				$sourceId, 
				$fromEventType, 
				$dateArgs
			);

			// Get count of conversion events
			$toCount = \Depicter::documentAnalyticsRepository()->getCount(
				$sourceId, 
				$toEventType, 
				$dateArgs
			);

			// If counting unique sessions, get actual analytics to count unique sessions
			if ($uniqueSessions) {
				$fromAnalytics = \Depicter::documentAnalyticsRepository()->getByDocumentId(
					$sourceId, 
					array_merge($dateArgs, ['event_type' => $fromEventType])
				);
				
				$toAnalytics = \Depicter::documentAnalyticsRepository()->getByDocumentId(
					$sourceId, 
					array_merge($dateArgs, ['event_type' => $toEventType])
				);

				// Count unique sessions
				$fromSessions = [];
				foreach ($fromAnalytics as $event) {
					if (!empty($event['session_id'])) {
						$fromSessions[$event['session_id']] = true;
					}
				}
				$fromCount = count($fromSessions);

				$toSessions = [];
				foreach ($toAnalytics as $event) {
					if (!empty($event['session_id'])) {
						$toSessions[$event['session_id']] = true;
					}
				}
				$toCount = count($toSessions);
			}

			// Calculate conversion rate
			$conversionRate = 0;
			if ($fromCount > 0) {
				$conversionRate = ($toCount / $fromCount) * 100;
			}

			return [
				'success'         => true,
				'from_event'      => $fromEventType,
				'to_event'        => $toEventType,
				'from_count'      => $fromCount,
				'to_count'        => $toCount,
				'conversion_rate' => round($conversionRate, 2),
				'unique_sessions' => $uniqueSessions
			];

		} catch (\Exception $e) {
			return [
				'success' => false,
				'errors'  => [$e->getMessage()]
			];
		}
	}
}
