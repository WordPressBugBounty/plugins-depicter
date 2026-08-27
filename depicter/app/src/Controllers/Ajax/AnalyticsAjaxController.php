<?php

namespace Depicter\Controllers\Ajax;

use Depicter\Utility\Sanitize;
use WPEmerge\Requests\RequestInterface;

class AnalyticsAjaxController
{
	/**
	 * Track an analytics event
	 *
	 * @param RequestInterface $request
	 * @param $view
	 *
	 * @return mixed
	 */
	public function track(RequestInterface $request, $view)
	{
		$sourceId = Sanitize::int($request->body('sourceId', 0));
		$eventType  = Sanitize::textfield($request->body('eventType', ''));
		$metaData   = $request->body('metaData', []);
		$csrfToken = Sanitize::textfield($request->body('_csrfToken', ''));

		if (empty($csrfToken) || !wp_verify_nonce($csrfToken, 'depicter-csrf-lead-' . $sourceId)) {
            return \Depicter::json([
                'errors' => [__('Invalid or missing CSRF token. The request could not be completed.', 'depicter')]
            ])->withStatus(400);
        }


		if (empty($sourceId) || empty($eventType)) {
			return \Depicter::json([
				'errors' => [__('Source ID and event type are required.', 'depicter')]
			])->withStatus(400);
		}

		// Sanitize meta data if provided
		if (!empty($metaData) && is_array($metaData)) {
			$sanitizedMeta = [];
			foreach ($metaData as $key => $value) {
				$sanitizedMeta[Sanitize::textfield($key)] = Sanitize::textfield($value);
			}
			$metaData = $sanitizedMeta;
		} else {
			$metaData = [];
		}

		try {
			$result = \Depicter::analytics()->track($sourceId, $eventType, $metaData);
			
			if ($result['success']) {
				return \Depicter::json($result)->withStatus(200);
			}

			return \Depicter::json($result)->withStatus(400);

		} catch (\Exception $e) {
			return \Depicter::json(['errors' => [$e->getMessage()]])->withStatus(400);
		}
	}

	/**
	 * Get analytics for a document
	 *
	 * @param RequestInterface $request
	 * @param $view
	 *
	 * @return mixed
	 */
	public function get(RequestInterface $request, $view)
	{
		$sourceId = Sanitize::int($request->query('sourceId', 0));

		if (empty($sourceId)) {
			return \Depicter::json([
				'errors' => [__('Source ID is required.', 'depicter')]
			])->withStatus(400);
		}

		$args = [
			'event_type' => Sanitize::textfield($request->query('eventType', '')),
			'dateStart'  => Sanitize::textfield($request->query('dateStart', '')),
			'dateEnd'    => Sanitize::textfield($request->query('dateEnd', '')),
			'order'      => Sanitize::textfield($request->query('order', 'DESC')),
			'orderBy'    => Sanitize::textfield($request->query('orderBy', 'created_at')),
			'page'       => Sanitize::int($request->query('page', 1)),
			'perPage'    => Sanitize::int($request->query('perpage', 10))
		];

		try {
			$response = \Depicter::analytics()->get($sourceId, $args);
			return \Depicter::json($response);

		} catch (\Exception $e) {
			return \Depicter::json(['errors' => [$e->getMessage()]])->withStatus(400);
		}
	}

	/**
	 * Get analytics count for a document
	 *
	 * @param RequestInterface $request
	 * @param $view
	 *
	 * @return mixed
	 */
	public function stats(RequestInterface $request, $view)
	{
		$sourceId = Sanitize::int($request->query('sourceId', 0));

		if (empty($sourceId)) {
			return \Depicter::json([
				'errors' => [__('Source ID is required.', 'depicter')]
			])->withStatus(400);
		}

		$args = [
			'event_type' => Sanitize::textfield($request->query('eventType', '')),
			'dateStart'  => Sanitize::textfield($request->query('dateStart', '')),
			'dateEnd'    => Sanitize::textfield($request->query('dateEnd', ''))
		];

		try {
			$response = \Depicter::analytics()->getCount($sourceId, $args);
			return \Depicter::json(['count' => $response]);

		} catch (\Exception $e) {
			return \Depicter::json(['errors' => [$e->getMessage()]])->withStatus(400);
		}
	}

	/**
	 * Delete analytics records
	 *
	 * @param RequestInterface $request
	 * @param $view
	 *
	 * @return mixed
	 */
	public function delete(RequestInterface $request, $view)
	{
		$id = Sanitize::int($request->body('ID', 0));
		
		if (empty($id)) {
			return \Depicter::json([
				'errors' => [__('Analytics ID is required.', 'depicter')]
			])->withStatus(400);
		}

		try {
			if (\Depicter::documentAnalyticsRepository()->delete($id)) {
				return \Depicter::json(['success' => true])->withStatus(200);
			}
		} catch (\Exception $e) {
			return \Depicter::json(['errors' => [$e->getMessage()]])->withStatus(400);
		}

		return \Depicter::json(['errors' => [__('Error while deleting analytics.', 'depicter')]])->withStatus(400);
	}

	/**
	 * Get conversion rate between two event types
	 *
	 * @param RequestInterface $request
	 * @param $view
	 *
	 * @return mixed
	 */
	public function getConversionRate(RequestInterface $request, $view)
	{
		$sourceId = Sanitize::int($request->query('sourceId', 0));
		$fromEventType = Sanitize::textfield($request->query('fromEventType', ''));
		$toEventType = Sanitize::textfield($request->query('toEventType', ''));

		if (empty($sourceId) || empty($fromEventType) || empty($toEventType)) {
			return \Depicter::json([
				'errors' => [__('Source ID, from event type, and to event type are required.', 'depicter')]
			])->withStatus(400);
		}

		$args = [
			'dateStart' => Sanitize::textfield($request->query('dateStart', '')),
			'dateEnd' => Sanitize::textfield($request->query('dateEnd', '')),
			'uniqueSessions' => $request->query('uniqueSessions', true)
		];

		try {
			$response = \Depicter::analytics()->getConversionRate($sourceId, $fromEventType, $toEventType, $args);
			
			if ($response['success']) {
				return \Depicter::json($response)->withStatus(200);
			}

			return \Depicter::json($response)->withStatus(400);

		} catch (\Exception $e) {
			return \Depicter::json(['errors' => [$e->getMessage()]])->withStatus(400);
		}
	}
}
